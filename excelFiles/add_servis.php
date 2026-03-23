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
$logger = new CustomLogger('excelFiles/add_servis');
$path = "excelFiles/cleaned.xlsx";

if (!file_exists($path)) {
   $logger->error("Файл не найден: $path");
   die("Ошибка: Файл не найден.");
}

// Чтение файла Excel
$reader = IOFactory::createReaderForFile($path);
$spreadsheet = $reader->load($path);
$worksheet = $spreadsheet->getSheet(0);
$lastRow = $worksheet->getHighestRow();

for ($i = 1; $i <= $lastRow; $i++) {
// for ($i = 1; $i <= 3; $i++) {
   // var_dump($i);
   // var_dump($lastRow);
   $ls = trim($worksheet->getCell('A' . $i)->getValue());
   $balans = trim($worksheet->getCell('G' . $i)->getValue());
   // $balans = str_replace([' ', ','], ['', '.'], $balans);
   // $balans = floatval($balans);

   $estateQuery = "SELECT ve.estatesid FROM vtiger_estates ve
        INNER JOIN vtiger_crmentity vc ON ve.estatesid = vc.crmid
        WHERE vc.deleted = 0 and ve.estate_number = ?
    ";
   $estateResult = $adb->pquery($estateQuery, [$ls]);


   if ($adb->num_rows($estateResult) > 0) {
      $invoiceQuery = "SELECT vi.invoiceid
                        FROM vtiger_estates ve 
                        INNER JOIN vtiger_crmentity vc on vc.crmid = ve.estatesid 
                        INNER JOIN vtiger_invoice vi on vi.cf_estate_id = ve.estatesid 
                        INNER JOIN vtiger_crmentity vc2 on vi.invoiceid = vc2.crmid 
                        WHERE vc.deleted = 0 and vc2.deleted = 0 and vi.subject in ('Первоначальное сальдо') and ve.estate_number = ?
       ";
      $invoiceResult = $adb->pquery($invoiceQuery, [$ls]);

      $estatesid = $adb->query_result($estateResult, 0, 'estatesid');

      $invoiceid = $adb->query_result($invoiceResult, 0, 'invoiceid');

      if ($invoiceid) {
         $sql = "UPDATE vtiger_crmentity vc
                  INNER JOIN vtiger_invoice vi ON vc.crmid = vi.invoiceid
                  SET vc.deleted = 1
                  WHERE vc.deleted = 0
                  AND vi.invoiceid = ?";
         $adb->pquery($sql, [$invoiceid]);
         $logger->log("У объекта - $ls - был счет, удаляем его и создаем новый");

      } else {
         $logger->log("У объекта - $ls - нет счета, создаем новый");
      }



      if ($estatesid) {
         $invoice = Vtiger_Record_Model::getCleanInstance("Invoice");
         $invoice->set('subject', 'Первоначальное сальдо');
         $invoice->set('cf_estate_id', $estatesid);
         $invoice->set('invoicestatus', 'Автоматически созданный');
         $invoice->set('invoicedate', date('Y-m-d'));
         $invoice->set('mode', 'create');

         if ($balans != 0) {
            $invoice->save();
            $invoice_id = $invoice->getId();
            if ($invoice_id) {
               add_service_to_invoice($invoice_id, $balans);
               update_flat_debt_by_flatid($estatesid);
            }
            // обновление ответственного на ТазаСуу
            $update_smownerid = $adb->pquery(
               "UPDATE vtiger_crmentity vc
               INNER JOIN vtiger_invoice vi ON vc.crmid = vi.invoiceid
               SET vc.smownerid = ?
               WHERE vc.deleted = 0 AND vi.invoiceid = ?",
               array(58, $invoice_id)
            );
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


function add_service_to_invoice($invoice_id, $margin)
{
   global $adb;
   $service_id = 33;
   $quantity = 1;

   $checkQuery = "SELECT 1 FROM vtiger_inventoryproductrel WHERE id = ? AND productid = ?";
   $checkResult = $adb->pquery($checkQuery, [$invoice_id, $service_id]);
   if ($adb->num_rows($checkResult) > 0) {
      // return;
   }

   $sql = "INSERT INTO vtiger_inventoryproductrel(id, productid, quantity, listprice, margin) VALUES(?,?,?,?,?)";
   $params = [$invoice_id, $service_id, $quantity, $margin, $margin];
   $adb->pquery($sql, $params);
   update_invoice_total_field($margin, $invoice_id);
}

function update_invoice_total_field($total, $invoice_id)
{
   global $adb;
   $sql = "UPDATE vtiger_invoice SET total=?, balance=?, subtotal=?, pre_tax_total=?, taxtype=? WHERE invoiceid=?";
   $adb->pquery($sql, [$total, $total, $total, $total, 'group_tax_inc', $invoice_id]);
}

function update_flat_debt_by_flatid($estatesid)
{
   global $adb;
   $adb->pquery("UPDATE vtiger_estates es
        SET es.cf_balance = 
            ((SELECT IFNULL((SELECT ROUND(SUM(total), 3) 
            FROM vtiger_invoice AS inv
            INNER JOIN vtiger_crmentity AS vc ON inv.invoiceid = vc.crmid
            WHERE vc.deleted = 0
            AND invoicestatus NOT IN ('Cancel')
            AND inv.cf_estate_id = es.estatesid), 0)) 
            -
            (SELECT IFNULL((SELECT ROUND(SUM(amount), 3) 
            FROM vtiger_payments AS sp
            INNER JOIN vtiger_crmentity AS vc ON sp.paymentsid = vc.crmid 
            WHERE vc.deleted = 0
            AND sp.cf_pay_type = 'Приход'
            AND sp.cf_paid_object = es.estatesid), 0)))
        WHERE es.estatesid = ?", [$estatesid]);
}