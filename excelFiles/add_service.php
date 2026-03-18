<?php
chdir('../');
require 'vendor/autoload.php';
include_once 'includes/Loader.php';
require_once 'include/utils/utils.php';
include_once 'include/utils/InventoryUtils.php';
include_once 'includes/http/Request.php';
include_once 'includes/runtime/Globals.php';
include_once 'includes/runtime/BaseModel.php';
include_once 'includes/runtime/Controller.php';
include_once 'includes/runtime/LanguageHandler.php';
require_once 'Logger.php';

global $adb;
global $current_user;
$current_user = Users::getActiveAdminUser();

// use PhpOffice\PhpSpreadsheet\IOFactory;

$logger = new CustomLogger('excelFiles/excelreader_services.log');


$res = $adb->pquery("SELECT ve.estate_number FROM vtiger_estates ve 
INNER JOIN vtiger_crmentity vc on vc.crmid = ve.estatesid 
WHERE vc.deleted = 0 and ve.cf_municipal_enterprise = 9", array());

$schetchik = 0;

for ($i = 0; $i < $adb->num_rows($res); $i++) {
// for ($i = 0; $i < 1; $i++) {
    $ls = $adb->query_result($res, $i, 'estate_number');

    $schetchik++;

    // echo "$schetchik - Обработка объекта с лс - $ls <br>";
    echo "$schetchik - Обработка объекта с лс - $ls \n";

    // exit('Ops!');

    // Поиск объекта по лицевому счёту (ЛС)
    $object_check = $adb->run_query_allrecords("SELECT * FROM vtiger_estates ve 
                                                INNER JOIN vtiger_crmentity vc on vc.crmid = ve.estatesid 
                                                WHERE vc.deleted = 0 AND ve.estate_number = '$ls'");

    if ($object_check) {
        $estate_id = $object_check[0]['estatesid'];

        $serviceId = 16900;

        // Вставляем новую запись в таблицу vtiger_crmentityrel
        $sql_insert = "INSERT INTO vtiger_crmentityrel (crmid, module, relcrmid, relmodule) VALUES (?, 'Estates', ?, 'Services')";
        $result = $adb->pquery($sql_insert, array($estate_id, $serviceId));

        // Проверка успешности вставки
        if ($result) {
            $logger->log("$i Добавлена услуга к $estate_id с лс - $ls");
            // var_dump("$i Добавлена услуга к $estate_id с лс - $ls");
        } else {
            $logger->log("Ошибка при добавлении services для объекта с id $estate_id");
            var_dump('Ошибка при добавлении services для объекта с id $estate_id');
        }
    } else {
        $logger->log("Объект с таким лс - $ls не найден!");
    }
}

exit();
?>