<?php
if ($_SERVER['REQUEST_METHOD'] != "POST") {
  exit();
}
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['action'])) {
  echo json_encode(array('error' => 'action is not set'));
  exit();
}

chdir('../../../../../../');
require_once 'include/utils/utils.php';
require_once 'Logger.php';
include_once 'includes/runtime/BaseModel.php';
include_once 'includes/runtime/Globals.php';
include_once 'includes/runtime/Controller.php';
include_once 'includes/http/Request.php';

global $current_user;
global $adb;

define('URL', $site_URL . '/webservice.php');
define('KEY', 'Ea5AGXKCAcu7GHxg');
// $ticket = Vtiger_Record_Model::getInstanceById(1186,"HelpDesk");
// $assigned_user_id = 30;

$current_user = Users::getActiveAdminUser();
$logger = new CustomLogger('bot/php_requests/bot_ticket_handler.log');

// if ($input['action'] == 'getMetersInfo') {
//   $estatesid = $input['estatesid'];

//   $results = $adb->run_query_allrecords("SELECT vm.*, vr.*
//                   FROM vtiger_meters vm 
//                   INNER JOIN vtiger_crmentity vc1 ON vm.metersid = vc1.crmid 
//                   LEFT JOIN vtiger_readings vr ON vm.metersid = vr.cf_reading_meter_link
//                   LEFT JOIN vtiger_crmentity vc2 ON vr.readingsid = vc2.crmid 
//                   WHERE vc1.deleted = 0 
//                   AND (vc2.deleted = 0 OR vc2.deleted IS NULL)
//                   AND vm.cf_meter_object_link = $estatesid");
//   $meters = [];
//   foreach ($results as $row) {
//     $meter_id = $row['metersid'];

//     if (!isset($meters[$meter_id])) {
//       $meters[$meter_id] = [
//         'metersid' => $row['metersid'],
//         'meter_number' => $row['meter_number'],
//         'cf_meter_object_link' => $row['cf_meter_object_link'],
//         'readings' => [],
//       ];
//     }

//     if (!empty($row['readingsid'])) {
//       $reading = [
//         'readingsid' => $row['readingsid'],
//         'meter_reading' => $row['meter_reading'],
//         'cf_reading_meter_link' => $row['cf_reading_meter_link'],
//         'cf_reading_date' => $row['cf_reading_date'],
//         'cf_reading_source' => $row['cf_reading_source'],
//         'cf_reading_object_link' => $row['cf_reading_object_link'],
//       ];

//       $meters[$meter_id]['readings'][] = $reading;
//     }
//   }

//   // Преобразуем в список
//   $meters = array_values($meters);
//   echo json_encode($meters);
// }
if ($input['action'] == 'getMetersInfo') {
  $estatesid = $input['estatesid'];

  // Получаем список счетчиков
  $meters_results = $adb->run_query_allrecords("SELECT vm.* 
        FROM vtiger_meters vm 
        INNER JOIN vtiger_crmentity vc1 ON vm.metersid = vc1.crmid 
        WHERE vc1.deleted = 0 
        AND vm.cf_deactivated  = 0
        AND vm.cf_meter_object_link = $estatesid");

  $meters = [];
  foreach ($meters_results as $row) {
    $metersid = $row['metersid'];

    // Добавляем счетчик в массив
    $meters[$metersid] = [
      'metersid' => $row['metersid'],
      'meter_number' => $row['meter_number'],
      'cf_meter_object_link' => $row['cf_meter_object_link'],
      'readings' => [],
    ];

    // Получаем показания для текущего счетчика
    $readings_results = $adb->run_query_allrecords("SELECT vr.readingsid, vr.meter_reading, vr.cf_reading_meter_link, vr.cf_reading_date, vr.cf_reading_source, vr.cf_reading_object_link,
	CASE 
        WHEN cf_used_in_bill = 1 THEN 'Да'
        WHEN cf_used_in_bill = 0 THEN 'Нет'
        ELSE 'Неизвестно'
    END AS cf_used_in_bill, vc.deleted 
FROM vtiger_readings vr 
INNER JOIN vtiger_crmentity vc ON vr.readingsid = vc.crmid 
WHERE vc.deleted = 0 
AND vr.cf_reading_meter_link = $metersid");

    // Добавляем показания к счетчику
    foreach ($readings_results as $reading_row) {
      $meters[$metersid]['readings'][] = [
        'readingsid' => $reading_row['readingsid'],
        'meter_reading' => $reading_row['meter_reading'],
        'cf_reading_date' => $reading_row['cf_reading_date'],
        'cf_reading_source' => $reading_row['cf_reading_source'],
        'cf_reading_object_link' => $reading_row['cf_reading_object_link'],
        'cf_used_in_bill' => $reading_row['cf_used_in_bill'],
      ];
    }
  }

  // Преобразуем массив в список
  $meters = array_values($meters);
  echo json_encode($meters);
}

if ($input['action'] == 'createReadings') {
  $userid = $input['userid'];
  $metersid = $input['metersid'];
  $inputValue = intval($input['inputValue']);
  $metersLink = $input['metersLink'];
  $selectedDate = $input['date'];
  // echo ($selectedDate);



  // $due_date = new DateTime();

  $readings = Vtiger_Record_Model::getCleanInstance("Readings");
  $readings->set('meter_reading', $inputValue);
  $readings->set('cf_reading_meter_link', $metersid);
  $readings->set('cf_reading_object_link', $metersLink);
  $readings->set('cf_reading_source', 'Ручной ввод');
  $readings->set('cf_reading_date', $selectedDate);
  // $readings->set('cf_reading_date', $due_date->format('Y-m-d'));
  $readings->set('assigned_user_id', $userid);
  $readings->set('mode', 'create');
  $readings->save();
  $readings_id = $readings->getId();
  // Проверяем, был ли создан новый объект
  if ($readings_id) {
    // Если ID был успешно создан, возвращаем статус "Ok"
    $response = [
      'status' => 'Ok',
      'readings_id' => $readings_id,
    ];
  } else {
    // Если ID не был создан, возвращаем статус "False"
    $response = [
      'status' => 'False',
      'message' => 'Не удалось создать показание',
    ];
  }

  echo json_encode($response);
  exit();
}
if ($input['action'] == 'updateReadings') {
  $userid = $input['userid'];
  $readingsid = $input['readingsid'];
  $readingDate = $input['readingDate'];
  $newReading = intval($input['newReading']);

  // Получаем запись "Readings" по ID и обновляем значения
  $readings = Vtiger_Record_Model::getInstanceById($readingsid, "Readings");
  $readings->set('meter_reading', $newReading);
  $readings->set('cf_reading_date', $readingDate);
  $readings->set('assigned_user_id', $userid); // Изменение пользователя
  $readings->set('mode', 'edit'); // Режим редактирования
  $readings->save(); // Сохраняем изменения
  $readings_id = $readings->getId();

  // Проверяем успешность сохранения
  if ($readings_id) {
    // Возвращаем статус "True", если запрос выполнен успешно
    $response = [
      'status' => 'True',
      'message' => 'Показание успешно обновлено!',
    ];
  } else {
    // Если запрос не был выполнен, возвращаем статус "False"
    $response = [
      'status' => 'False',
      'message' => 'Не удалось обновить показание!',
    ];
  }

  // Возвращаем JSON-ответ
  echo json_encode($response);
  exit();
}
if ($input['action'] == 'deleteReadings') {
  $readingsid = $input['readingsid'];

  try {
    // Получаем экземпляр записи
    $reading_record = Vtiger_Record_Model::getInstanceById($readingsid, "Readings");

    // Проверяем, существует ли запись
    if ($reading_record) {
      // Удаляем запись
      $reading_record->delete();

      // Формируем успешный ответ
      $response = [
        'status' => 'True',
        'message' => 'Показание успешно удалено!',
      ];
    } else {
      // Если запись не найдена
      $response = [
        'status' => 'False',
        'message' => 'Показание с указанным ID не найдено!',
      ];
    }
  } catch (Exception $e) {
    // Обрабатываем ошибки, если возникли
    $response = [
      'status' => 'False',
      'message' => 'Произошла ошибка при удалении: ' . $e->getMessage(),
    ];
  }

  // Возвращаем JSON-ответ
  echo json_encode($response);
  exit();
}
?>