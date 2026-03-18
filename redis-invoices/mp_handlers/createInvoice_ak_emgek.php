<?php
chdir('../');

ini_set('memory_limit', '512M');

// include_once 'includes/Loader.php';
require_once 'include/utils/utils.php';
require_once 'Logger.php';
require_once 'includes/runtime/BaseModel.php';
require_once 'includes/runtime/Globals.php';
include_once 'includes/runtime/Controller.php';
include_once 'includes/http/Request.php';


global $current_user;
global $adb;
$logger = new CustomLogger('createInvoice/createInvoice.log');
$current_user = Users::getActiveAdminUser();

$res = $adb->pquery("SELECT * FROM vtiger_estates es 
                INNER JOIN vtiger_crmentity vc ON es.estatesid = vc.crmid 
                WHERE vc.deleted = 0", array());

//               
// $res = $adb->pquery("SELECT * FROM vtiger_estates es
//     INNER JOIN vtiger_crmentity vc ON es.estatesid = vc.crmid
//     LEFT JOIN vtiger_contactdetails cd ON cd.contactid = es.cf_contact_id
//     LEFT JOIN vtiger_crmentity vc2 ON cd.contactid = vc2.crmid
//     WHERE vc.deleted = 0
//       AND vc2.deleted = 0
//       AND es.estate_number = 100529
//       AND vc.createdtime < ?", array(date('Y-m-d')));


$due_date = new DateTime();
$period = new DateTime();

$translate_month = array(
    'Jan' => 'Январь',
    'Feb' => 'Февраль',
    'Mar' => 'Март',
    'Apr' => 'Апрель',
    'May' => 'Май',
    'Jun' => 'Июнь',
    'Jul' => 'Июль',
    'Aug' => 'Август',
    'Sep' => 'Сентябрь',
    'Oct' => 'Октябрь',
    'Nov' => 'Ноябрь',
    'Dec' => 'Декабрь'
);

$prevPeriod = (clone $period)->modify('-1 month');
$theme = "За " . $translate_month[$prevPeriod->format("M")] . " " . $prevPeriod->format("Y") . " года";

// $theme = "За " . $translate_month[$period->format("M")] . " " . $period->format("Y") . " года";
$theme = "За Май 2025 года";
// $theme = "За test 2026 года";
$logger->log("Генерация счетов за $theme");
// var_dump($adb->num_rows($res));
// exit();
for($i = 0; $i < $adb->num_rows($res); $i++) {
// for ($i = 0; $i < $adb->num_rows($res); $i++) {
//for ($i = 0; $i < 1; $i++) {

    // for ($i = 0; $i < 1; $i++) {
    $contactid = $adb->query_result($res, $i, 'contactid');
    $estatesid = $adb->query_result($res, $i, 'estatesid');
    $beneficiaries = $adb->query_result($res, $i, 'cf_beneficiaries');
    $estate_number = $adb->query_result($res, $i, 'estate_number');
    $estate_type = $adb->query_result($res, $i, 'cf_estate_type');
    $object_type = $adb->query_result($res, $i, 'cf_object_type');
    $litera = $adb->query_result($res, $i, 'cf_litera');
    $apartment_number = $adb->query_result($res, $i, 'cf_apartment_number');
    $number_of_residents = $adb->query_result($res, $i, 'cf_number_of_residents');
    $deactivated = $adb->query_result($res, $i, 'cf_deactivated');

    if ($deactivated) { // Если деактивирован, не создается счет
        continue;
    }

   

    $services_data = $adb->pquery("SELECT DISTINCT s.serviceid, s.unit_price
                FROM vtiger_crmentityrel rel
                INNER JOIN vtiger_service s ON s.serviceid = rel.relcrmid 
                INNER JOIN vtiger_servicecf scf ON scf.serviceid = s.serviceid
                INNER JOIN vtiger_crmentity crm ON crm.crmid = s.serviceid
                WHERE rel.relmodule = 'Services'
                AND crm.deleted = 0
                AND rel.crmid = ?", array($estatesid));

    // var_dump($services_data);
    // exit();

    if ($adb->num_rows($services_data) > 0) {
        $invoice = Vtiger_Record_Model::getCleanInstance("Invoice");
        $invoice->set('cf_estate_id', $estatesid);
        $invoice->set('subject', $theme);
        $invoice->set('cf_invoice_estate_litera', $litera);
        $invoice->set('cf_invoice_apartment_number', $apartment_number);
        $invoice->set('$cf_invoice_object_type', $object_type);
        $invoice->set('assigned_user_id', 87);
        $invoice->set('invoicedate', $due_date->format('Y-m-d'));
        $invoice->set('invoicestatus', 'AutoCreated');
        $invoice->set('currency_id', 1);
        $invoice->set('mode', 'create');

        $invoice->save();

        $invoice_id = $invoice->getId();

        // var_dump($invoice_id);
        // exit();
        if ($invoice_id != null) {
            for ($j = 0; $j < $adb->num_rows($services_data); $j++) {
                 $quantity = 1;
                $margin = ($listprice * $quantity);
                $listprice = $adb->query_result($services_data, $j, 'unit_price');
                $service_id = $adb->query_result($services_data, $j, 'serviceid');
                add_service_to_invoice($invoice_id, $service_id, $listprice, 1, $quantity, $margin);

            }
            update_flat_debt_by_flatid($estatesid);

        }
        $invoice_id != null ? $logger->log("#$i Счет $theme успешно создан ID: $invoice_id ID Дома: $estatesid") : $logger->log("#$i! Ошибка `при создании Счета ! ID Дома: $estatesid ");
    } else {
        $logger->log("#$i! У данного дома нету услуги! ID Дома: $estatesid ");
    }

}
// exit();


function add_service_to_invoice($invoice_id, $service_id, $listprice, $accrual_base, $quantity, $margin)
{

    global $adb;
    $sql = "INSERT INTO vtiger_inventoryproductrel(id, productid, quantity, listprice, margin, accrual_base) VALUES(?,?,?,?,?,?)";

    $params = array($invoice_id, $service_id, $quantity, $listprice, $margin, $accrual_base);
    $adb->pquery($sql, $params);

    $total = get_total_sum_by_service($invoice_id);

    if ($total) {
        update_invoice_total_field($total, $invoice_id);
    }
}

function update_invoice_total_field($total, $invoiceid)
{
    global $adb;
    $sql = "UPDATE vtiger_invoice SET total=?, balance=?, subtotal=?, pre_tax_total=?, taxtype=? WHERE invoiceid=?";
    $adb->pquery($sql, array($total, $total, $total, $total, 'group_tax_inc', $invoiceid));
}

function get_total_sum_by_service($invoiceid)
{
    global $adb;
    $sql = "SELECT SUM(margin) AS total FROM vtiger_inventoryproductrel WHERE id=?";
    $result = $adb->pquery($sql, array($invoiceid));
    $total = $adb->query_result($result, 0, 'total');
    return $total;
}

function update_flat_debt_by_flatid($estatesid)
{
    global $adb;

    $adb->pquery("UPDATE vtiger_estates es
                SET es.cf_balance = 
                    ((SELECT IFNULL((SELECT ROUND(SUM(total), 3) AS summ 
                      FROM vtiger_invoice AS inv
                      INNER JOIN vtiger_crmentity AS vc ON inv.invoiceid = vc.crmid
                      WHERE vc.deleted = 0
                      AND invoicestatus NOT IN ('Cancel')
                      AND inv.cf_estate_id = es.estatesid), 0)) 
                        -
                      (SELECT IFNULL((SELECT ROUND(SUM(amount), 3) AS summ 
                      FROM vtiger_payments AS sp
                      INNER JOIN vtiger_crmentity AS vc ON sp.paymentsid = vc.crmid 
                      WHERE vc.deleted = 0
                      AND sp.cf_pay_type = 'Приход'
                      AND sp.cf_paid_object = es.estatesid), 0)))
                WHERE es.estatesid = ?", array($estatesid));
}
