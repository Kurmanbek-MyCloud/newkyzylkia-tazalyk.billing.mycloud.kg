<?php
chdir('../');
require 'vendor/autoload.php';
include_once 'includes/Loader.php';
require_once 'include/utils/utils.php';
include_once 'includes/runtime/Globals.php';
require_once 'Logger.php';

global $adb;
global $current_user;
$current_user = Users::getActiveAdminUser();

use PhpOffice\PhpSpreadsheet\IOFactory;

// Инициализация логгера
$logger = new CustomLogger('excelFiles/add_indication.log');
$path = "excelFiles/показания.xlsx";

if (!file_exists($path)) {
   $logger->error("Файл не найден: $path");
   die("Ошибка: Файл не найден.");
}

// Чтение файла Excel
$reader = IOFactory::createReaderForFile($path);
$spreadsheet = $reader->load($path);
$worksheet = $spreadsheet->getSheet(0);
$lastRow = $worksheet->getHighestRow();

for ($i = 38; $i <= 517; $i++) {
   // for ($i = 1; $i <= 3; $i++) {
   // var_dump($i);
   // var_dump($lastRow);


   $ls = trim($worksheet->getCell('A' . $i)->getValue());
   $readings_data = trim($worksheet->getCell('I' . $i)->getValue());


   $estateQuery = "SELECT ve.estatesid FROM vtiger_estates ve
        INNER JOIN vtiger_crmentity vc ON ve.estatesid = vc.crmid
        WHERE vc.deleted = 0 and ve.estate_number = ?
    ";
   $estateResult = $adb->pquery($estateQuery, [$ls]);


   if ($adb->num_rows($estateResult) > 0) {
      $estatesid = $adb->query_result($estateResult, 0, 'estatesid');

      if ($estatesid) {
         $MetersIDQuery = "SELECT vm.metersid FROM vtiger_meters vm 
                           INNER JOIN vtiger_crmentity vc on vc.crmid = vm.metersid 
                           INNER JOIN vtiger_estates ve on ve.estatesid = vm.cf_meter_object_link
                           INNER JOIN vtiger_crmentity vc2 on vc2.crmid = ve.estatesid 
                           WHERE vc.deleted = 0 and vc2.deleted = 0 and ve.estate_number = ?";

         $MetersIDResult = $adb->pquery($MetersIDQuery, [$ls]);
         $metersID = $adb->query_result($MetersIDResult, 0, 'metersid');

         if ($metersID) {

            $readings = Vtiger_Record_Model::getCleanInstance("Readings");
            $readings->set('meter_reading', $readings_data);
            $readings->set('cf_reading_meter_link', $metersID);
            $readings->set('cf_reading_date', date('Y-m-d'));
            $readings->set('cf_reading_source', 'Ручной ввод');
            $readings->set('mode', 'create');
            $readings->save();
            $readings_id = $readings->getId();

            if ($readings_id) {
               $logger->log("Показание - $readings_data - для объекта - $ls - создано. ID - $readings_id");
            } else {$logger -> log("Ошибка при создании показания");}

         } else {
            $logger->log("У объекта - $ls - нет счетчика");
         }


      } else {
         var_dump('нету estatesid', $i);
         continue;
      }

   } else {
      var_dump('нету estateResult', $i);
      continue;
   }
   var_dump('сохранил', $i);
}
