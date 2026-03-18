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

use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;

global $adb, $current_user;
$current_user = Users::getActiveAdminUser();

$path = "excelFiles/karajygach/meters_kara_jygach.csv"; // Укажите путь к вашему файлу



// Проверка существования файла
if (!file_exists($path)) {
  echo "Файл не найден: $path\n";
  exit;
}

// Создаем reader для построчного чтения
$reader = ReaderEntityFactory::createCSVReader();

// Проверка открытия файла
try {
  $reader->open($path);
} catch (Exception $e) {
  echo "Ошибка при открытии файла: " . $e->getMessage() . "\n";
  exit;
}

// Инициализируем логгер
$logger = new CustomLogger('excelFiles/karajygach/add_meters_karajygach.log');
$logger->log("Начало обработки файла: $path");

$headerMap = [];
$recordCounter = 0;

$maxRecords = 1480; // Максимальное количество записей для обработки

foreach ($reader->getSheetIterator() as $sheet) {
  foreach ($sheet->getRowIterator() as $row) {
    // Сохраняем заголовки из первой строки
    if (empty($headerMap)) {
      foreach ($row->getCells() as $index => $cell) {
        $headerMap[trim($cell->getValue())] = $index;
      }
      continue;
    }

    $cells = $row->getCells();

    // Проверяем, является ли строка пустой
    if (empty(array_filter($cells, fn($cell) => $cell->getValue() !== null))) {
      continue;
    }

    $col = fn($name) => isset($headerMap[$name], $cells[$headerMap[$name]])
      ? trim($cells[$headerMap[$name]]->getValue())
      : null;

    // Извлекаем данные из колонок
    $number_meter = $col('meter');
    $ls = $col('cf_1420');
    $date_ustanovka = $col('cf_1505');
    $proizvoditel_meter = $col('cf_1491');
    $nujno_registrirovat_meter = $col('cf_1493');

    $opisanie = $col('cf_1426');
    $start_readings = $col('cf_1456');
    $date_proverka_meter = $col('cf_1329');


    $recordCounter++;
    echo "\n$recordCounter Обработка ЛС: $ls\n";

    // Пропускаем если нет лицевого счета
    if (empty($ls)) {
      $logger->log("$recordCounter Пропущена строка - нет лицевого счета");
      continue;
    }


    add_estates($logger, $adb, $recordCounter, $ls, $number_meter, $date_ustanovka, $proizvoditel_meter, $nujno_registrirovat_meter,
    $opisanie, $start_readings, $date_proverka_meter);

    if ($recordCounter >= $maxRecords) {
      // $logger->log("Достигнуто максимальное количество записей: $maxRecords. Завершаем обработку.");
      break 2; // Выход из обоих циклов

    }
  }
}

$reader->close();
$logger->log("Обработка файла завершена. Всего обработано записей: $recordCounter");
echo "\nОбработка завершена!\n";


function add_estates($logger, $adb, $recordCounter, $ls, $number_meter, $date_ustanovka, $proizvoditel_meter, $nujno_registrirovat_meter,
    $opisanie, $start_readings, $date_proverka_meter)
{
  // При отправке на гит ПОСТАВИТЬ креды сервера
  // Боевой
  // $assigned_user_id = 89;
  // $cf_municipal_enterprise = 53936;

  // Тестовый
  $assigned_user_id = 89;
  $cf_municipal_enterprise = 51854;

  $check_result = $adb->pquery("SELECT ve.estatesid FROM vtiger_estates ve
                  INNER JOIN vtiger_crmentity vc on vc.crmid = ve.estatesid
                  WHERE vc.deleted = 0 and ve.estate_number = ?", [$ls]);
  $check_row = $adb->fetch_array($check_result);

  $estates_id = $check_row['estatesid'];



  if (empty($estates_id)) { 

    $logger->log("Объект с таким лс - $ls - нет в биллинге");
    echo ("Объект с таким лс - $ls - нет в биллинге \n");

  } else {


    $estate_record = Vtiger_Record_Model::getCleanInstance("Meters");
    $estate_record->set('meter_number', $number_meter);
    $estate_record->set('cf_reading_verification_date', $start_readings);
    $estate_record->set('cf_meter_verification_date', $date_ustanovka);
    $estate_record->set('cf_manufactur', $proizvoditel_meter);
    $estate_record->set('cf_meter_object_link', $estates_id);
    $estate_record->set('cf_description', $opisanie);
    $estate_record->set('cf_date_of_meter_verification', $date_proverka_meter);
    $estate_record->set('cf_meter_needs_registered', $nujno_registrirovat_meter);


    $estate_record->set('assigned_user_id', $assigned_user_id);
    // $estate_record->set('cf_municipal_enterprise', $cf_municipal_enterprise);

    $estate_record->set('mode', 'create');
    $estate_record->save();
    $estate_id = $estate_record->getId();

    if ($estate_id) {
      // meter через set() не сохраняется — обновляем напрямую через БД
      $adb->pquery("UPDATE vtiger_meters SET meter = ? WHERE metersid = ?", [(string)$number_meter, $estate_id]);

      $logger->log("Объект успешно добавлен. ID - $estate_id");
      echo ("Объект с лс $ls успешно добавлен \n");
    }
  }

}
