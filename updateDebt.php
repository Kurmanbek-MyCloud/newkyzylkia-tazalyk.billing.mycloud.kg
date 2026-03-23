<?php
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

$logger = new CustomLogger('updateDebt');


// Обновление задолженности по дому
$estate_sql_result = $adb->pquery("SELECT es.estatesid FROM vtiger_estates es
INNER JOIN vtiger_crmentity vc ON es.estatesid = vc.crmid 
WHERE vc.deleted = 0
ORDER BY es.estate_number DESC");

for ($i = 0; $i <= $adb->num_rows($estate_sql_result); $i++) {
  $estates_id = $adb->query_result($estate_sql_result, $i, 'estatesid');

  $estates = Vtiger_Record_Model::getInstanceById($estates_id, "Estates");
  $estates->set('mode', 'edit');
  $estates->save();

  $logger->log("#$i Объект успешно обновлен. ID Дома: $estates_id");
}
exit();
