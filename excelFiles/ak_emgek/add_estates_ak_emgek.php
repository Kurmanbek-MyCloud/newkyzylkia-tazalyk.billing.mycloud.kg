<?php
chdir('../');
require 'vendor/autoload.php';
include_once 'includes/Loader.php';
require_once 'include/utils/utils.php';
include_once 'includes/runtime/Globals.php';
require_once 'Logger.php';


// var_dump('1');
// exit();
global $adb;
global $current_user;
$current_user = Users::getActiveAdminUser();

use PhpOffice\PhpSpreadsheet\IOFactory;

$logger = new CustomLogger('excelFiles/add_estates_service.log');
$path = "excelFiles/ak_emgek3.xlsx"; // ← укажи имя файла

if (!file_exists($path)) {
    $logger->error("Файл не найден: $path");
    die("Ошибка: Файл не найден.");
}

$serviceId = 51843; // ID услуги

$reader = IOFactory::createReaderForFile($path);
$spreadsheet = $reader->load($path);
$worksheet = $spreadsheet->getSheet(0);
$lastRow = $worksheet->getHighestRow();

for ($i = 2; $i <= $lastRow; $i++) {
    $ls         = trim($worksheet->getCell('A' . $i)->getValue());
    $fio        = trim($worksheet->getCell('B' . $i)->getValue());
    $street     = trim($worksheet->getCell('C' . $i)->getValue());
    $house      = trim($worksheet->getCell('D' . $i)->getValue());
    $nas_punkt  = trim($worksheet->getCell('E' . $i)->getValue());
    $has_service = trim($worksheet->getCell('F' . $i)->getValue()); // "да" или пусто

    if (empty($ls)) {
        $logger->log("$i Пустой ЛС, пропускаем.");
        continue;
    }

    // 1. Проверяем, есть ли объект
    $estateQuery = "SELECT ve.estatesid FROM vtiger_estates ve
        INNER JOIN vtiger_crmentity vc ON ve.estatesid = vc.crmid
        WHERE vc.deleted = 0 AND ve.estate_number = ?";
    $estateResult = $adb->pquery($estateQuery, [$ls]);

    if ($adb->num_rows($estateResult) > 0) {
        $row = $adb->fetch_array($estateResult);
        $estate_id = $row['estatesid'];
        $logger->log("$i ЛС $ls — объект найден (id=$estate_id).");
        echo "$i ЛС $ls — объект уже есть (id=$estate_id).\n";
    } else {
        // 2. Создаём объект
        echo "$i ЛС $ls — создаём объект.\n";

        $estates = Vtiger_Record_Model::getCleanInstance("Estates");
        $estates->set('estate_number', $ls);
        $estates->set('cf_lastname', $fio);
        $estates->set('cf_streets', $street);
        $estates->set('cf_municipal_enterprise', 51840);
        $estates->set('cf_plot', 'МП «Ак-Эмгек-Тазалык»');
        $estates->set('assigned_user_id', 89);
        $estates->set('cf_house_number', $house);
        $estates->set('cf_inhabited_locality', $nas_punkt);
        $estates->set('mode', 'create');
        $estates->save();
        $estate_id = $estates->getId();

        if (!$estate_id) {
            $logger->log("$i ЛС $ls — ошибка при создании объекта!");
            echo "$i ЛС $ls — ошибка при создании!\n";
            continue;
        }

        $logger->log("$i ЛС $ls — объект создан (id=$estate_id).");
    }

    // 3. Привязываем услугу (если в столбце F стоит "да", либо всегда — убери условие если нужно)
    if (strtolower($has_service) === 'да' || true) { // ← убери "|| true" если нужна проверка по колонке F

        // Проверка: не привязана ли услуга уже
        $checkRel = $adb->pquery(
            "SELECT 1 FROM vtiger_crmentityrel 
             WHERE crmid = ? AND relcrmid = ? AND module = 'Estates' AND relmodule = 'Services'",
            [$estate_id, $serviceId]
        );

        if ($adb->num_rows($checkRel) > 0) {
            $logger->log("$i ЛС $ls — услуга $serviceId уже привязана, пропускаем.");
            echo "$i ЛС $ls — услуга уже есть.\n";
        } else {
            $result = $adb->pquery(
                "INSERT INTO vtiger_crmentityrel (crmid, module, relcrmid, relmodule) 
                 VALUES (?, 'Estates', ?, 'Services')",
                [$estate_id, $serviceId]
            );

            if ($result) {
                $logger->log("$i ЛС $ls — услуга $serviceId привязана к объекту $estate_id.");
                echo "$i ЛС $ls — услуга привязана.\n";
            } else {
                $logger->log("$i ЛС $ls — ошибка при привязке услуги!");
                echo "$i ЛС $ls — ошибка при привязке услуги!\n";
            }
        }
    }
}

echo "Готово.\n";