<?php
chdir('../../');
require_once 'include/database/PearDatabase.php';
require 'vendor/autoload.php';
global $adb;

// Получение доступных платёжных систем
function getPaymentSystems() {
    global $adb;
    $query = "SELECT DISTINCT vp.cf_payment_source 
              FROM vtiger_payments vp
              INNER JOIN vtiger_crmentity vc ON vp.paymentsid = vc.crmid
              WHERE vc.deleted = 0 AND vp.cf_payment_source NOT IN ('тестовый')";
    return $adb->run_query_allrecords($query);
}


// Подсчёт записей
function countPayments($system = null, $startDate = null, $endDate = null) {
    global $adb;
    $query = "SELECT COUNT(*) as count, SUM(vp.amount) as total_amount 
              FROM vtiger_payments vp
              INNER JOIN vtiger_crmentity vc ON vp.paymentsid = vc.crmid 
              WHERE vc.deleted = 0";
                  $params = [];

              if (!empty($_GET['mp'])) {
    $query .= " AND vp.cf_paid_object IN (
                    SELECT estatesid
                    FROM vtiger_estates
                    WHERE cf_municipal_enterprise = ?
                )";
    $params[] = (int)$_GET['mp'];
}




    if (!empty($system)) {
        $query .= " AND vp.cf_payment_source = ?";
        $params[] = $system;
    }
    if (!empty($startDate)) {
        $query .= " AND vp.cf_pay_date >= ?";
        $params[] = $startDate;
    }
    if (!empty($endDate)) {
        $query .= " AND vp.cf_pay_date <= ?";
        $params[] = $endDate;
    }

    $result = $adb->pquery($query, $params);
    return $result->fields;
}



function getDebtorb($startDate = null, $endDate = null, $sortColumn = null, $sortOrder = 'DESC', $limit = 50, $offset = 0, $streetFilter = null, $houseFilter = null, $onlyDebtors = false,$mp = null) {
    global $adb;

    $formattedStartDate = !empty($startDate) ? $startDate . " 00:00:00" : null;
    $formattedEndDate = !empty($endDate) ? $endDate . " 23:59:59" : null;

    $allowedSortColumns = ['cf_contract_id', 'cf_client_name', 'total_balance'];
    $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'cf_contract_id';
    $sortOrder = in_array(strtoupper($sortOrder), ['ASC', 'DESC']) ? $sortOrder : 'DESC';

    // ORDER BY
    $orderBy = "ORDER BY ve.createdtime DESC";
    if ($sortColumn === 'cf_contract_id') {
        $orderBy = "ORDER BY ve.estate_number $sortOrder";
    } elseif ($sortColumn === 'cf_client_name') {
        $orderBy = "ORDER BY ve.cf_lastname $sortOrder";
    } elseif ($sortColumn === 'total_balance') {
        $orderBy = "ORDER BY final_balance $sortOrder";
    }

    // WHERE условия
    $conditions = [];
    // $params = [
    //     $formattedStartDate,
    //     $formattedStartDate,
    //     $formattedEndDate,
    //     $formattedStartDate,
    //     $formattedEndDate,
    //     $formattedStartDate,
    //     $formattedEndDate,
    //     $formattedEndDate,
    // ];
    
$params = [];

if (!empty($streetFilter)) {
    $conditions[] = "ve.cf_streets = ?";
    $params[] = $streetFilter;
}

if (!empty($houseFilter)) {
    $conditions[] = "ve.cf_house_number = ?";
    $params[] = $houseFilter;
}

if (!empty($_GET['mp'])) {
    $conditions[] = "ve.estatesid IN (
        SELECT estatesid FROM vtiger_estates WHERE cf_municipal_enterprise = ?
    )";
    $params[] = (int)$_GET['mp'];
}


$adb->pquery("SET @date_from = ?, @date_to = ?", [$startDate, $endDate]);

$whereClause = "";
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

    // Формируем запрос с учетом параметра $onlyDebtors
    $query = "WITH balance_data AS (
    SELECT 
        i.cf_estate_id AS estate_id,
        i.total AS amount,
        ci.createdtime AS operation_date,
        'invoice' AS type
    FROM vtiger_invoice i
    INNER JOIN vtiger_crmentity ci ON ci.crmid = i.invoiceid AND ci.deleted = 0

    UNION ALL

    SELECT 
        vi.cf_estate_id,
        p.penalty_amount,
        cp.createdtime,
        'penalty'
    FROM vtiger_penalty p
    INNER JOIN vtiger_crmentity cp ON cp.crmid = p.penaltyid AND cp.deleted = 0
    INNER JOIN vtiger_invoice vi ON vi.invoiceid = p.cf_to_ivoice
    INNER JOIN vtiger_crmentity vci ON vci.crmid = vi.invoiceid AND vci.deleted = 0

    UNION ALL

    SELECT 
        vp.cf_paid_object,
        -vp.amount,
        vp.cf_pay_date,
        'payment'
    FROM vtiger_payments vp
    INNER JOIN vtiger_crmentity cp ON cp.crmid = vp.paymentsid AND cp.deleted = 0
)

SELECT 
    ve.estatesid,
    ve.estate_number AS cf_contract_id,
    ve.cf_lastname AS cf_client_name,
    ve.cf_house_number,
    ve.cf_litera,
    ve.cf_streets,
    MAX(vp.cf_payment_type) as cf_payment_type,

    COALESCE(SUM(CASE WHEN bd.operation_date < @date_from THEN bd.amount ELSE 0 END), 0) AS start_balance,

COALESCE(SUM(CASE 
    WHEN bd.operation_date BETWEEN @date_from AND @date_to AND bd.type = 'invoice'
    THEN bd.amount ELSE 0 END), 0) AS invoice_amount,

COALESCE(SUM(CASE 
    WHEN bd.operation_date BETWEEN @date_from AND @date_to AND bd.type = 'penalty'
    THEN bd.amount ELSE 0 END), 0) AS penalty_amount,

COALESCE(SUM(CASE 
    WHEN bd.operation_date BETWEEN @date_from AND @date_to AND bd.type = 'payment'
    THEN bd.amount ELSE 0 END), 0) AS payment_amount,

COALESCE(SUM(CASE WHEN bd.operation_date <= @date_to THEN bd.amount ELSE 0 END), 0) AS end_balance


FROM vtiger_estates ve
INNER JOIN vtiger_crmentity vc ON vc.crmid = ve.estatesid AND vc.deleted = 0
LEFT JOIN balance_data bd ON bd.estate_id = ve.estatesid

LEFT JOIN vtiger_payments vp ON vp.cf_paid_object = ve.estatesid
LEFT JOIN vtiger_crmentity cp2 ON cp2.crmid = vp.paymentsid AND cp2.deleted = 0

$whereClause

GROUP BY 
    ve.estatesid,
    ve.estate_number,
    ve.cf_lastname,
    ve.cf_house_number,
    ve.cf_litera,
    ve.cf_streets,
    vp.cf_payment_type

HAVING 1 = 1 " . ($onlyDebtors ? " AND end_balance > 0" : "") . "
$orderBy";
// $params = [];




    // Добавляем LIMIT только если есть фильтры
    $hasFilters = $formattedStartDate || $formattedEndDate || $streetFilter || $houseFilter || $onlyDebtors;
    if ($hasFilters) {
        $query .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
    }

    // Выполняем запрос
    $result = $adb->pquery($query, $params);
    if (!$result) {
        echo "<pre>SQL Error: " . $adb->database->_errorMsg . "</pre>";
        echo "<pre>Query: " . $query . "</pre>";
        echo "<pre>Params: " . print_r($params, true) . "</pre>";
        exit;
    }

    // Обрабатываем результат
    $payments = [];
    while ($row = $result->FetchRow()) {
        $payments[] = $row;
    }

    return $payments;
}













function getPayments($system = null, $startDate = null, $endDate = null, $sortColumn, $sortOrder, $limit, $offset) {
    global $adb;

    $query = "SELECT ve.cf_lastname, vp.cf_payment_source, vp.amount, vp.cf_pay_date, vp.cf_pay_no, vp.cf_txnid,cf_payment_type, vp.cf_paid_service, ve.estate_number FROM vtiger_payments vp 
                INNER JOIN vtiger_estates ve ON vp.cf_paid_object = ve.estatesid 
                INNER JOIN vtiger_crmentity vc ON vp.paymentsid = vc.crmid 
                WHERE vc.deleted = 0";
                    $params = [];

                if (!empty($_GET['mp'])) {
    $query .= " AND ve.cf_municipal_enterprise = ?";
    $params[] = (int)$_GET['mp'];
}




    if (!empty($system)) {
        $query .= " AND vp.cf_payment_source = ?";
        $params[] = $system;
    }

    if (!empty($startDate)) {
        $query .= " AND vp.cf_pay_date >= ?";
        $params[] = $startDate;
    }
    if (!empty($endDate)) {
        $query .= " AND vp.cf_pay_date <= ?";
        $params[] = $endDate;
    }

    $validSortColumns = [
        'cf_pay_no' => 'vp.cf_pay_no',
        'cf_pay_date' => 'vp.cf_pay_date',
        'amount' => 'vp.amount',
        'cf_payment_source' => 'vp.cf_payment_source',
        'cf_txnid' => 'vp.cf_txnid',
        'cf_paid_service' => 'vp.cf_paid_service',
    ];

    if (isset($sortColumn) && array_key_exists($sortColumn, $validSortColumns)) {
        $query .= " ORDER BY " . $validSortColumns[$sortColumn] . " $sortOrder";
    } else {
        $query .= " ORDER BY vp.cf_pay_date DESC";
    }

    if (!isset($limit) || $limit <= 0) {
        $limit = 100;
    }
    if (!isset($offset) || $offset < 0) {
        $offset = 0;
    }

    $query .= " LIMIT $limit OFFSET $offset";

    $result = $adb->pquery($query, $params, true);

    $payments = [];

    while ($row = $result->FetchRow()) {
        $payments[] = $row;
    }

    return ['payments' => $payments];
}


// Получение маршрутов
function getRoute($limit = 10, $offset = 0, $streetFilter = null, $houseNumberFilter = null) {
    global $adb;

    // Убираем лимит и смещение для получения всех данных при экспорте
    if ($limit === null) {
        $limit = '';
        $offset = '';
    } else {
        $limit = is_numeric($limit) && $limit > 0 ? (int) $limit : 10;
        $offset = is_numeric($offset) && $offset >= 0 ? (int) $offset : 0;
    }

    $query = "WITH RankedReadings AS (
        SELECT 
            vr.readingsid,
            vr.cf_reading_meter_link,
            vr.meter_reading AS last_reading,
            ROW_NUMBER() OVER (PARTITION BY vr.cf_reading_meter_link ORDER BY vr.cf_reading_date DESC) AS rn
        FROM vtiger_readings vr
        INNER JOIN vtiger_crmentity vc_sub ON vc_sub.crmid = vr.readingsid
        WHERE vc_sub.deleted = 0
    )
    SELECT DISTINCT
        ve.estate_number, 
        ve.cf_streets AS street, 
        ve.cf_litera AS apartment, 
        vm.cf_manufactur AS meter_manufacturer, 
        vm.meter_number, 
        rr.last_reading,
        ve.cf_lastname AS subscriber_name, 
        ve.cf_balance,
        vp.cf_pay_date AS last_payment_date,
        vm.cf_number_digits AS capacity,
        ve.cf_house_number AS house_number,
        ve.cf_litera AS litera
    FROM 
        vtiger_estates ve
    INNER JOIN 
        vtiger_meters vm ON ve.estatesid = vm.cf_meter_object_link 
    INNER JOIN 
        RankedReadings rr ON rr.cf_reading_meter_link = vm.metersid AND rr.rn = 1 
    LEFT JOIN 
        (SELECT cf_paid_object, MAX(cf_pay_date) AS cf_pay_date FROM vtiger_payments GROUP BY cf_paid_object) vp 
        ON vp.cf_paid_object = ve.estatesid 
    INNER JOIN 
        vtiger_crmentity vc ON ve.estatesid = vc.crmid
    WHERE 
        vc.deleted = 0 AND vm.cf_deactivated = 0 ";

    $params = [];

    // Применение фильтра по улице, если он задан
    if (!empty($streetFilter)) {
        $query .= " AND ve.cf_streets = ?";
        $params[] = $streetFilter;
    }

    // Применение фильтра по номеру дома, если он задан
    if (!empty($houseNumberFilter)) {
        $query .= " AND ve.cf_house_number = ?";
        $params[] = $houseNumberFilter;
    }

    $query .= " GROUP BY
                ve.estate_number, ve.cf_streets, ve.cf_litera, vm.cf_manufactur, vm.meter_number, rr.last_reading,
                ve.cf_lastname, ve.cf_balance, vp.cf_pay_date, vm.cf_number_digits, ve.cf_house_number
            ORDER BY 
                (CASE WHEN ve.cf_litera REGEXP '^[0-9]+$' THEN 0 ELSE 1 END),
                CAST(REGEXP_SUBSTR(ve.cf_litera, '^[0-9]+') AS UNSIGNED), 
                LENGTH(ve.cf_litera), 
                ve.cf_litera";

    // Если $limit не пустой, добавляем его
    if ($limit !== '') {
        $query .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
    }

    // Выполнение запроса
    $result = $adb->pquery($query, $params);

    return $result->GetArray();
}



function getReportData($reportType, $limit = 10, $offset = 0, $settlementFilter = null, $startDate = null, $endDate = null) {
    global $adb;

    $maxLimit = 1000;
    $limit = is_numeric($limit) && $limit > 0 ? (int) $limit : 10;
    $limit = $limit > $maxLimit ? $maxLimit : $limit;
    $offset = is_numeric($offset) && $offset >= 0 ? (int) $offset : 0;

    $params = [];
    $where = "WHERE vc.deleted = 0";
    $select = "ve.*, ve.cf_inhabited_locality AS settlement, vc.deleted";
    $from = "FROM vtiger_estates ve INNER JOIN vtiger_crmentity vc ON ve.estatesid = vc.crmid";
    $orderBy = "ORDER BY ve.cf_inhabited_locality";

    if (!empty($settlementFilter)) {
        $where .= " AND ve.cf_inhabited_locality = ?";
        $params[] = $settlementFilter;
    }

    if (!empty($startDate)) {
        $where .= " AND vr.cf_reading_date >= ?";
        $params[] = $startDate;
    }

    if (!empty($endDate)) {
        $where .= " AND vr.cf_reading_date <= ?";
        $params[] = $endDate;
    }

    switch ($reportType) {
        case 'consumption':
            $select = "
                ve.cf_inhabited_locality AS settlement,
                vm.meter_number AS meter_number,
                SUM(vr.meter_reading) AS consumption,
                SUM(vr.meter_reading * 1.5) AS amount,
                COUNT(DISTINCT ve.cf_litera) AS apartments
            ";
            $from = "
                FROM vtiger_estates ve
                INNER JOIN vtiger_crmentity vc ON ve.estatesid = vc.crmid
                INNER JOIN vtiger_meters vm ON ve.estatesid = vm.cf_meter_object_link
                INNER JOIN vtiger_readings vr ON vm.metersid = vr.cf_reading_meter_link
                INNER JOIN vtiger_crmentity vc2 ON vc2.crmid = vr.readingsid
            ";
            $where .= " AND vc2.deleted = 0";
            $groupBy = "GROUP BY ve.cf_inhabited_locality, vm.meter_number";
            break;

        default:
            $select = "ve.cf_inhabited_locality AS settlement, ve.cf_litera, ve.cf_lastname";
            $from = "
                FROM vtiger_estates ve
                INNER JOIN vtiger_crmentity vc ON ve.estatesid = vc.crmid
            ";
            break;
    }

    $query = "SELECT $select $from $where " .
        (!empty($groupBy) ? $groupBy : "") . " " .
        "$orderBy LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $result = $adb->pquery($query, $params);
    return $result->GetArray();
}








function getStreets() {
    global $adb;
    $res = $adb->pquery("
        SELECT DISTINCT cf_streets AS street
        FROM vtiger_estates
        WHERE cf_streets IS NOT NULL
          AND TRIM(cf_streets) <> ''
        ORDER BY cf_streets
    ");
    $out = [];
    while ($r = $res->FetchRow()) {
        $out[] = $r;
    }
    return $out;
}




function getSettlements() {
    global $adb;
    $query = " SELECT DISTINCT ve.cf_inhabited_locality AS settlement
              FROM vtiger_estates ve
              INNER JOIN vtiger_crmentity vc ON ve.estatesid = vc.crmid
              WHERE vc.deleted = 0
              ORDER BY ve.cf_streets  ";
    $result = $adb->run_query_allrecords($query);
    return $result;
}

function getHouseNumbers($streetFilter) {
    global $adb;
    $query = "SELECT DISTINCT ve.cf_house_number AS house_number
              FROM vtiger_estates ve
              INNER JOIN vtiger_crmentity vc ON ve.estatesid = vc.crmid
              WHERE vc.deleted = 0 AND ve.cf_streets = ?
              ORDER BY ve.cf_house_number";
    $params = [$streetFilter];
    $result = $adb->pquery($query, $params);
    return $result->GetArray();
}



function exportToPDF($data) {
    $html = '<html><head>
                <meta charset="UTF-8">
                <style>
                    body {
                        font-family: "DejaVu Sans", sans-serif;
                        font-size: 8px;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 10px;
                    }
                    th, td {
                        padding: 2px 4px;
                        text-align: center;
                        border: 1px solid #000;
                        white-space: nowrap;
                    }
                    th {
                        font-weight: bold;
                        background-color: #f2f2f2;
                        font-size: 8px;
                    }
                    td:first-child {
                        width: 30px;
                        font-weight: bold;
                    }
                    h1, h2 {
                        text-align: center;
                        margin: 5px 0;
                    }
                </style>
            </head><body>';

    $html .= '<h1>МАРШРУТНЫЙ ЛИСТ</h1>';
    $html .= '<h2>Дата обхода: ' . date('d.m.Y') . '</h2>';
    $html .= '<h2>Обходчик: ________________________</h2>';


    $html .= '<table>';
    $html .= '<thead>
                <tr>
                    <th>№</th>
                    <th>ЛС</th>
                    <th>№ дома</th>
                    <th>Кв</th>
                    <th>Производитель</th>
                    <th>Значность</th>
                    <th>Номер</th>
                    <th>Последнее</th>
                    <th>Текущее</th>
                    <th>ФИО</th>
                    <th>Баланс</th>
                    <th>Дата оплаты</th>
                    <th>Дог.</th>
                    <th>Откл.</th>
                </tr>
              </thead><tbody>';
    $street = htmlspecialchars($data[0]['street'] ?? 'Не указана');
    $html .= '<h4>Улица: ' . $street . '</h4>';
    foreach ($data as $index => $row) {
        $html .= '<tr>
                    <td>' . ($index + 1) . '</td>
                    <td>' . htmlspecialchars($row['estate_number'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($row['house_number'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($row['apartment'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($row['meter_manufacturer'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($row['capacity'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($row['meter_number'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($row['last_reading'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($row['current_reading'] ?? ' ') . '</td>
                    <td>' . htmlspecialchars($row['subscriber_name'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($row['cf_balance'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($row['last_payment_date'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($row[''] ?? ' ') . '</td>
                    <td>' . htmlspecialchars($row[''] ?? ' ') . '</td>
                  </tr>';
    }

    $html .= '</tbody></table>';


    $dompdf = new Dompdf\Dompdf();
    $dompdf->loadHtml($html);

    // Устанавливаем размер и ориентацию страницы
    $dompdf->setPaper('A4', 'landscape');

    // Генерация и вывод PDF
    $dompdf->render();
    $dompdf->stream("Маршрутный_лист.pdf");
}


function getMunicipalEnterprises() {
    global $adb;
    return $adb->run_query_allrecords("
        SELECT accountid, accountname
        FROM vtiger_account
        INNER JOIN vtiger_crmentity c ON c.crmid = accountid
        WHERE c.deleted = 0
        ORDER BY accountname
    ");
}

function getNew($startDate = null, $endDate = null, $sortColumn, $sortOrder, $limit, $offset) {
    global $adb;

    $query = "SELECT ve.estate_number, ve.cf_lastname, ve.cf_streets, ve.cf_house_number, 
                     ve.cf_litera
              FROM vtiger_estates ve 
              INNER JOIN vtiger_crmentity vc2 ON vc2.crmid = ve.estatesid 
              WHERE vc2.deleted = 0";

    $params = [];

    // Если указаны обе даты — фильтруем по диапазону
    if (!empty($startDate) && !empty($endDate)) {
        $query .= " AND vc2.createdtime BETWEEN ? AND ?";
        $params[] = $startDate . " 00:00:00";
        $params[] = $endDate . " 23:59:59";
    } 
    // Если указана только начальная дата — ищем от нее до текущей даты
    elseif (!empty($startDate)) {
        $query .= " AND vc2.createdtime >= ?";
        $params[] = $startDate . " 00:00:00";
    } 
    // Если указана только конечная дата — ищем записи до нее
    elseif (!empty($endDate)) {
        $query .= " AND vc2.createdtime <= ?";
        $params[] = $endDate . " 23:59:59";
    }

    // Добавляем сортировку
    if (!empty($sortColumn) && !empty($sortOrder)) {
        $query .= " ORDER BY $sortColumn $sortOrder";
    } else {
        $query .= " ORDER BY vc2.createdtime DESC"; // По умолчанию сортируем по дате создания (от новых к старым)
    }

    // Лимит и оффсет
    if (!isset($limit) || $limit <= 0) {
        $limit = 100;
    }
    if (!isset($offset) || $offset < 0) {
        $offset = 0;
    }
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    // Выполняем запрос
    $result = $adb->pquery($query, $params, true);

    $payments = [];
    while ($row = $result->FetchRow()) {
        $payments[] = $row;
    }
    
    return $payments;
}
