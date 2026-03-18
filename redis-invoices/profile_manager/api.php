<?php
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['action'])) {
    echo json_encode(['error' => 'action is not set']);
    exit();
}

$profilesDir = __DIR__ . '/../profiles';

// DB actions require vtiger connection
$dbActions = ['getGroups', 'getServices', 'testGeneration', 'getAccounts'];
if (in_array($input['action'], $dbActions)) {
    chdir(__DIR__ . '/../../');
    require_once 'include/database/PearDatabase.php';
    require_once 'include/utils/utils.php';
    require_once 'modules/Users/Users.php';
    require_once 'includes/runtime/BaseModel.php';
    require_once 'includes/runtime/Globals.php';
    include_once 'includes/runtime/Controller.php';
    include_once 'includes/http/Request.php';
    global $adb, $current_user;
    $current_user = Users::getActiveAdminUser();
}

header('Content-Type: application/json; charset=utf-8');

// ==================== PROFILES CRUD ====================

if ($input['action'] == 'getProfiles') {
    $profiles = [];
    $files = glob($profilesDir . '/*.json');
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data) {
            $data['_filename'] = basename($file);
            $profiles[] = $data;
        }
    }
    echo json_encode($profiles, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($input['action'] == 'getProfile') {
    $filename = basename($input['filename']);
    $filepath = $profilesDir . '/' . $filename;
    if (!file_exists($filepath)) {
        echo json_encode(['error' => 'Profile not found']);
        exit();
    }
    $data = json_decode(file_get_contents($filepath), true);
    $data['_filename'] = $filename;
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($input['action'] == 'saveProfile') {
    $profile = $input['profile'];
    $filename = $input['filename'] ?? null;

    if (!$filename) {
        // Generate filename from name
        $slug = preg_replace('/[^a-zA-Z0-9а-яА-ЯёЁ]+/u', '_', $profile['name']);
        $slug = mb_strtolower($slug, 'UTF-8');
        $slug = trim($slug, '_');
        $filename = $slug . '.json';
    }

    $filename = basename($filename);
    $filepath = $profilesDir . '/' . $filename;

    // Remove internal fields
    unset($profile['_filename']);

    $json = json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($filepath, $json);

    echo json_encode(['success' => true, 'filename' => $filename], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($input['action'] == 'deleteProfile') {
    $filename = basename($input['filename']);
    $filepath = $profilesDir . '/' . $filename;
    if (file_exists($filepath)) {
        unlink($filepath);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'File not found']);
    }
    exit();
}

// ==================== DB LOOKUPS ====================

if ($input['action'] == 'getGroups') {
    $result = $adb->pquery("SELECT groupid, groupname FROM vtiger_groups ORDER BY groupname", []);
    $groups = [];
    for ($i = 0; $i < $adb->num_rows($result); $i++) {
        $groups[] = [
            'groupid' => $adb->query_result($result, $i, 'groupid'),
            'groupname' => $adb->query_result($result, $i, 'groupname'),
        ];
    }
    echo json_encode($groups, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($input['action'] == 'getAccounts') {
    $result = $adb->pquery("SELECT va.accountid, va.accountname FROM vtiger_account va INNER JOIN vtiger_crmentity vc ON va.accountid = vc.crmid WHERE vc.deleted = 0 ORDER BY va.accountname", []);
    $accounts = [];
    for ($i = 0; $i < $adb->num_rows($result); $i++) {
        $accounts[] = [
            'accountid' => $adb->query_result($result, $i, 'accountid'),
            'accountname' => $adb->query_result($result, $i, 'accountname'),
        ];
    }
    echo json_encode($accounts, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($input['action'] == 'getServices') {
    $result = $adb->pquery("SELECT s.serviceid, s.servicename, s.unit_price FROM vtiger_service s INNER JOIN vtiger_crmentity vc ON s.serviceid = vc.crmid WHERE vc.deleted = 0 ORDER BY s.servicename", []);
    $services = [];
    for ($i = 0; $i < $adb->num_rows($result); $i++) {
        $services[] = [
            'serviceid' => $adb->query_result($result, $i, 'serviceid'),
            'servicename' => $adb->query_result($result, $i, 'servicename'),
            'unit_price' => $adb->query_result($result, $i, 'unit_price'),
        ];
    }
    echo json_encode($services, JSON_UNESCAPED_UNICODE);
    exit();
}

// ==================== TEST ENGINE (DRY-RUN) ====================

if ($input['action'] == 'testGeneration') {
    $profileData = $input['profile'];
    $estatesid = intval($input['estatesid']);
    $monthName = $input['month'];
    $year = intval($input['year']);

    $result = [];
    $result['estate'] = null;
    $result['services'] = [];
    $result['totals'] = ['margin' => 0, 'tax_amount' => 0, 'total' => 0];
    $result['errors'] = [];

    // 1. Get estate data (search by estatesid OR estate_number)
    $sql = "SELECT es.*, vc.deleted FROM vtiger_estates es INNER JOIN vtiger_crmentity vc ON es.estatesid = vc.crmid WHERE vc.deleted = 0 AND (es.estatesid = ? OR es.estate_number = ?)";
    $res = $adb->pquery($sql, [$estatesid, $input['estatesid']]);

    if ($adb->num_rows($res) == 0) {
        echo json_encode(['error' => "Объект #" . $input['estatesid'] . " не найден (ни по ID, ни по лицевому счёту)"], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $row = $adb->fetchByAssoc($res);
    $estatesid = intval($row['estatesid']); // use real CRM ID for further queries
    $result['estate'] = [
        'estatesid' => $row['estatesid'],
        'estate_number' => $row['estate_number'],
        'cf_object_type' => $row['cf_object_type'],
        'cf_number_of_residents' => $row['cf_number_of_residents'],
        'cf_area' => $row['cf_area'],
        'cf_deactivated' => $row['cf_deactivated'],
    ];

    if ($row['cf_deactivated']) {
        $result['errors'][] = 'Объект деактивирован';
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 2. Get services linked to estate
    $svcRes = $adb->pquery("SELECT DISTINCT s.serviceid, s.servicename, s.unit_price
        FROM vtiger_crmentityrel rel
        INNER JOIN vtiger_service s ON s.serviceid = rel.relcrmid
        INNER JOIN vtiger_crmentity crm ON crm.crmid = s.serviceid
        WHERE rel.relmodule = 'Services' AND crm.deleted = 0 AND rel.crmid = ?", [$estatesid]);

    $linkedServices = [];
    for ($i = 0; $i < $adb->num_rows($svcRes); $i++) {
        $sid = $adb->query_result($svcRes, $i, 'serviceid');
        $linkedServices[$sid] = [
            'serviceid' => $sid,
            'servicename' => $adb->query_result($svcRes, $i, 'servicename'),
            'unit_price' => floatval($adb->query_result($svcRes, $i, 'unit_price')),
        ];
    }

    if (empty($linkedServices)) {
        $result['errors'][] = 'У объекта нет привязанных услуг';
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 3. Process each service from profile
    $profileServices = $profileData['services'] ?? [];
    $readingPeriod = $profileData['reading_period'] ?? 'last_two';

    $totalMargin = 0;
    $totalTaxAmount = 0;

    foreach ($profileServices as $serviceId => $rule) {
        $serviceId = intval($serviceId);
        $calcType = $rule['calc'];

        if (!isset($linkedServices[$serviceId])) {
            $result['services'][] = [
                'serviceid' => $serviceId,
                'label' => $rule['label'] ?? "Услуга #$serviceId",
                'calc' => $calcType,
                'status' => 'skipped',
                'message' => 'Услуга не привязана к объекту',
            ];
            continue;
        }

        $svc = $linkedServices[$serviceId];
        $listprice = $svc['unit_price'];

        // Get taxes
        $taxes = getServiceTaxes($serviceId, $adb);
        $tax1 = $taxes[0];
        $tax2 = $taxes[1];
        $tax3 = $taxes[2];
        $totalTaxPercent = $tax1 + $tax2 + $tax3;

        $serviceResult = [
            'serviceid' => $serviceId,
            'servicename' => $svc['servicename'],
            'label' => $rule['label'] ?? $svc['servicename'],
            'calc' => $calcType,
            'listprice' => $listprice,
            'tax1' => $tax1,
            'tax2' => $tax2,
            'tax3' => $tax3,
            'status' => 'ok',
            'lines' => [],
        ];

        if ($calcType == 'fixed') {
            $qty = 1;
            $margin = $listprice;
            $taxAmount = $margin * $totalTaxPercent / 100;
            $serviceResult['lines'][] = [
                'description' => 'Фиксированная',
                'qty' => $qty,
                'price' => $listprice,
                'margin' => $margin,
                'tax_amount' => round($taxAmount, 2),
                'total' => round($margin + $taxAmount, 2),
            ];
            $totalMargin += $margin;
            $totalTaxAmount += $taxAmount;

        } elseif ($calcType == 'by_residents') {
            $qty = intval($row['cf_number_of_residents']);
            if ($qty <= 0) {
                $serviceResult['status'] = 'warning';
                $serviceResult['message'] = 'Количество жильцов = 0';
                $qty = 0;
            }
            $margin = $listprice * $qty;
            $taxAmount = $margin * $totalTaxPercent / 100;
            $serviceResult['lines'][] = [
                'description' => "По жильцам ($qty чел.)",
                'qty' => $qty,
                'price' => $listprice,
                'margin' => $margin,
                'tax_amount' => round($taxAmount, 2),
                'total' => round($margin + $taxAmount, 2),
            ];
            $totalMargin += $margin;
            $totalTaxAmount += $taxAmount;

        } elseif ($calcType == 'by_area') {
            $qty = floatval($row['cf_area']);
            if ($qty <= 0) {
                $serviceResult['status'] = 'warning';
                $serviceResult['message'] = 'Площадь = 0';
            }
            $margin = $listprice * $qty;
            $taxAmount = $margin * $totalTaxPercent / 100;
            $serviceResult['lines'][] = [
                'description' => "По площади ($qty м²)",
                'qty' => $qty,
                'price' => $listprice,
                'margin' => $margin,
                'tax_amount' => round($taxAmount, 2),
                'total' => round($margin + $taxAmount, 2),
            ];
            $totalMargin += $margin;
            $totalTaxAmount += $taxAmount;

        } elseif ($calcType == 'by_meters') {
            $metersResult = calculateMeterReadings($estatesid, $serviceId, $listprice, $monthName, $year, $readingPeriod, $totalTaxPercent, $adb);
            $serviceResult['lines'] = $metersResult['lines'];
            if (!empty($metersResult['warnings'])) {
                $serviceResult['status'] = 'warning';
                $serviceResult['message'] = implode('; ', $metersResult['warnings']);
            }
            $totalMargin += $metersResult['totalMargin'];
            $totalTaxAmount += $metersResult['totalTax'];
        }

        $result['services'][] = $serviceResult;
    }

    $result['totals'] = [
        'margin' => round($totalMargin, 2),
        'tax_amount' => round($totalTaxAmount, 2),
        'total' => round($totalMargin + $totalTaxAmount, 2),
    ];

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit();
}

echo json_encode(['error' => 'Unknown action: ' . $input['action']]);
exit();

// ==================== HELPER FUNCTIONS ====================

function getServiceTaxes($serviceId, $adb) {
    $sql = "SELECT vptr.taxpercentage, COALESCE(vit.taxname, '') as taxname
            FROM vtiger_producttaxrel vptr
            LEFT JOIN vtiger_inventorytaxinfo vit ON vptr.taxid = vit.taxid
            WHERE vptr.productid = ?
            ORDER BY vptr.taxid";
    $result = $adb->pquery($sql, [$serviceId]);
    $taxes = [0, 0, 0];

    for ($i = 0; $i < $adb->num_rows($result); $i++) {
        $pct = floatval($adb->query_result($result, $i, 'taxpercentage'));
        $name = strtolower(trim($adb->query_result($result, $i, 'taxname')));

        if (stripos($name, 'ндс') !== false || stripos($name, 'vat') !== false) {
            $taxes[0] = $pct;
        } elseif (stripos($name, 'нсп') !== false || stripos($name, 'nsp') !== false) {
            $taxes[1] = $pct;
        } else {
            if ($adb->num_rows($result) == 1 && $taxes[0] == 0 && $taxes[1] == 0) {
                $taxes[1] = $pct;
            } else {
                $taxes[2] = $pct;
            }
        }
    }
    return $taxes;
}

function getMonthNumber($monthName) {
    $months = [
        'Январь' => 1, 'Февраль' => 2, 'Март' => 3, 'Апрель' => 4,
        'Май' => 5, 'Июнь' => 6, 'Июль' => 7, 'Август' => 8,
        'Сентябрь' => 9, 'Октябрь' => 10, 'Ноябрь' => 11, 'Декабрь' => 12
    ];
    return $months[$monthName] ?? null;
}

function calculateMeterReadings($estatesid, $serviceId, $listprice, $monthName, $year, $readingPeriod, $totalTaxPercent, $adb) {
    $lines = [];
    $warnings = [];
    $totalMargin = 0;
    $totalTax = 0;

    $meters = $adb->pquery("SELECT vm.metersid, vm.meter_number
        FROM vtiger_meters vm
        INNER JOIN vtiger_crmentity vc ON vm.metersid = vc.crmid
        WHERE vc.deleted = 0 AND vm.cf_deactivated = 0 AND vm.cf_meter_object_link = ?", [$estatesid]);

    if ($adb->num_rows($meters) == 0) {
        $warnings[] = 'Счётчики не найдены';
        return ['lines' => $lines, 'warnings' => $warnings, 'totalMargin' => 0, 'totalTax' => 0];
    }

    for ($k = 0; $k < $adb->num_rows($meters); $k++) {
        $metersid = $adb->query_result($meters, $k, 'metersid');
        $meterNumber = $adb->query_result($meters, $k, 'meter_number');

        if ($readingPeriod == 'next_month') {
            $readings = getMeterReadingsNextMonth($metersid, $monthName, $year, $adb);
        } elseif ($readingPeriod == 'last_two') {
            $readings = getMeterReadingsLastTwo($metersid, $adb);
        } else {
            $readings = getMeterReadingsLastTwo($metersid, $adb);
        }

        $currentMd = $readings['current'];
        $prevMd = $readings['previous'];
        $currentDate = $readings['current_date'] ?? '-';
        $prevDate = $readings['previous_date'] ?? '-';
        $qty = $currentMd - $prevMd;

        if ($qty <= 0) {
            $warnings[] = "Счётчик №$meterNumber: расход <= 0 (пред=$prevMd, тек=$currentMd)";
            $qty = 0;
        }

        $margin = $qty * $listprice;
        $taxAmount = $margin * $totalTaxPercent / 100;

        $lines[] = [
            'description' => "Счётчик №$meterNumber",
            'meter_number' => $meterNumber,
            'prev_reading' => $prevMd,
            'prev_date' => $prevDate,
            'cur_reading' => $currentMd,
            'cur_date' => $currentDate,
            'qty' => $qty,
            'price' => $listprice,
            'margin' => $margin,
            'tax_amount' => round($taxAmount, 2),
            'total' => round($margin + $taxAmount, 2),
        ];

        $totalMargin += $margin;
        $totalTax += $taxAmount;
    }

    return ['lines' => $lines, 'warnings' => $warnings, 'totalMargin' => $totalMargin, 'totalTax' => $totalTax];
}

function getMeterReadingsNextMonth($metersid, $monthName, $year, $adb) {
    $monthNumber = getMonthNumber($monthName);
    $nextMonth = $monthNumber + 1;
    $nextYear = $year;
    if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

    $startDate = sprintf('%04d-%02d-01', $nextYear, $nextMonth);
    $endDate = date('Y-m-t', strtotime("$nextYear-$nextMonth-01"));

    $curRes = $adb->pquery("SELECT vr.meter_reading, vr.cf_reading_date FROM vtiger_readings vr
        INNER JOIN vtiger_crmentity vc ON vc.crmid = vr.readingsid
        WHERE vc.deleted = 0 AND vr.cf_reading_meter_link = ?
        AND vr.cf_reading_date >= ? AND vr.cf_reading_date <= ?
        ORDER BY vr.cf_reading_date ASC LIMIT 1", [$metersid, $startDate, $endDate]);

    $current = 0; $currentDate = null;
    if ($adb->num_rows($curRes) > 0) {
        $current = floatval($adb->query_result($curRes, 0, 'meter_reading'));
        $currentDate = $adb->query_result($curRes, 0, 'cf_reading_date');
    }

    $prevStart = sprintf('%04d-%02d-01', $year, $monthNumber);
    $prevEnd = date('Y-m-t', strtotime("$year-$monthNumber-01"));

    $prevRes = $adb->pquery("SELECT vr.meter_reading, vr.cf_reading_date FROM vtiger_readings vr
        INNER JOIN vtiger_crmentity vc ON vc.crmid = vr.readingsid
        WHERE vc.deleted = 0 AND vr.cf_reading_meter_link = ?
        AND vr.cf_reading_date >= ? AND vr.cf_reading_date <= ?
        ORDER BY vr.cf_reading_date DESC LIMIT 1", [$metersid, $prevStart, $prevEnd]);

    $previous = 0; $prevDate = null;
    if ($adb->num_rows($prevRes) > 0) {
        $previous = floatval($adb->query_result($prevRes, 0, 'meter_reading'));
        $prevDate = $adb->query_result($prevRes, 0, 'cf_reading_date');
    } else {
        $fallback = $adb->pquery("SELECT vr.meter_reading, vr.cf_reading_date FROM vtiger_readings vr
            INNER JOIN vtiger_crmentity vc ON vc.crmid = vr.readingsid
            WHERE vc.deleted = 0 AND vr.cf_reading_meter_link = ? AND vr.cf_reading_date < ?
            ORDER BY vr.cf_reading_date DESC LIMIT 1", [$metersid, $startDate]);
        if ($adb->num_rows($fallback) > 0) {
            $previous = floatval($adb->query_result($fallback, 0, 'meter_reading'));
            $prevDate = $adb->query_result($fallback, 0, 'cf_reading_date');
        }
    }

    return ['current' => $current, 'previous' => $previous, 'current_date' => $currentDate, 'previous_date' => $prevDate];
}

function getMeterReadingsLastTwo($metersid, $adb) {
    $res = $adb->pquery("SELECT vr.meter_reading, vr.cf_reading_date FROM vtiger_readings vr
        INNER JOIN vtiger_crmentity vc ON vc.crmid = vr.readingsid
        WHERE vc.deleted = 0 AND vr.cf_reading_meter_link = ?
        ORDER BY vr.cf_reading_date DESC LIMIT 2", [$metersid]);

    $current = 0; $previous = 0; $currentDate = null; $prevDate = null;
    if ($adb->num_rows($res) >= 1) {
        $current = floatval($adb->query_result($res, 0, 'meter_reading'));
        $currentDate = $adb->query_result($res, 0, 'cf_reading_date');
    }
    if ($adb->num_rows($res) >= 2) {
        $previous = floatval($adb->query_result($res, 1, 'meter_reading'));
        $prevDate = $adb->query_result($res, 1, 'cf_reading_date');
    }

    return ['current' => $current, 'previous' => $previous, 'current_date' => $currentDate, 'previous_date' => $prevDate];
}
?>
