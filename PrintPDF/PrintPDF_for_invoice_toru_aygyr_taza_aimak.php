<?php
// exit();
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();
ini_set('memory_limit', -1);
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Asia/Bishkek');
set_time_limit(0);
ini_set('max_execution_time', 3600); // 1 час

chdir('../');

require_once 'include/utils/utils.php';
require_once 'include/database/PearDatabase.php';
require_once 'libraries/tcpdf/tcpdf.php';
require 'vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\RoundBlockSizeMode;

// var_dump($_GET);
// exit();
if (isset($_GET['module'])) {

    if ($_GET['module'] == 'Invoice') {

        if (isset($_GET['selectedIds'])) {
            // Очищаем буфер перед любым выводом
            ob_clean();

            $selectedIds = $_GET['selectedIds'];
            $idList = json_decode($selectedIds);
            $viewName = isset($_GET['viewname']) ? $_GET['viewname'] : '';
            $params = isset($_GET['params']) ? $_GET['params'] : '';
            $parsedSearchParams = !empty($params) ? json_decode($params, true) : null;

            try {
                $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
                $pdf->SetAuthor('VTigerCRM - Billing');
                $pdf->SetTitle('Invoices');
                $pdf->SetPrintHeader(false);
                $pdf->SetPrintFooter(false);
                $pdf->SetFont('dejavusans', '', 12);
                $pdf->SetMargins(10, 10, 7, 7);
                $pdf->AddPage();
                $currentY = 10;
                $invoiceHeight = 87;
                $pdf->SetAutoPageBreak(false, 0);

                if ($selectedIds == "all") {
                    $idList = getIdList($viewName, $parsedSearchParams);
                }
                
                // Получаем QR код один раз для всех счетов
                $qrCodeData = getQrCode();
                
                // Получаем все данные счетов одним запросом
                $allInvoicesData = getAllInvoicesData($idList);
                
                foreach ($idList as $id) {
                    $html = getHtml($id, $qrCodeData, $allInvoicesData);

                    if ($currentY + $invoiceHeight > 290) {
                        $pdf->AddPage();
                        $currentY = 10;
                    }

                    $pdf->writeHTMLCell(
                        $w = 0,
                        $h = $invoiceHeight,
                        $x = 10,
                        $y = $currentY,
                        $html,
                        $border = 0,
                        $ln = 0,
                        $fill = false,
                        $reseth = false,
                        $align = '',
                        $autopadding = true
                    );

                    $currentY += $invoiceHeight;
                }

                // Очищаем буфер перед отправкой PDF
                ob_clean();

                // Отправляем PDF
                $pdf->Output('Invoices_' . date('YmdHis') . '.pdf', 'I');
                exit();
            } catch (Exception $e) {
                // Очищаем буфер в случае ошибки
                ob_clean();
                error_log("PDF Generation Error: " . $e->getMessage());
                die("Error generating PDF: " . $e->getMessage());
            }
        }
    }
    exit();
}

function getIdList($viewName, $parsedSearchParams)
{
    global $adb;
    $idList = [];
    $sql_select = "SELECT inv.invoiceid FROM vtiger_invoice inv
					INNER JOIN vtiger_crmentity vc ON inv.invoiceid = vc.crmid
					LEFT JOIN vtiger_estates es ON inv.cf_estate_id = es.estatesid
					INNER JOIN vtiger_crmentity vc2 ON es.estatesid = vc2.crmid";
    $sql_where = " WHERE vc.deleted = 0 ";

    if ($parsedSearchParams !== null) {
        foreach ($parsedSearchParams as $criteria) {
            foreach ($criteria as $condition) {
                $field = $condition[0];
                $operator = $condition[1];
                $value = $condition[2];
                if (strpos($field, 'subject') !== false) {
                    $sql_where .= " AND inv.subject LIKE '%$value%'";
                } elseif ($field == '(cf_estate_id ; (Estates) cf_object_type)') {
                    $parts = explode(",", $value);
                    $sqlValues = array_map(function ($part) {
                        return "'" . trim($part) . "'";
                    }, $parts);
                    $sqlString = implode(", ", $sqlValues);
                    $sql_where .= " AND es.cf_object_type IN ($sqlString)";
                } elseif ($field == 'invoicedate') {
                    if ($operator == 'bw') {
                        $dateRange = explode(",", $value);
                        $startDate = date('Y-m-d', strtotime($dateRange[0]));
                        $endDate = date('Y-m-d', strtotime($dateRange[1]));
                        $sql_where .= " AND inv.invoicedate BETWEEN '$startDate' AND '$endDate'";
                    }
                } elseif ($field == '(cf_estate_id ; (Estates) cf_streets)') {
                    $parts = explode(",", $value);
                    $sqlValues = array_map(function ($part) {
                        return "'" . trim($part) . "'";
                    }, $parts);
                    $sqlString = implode(", ", $sqlValues);
                    $sql_where .= " AND es.cf_streets IN ($sqlString)";
                } elseif ($field == 'invoicestatus') {
                    $parts = explode(",", $value);
                    $sqlValues = array_map(function ($part) {
                        return "'" . trim($part) . "'";
                    }, $parts);
                    $sqlString = implode(", ", $sqlValues);
                    $sql_where .= " AND inv.invoicestatus IN ($sqlString)";
                } elseif ($field == '(cf_estate_id ; (Estates) assigned_user_id)') {
                    $parts = explode(",", $value);
                    // Fetch user ids based on user names
                    $userIds = [];
                    foreach ($parts as $fullName) {
                        $nameParts = explode(" ", trim($fullName));
                        $firstName = $nameParts[0];
                        $lastName = $nameParts[1];
                        $query = "SELECT id FROM vtiger_users WHERE first_name = '" . $firstName . "' AND last_name = '" . $lastName . "'";
                        $result = $adb->run_query_allrecords($query);
                        foreach ($result as $row) {
                            $userIds[] = $row['id'];
                        }
                    }
                    $sqlValues = array_map(function ($id) {
                        return "'" . $id . "'";
                    }, $userIds);
                    $sqlString = implode(", ", $sqlValues);
                    $sql_where .= " AND vc2.smownerid IN ($sqlString)";
                }
            }
        }
    } else {
        echo 'Ошибка декодирования JSON.';
    }
    $order_by = " ORDER BY es.cf_streets, es.cf_house_number, es.cf_apartment_number ASC";
    $sql = $sql_select . $sql_where . $order_by;
    $ids_result = $adb->run_query_allrecords($sql);
    foreach ($ids_result as $value) {
        array_push($idList, $value['invoiceid']);
    }
    return $idList;
}

// Новая функция для получения всех данных счетов одним запросом
function getAllInvoicesData($idList)
{
    global $adb;
    
    if (empty($idList)) {
        return [];
    }
    
    $idString = implode(',', $idList);
    
    // Получаем основные данные счетов
    $mainQuery = "SELECT 
        inv.invoiceid,
        o.organizationname AS org_name, 
        o.address AS org_address,
        o.state,
        o.city AS org_city,
        o.vatid AS org_inn,
        o.org_account AS org_account,
        o.phone AS org_phone,
        o.org_bik AS org_bik,
        o.org_bank AS org_bank,
        inv.subject,
        inv.total,
        inv.pre_tax_total,
        inv.invoicedate,
        es.estate_number, 
        es.cf_balance as debt,
        es.cf_object_type,
        es.cf_inhabited_locality,
        es.cf_streets,
        es.cf_house_number,
        es.cf_apartment_number,
        es.cf_litera,
        es.cf_contract_num,
        es.cf_lastname, 
        u.first_name AS controler_first_name,
        u.last_name AS controler_last_name,
        u.phone_mobile
        FROM vtiger_invoice inv
        LEFT JOIN vtiger_estates es ON inv.cf_estate_id = es.estatesid 
        INNER JOIN vtiger_crmentity vc ON inv.invoiceid = vc.crmid 
        LEFT JOIN vtiger_crmentity vc2 ON vc2.crmid = es.estatesid 
        LEFT JOIN vtiger_users u ON vc2.smownerid = u.id  
        JOIN vtiger_organizationdetails o
        WHERE vc.deleted = 0
        AND inv.invoiceid IN ($idString)";
    
    $mainResult = $adb->run_query_allrecords($mainQuery);
    
    // Получаем данные услуг
    $servicesQuery = "SELECT 
        inventoryproductrel.id as invoiceid,
        service.servicename, 		
        service.serviceid, 
        service.cf_accrual_base as accrual_base, 
        service.cf_method as cf_method, 
        inventoryproductrel.previous_reading, 
        inventoryproductrel.current_reading, 
        inventoryproductrel.quantity, 
        inventoryproductrel.listprice, 
        inventoryproductrel.accrual_base as qty_days, 
        tax1,
        tax2,
        tax3,
        inventoryproductrel.margin,
        (COALESCE(inventoryproductrel.tax1, 0) + 
            COALESCE(inventoryproductrel.tax2, 0) + 
            COALESCE(inventoryproductrel.tax3, 0)) / 100 * inventoryproductrel.margin AS tax_amount
        FROM vtiger_inventoryproductrel inventoryproductrel 
        LEFT JOIN vtiger_service service ON service.serviceid = inventoryproductrel.productid
        WHERE inventoryproductrel.id IN ($idString)";
    
    $servicesResult = $adb->run_query_allrecords($servicesQuery);
    
    // Получаем штрафы
    $penaltyQuery = "SELECT 
        vp.cf_to_ivoice as invoiceid,
        SUM(vp.penalty_amount) as penalty 
        FROM vtiger_penalty vp 
        INNER JOIN vtiger_crmentity vc ON vp.penaltyid = vc.crmid 
        WHERE vc.deleted = 0
        AND vp.cf_to_ivoice IN ($idString)
        GROUP BY vp.cf_to_ivoice";
    
    $penaltyResult = $adb->run_query_allrecords($penaltyQuery);
    
    // Группируем данные по invoiceid
    $data = [];
    
    // Основные данные
    foreach ($mainResult as $row) {
        $data[$row['invoiceid']] = [
            'main' => $row,
            'services' => [],
            'penalty' => 0
        ];
    }
    
    // Услуги
    foreach ($servicesResult as $row) {
        if (isset($data[$row['invoiceid']])) {
            $data[$row['invoiceid']]['services'][] = $row;
        }
    }
    
    // Штрафы
    foreach ($penaltyResult as $row) {
        if (isset($data[$row['invoiceid']])) {
            $data[$row['invoiceid']]['penalty'] = $row['penalty'];
        }
    }
    
    return $data;
}

function getHtml($invoiceId, $qrCodeData, $allInvoicesData)
{
    global $adb;
    
    // Получаем данные из кэша
    if (!isset($allInvoicesData[$invoiceId])) {
        return '<div>Ошибка: данные счета не найдены</div>';
    }
    
    $invoiceData = $allInvoicesData[$invoiceId];
    $row = $invoiceData['main'];
    $services = $invoiceData['services'];
    $penalty = $invoiceData['penalty'];

    $lastname = $row['cf_lastname'];
    $debt = round($row['debt'], 2);
    $object_type = $row['cf_object_type'];
    $personalAccount = $row['estate_number'];
    $createdtime = date("d-m-Y", strtotime($row['invoicedate']));
    $house_no = $row['cf_house_number'];
    $street = $row['cf_streets'];
    $litera = $row['cf_litera'];
    $apartment_number = $row['cf_apartment_number'];
    $inhabited_locality = $row['cf_inhabited_locality'];
    $subject = $row['subject'];

    $pre_tax_total = $row['pre_tax_total'];
    $total = $row['total'];

    $address = "";

    // Добавляем улицу, если она есть
    if (!empty($street)) {
        $address .= " ул.$street";
    }
    if (!empty($house_no)) {
        $address .= " д.$house_no";
    }
    if (!empty($litera)) {
        $address .= "/$litera";
    }
    if (!empty($apartment_number)) {
        $address .= " кв.$apartment_number";
    }

    // Organization information
    $org_name = $row['org_name'];
    $org_phone = $row['org_phone'];
    $state = $row['state'];
    $org_city = $row['org_city'];
    $org_inn = $row['org_inn'];
    $org_address = $row['org_address'];

    $present_day = date('d-m-Y');
    $paytime = date('d-m-Y', strtotime($createdtime . "+10 days"));

    $html1 = "
	
	<table border= \"0\"class=\"head\" cellpadding=\"1\" >
		<tr class=\"td_border_top\">
			<td width=\"36%\">
				<strong>ЭСЕП-БИЛДИРМЕ / СЧЕТ-ИЗВЕЩЕНИЕ</strong><br>
				<span style=\"font-size: 8px;\">Мөөнөтү / Период счета: $subject</span><br>
				<span style=\"font-size: 8px;\">Эсеп жазылды / Счёт выписан: $createdtime</span><br>
				<span style=\"font-size: 8px;\">Төлөш керек / Оплатить до: $paytime </span><br>
				<span style=\"font-size: 8px;\">Эсеп басылган / Счет распечатан: $present_day </span>

			</td>
			<td width=\"31%\" class = \"space\" style=\" padding: 0;\" align=\"center\"> $org_name<br>
				ИНН " . $org_inn . "<br>" . 'тел.' . $org_phone . "<br>" . 'г.' . $org_city . ' ' . "$org_address
			</td>
			<td width=\"32%\" style=\" text-align:right \">
				<strong>Эсеп / Лицевой счет:</strong> <span style=\" font-size: 10px; font-weight: bold\">$personalAccount</span><br>
				Аты жөнү / ФИО: <b>$lastname</b><br>
				Дареги / Адрес: <span style=\" font-size: 8px;font-weight: bold\">$inhabited_locality <br>$address</span>
			</td>
		</tr>
	</table>";

    $html2 = "
			<table border= \"0\" class=\"body\" cellpadding=\"1\">
			<tr class=\"table-head\">
				<td width=\"55%\">Коммуналдык кызмат / Ком. услуга</td>
				<td width=\"15%\" style=\"text-align: center;\">Количество</td>
				<td width=\"15%\" style=\"text-align: right;\">Тариф</td>
				<td width=\"15%\" style=\"text-align: right;\">Сумма</td>
			</tr>";

    $temp_summ = 0;
    foreach ($services as $row) {
        $serviceName = $row['servicename'];
        $quantity = round($row['quantity'], 2);
        $listPrice = round($row['listprice'], 3);
        $tax1 = round($row['tax1'], 2);
        $tax2 = round($row['tax2'], 2);
        $margin = round($row['margin'], 2);
        $method = $row['cf_method'];
        $previousReading = round($row['previous_reading'], 3);
        $currentReading = round($row['current_reading'], 3);

        $qty_days = $row['qty_days'];
        $tax_amount_NSP = round(($tax2 * $margin) / 100, 2);
        $tax_amount_NDS = round(($tax1 * $margin) / 100, 2);

        $total_with_tax = $margin + $tax_amount_NSP + $tax_amount_NSP;

        if ($quantity < 0) {
            $margin = 0;
        }

        $html2 .= "
			<tr>
				<td width=\"55%\">$serviceName</td>
				<td width=\"15%\" style=\"text-align: center;\">$quantity</td>
				<td width=\"15%\" style=\"text-align: right;\">$listPrice</td>
				<td width=\"15%\" style=\"text-align: right;\">$margin</td>
			</tr>";
        $temp_summ += $margin;
    }

    $previous_debt = $debt - ($total + $penalty); // вычисляем предыдущую задолженность

    if ($previous_debt > 0) {
        $zadolzhennost = $previous_debt; // задолженность
        $overpayment = 0;
    } elseif ($previous_debt < 0) {
        $zadolzhennost = 0;
        $overpayment = abs($previous_debt); // переплата
    } else {
        $zadolzhennost = 0;
        $overpayment = 0;
    }

    // Генерация QR-кода только если нет переплаты
    $qrCodeImageBase64 = '';
    if ($overpayment == 0 && !empty($qrCodeData)) {
        $qrCodeImageBase64 = generateQrCode($qrCodeData, $personalAccount, $subject, $debt);
    }

    $itogoKOplate = $overpayment > 0 ? 0 : round($debt, 2);

    $html2 .= "
<tr>
    <td width=\"55%\" style=\"border: none; text-align: left;\">";
    if ($overpayment > 0) {
        $html2 .= "
        <div style=\"border: none; text-align: left;\">QR-код не выведен, так как к оплате 0 сом</div>";
    } else {
        $html2 .= "
        <div style=\"border: none; text-align: left;\">Вы можете оплатить через QR-код\Сиз QR аркылуу төлөй аласыз</div>";
        if (!empty($qrCodeImageBase64)) {
            $html2 .= "
        <div style=\"border: none; text-align: left;\">
        <img src=\"data:image/png;base64,{$qrCodeImageBase64}\" width=\"60\" alt=\"QR-код для оплаты\" />
        </div>";
        } else {
            $html2 .= "
        <div style=\"border: none; text-align: left;\">Qr заблокирован , партнёр временно недоступен. Попробуйте позже
        </div>";
        }
    }

$html2 .= "
    </td>
    <td width=\"30%\" style=\"border: none; text-align: left; border-right:.5px solid black;\">
        <div style=\"border: none; text-align: right;\">Начислено</div>
        <div style=\"border: none; text-align: right;\">Карыз / Задолженность</div>
        <div style=\"border: none; text-align: right;\">Ашыкча төлөм / Переплата<br></div>
        <div style=\"border: none; text-align: right;\"><b>Жалпы төлөм / Итого к оплате</b></div>
    </td>
    <td width=\"15%\" style=\"text-align: left;\">
        <div style=\"text-align: right;\">" . round($total, 2) . "</div>
        <div style=\"text-align: right;\">" . round($zadolzhennost, 2) . "</div>
        <div style=\"text-align: right;\">" . round($overpayment, 2) . "<br></div>
        <div style=\"text-align: right;\"><b>" . $itogoKOplate . "</b></div>
    </td>
</tr>
</table>";

    $htmlResult =
        <<<EOD
		<style>

		.invoice {
			font-size: 8px;
		}
		.body {
			width:100%;
			padding: 0;
			margin: 0;
		}
		.body td {
			border: .5px solid black;
		}
		.head td {

		}
		.table-head {
			font-weight: bold;
		}

		div.invoice {
			border-bottom: .1px dashed black;
			
		}
		.td_border_bot{

		}
		/*.td_border_top{
			border-top: 1px solid black;
			border-right: 1px solid black;
			border-left: 1px solid black;
			border-bottom: 1px solid black;
		}*/


		.space{
			display: flex;
			border: none;
			justify-content: center;
		}

		</style>

		<div nobr="true" class="invoice">
			<table border="1">
			<tr>
				<td> 
					$html1
					$html2
				</td>
			</tr>
			</table>
		</div>
EOD;

    return $htmlResult;

}

function getQrCode()
{
    global $adb;
    $result = $adb->run_query_allrecords(
        "SELECT vp.cf_qr_check,
        vp.cf_qr_service_id,
        vp.cf_qr_url,
        vp.cf_qr_login,
        vp.cf_qr_password
        FROM vtiger_pymentssystem vp
        INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.pymentssystemid
        WHERE vp.payer_title = 'MegaPay Таза Аймак'
        AND vp.cf_qr_check = 'Подключен'
        AND vc.deleted = 0 AND vp.cf_municipal_enterprise = 9"
    );

    if ($result && count($result) > 0) {
        $row = $result[0];

        $qr_check = $row['cf_qr_check'];
        $qr_service_id = $row['cf_qr_service_id'];
        $qr_url = $row['cf_qr_url'];
        $qr_login = $row['cf_qr_login'];
        $qr_password = $row['cf_qr_password'];

        return [
            'qr_check' => $qr_check,
            'qr_service_id' => $qr_service_id,
            'qr_url' => $qr_url,
            'qr_login' => $qr_login,
            'qr_password' => $qr_password
        ];
    } else {
        // Возвращаем пустой массив или обрабатываем ошибку
        return [];
    }
}

function generateQrCode($qrCodeData, $personalAccount, $serviceName, $total)
{
    // Данные для POST запроса
    if ($total > 50000 or $total < 0) {
        $editable = true;
        $total = 1;
    } else {
        $editable = false;
    }

    $postData = [
        'serviceId' => $qrCodeData['qr_service_id'],
        'destination' => $personalAccount,
        'paymentComment' => $serviceName,
        'paymentAmount' => $total,
        'amountEditable' => $editable,
        'qrType' => 'DYNAMIC',
        'destinationEditable' => false,
        'qrTransactionId' => ''
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $qrCodeData['qr_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($qrCodeData['qr_login'] . ':' . $qrCodeData['qr_password'])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $responseData = json_decode($response, true);

    if ($responseData['status'] == 'SUCCESS' && isset($responseData['data'])) {
        $qrCodeUrl = $responseData['data'];
        $qrCode = new QrCode($qrCodeUrl);
        $writer = new PngWriter();
        $qrCodeImage = $writer->write($qrCode);
        $dataUri = $qrCodeImage->getDataUri();
        // Получаем только base64-строку без префикса
        $base64 = explode(',', $dataUri, 2)[1];
        return $base64;
    }
    return '';
}
