<?php

chdir('../');

ini_set('memory_limit', '512M');

require_once 'include/utils/utils.php';
require_once 'Logger.php';
require_once 'includes/runtime/BaseModel.php';
require_once 'includes/runtime/Globals.php';
include_once 'includes/runtime/Controller.php';
include_once 'includes/http/Request.php';

global $current_user;
global $adb;

$logger = new CustomLogger('api_paymentshub/get_estates.log');
$current_user = Users::getActiveAdminUser();

// Выполняем SQL-запрос
$query = "SELECT ve.estate_number, ve.cf_lastname, ve.cf_balance 
          FROM vtiger_estates ve 
          INNER JOIN vtiger_crmentity vc ON vc.crmid = ve.estatesid 
          WHERE vc.deleted = 0";

$result = $adb->pquery($query, []);

// Проверяем, есть ли данные
$data = [];

if ($adb->num_rows($result) > 0) {
    while ($row = $adb->fetch_array($result)) {
        $data[] = [
            'estate_number' => $row['estate_number'],
            'lastname' => $row['cf_lastname'],
            'balance' => $row['cf_balance']
        ];
    }
}

// Отправляем JSON-ответ
header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;