<?php

/**
 * Обработчик для МП Тамчы ТазаСуу (groupid = 58)
 */

$rootPath = dirname(dirname(__DIR__)) . '/';
chdir($rootPath);

require_once 'include/utils/utils.php';
require_once 'Logger.php';
require_once 'includes/runtime/BaseModel.php';
require_once 'includes/runtime/Globals.php';
include_once 'includes/runtime/Controller.php';
include_once 'includes/http/Request.php';

require_once $rootPath . 'include/database/PearDatabase.php';
$adb = new PearDatabase();
$adb->connect();

require_once $rootPath . 'modules/Users/Users.php';
$current_user = Users::getActiveAdminUser();
$logger = new CustomLogger('redis-invoices/mp_handlers/tamchy_taza_suu');

/**
 * Проверка подключения к БД и переподключение если соединение умерло.
 * Для долгоживущих воркеров MySQL может закрыть соединение по wait_timeout.
 */
function ensureDbConnection($logger) {
    global $adb;
    $connected = false;

    if ($adb->database) {
        try {
            $result = @$adb->database->Execute("SELECT 1");
            $connected = ($result !== false);
        } catch (Exception $e) {
            $connected = false;
        }
    }

    if (!$connected) {
        $logger->log("⚠️ Соединение с БД потеряно, переподключаемся...");
        $adb->database = null;
        $adb->connect();
        $logger->log("✅ Соединение с БД восстановлено");
    }
}

/**
 * Обработка счетов для МП Тамчы ТазаСуу
 */
function processTamchyTazasuuInvoice($task, $logger) {
    global $adb, $current_user;

    // Проверяем подключение к БД перед обработкой задачи
    ensureDbConnection($logger);

    $logger->log("=== НАЧАЛО ОБРАБОТКИ МП Тамчы ТазаСуу ===");

    $estatesid = intval($task['objectID']);
    $months = $task['months'];
    $year = isset($task['year']) ? intval($task['year']) : intval(date('Y'));

    $logger->log("Объект: $estatesid, месяцы: " . implode(', ', $months) . ", год: $year");

    // Получаем данные объекта
    $res = $adb->pquery(
        "SELECT * FROM vtiger_estates es
         INNER JOIN vtiger_crmentity vc ON es.estatesid = vc.crmid
         WHERE vc.deleted = 0 AND es.estatesid = ?",
        array($estatesid)
    );

    if ($adb->num_rows($res) == 0) {
        $logger->log("❌ Object ID $estatesid не найден");
        return ['success' => false, 'error' => 'Объект не найден'];
    }

    $object_type       = $adb->query_result($res, 0, 'cf_object_type');
    $number_of_residents = $adb->query_result($res, 0, 'cf_number_of_residents');
    $deactivated       = $adb->query_result($res, 0, 'cf_deactivated');

    if ($deactivated) {
        $logger->log("❌ Object ID $estatesid деактивирован, пропускаем");
        return ['success' => false, 'error' => 'Объект деактивирован'];
    }

    foreach ($months as $monthName) {
        $theme = $monthName . " " . $year . " года";

        $services_data = $adb->pquery(
            "SELECT DISTINCT s.serviceid, s.servicename, s.unit_price, s.cf_method
             FROM vtiger_crmentityrel rel
             INNER JOIN vtiger_service s ON s.serviceid = rel.relcrmid
             INNER JOIN vtiger_servicecf scf ON scf.serviceid = s.serviceid
             INNER JOIN vtiger_crmentity crm ON crm.crmid = s.serviceid
             WHERE rel.relmodule = 'Services'
             AND crm.deleted = 0
             AND rel.crmid = ?",
            array($estatesid)
        );

        $servicesCount = $adb->num_rows($services_data);

        if ($servicesCount == 0) {
            $logger->log("❌ Услуги для объекта $estatesid не найдены");
            return ['success' => false, 'error' => 'Услуги не найдены'];
        }

        // Проверяем, не существует ли уже счет за этот месяц
        $existingInvoice = $adb->pquery(
            "SELECT vi.invoiceid
             FROM vtiger_invoice vi
             INNER JOIN vtiger_crmentity vc ON vi.invoiceid = vc.crmid
             WHERE vc.deleted = 0
             AND vi.subject = ?
             AND vi.cf_estate_id = ?",
            array($theme, $estatesid)
        );

        if ($adb->num_rows($existingInvoice) > 0) {
            $existingInvoiceId = $adb->query_result($existingInvoice, 0, 'invoiceid');
            $logger->log("❌ Счет '$theme' уже существует для Object ID $estatesid (ID: $existingInvoiceId). Пропускаем.");
            return ['success' => false, 'error' => "Счет за $monthName $year года уже существует (ID: $existingInvoiceId)"];
        }

        $invoiceId = createInvoice($estatesid, $theme, $monthName, $adb, $logger);

        if (!$invoiceId) {
            $logger->log("❌ ОШИБКА: Не удалось создать счет для объекта $estatesid");
            return ['success' => false, 'error' => 'Не удалось создать счет'];
        }

        $totalAccrued = 0;
        $processedServices = 0;

        for ($j = 0; $j < $servicesCount; $j++) {
            $service_id   = $adb->query_result($services_data, $j, 'serviceid');
            $service_name = $adb->query_result($services_data, $j, 'servicename');
            $listprice    = $adb->query_result($services_data, $j, 'unit_price');
            $cf_method    = $adb->query_result($services_data, $j, 'cf_method');

            $taxes = get_service_tax_percentages($service_id, $adb);
            $tax1 = $taxes[0];
            $tax2 = $taxes[1];
            $tax3 = $taxes[2];

            try {
                if ($cf_method == 'Счетчик') {
                    // Услуга со счетчиками
                    $accrued = add_meters_to_service($invoiceId, $service_id, $service_name, $estatesid, $listprice, $object_type, $monthName, $year, $tax1, $tax2, $tax3, $logger, $adb);
                    if ($accrued == 0) {
                        $logger->log("WARN: Услуга '$service_name' — начисление 0 за $monthName $year (нет показаний?)");
                    }
                    $totalAccrued += $accrued;
                    $processedServices++;
                } elseif ($cf_method == 'Кол-во ч.') {
                    // По количеству жильцов
                    $quantity = $number_of_residents;
                    $margin   = $listprice * $quantity;
                    add_service_to_invoice($invoiceId, $service_id, $quantity, 0, 0, $quantity, $listprice, $margin, $tax1, $tax2, $tax3, $adb);
                    $totalAccrued += $margin;
                    $processedServices++;
                } elseif ($cf_method == 'Фиксированный') {
                    // Фиксированная услуга
                    $margin = $listprice;
                    add_service_to_invoice($invoiceId, $service_id, 1, 0, 0, 1, $listprice, $margin, $tax1, $tax2, $tax3, $adb);
                    $totalAccrued += $margin;
                    $processedServices++;
                } else {
                    $logger->log("WARN: Услуга '$service_name' (ID: $service_id) — неизвестный метод: '$cf_method'");
                }
            } catch (Exception $e) {
                $logger->log("❌ ОШИБКА услуга '$service_name' (ID: $service_id): " . $e->getMessage());
            }
        }

        $results = get_total_sum_by_service($invoiceId, $adb);

        if ($results['total'] && $results['total'] > 0) {
            update_invoice_total_field($results['total'], $results['margin'], $invoiceId, $adb);
            $logger->log("✅ Счет ID $invoiceId: итого=" . round($results['total'], 2) . " сом (без НДС=" . round($results['margin'], 2) . ")");
            update_flat_debt_by_flatid($estatesid, $adb);
        } else {
            $logger->log("❌ ОШИБКА: Итоги нулевые для $monthName $year (объект $estatesid, услуг: $processedServices/$servicesCount)");
            return ['success' => false, 'error' => "Счет получился нулевым для $monthName $year года."];
        }
    }

    $logger->log("=== КОНЕЦ ОБРАБОТКИ МП Тамчы ТазаСуу ===");
    return ['success' => true, 'invoiceId' => $invoiceId ?? null];
}

/**
 * Создание счета
 */
function createInvoice($estatesid, $theme, $month, $adb, $logger) {
    try {
        global $adb, $current_user;

        $create_date = new DateTime();
        $invoice = Vtiger_Record_Model::getCleanInstance("Invoice");
        $invoice->set('subject', $theme);
        $invoice->set('cf_month', $month);
        $invoice->set('cf_estate_id', $estatesid);
        $invoice->set('invoicedate', $create_date->format('Y-m-d'));
        $invoice->set('invoicestatus', 'AutoCreated');
        $invoice->set('assigned_user_id', 1);
        $invoice->set('currency_id', 1);
        $invoice->set('mode', 'create');
        $invoice->save();
        $invoiceId = $invoice->getId();

        if ($invoiceId) {
            $logger->log("✅ Счет создан: ID=$invoiceId, тема='$theme'");
            $adb->pquery(
                "UPDATE vtiger_crmentity vc
                 INNER JOIN vtiger_invoice vi ON vc.crmid = vi.invoiceid
                 SET vc.smownerid = ?
                 WHERE vc.deleted = 0 AND vi.invoiceid = ?",
                array(58, $invoiceId)
            );
        } else {
            $logger->log("❌ ОШИБКА: Счет не создан (тема='$theme', объект=$estatesid)");
        }

        return $invoiceId;
    } catch (Exception $e) {
        $logger->log("❌ ОШИБКА при создании счета: " . $e->getMessage());
        return false;
    }
}

/**
 * Получение процентов налогов для услуги
 * Возвращает массив [tax1 (НДС), tax2 (НСП), tax3 (другие)]
 */
function get_service_tax_percentages($service_id, $adb) {
    $sql = "SELECT vptr.taxid, vptr.taxpercentage,
                   COALESCE(vit.taxname, '') as taxname
            FROM vtiger_producttaxrel vptr
            LEFT JOIN vtiger_inventorytaxinfo vit ON vptr.taxid = vit.taxid
            WHERE vptr.productid = ?
            ORDER BY vptr.taxid";
    $result = $adb->pquery($sql, array($service_id));
    $num_rows = $adb->num_rows($result);

    $taxes = [0, 0, 0];
    $undefined_taxes = [];

    if ($num_rows > 0) {
        for ($i = 0; $i < $num_rows; $i++) {
            $taxid = $adb->query_result($result, $i, 'taxid');
            $taxpercentage = floatval($adb->query_result($result, $i, 'taxpercentage'));
            $taxname = strtolower(trim($adb->query_result($result, $i, 'taxname')));

            if (stripos($taxname, 'ндс') !== false || stripos($taxname, 'vat') !== false) {
                $taxes[0] = $taxpercentage;
            } elseif (stripos($taxname, 'нсп') !== false || stripos($taxname, 'nsp') !== false) {
                $taxes[1] = $taxpercentage;
            } else {
                $undefined_taxes[] = ['taxid' => $taxid, 'taxpercentage' => $taxpercentage];
            }
        }

        if (count($undefined_taxes) > 0) {
            if ($num_rows == 1 && $taxes[0] == 0 && $taxes[1] == 0) {
                $taxes[1] = $undefined_taxes[0]['taxpercentage'];
            } else {
                foreach ($undefined_taxes as $undef_tax) {
                    if ($taxes[2] == 0) {
                        $taxes[2] = $undef_tax['taxpercentage'];
                        break;
                    }
                }
            }
        }
    }

    return $taxes;
}

/**
 * Добавление услуги в счет
 */
function add_service_to_invoice($invoice_id, $service_id, $meter_number, $prev_md, $current_md, $quantity, $listprice, $margin, $tax1, $tax2, $tax3, $adb, $prev_reading_id = null, $cur_reading_id = null) {
    try {
        $sql = "INSERT INTO vtiger_inventoryproductrel(id, productid, accrual_base, previous_reading, current_reading, quantity, listprice, margin, tax1, tax2, tax3, prev_reading_id, cur_reading_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $params = array($invoice_id, $service_id, $meter_number, $prev_md, $current_md, $quantity, $listprice, $margin, $tax1, $tax2, $tax3, $prev_reading_id, $cur_reading_id);
        $adb->pquery($sql, $params);

        // Отметить показания как использованные
        if ($prev_reading_id) {
            $adb->pquery("UPDATE vtiger_readings SET cf_used_in_bill = 1 WHERE readingsid = ?", array($prev_reading_id));
        }
        if ($cur_reading_id) {
            $adb->pquery("UPDATE vtiger_readings SET cf_used_in_bill = 1 WHERE readingsid = ?", array($cur_reading_id));
        }
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Обновление итогов счета
 */
function update_invoice_total_field($total, $margin, $invoiceid, $adb) {
    $sql = "UPDATE vtiger_invoice
            SET subtotal = ?,
                total = ?,
                taxtype = ?,
                pre_tax_total = ?,
                balance = ?
            WHERE invoiceid = ?";
    $adb->pquery($sql, array($total, $total, 'individual', $margin, $total, $invoiceid));
}

/**
 * Получение суммы по услугам
 */
function get_total_sum_by_service($invoiceid, $adb) {
    $sql = "SELECT
            SUM(margin * (1 + (COALESCE(tax1, 0) + COALESCE(tax2, 0) + COALESCE(tax3, 0)) / 100)) AS total,
            SUM(margin) AS margin
            FROM vtiger_inventoryproductrel
            WHERE id = ?";
    $result = $adb->pquery($sql, array($invoiceid));
    $total  = $adb->query_result($result, 0, 'total');
    $margin = $adb->query_result($result, 0, 'margin');
    return array('total' => $total, 'margin' => $margin);
}

/**
 * Обновление долга квартиры
 */
function update_flat_debt_by_flatid($estatesid, $adb) {
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

/**
 * Преобразование названия месяца в номер месяца
 */
function getMonthNumber($monthName) {
    $months = [
        'Январь' => 1, 'Февраль' => 2, 'Март' => 3, 'Апрель' => 4,
        'Май' => 5, 'Июнь' => 6, 'Июль' => 7, 'Август' => 8,
        'Сентябрь' => 9, 'Октябрь' => 10, 'Ноябрь' => 11, 'Декабрь' => 12
    ];
    return $months[$monthName] ?? null;
}

/**
 * Добавление услуг со счетчиками
 */
function add_meters_to_service($invoice_id, $service_id, $service_name, $estatesid, $listprice, $object_type, $monthName, $year, $tax1, $tax2, $tax3, $logger, $adb) {
    $totalAccrued = 0;

    $monthNumber = getMonthNumber($monthName);
    if (!$monthNumber) {
        $logger->log("❌ ОШИБКА: Неизвестный месяц: $monthName");
        return 0;
    }

    // Показания, снятые в следующем месяце, закрывают текущий
    $nextMonth = $monthNumber + 1;
    $nextYear  = $year;
    if ($nextMonth > 12) {
        $nextMonth = 1;
        $nextYear++;
    }

    $startDate    = sprintf('%04d-%02d-01', $nextYear, $nextMonth);
    $endDate      = date('Y-m-t', strtotime("$nextYear-$nextMonth-01"));
    $prevStartDate = sprintf('%04d-%02d-01', $year, $monthNumber);
    $prevEndDate  = date('Y-m-t', strtotime("$year-$monthNumber-01"));

    $meters = $adb->pquery(
        "SELECT * FROM vtiger_meters vm
         INNER JOIN vtiger_crmentity vc ON vm.metersid = vc.crmid
         WHERE vc.deleted = 0
         AND vm.cf_meter_object_link = ?",
        array($estatesid)
    );

    $metersCount = $adb->num_rows($meters);

    if ($metersCount == 0) {
        $logger->log("WARN: Счетчики не найдены для объекта $estatesid (услуга '$service_name')");
        return 0;
    }

    for ($k = 0; $k < $metersCount; $k++) {
        $metersid    = $adb->query_result($meters, $k, 'metersid');
        $meter_number = $adb->query_result($meters, $k, 'meter_number');

        // Текущее показание (снято в следующем месяце, закрывает текущий)
        $meters_data = $adb->pquery(
            "SELECT vr.readingsid, vr.meter_reading, vr.cf_reading_date
             FROM vtiger_readings vr
             INNER JOIN vtiger_crmentity vc ON vc.crmid = vr.readingsid
             WHERE vc.deleted = 0
             AND vr.cf_reading_meter_link = ?
             AND vr.cf_reading_date >= ?
             AND vr.cf_reading_date <= ?
             ORDER BY vr.cf_reading_date ASC
             LIMIT 1",
            array($metersid, $startDate, $endDate)
        );

        if ($adb->num_rows($meters_data) == 0) {
            $logger->log("WARN: Счетчик №$meter_number — нет показания за $startDate - $endDate (закрывающего $monthName)");
            continue;
        }

        $cur_reading_id = $adb->query_result($meters_data, 0, 'readingsid');
        $current_md     = floatval($adb->query_result($meters_data, 0, 'meter_reading'));
        $current_date   = $adb->query_result($meters_data, 0, 'cf_reading_date');

        // Предыдущее показание (снято в текущем месяце)
        $prev_reading_id = null;
        $prev_meters_data = $adb->pquery(
            "SELECT vr.readingsid, vr.meter_reading, vr.cf_reading_date
             FROM vtiger_readings vr
             INNER JOIN vtiger_crmentity vc ON vc.crmid = vr.readingsid
             WHERE vc.deleted = 0
             AND vr.cf_reading_meter_link = ?
             AND vr.cf_reading_date >= ?
             AND vr.cf_reading_date <= ?
             ORDER BY vr.cf_reading_date DESC
             LIMIT 1",
            array($metersid, $prevStartDate, $prevEndDate)
        );

        if ($adb->num_rows($prev_meters_data) > 0) {
            $prev_reading_id = $adb->query_result($prev_meters_data, 0, 'readingsid');
            $prev_md   = floatval($adb->query_result($prev_meters_data, 0, 'meter_reading'));
            $prev_date = $adb->query_result($prev_meters_data, 0, 'cf_reading_date');
        } else {
            // Берем последнее показание до начала следующего месяца
            $prev_meters_data = $adb->pquery(
                "SELECT vr.readingsid, vr.meter_reading, vr.cf_reading_date
                 FROM vtiger_readings vr
                 INNER JOIN vtiger_crmentity vc ON vc.crmid = vr.readingsid
                 WHERE vc.deleted = 0
                 AND vr.cf_reading_meter_link = ?
                 AND vr.cf_reading_date < ?
                 ORDER BY vr.cf_reading_date DESC
                 LIMIT 1",
                array($metersid, $startDate)
            );

            if ($adb->num_rows($prev_meters_data) > 0) {
                $prev_reading_id = $adb->query_result($prev_meters_data, 0, 'readingsid');
                $prev_md   = floatval($adb->query_result($prev_meters_data, 0, 'meter_reading'));
                $prev_date = $adb->query_result($prev_meters_data, 0, 'cf_reading_date');
            } else {
                $prev_md   = 0;
                $prev_date = 'нет';
                $logger->log("WARN: Счетчик №$meter_number — не найдено предыдущего показания до $startDate");
            }
        }

        $quantity = $current_md - $prev_md;

        if ($quantity > 0) {
            $margin = $quantity * $listprice;
            add_service_to_invoice($invoice_id, $service_id, $meter_number, $prev_md, $current_md, $quantity, $listprice, $margin, $tax1, $tax2, $tax3, $adb, $prev_reading_id, $cur_reading_id);
            $logger->log("Счетчик №$meter_number: пред=$prev_md [ID:$prev_reading_id] ($prev_date), тек=$current_md [ID:$cur_reading_id] ($current_date), расход=$quantity, сумма=$margin сом");
            $totalAccrued += $margin;
        } else {
            $logger->log("WARN: Счетчик №$meter_number — расход=$quantity (пред=$prev_md, тек=$current_md)");
        }
    }

    return $totalAccrued;
}

$logger->log("Обработчик МП Тамчы ТазаСуу загружен успешно");
?>
