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

$path = "excelFiles/karajygach/flats_service.csv"; // Укажите путь к вашему файлу



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
$logger = new CustomLogger('excelFiles/karajygach/add_service_karajygach.log');
$logger->log("Начало обработки файла: $path");

$headerMap = [];
$recordCounter = 0;

$maxRecords = 1526; // Максимальное количество записей для обработки

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
    $service_name = $col('servicename');
    $old_service_id = $col('serviceid');




    $recordCounter++;
    echo "\n$recordCounter Обработка ЛС: $ls\n";

    // Пропускаем если нет лицевого счета
    if (empty($ls)) {
      $logger->log("$recordCounter Пропущена строка - нет лицевого счета");
      continue;
    }


    add_service($logger, $adb, $recordCounter, $ls, $service_name, $old_service_id);

    if ($recordCounter >= $maxRecords) {
      // $logger->log("Достигнуто максимальное количество записей: $maxRecords. Завершаем обработку.");
      break 2; // Выход из обоих циклов

    }
  }
}

$reader->close();
$logger->log("Обработка файла завершена. Всего обработано записей: $recordCounter");
echo "\nОбработка завершена!\n";


function add_service($logger, $adb, $recordCounter, $ls, $service_name, $old_service_id)
{

  echo "Обработка объекта с лс - $ls \n";

  // exit('Ops!');

  // Поиск объекта по лицевому счёту (ЛС)
  $object_result = $adb->pquery("SELECT ve.estatesid FROM vtiger_estates ve
                                  INNER JOIN vtiger_crmentity vc on vc.crmid = ve.estatesid
                                  WHERE vc.deleted = 0 AND ve.estate_number = ?", [$ls]);
  $object_row = $adb->fetch_array($object_result);

  if ($object_row) {
    $estate_id = $object_row['estatesid'];

    if ($old_service_id == 8052) {       // Питьевая вода Юр Лица
      $serviceId = 53938;
    } elseif ($old_service_id == 33244) {  // Услуги питьевой воды (население)
      $serviceId = 53937;
    } elseif ($old_service_id == 0) {
      $serviceId = 0;
    } else {
      $logger->log("$recordCounter Неизвестный old_service_id=$old_service_id для лс $ls — пропускаем");
      echo "Неизвестный old_service_id=$old_service_id для лс $ls — пропускаем\n";
      return;
    }

    // Проверяем, нет ли уже такой связи
    $rel_check = $adb->pquery("SELECT 1 FROM vtiger_crmentityrel WHERE crmid = ? AND relcrmid = ? AND module = 'Estates' AND relmodule = 'Services'", [$estate_id, $serviceId]);
    if ($adb->fetch_array($rel_check)) {
      $logger->log("$recordCounter Связь уже существует для объекта $estate_id с лс $ls");
      echo "Связь уже существует для лс $ls\n";
      return;
    }

    // Вставляем новую запись в таблицу vtiger_crmentityrel
    $result = $adb->pquery("INSERT INTO vtiger_crmentityrel (crmid, module, relcrmid, relmodule) VALUES (?, 'Estates', ?, 'Services')", [$estate_id, $serviceId]);

    if ($result) {
      $logger->log("$recordCounter Добавлена услуга к $estate_id с лс - $ls");
    } else {
      $logger->log("Ошибка при добавлении services для объекта с id $estate_id");
      echo "Ошибка при добавлении services для объекта с id $estate_id\n";
    }
  } else {
    $logger->log("Объект с таким лс - $ls не найден!");
  }
}
