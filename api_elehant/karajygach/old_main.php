<?php
// ini_set('display_errors','on'); version_compare(PHP_VERSION, '5.5.0') <= 0 ? error_reporting(E_WARNING & ~E_NOTICE & ~E_DEPRECATED) : error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);   // DEBUGGING
include_once 'includes/Loader.php';
include_once 'include/utils/utils.php';
include_once 'include/utils/InventoryUtils.php';
vimport('includes.http.Request');
vimport('includes.runtime.Globals');
vimport('includes.runtime.BaseModel');
vimport('includes.runtime.Controller');
vimport('includes.runtime.LanguageHandler');
global $adb;
global $current_user;
$current_user = Users::getActiveAdminUser();

$meters = $adb->pquery("SELECT RIGHT(m.meter, 5) AS meter  
FROM vtiger_meters m 
INNER JOIN vtiger_meterscf mcf ON mcf.metersid = m.metersid
INNER JOIN vtiger_crmentity crm ON crm.crmid = m.metersid
WHERE crm.deleted = 0
AND m.meter REGEXP '^[0-9]+$'", array());
$requestArray = [];
$n_start = 0;
for ($i = 0; $i < $adb->num_rows($meters); $i++) {
  // for ($i=0; $i < 1; $i++) { 
  $n = $i + 1;
  $meter = $adb->query_result($meters, $i, 'meter');
  array_push($requestArray, '2-1-' . $meter);
  if ($n % 100 == 0) {
    send_request(json_encode($requestArray), $n_start, $n);
    $requestArray = [];
    $n_start = $n;
  }
}
send_request(json_encode($requestArray), $n_start, $n);
function send_request($test_array, $n_start, $n_current) {
  global $adb;

  // Отправляем запрос к API
  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://cntdev.ru/api',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => '{"list":' . $test_array . '}',
    CURLOPT_HTTPHEADER => array(
      'Authorization: karajygachao@gmail.com:759c4479-7b7f-4595-9067-1ba1b8c5236e',
      'Content-Type: application/json'
    ),
  ));

  $response = curl_exec($curl);
  curl_close($curl);

  // Обрабатываем ответ от API
  $response = json_decode($response);
  if ($response->status == 'error') {
    logMetersDataConnector("Error: {$response->data}");
    return;
  }

  if ($response->status == 'success') {
    $meters = $adb->pquery("SELECT * FROM vtiger_meters m 
                          INNER JOIN vtiger_meterscf mcf ON mcf.metersid = m.metersid
                          INNER JOIN vtiger_crmentity crm ON crm.crmid = m.metersid
                          WHERE crm.deleted = 0
                          AND m.meter REGEXP '^[0-9]+$'", array());

    $responseArray = [];
    foreach ($response->data->meters as $meterData) {
      $meter_no = explode('.', $meterData->id)[2]; // Получаем номер счетчика
      $value = $meterData->value; // Показание
      $updated_date = date("Y-m-d", $meterData->updated); // Дата обновления
      $responseArray[$meter_no] = array('value' => $value, 'updated_date' => $updated_date);
    }
    // Перебираем показания из базы
    for ($k = $n_start; $k < $n_current; $k++) {
      $check_meter_no = $adb->query_result($meters, $k, 'meter');
      $meter_id = $adb->query_result($meters, $k, 'metersid');
      $house_id = $adb->query_result($meters, $k, 'cf_1319');

      // var_dump(['check_meter_no' => $check_meter_no, 'meter_id' => $meter_id, 'house_id' => $house_id]);
      if (array_key_exists($check_meter_no, $responseArray)) {
        $new_value = $responseArray[$check_meter_no]['value'];
        $new_date = $responseArray[$check_meter_no]['updated_date'];

        // Проверка существующего показания за тот же месяц
        $existing_data = $adb->pquery("SELECT md.metersdataid ,md.data FROM vtiger_metersdata md
                                      INNER JOIN vtiger_metersdatacf mdcf ON md.metersdataid = mdcf.metersdataid 
                                      INNER JOIN vtiger_crmentity vc ON md.metersdataid = vc.crmid 
                                      WHERE vc.deleted = 0
                                      AND mdcf.cf_1317 = ?
                                      AND MONTH(mdcf.cf_1325) = MONTH(?) 
                                      AND YEAR(mdcf.cf_1325) = YEAR(?)
                                      AND mdcf.cf_1499 = 0 # Не берем те которые были использованы для счета
                                      ORDER BY mdcf.cf_1325 DESC LIMIT 1", array($meter_id, $new_date, $new_date));
        $last_value = $adb->query_result($existing_data, 0, 'data');
        if ($adb->num_rows($existing_data) > 0) {
          // Показание за этот месяц уже существует, проверяем нужно ли обновить
          $metersdataid = $adb->query_result($existing_data, 0, 'metersdataid'); // Получаем ID существующей записи

          if ($new_value > $last_value) {
            // Обновляем показание в vtiger_metersdata
            $adb->pquery("UPDATE vtiger_metersdata SET data = ? WHERE metersdataid = ?", array($new_value, $metersdataid)); // Используем metersdataid для обновления

            // Обновляем cf_1325 в vtiger_metersdatacf
            $adb->pquery("UPDATE vtiger_metersdatacf SET cf_1325 = ? WHERE metersdataid = ?", array($new_date, $metersdataid)); // Используем metersdataid для обновления

            logMetersDataConnector("$k Показание обновлено для счетчика #$check_meter_no: $new_value - $new_date ");
          } else {
            logMetersDataConnector("$k Новое показание для счетчика #$check_meter_no: Дата: $new_date - $new_value не больше существующего: $last_value. Игнорируем.");
          }
        } else {
          // Если данных за этот месяц нет — проверяем, больше ли новое показание, чем последнее
          if ($new_value > $last_value) {
            // Добавляем новое показание
            $MetersData = Vtiger_Record_Model::getCleanInstance("MetersData");
            $MetersData->set('data', $new_value);
            $MetersData->set('cf_1317', $meter_id);
            $MetersData->set('cf_1325', $new_date);
            $MetersData->set('cf_1333', $house_id);
            $MetersData->set('cf_1327', 'сайт cntdev.ru');
            $MetersData->set('assigned_user_id', 1);
            $MetersData->set('mode', 'create');
            $MetersData->save();
            $MetersData_id = $MetersData->getId();
            if ($MetersData_id) {
              logMetersDataConnector("$k Показание добавлено для счетчика #$check_meter_no: $new_value");
            } else {
              logMetersDataConnector("$k Ошибка при добавлении показание для счетчика #$check_meter_no: $new_value");
            }
          } else {
            logMetersDataConnector("$k Новое показание для счетчика #$check_meter_no: $new_value не больше последнего: $last_value. Игнорируем.");
          }
        }
      } else {
        logMetersDataConnector("$k Новых данных для счетчика #$check_meter_no не получено, пропускаем.");
      }
      // exit();
    }
  }
}

function logMetersDataConnector($message) {
  $logFile = 'metersDataConnector.log';
  $text = date('Y-m-d H:i:s') . ": $message\n";
  $open = fopen($logFile, 'a');

  fwrite($open, $text);
  fclose($open);
}