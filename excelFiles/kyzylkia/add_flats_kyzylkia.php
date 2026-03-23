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

$path = "excelFiles/kyzylkia/flats_kyzylkia.csv"; // Укажите путь к вашему файлу



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
$logger = new CustomLogger('excelFiles/kyzylkia/add_flats_kyzylkia');
$logger->log("Начало обработки файла: $path");

$headerMap = [];
$recordCounter = 0;

$maxRecords = 2; // Максимальное количество записей для обработки

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
    $ls = $col('cf_1420');
    $house_number = $col('flat');
    $litera = $col('flats_litera');
    $apartment_number = $col('cf_1446');
    $type_doma = $col('cf_1444');       // кв/дом
    $deactivate = $col('cf_1454');      
    $type_lico = $col('cf_cf_object_type');      
    $nas_punkt = $col('cf_1450');      
    $plot = $col('cf_1452');      
    $dop_adress = $col('cf_second_address');      
    $street = $col('cf_1448');      
    $lastname = $col('lastname');       // собственник
    $kol_people = $col('cf_1261');      
    $mobile = $col('mobile');       // Количество проживающих



    $recordCounter++;
    echo "\n$recordCounter Обработка ЛС: $ls\n";

    // Пропускаем если нет лицевого счета
    if (empty($ls)) {
      $logger->log("$recordCounter Пропущена строка - нет лицевого счета");
      continue;
    }


    add_estates($logger, $adb, $recordCounter, $ls, $house_number, $litera, $apartment_number, $type_doma, $deactivate, $type_lico, $nas_punkt, $plot, $dop_adress, $street, $lastname, $kol_people, $mobile);

    if ($recordCounter >= $maxRecords) {
      // $logger->log("Достигнуто максимальное количество записей: $maxRecords. Завершаем обработку.");
      break 2; // Выход из обоих циклов

    }
  }
}

$reader->close();
$logger->log("Обработка файла завершена. Всего обработано записей: $recordCounter");
echo "\nОбработка завершена!\n";


function add_estates($logger, $adb, $recordCounter, $ls, $house_number, $litera, $apartment_number, 
$type_doma, $deactivate, $type_lico, $nas_punkt, $plot, $dop_adress, $street, $lastname, $kol_people, $mobile)
{

  $cf_municipal_enterprise = 53939;


  $check_result = $adb->pquery("SELECT ve.estatesid FROM vtiger_estates ve
                  INNER JOIN vtiger_crmentity vc on vc.crmid = ve.estatesid
                  WHERE vc.deleted = 0 and ve.estate_number = ?", [$ls]);
  $check_row = $adb->fetch_array($check_result);
  $estates_id = $check_row['estatesid'];

  if (empty($estates_id)) {
    $estate_record = Vtiger_Record_Model::getCleanInstance("Estates");

    $estate_record->set('estate_number', $ls);
    $estate_record->set('cf_house_number', $house_number);
    $estate_record->set('cf_litera', $litera);
    $estate_record->set('cf_apartment_number', $apartment_number);
    $estate_record->set('cf_number_of_residents', $kol_people);
    $estate_record->set('cf_lastname', $lastname);
    $estate_record->set('cf_deactivated', $deactivate);
    $estate_record->set('cf_object_type', $type_lico);
    $estate_record->set('cf_second_address', $dop_adress);
    $estate_record->set('cf_mobile_phone', $mobile);

    $estate_record->set('cf_municipal_enterprise', $cf_municipal_enterprise);
    $estate_record->set('cf_plot', $plot);
    $estate_record->set('cf_inhabited_locality', $nas_punkt);
    $estate_record->set('cf_streets', $street);

    $estate_record->set('cf_legal_entity_name', $);
    $estate_record->set('cf_doc_number', $);

    $estate_record->set('mode', 'create');
    $estate_record->save();
    $estate_id = $estate_record->getId();

    if ($estate_id) {
      // estate_number через set() не сохраняется — обновляем напрямую через БД
      $adb->pquery("UPDATE vtiger_estates SET estate_number = ? WHERE estatesid = ?", [(string)$ls, $estate_id]);

      $logger->log("Объект успешно добавлен. ID - $estate_id");
      echo ("Объект с лс $ls успешно добавлен \n");
    }
  } else {
    $logger->log("Объект с таким лс - $ls - уже есть в биллинге");
    echo ("Объект с таким лс - $ls - уже есть в биллинге \n");
  }

}
