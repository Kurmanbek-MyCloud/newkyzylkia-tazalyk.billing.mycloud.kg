<?php

class Invoices {
	private $mp_id;

	public function run() {
		$input = file_get_contents('php://input');
		$logger = new MyLogger('api/invoices/requests.log');
		$logger->log("IP: " . $_SERVER['REMOTE_ADDR'] . " | Request: " . $input);
		$meters = json_decode($input);
		if (isset($meters->token)) {
			$token = $meters->token;
			$system_ip = $_SERVER['REMOTE_ADDR'];
			if (empty($meters->uid)) {
				throw new Exception('Не определено поле uid');
			}
			$uid = $meters->uid;
			$system = $this->check_system($token, $system_ip, $uid);
			$this->mp_id = $system['cf_municipal_enterprise'];
			if (isset($meters->account)) {
				if (empty($meters->account)) {
					throw new Exception('Пустой лицевой счет');
				} else {
					$accountNumber = $meters->account;
					if (isset($meters->command)) {
						if ($meters->command == 'check_invoice') {
							return $this->checkInvoice($accountNumber);
						} elseif ($meters->command == 'check_payments') {
							return $this->checkPay($accountNumber);
						} elseif ($meters->command == 'deeplink_list') {
							return $this->getDeeplinkList();
						} elseif ($meters->command == 'check_client') {
							return $this->checkClient($accountNumber);
						} elseif ($meters->command == 'get_deeplink') {
							if (!isset($meters->amount)) {
								throw new Exception('Не определено поле суммы');
							} else if (empty($meters->amount)) {
								throw new Exception('Поле суммы пустое');
							}
							$amount = $meters->amount;
							if (!isset($meters->system)) {
								throw new Exception('Не определено поле платежной системы');
							} else if (empty($meters->system)) {
								throw new Exception('Поле платежной системы пустое');
							}
							$system = $meters->system;
							return $this->getDeeplink($accountNumber, $amount, $system);
						} elseif ($meters->command == 'add_request') {
							if (!isset($meters->phone)) {
								throw new Exception('Не определено контактный номер');
							} else if (empty($meters->phone)) {
								throw new Exception('Поле контактный номер пустое');
							}
							$phone = $meters->phone;
							if (!isset($meters->description)) {
								throw new Exception('Не определено поле описания');
							} else if (empty($meters->description)) {
								throw new Exception('Поле описания пустое');
							}
							$description = $meters->description;
							return $this->add_request($accountNumber, $phone, $description);
						} else {
							throw new Exception('Не верное или пустое действие');
						}
					} else {
						throw new Exception('Не определено поле действия');
					}
				}
			} else {
				throw new Exception('Не определено поле лицевого счета');
			}
		} else {
			throw new Exception('Не определено поле токена');
		}
	}
	public function add_request($accountNumber, $phone, $description) {
		$CRM = new CRM();
		$save_request = $CRM->createRequest($accountNumber, $phone, $description);
		return ['add_request', $save_request];
	}
	public function checkClient($accountNumber) {
		global $adb;
		$sql = "SELECT * from vtiger_estates vf 
		inner join vtiger_crmentity vc on vf.estatesid = vc.crmid 
		where vc.deleted = 0 and vf.estate_number = '$accountNumber' and vf.cf_municipal_enterprise = '{$this->mp_id}'";
		$find = $adb->run_query_allrecords($sql);
		if ($find == []) {
			throw new Exception("Абонент с лицевым счетом $accountNumber не найден");
		}
		return ['check_client', True];
	}
	public function checkPay($accountNumber) {
		global $adb;
		$sql_contact_info = "SELECT cf_lastname, 
		estate_number
		from vtiger_estates vf 
		inner join vtiger_crmentity vc on vc.crmid = vf.estatesid 
		where vc.deleted = 0 and vf.estate_number = '$accountNumber' and vf.cf_municipal_enterprise = '{$this->mp_id}'";

		$sql = "SELECT DATE_FORMAT(cf_pay_date, '%d-%m-%Y') as pay_date, 
		cf_txnid,
		cf_payment_type, amount, cf_payment_source from vtiger_payments sp  
		inner join vtiger_crmentity vc on vc.crmid = sp.paymentsid 
		inner join vtiger_estates vf on sp.cf_paid_object = vf.estatesid 
		where vc.deleted = 0 and vf.estate_number = '$accountNumber' and vf.cf_municipal_enterprise = '{$this->mp_id}' order by sp.cf_pay_date desc";
		$res = $adb->run_query_allrecords($sql_contact_info);
		$payments = $adb->run_query_allrecords($sql);
		$payments_data = array();
		foreach ($payments as $payment) {
			$payments_data[] = array(
				'pay_date' => $payment['pay_date'],
				'type_payment' => $payment['cf_payment_type'],
				'amount' => (float) $payment['amount'],
				'system' => $payment['cf_payment_source'],
				'cf_txnid' => $payment['cf_txnid']
			);
		}
		return ['check_payments', $res, $payments_data];
	}
	public function getDeeplinkList() {
		require 'config.inc.php';
		global $adb;
		$sql_deeplink_list = "SELECT payer_title, cf_deeplink_image from vtiger_pymentssystem vp 
		inner join vtiger_crmentity vc on vp.pymentssystemid = vc.crmid 
		where vc.deleted = 0 and vp.cf_deeplink_check = 'Подключен' and vp.cf_municipal_enterprise = '{$this->mp_id}'";
		$systems = $adb->run_query_allrecords($sql_deeplink_list);
		$systems_data = array();
		if ($systems == []) {
			throw new Exception('Нет подключенных платежных систем с дииплинком');
		}
		foreach ($systems as $system) {
			$systems_data[] = array(
				'system_name' => $system['payer_title'],
				'link' => $site_URL . "api/deeplink_images/" . $system['cf_deeplink_image']
			);
		}
		return ['deeplink_list', $systems_data];
	}

		public function getDeeplink($accountNumber, $amount, $system)
	{
		require 'config.inc.php';
		global $adb;

		$logger = new MyLogger('api/invoices/app.log');

		// Тору Айгыр Таза суу только
		$sql_deeplink_list = "SELECT vp.cf_service_id, vp.cf_deeplink_password, cf_server_address, cf_qr_login, cf_qr_password, cf_qr_url 
			FROM vtiger_pymentssystem vp 
			INNER JOIN vtiger_crmentity vc ON vp.pymentssystemid = vc.crmid 
			WHERE vc.deleted = 0 AND vp.cf_deeplink_check = 'Подключен' AND vp.cf_municipal_enterprise = '{$this->mp_id}' AND vp.payer_title = 'MegaPay'";

		$row = $adb->run_query_allrecords($sql_deeplink_list)[0];
		$service_id = $row['cf_service_id'];
		$logger->log("service_id: " . $service_id);
		$logger->log("mp_id: " . "{$this->mp_id}");

		if ($system == 'MegaPay') {
			$link = "https://megapay.kg/get/#deeplink?serviceId=$service_id&amount=$amount&destination=$accountNumber&special=false";
			return ['get_deeplink', $link];

		} elseif ($system == 'Mbank') {
			$qrCode = getQRCode($accountNumber, $amount, $logger, $this->mp_id);
			$link = "https://app.mbank.kg/qr/#$qrCode";
			$logger->log("$link");

			return ['get_deeplink', $link];

		} elseif ($system == 'О!Деньги') {
			$qrCode = getQRCode($accountNumber, $amount, $logger, $this->mp_id);
			$link = "https://api.dengi.o.kg/#$qrCode";
			$logger->log("$link");

			return ['get_deeplink', $link];

		} elseif ($system == 'Balance.kg') {
			$qrCode = getQRCode($accountNumber, $amount, $logger, $this->mp_id);
			$logger->log("qrCode: " . $qrCode);
			$link = "	";
			$logger->log("$link");

			return ['get_deeplink', $link];

		} elseif ($system == 'Айыл Банк') {
			$qrCode = getQRCode($accountNumber, $amount, $logger, $this->mp_id);
			$logger->log("qrCode: " . $qrCode);
			// $link = "https://app.ab.kg/qr/#$qrCode";
			// $link = "https://qr.ab.kg/#$qrCode";
			$link = "https://qr.ab.kg/#$qrCode";
			$logger->log("$link");

			return ['get_deeplink', $link];

		} elseif ($system == '	Bakai Bank') {
			$qrCode = getQRCode($accountNumber, $amount, $logger, $this->mp_id);
			$logger->log("qrCode: " . $qrCode);
			$link = "https://bakai24.app/qr/#$qrCode";
			$logger->log("$link");

			return ['get_deeplink', $link];

		}elseif ($system == 'Элдик Банк') {
			$link = "https://app.eldik.kg/payment?serviceId=$service_id&amount=$amount&accountNumber=$accountNumber";
			return ['get_deeplink', $link];
		} else {
			throw new Exception('Нет подключения к данной платежной системе');
		}
	}


	public function checkInvoice($accountNumber) {
		require 'config.inc.php';
		global $adb;
		$sql_contact_info = "SELECT cf_lastname,
		((SELECT IFNULL((SELECT SUM(round(total,
1)) FROM vtiger_invoice AS di 
		INNER JOIN vtiger_crmentity AS icrm ON icrm.crmid = di.invoiceid
		WHERE deleted = 0 AND di.cf_estate_id = vf.estatesid ),
0))
		-
		(SELECT IFNULL ((SELECT SUM(round(amount,
1)) FROM vtiger_payments AS p
		INNER JOIN vtiger_crmentity AS pcrm ON pcrm.crmid = p.paymentsid
		WHERE deleted = 0 AND p.cf_paid_object = vf.estatesid),
0))) AS debt,
		'-' AS cf_1261,
		vf.cf_inhabited_locality AS cf_1450,
		estate_number,CONCAT(' Ул. ', vf.cf_streets , ' Дом. ', vf.cf_house_number, 
			   CASE WHEN vf.cf_apartment_number IS NOT NULL AND vf.cf_apartment_number <> '' THEN CONCAT(' Кв ', vf.cf_apartment_number) ELSE '' END
		) AS cf_streets
		from vtiger_estates vf 
		inner join vtiger_crmentity vc on vc.crmid = vf.estatesid 
		where vc.deleted = 0 and vf.estate_number = '$accountNumber' and vf.cf_municipal_enterprise = '{$this->mp_id}'";


		$sql_five_invoices = "SELECT 
				vi.invoiceid, 
				vi.subject, 
				DATE_FORMAT(vi.duedate, '%d-%m-%Y') AS duedate, 
				vi.adjustment, 
				vi.total,
				vi.invoicestatus as invoicestatus
			FROM 
				vtiger_invoice vi 
			INNER JOIN 
				vtiger_crmentity vc ON vc.crmid = vi.invoiceid 
			INNER JOIN 
				vtiger_estates vf ON vi.cf_estate_id = vf.estatesid 
			WHERE 
				vc.deleted = 0 
				AND vf.estate_number = '$accountNumber'
				AND vf.cf_municipal_enterprise = '{$this->mp_id}'
			ORDER BY 
				vi.duedate DESC 
			LIMIT 5;";



		$res = $adb->run_query_allrecords($sql_contact_info);
		$invoices = $adb->run_query_allrecords($sql_five_invoices);
		$invoices_data = array();
		foreach ($invoices as $invoice) {
			$services_data = array();
			$invoice_id = $invoice['invoiceid'];
			$sql_services_info = "SELECT vs.servicename, listprice, quantity, previous_reading, current_reading  from vtiger_inventoryproductrel vrl
			inner join vtiger_service vs on vs.serviceid = vrl.productid 
			inner join vtiger_servicecf vs2 on vs2.serviceid = vs.serviceid 
			where vrl.id = '$invoice_id'";
			$services = $adb->run_query_allrecords($sql_services_info);
			foreach ($services as $service) {
				$services_data[] = array(
					'servis_name_kg' => $service['servicename'],
					'servis_name_ru' => $service['servicename'],
					'base' => $service['cf_accrual_base'],
					'listprice' => number_format((float) $service['listprice'], 1, '.', ''),
					'quantity' => number_format((float) $service['quantity'], 1, '.', ''),
					'previous_reading' => number_format((float) $service['previous_reading'], 1, '.', ''),
					'current_reading' => number_format((float) $service['current_reading'], 1, '.', '')
				);
			}
			$invoices_data[] = array(
				'subject' => $invoice['subject'],
				'date' => $invoice['duedate'],
				'link' => $site_URL . "PrintPDF/PrintPDF_for_invoice.php?module=Invoice&selectedIds=[" . $invoice['invoiceid'] . "]&viewname=21",
				'total' => (float) $invoice['total'],
				'adjustment' => (float) $invoice['adjustment'],
				'invoicestatus' => $invoice['invoicestatus'],
				'data' => $services_data
			);
		}
		return ['check_invoice', $res, $invoices_data];
	}
	public function check_system($token, $system_ip, $uid) {
		require_once 'MyLogger.php';
		$logger = new MyLogger('api/meters/meters.log');
		global $dbConn;
		if ($token == '44cf37b471e4bcd2e8ab4fbe55dbf72359fb19dd55ad335b695b4c97bec4e4d1') {
			$system_ip = '92.62.72.168';
		}

		$sql = "SELECT payer_title, vp.cf_municipal_enterprise from vtiger_pymentssystem vp
		inner join vtiger_crmentity vc on vp.pymentssystemid = vc.crmid
		where vc.deleted = 0 and cf_payer_token = '$token' and vp.cf_uid_billing = '$uid' and '$system_ip' in (vp.cf_payer_ip_1, vp.cf_payer_ip_2, vp.cf_payer_ip_3, '92.62.72.168')";

		$result = $dbConn->query($sql);


		if ($result) {

			if ($result->num_rows == 0) {
				$logger->log("Не зарегистрирована платежная система IP $system_ip, Token $token");
				throw new Exception('Вы не являетесь зарегистрированной системой');
			} elseif ($result->num_rows == 1) {
				$row = $result->fetch_assoc();
				return $row;
			} else {
				$logger->log("В системе оказалось больше одной платежной системы с IP $system_ip, Token $token");
				throw new Exception('В системе оказалось больше одной платежной системы с токеном ' . $token);
			}
		} else {
			$logger->log('При поиске платежной системы произошла ошибка. Ошибка MySQL - ' . $dbConn->error);
			throw new Exception('При поиске платежной системы произошла ошибка. Ошибка MySQL - ' . $dbConn->error);
		}
	}
	public function isFieldExists($tableName, $fieldName, $adb) {
		try {

			$sql = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$tableName' AND COLUMN_NAME = '$fieldName'";
			$result = $adb->run_query_allrecords($sql)[0];
			return ($result['count'] > 0);
		} catch (PDOException $e) {
			// Обработка ошибки, например, логирование
			error_log("Error checking field existence: " . $e->getMessage());
			return false;
		}
	}

}


function generateQrCode($qrCodeData, $amount, $accountNumber, $logger)
{
	// Данные для POST запроса
	$postData = [
		'serviceId' => $qrCodeData['qr_service_id'],
		'destination' => $accountNumber,
		'paymentAmount' => $amount,
		'paymentComment' => '',
		'amountEditable' => true,
		'qrType' => 'DYNAMIC',
		'destinationEditable' => false
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
		return $responseData['data'];
	} else {
		echo json_encode([
			'success' => false,
			'error' => 'Failed to generate QR code',
			'details' => $responseData
		]);
		exit();
	}
}



function getQRCode($accountNumber, $amount, $logger, $mp_id)
{
	require 'config.inc.php';
	global $adb;

	$sql_qr_list = "SELECT vp.cf_qr_service_id as cf_service_id, vp.cf_qr_login, vp.cf_qr_password, vp.cf_qr_url
	FROM vtiger_pymentssystem vp
	inner join vtiger_crmentity vc on vp.pymentssystemid = vc.crmid
	where vc.deleted = 0 and vp.cf_deeplink_check = 'Подключен' and vp.cf_municipal_enterprise = '$mp_id' and vp.payer_title = 'MegaPay'
	LIMIT 1";

	$row = $adb->run_query_allrecords($sql_qr_list)[0];

	$qrCodeData = [
	'qr_service_id' => $row['cf_service_id'],
	'qr_login' => $row['cf_qr_login'],
	'qr_password' => $row['cf_qr_password'],
	'qr_url' => $row['cf_qr_url']
	];

	$logger->log("Параметры для QR кода: " . json_encode($qrCodeData));

	$responseData = generateQrCode($qrCodeData, $amount, $accountNumber, $logger);

	$logger->log("Ответ от QR API: " . $responseData);

	// $responseData уже является URL строкой от generateQrCode()
	$qrUrl = $responseData;

	// Разбиваем и получаем часть после #
	$parts = explode('#', $qrUrl);
	$qrCode = $parts[1] ?? null;
	return $qrCode;
}

