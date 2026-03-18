<?php
chdir(__DIR__ . '/../../');
require 'vendor/autoload.php';
include_once 'includes/Loader.php';
require_once 'include/utils/utils.php';
require_once 'Logger.php';

include_once 'include/utils/InventoryUtils.php';
include_once 'includes/http/Request.php';
include_once 'includes/runtime/Globals.php';
include_once 'includes/runtime/BaseModel.php';
include_once 'includes/runtime/Controller.php';
include_once 'includes/runtime/LanguageHandler.php';

global $adb, $current_user;
$current_user = Users::getActiveAdminUser();

$logFile = __DIR__ . '/new_main.log';

log_it("=== Начало синхронизации ===");

// Загружаем все счётчики с их ID и привязкой к объекту (cf_meter_object_link)
$meters = $adb->pquery(
  "SELECT RIGHT(m.meter_number, 5) AS meter, m.metersid, m.cf_meter_object_link
   FROM vtiger_meters m
   INNER JOIN vtiger_crmentity crm ON crm.crmid = m.metersid
   WHERE crm.deleted = 0
   AND m.meter_number REGEXP '^[0-9]+$'",
  []
);

$total = $adb->num_rows($meters);
log_it("Найдено счётчиков в БД: $total");

$requestArray = [];
$meterMap     = []; // meter_5digit => ['meter_id' => X, 'house_id' => Y]
$n_start      = 0;

for ($i = 0; $i < $total; $i++) {
  $meter    = $adb->query_result($meters, $i, 'meter');
  $meterId  = $adb->query_result($meters, $i, 'metersid');
  $houseId  = $adb->query_result($meters, $i, 'cf_meter_object_link');

  $requestArray[]   = '2-1-' . $meter;
  $meterMap[$meter] = ['meter_id' => $meterId, 'house_id' => $houseId];

  $n = $i + 1;
  if ($n % 100 == 0) {
    fetch_and_save($requestArray, $meterMap, $n_start, $n);
    $requestArray = [];
    $meterMap     = [];
    $n_start      = $n;
  }
}

// Отправляем остаток
if (!empty($requestArray)) {
  fetch_and_save($requestArray, $meterMap, $n_start, $total);
}

log_it("=== Синхронизация завершена ===");

// -------------------------------------------------------

function fetch_and_save($requestArray, $meterMap, $n_start, $n_current) {
  global $adb;

  $payload = json_encode(['list' => $requestArray]);
  log_it("Отправляем батч [$n_start - $n_current], счётчиков: " . count($requestArray));

  $curl = curl_init();
  curl_setopt_array($curl, [
    CURLOPT_URL            => 'https://cntdev.ru/api',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
      'Authorization: karajygachao@gmail.com:759c4479-7b7f-4595-9067-1ba1b8c5236e',
      'Content-Type: application/json',
    ],
  ]);

  $response  = curl_exec($curl);
  $curlError = curl_error($curl);
  $httpCode  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl);

  if ($curlError) {
    log_it("CURL ошибка: $curlError");
    return;
  }

  log_it("HTTP код: $httpCode");

  $decoded = json_decode($response);
  if (json_last_error() !== JSON_ERROR_NONE) {
    log_it("Ошибка парсинга JSON: " . json_last_error_msg());
    return;
  }

  if ($decoded->status === 'error') {
    log_it("Ошибка от API: {$decoded->data}");
    return;
  }

  if ($decoded->status !== 'success') {
    return;
  }

  $apiMeters = $decoded->data->meters ?? [];
  log_it("Получено показаний от API: " . count($apiMeters));

  foreach ($apiMeters as $meterData) {
    // Номер счётчика — последняя часть id "2.1.NNNNN"
    $parts    = explode('.', $meterData->id);
    $meter_no = $parts[2] ?? null;
    if ($meter_no === null) continue;

    $new_value = round((float)$meterData->value, 3);
    $new_date  = date("Y-m-d", $meterData->updated);

    // Ищем счётчик в нашей карте батча
    if (!isset($meterMap[$meter_no])) {
      log_it("  Счётчик $meter_no — не найден в карте батча, пропускаем");
      continue;
    }

    $meter_id = $meterMap[$meter_no]['meter_id'];
    $house_id = $meterMap[$meter_no]['house_id'];

    // Ищем существующее показание за тот же месяц и год (не использованное в счёте)
    $existing = $adb->pquery(
      "SELECT r.readingsid, r.meter_reading
       FROM vtiger_readings r
       INNER JOIN vtiger_crmentity vc ON vc.crmid = r.readingsid
       WHERE vc.deleted = 0
         AND r.cf_reading_meter_link = ?
         AND MONTH(r.cf_reading_date) = MONTH(?)
         AND YEAR(r.cf_reading_date) = YEAR(?)
         AND r.cf_used_in_bill = 0
       ORDER BY r.cf_reading_date DESC
       LIMIT 1",
      [$meter_id, $new_date, $new_date]
    );

    if ($adb->num_rows($existing) > 0) {
      // Показание за этот месяц уже есть
      $existing_id    = $adb->query_result($existing, 0, 'readingsid');
      $existing_value = (float)$adb->query_result($existing, 0, 'meter_reading');

      if ($new_value == $existing_value) {
        log_it("  Счётчик $meter_no | $new_date | значение $new_value — уже актуально, пропускаем");
      } elseif ($new_value > $existing_value) {
        // Новое значение больше — обновляем
        $adb->pquery(
          "UPDATE vtiger_readings SET meter_reading = ?, cf_reading_date = ? WHERE readingsid = ?",
          [$new_value, $new_date, $existing_id]
        );
        log_it("  Счётчик $meter_no | $new_date | обновлено: $existing_value → $new_value");
      } else {
        // Существующее значение больше — не трогаем
        log_it("  Счётчик $meter_no | $new_date | существующее $existing_value > нового $new_value, пропускаем");
      }
    } else {
      // Показания за этот месяц нет — добавляем новое
      $reading = Vtiger_Record_Model::getCleanInstance("Readings");
      $reading->set('meter_reading',          (string)$new_value);
      $reading->set('cf_reading_meter_link',  $meter_id);
      $reading->set('cf_reading_date',        $new_date);
      $reading->set('cf_reading_object_link', $house_id);
      $reading->set('cf_reading_source',      'сайт cntdev.ru');
      $reading->set('assigned_user_id',       1);
      $reading->set('mode', 'create');
      $reading->save();
      $new_id = $reading->getId();

      if ($new_id) {
        log_it("  Счётчик $meter_no | $new_date | добавлено показание $new_value (ID: $new_id)");
      } else {
        log_it("  Счётчик $meter_no | $new_date | ОШИБКА при добавлении показания $new_value");
      }
    }
  }
}

function log_it($message) {
  global $logFile;
  $line = date('Y-m-d H:i:s') . ": $message\n";
  file_put_contents($logFile, $line, FILE_APPEND);
  echo $line;
}
