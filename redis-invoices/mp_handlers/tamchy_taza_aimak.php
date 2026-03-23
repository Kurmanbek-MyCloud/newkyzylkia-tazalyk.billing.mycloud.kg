<?php

/**
 * Обработчик для МП Таза Аймак (groupid = 59)
 */

$rootPath = dirname(dirname(__DIR__)) . '/';
chdir($rootPath);

// Подключаем файлы Vtiger
require_once 'include/utils/utils.php';
require_once 'Logger.php';
include_once 'includes/runtime/BaseModel.php';
include_once 'includes/runtime/Globals.php';
include_once 'includes/runtime/Controller.php';
include_once 'includes/http/Request.php';

// Инициализируем подключение к базе данных глобально
require_once $rootPath . 'include/database/PearDatabase.php';
$adb = new PearDatabase();
$adb->connect();

// Инициализируем пользователя глобально
require_once $rootPath . 'modules/Users/Users.php';
$current_user = Users::getActiveAdminUser();
$logger = new CustomLogger('redis-invoices/mp_handlers/tamchy_taza_aimak');

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
 * Обработка счетов для МП Таза Аймак
 */

function processTamchyTazaAimakInvoice($task, $logger) {
    // Используем глобальные переменные
    global $adb, $current_user;

    // Проверяем подключение к БД перед обработкой задачи
    ensureDbConnection($logger);

    $logger->log("=== НАЧАЛО ОБРАБОТКИ МП Таза Аймак ===");
    echo "Начинаем обработку для МП Таза Аймак\n";

    $estatesid = $task['objectID'];
    $months = $task['months'];

    $logger->log("Объект: $estatesid, месяцы: " . implode(', ', $months));
    
    // Получаем данные объекта недвижимости
    $res = $adb->pquery("SELECT es.* FROM vtiger_estates es
                        INNER JOIN vtiger_crmentity vc ON es.estatesid = vc.crmid
                        WHERE vc.deleted = 0 AND es.estatesid = ?", array($estatesid));
    
    if ($adb->num_rows($res) == 0) {
        $logger->log("Object ID $estatesid не найден в базе данных");
        echo "Object ID $estatesid не найден в базе данных\n";
        return ['success' => false, 'error' => 'Объект не найден'];
    }
    
    // Получаем данные объекта
    $estate_number = $adb->query_result($res, 0, 'estate_number');
    $object_type = $adb->query_result($res, 0, 'cf_object_type');
    $litera = $adb->query_result($res, 0, 'cf_litera');
    $apartment_number = $adb->query_result($res, 0, 'cf_apartment_number');
    $number_of_residents = $adb->query_result($res, 0, 'cf_number_of_residents');
    $deactivated = $adb->query_result($res, 0, 'cf_deactivated');
    $area = $adb->query_result($res, 0, 'cf_area');
    
    if ($deactivated) {
        $logger->log("❌ Object ID $estatesid деактивирован, пропускаем");
        echo "Object ID $estatesid деактивирован, пропускаем\n";
        return;
    }
    
    // Получаем год из задачи или используем текущий год
    $year = isset($task['year']) ? intval($task['year']) : intval(date('Y'));
    
    // Обрабатываем каждый месяц из задачи
    foreach ($months as $monthName) {
        $theme = $monthName . " " . $year . " года";
        echo "Обрабатываем месяц: $monthName для года: $year\n";
        
        // Получаем услуги для объекта (GROUP BY чтобы исключить дубли если услуга привязана несколько раз)
        $services_data = $adb->pquery("SELECT s.serviceid, MAX(s.unit_price) as unit_price
                    FROM vtiger_crmentityrel rel
                    INNER JOIN vtiger_service s ON s.serviceid = rel.relcrmid
                    INNER JOIN vtiger_crmentity crm ON crm.crmid = s.serviceid
                    WHERE rel.relmodule = 'Services'
                    AND crm.deleted = 0
                    AND rel.crmid = ?
                    GROUP BY s.serviceid", array($estatesid));
        
        if ($adb->num_rows($services_data) > 0) {
            $servicesCount = $adb->num_rows($services_data);

            // Проверяем, не существует ли уже счет за этот месяц и год для данного объекта
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
                $logger->log("❌ Счет за $monthName $year года уже существует для Object ID $estatesid (Invoice ID: $existingInvoiceId). Пропускаем.");
                return ['success' => false, 'error' => "Счет за $monthName $year года уже существует (ID: $existingInvoiceId)"];
            }

            // Создаем новый счет
            $invoiceId = createInvoice($estatesid, $theme, $monthName, $logger);
            
            if (!$invoiceId) {
                $logger->log("❌ ОШИБКА: Не удалось создать счет для Object ID: $estatesid");
                return ['success' => false, 'error' => 'Не удалось создать счет'];
            }

            // Обрабатываем каждую услугу
            $servicesProcessed = 0;
            for ($j = 0; $j < $adb->num_rows($services_data); $j++) {
                $service_id = $adb->query_result($services_data, $j, 'serviceid');
                $listprice = $adb->query_result($services_data, $j, 'unit_price');
                
                try {
                    // Получаем налоги для услуги
                    $taxes = get_service_tax_percentages($service_id, $adb);
                    $tax1 = $taxes[0];
                    $tax2 = $taxes[1];
                    $tax3 = $taxes[2];
                    $totalTax = $tax1 + $tax2 + $tax3;
                    
                    if ($service_id == 16900) {
                        // Фиксированная услуга
                        $margin = $listprice;
                        add_service_to_invoice($invoiceId, $service_id, 1, 0, 0, 1, $listprice, $margin, $tax1, $tax2, $tax3, $adb);
                        $servicesProcessed++;
                        
                    } else {
                        $logger->log("❌ ⚠️ Неизвестная услуга ID: $service_id, пропускаем");
                        continue;
                    }
                } catch (Exception $e) {
                    $logger->log("❌ ОШИБКА при добавлении услуги ID $service_id: " . $e->getMessage());
                }
            }
            
            // Обновляем итоги счета
            $results = get_total_sum_by_service($invoiceId, $adb);
            
            if ($results['total'] && $results['total'] > 0) {
                update_invoice_total_field($results['total'], $results['margin'], $invoiceId, $adb);
                $logger->log("✅ Счет ID $invoiceId: итого=" . round($results['total'], 2) . " сом (без НДС=" . round($results['margin'], 2) . ")");
                update_flat_debt_by_flatid($estatesid, $adb);
            } else {
                $logger->log("❌ ОШИБКА: Итоги счета нулевые для $monthName $year года (объект $estatesid, услуг обработано: $servicesProcessed/$servicesCount)");
                return ['success' => false, 'error' => "Счет получился нулевым для $monthName $year года."];
            }
        } else {
            $logger->log("Услуги для Object ID $estatesid не найдены");
            return ['success' => false, 'error' => 'Услуги не найдены'];
        }
    }
    
    $logger->log("=== КОНЕЦ ОБРАБОТКИ МП Таза Аймак ===");
    echo "Обработка завершена\n";
    
    // Возвращаем результат (предполагаем успех, если дошли до сюда)
    return ['success' => true, 'invoiceId' => $invoiceId ?? null];
}

/**
 * Создание счета
 */
function createInvoice($estatesid, $theme, $month, $logger) {
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
                array(59, $invoiceId)
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
 * Возвращает массив с tax1, tax2, tax3 (может быть до 3 налогов)
 * tax1 = НДС, tax2 = НСП, tax3 = другие налоги
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

    $taxes = [0, 0, 0]; // tax1 (НДС), tax2 (НСП), tax3 (другие)
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
function add_service_to_invoice($invoice_id, $service_id, $meter_number, $prev_md, $current_md, $quantity, $listprice, $margin, $tax1, $tax2, $tax3, $adb) {
    try {
        $sql = "INSERT INTO vtiger_inventoryproductrel(id, productid, accrual_base, previous_reading, current_reading, quantity, listprice, margin, tax1, tax2, tax3) VALUES(?,?,?,?,?,?,?,?,?,?,?)";
        $params = array($invoice_id, $service_id, $meter_number, $prev_md, $current_md, $quantity, $listprice, $margin, $tax1, $tax2, $tax3);
        $adb->pquery($sql, $params);
        // Логирование будет в вызывающей функции
    } catch (Exception $e) {
        // Ошибка будет обработана в вызывающей функции
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
    $total = $adb->query_result($result, 0, 'total');
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
 * Добавление услуг со счетчиками
 */
function add_meters_to_service($invoice_id, $service_id, $estatesid, $listprice, $object_type, $tax1, $tax2, $tax3, $logger, $adb) {
    $logger->log("Обрабатываем счетчики для Object ID: $estatesid");
    
    // Логика для индивидуальных счетчиков
    $meters = $adb->pquery("SELECT * FROM vtiger_meters vm 
                            INNER JOIN vtiger_crmentity vc ON vm.metersid = vc.crmid 
                            WHERE vc.deleted = 0
                            AND vm.cf_meter_object_link = ?", array($estatesid));


    $logger->log("Найдено счетчиков: " . $adb->num_rows($meters));


    for ($k = 0; $k < $adb->num_rows($meters); $k++) {
        $metersid = $adb->query_result($meters, $k, 'metersid');
        $coefficient = $adb->query_result($meters, $k, 'cf_coefficient');
        $meter_number = $adb->query_result($meters, $k, 'meter_number');

        $meters_data = $adb->pquery("SELECT * FROM vtiger_readings vr 
                              INNER JOIN vtiger_crmentity vc ON vc.crmid = vr.readingsid 
                              WHERE vc.deleted = 0
                              AND vr.cf_reading_meter_link = ?
                              ORDER BY vr.cf_reading_date DESC", array($metersid));

        $num_readings = $adb->num_rows($meters_data);

        $current_md = floatval($adb->query_result($meters_data, 0, 'meter_reading'));
        $prev_md = ($num_readings > 1) ? floatval($adb->query_result($meters_data, 1, 'meter_reading')) : 0;

        $quantity = $current_md - $prev_md;

        $logger->log("Счетчик $meter_number: пред.пок.=$prev_md, тек.пок.=$current_md, расход=$quantity");

        if ($quantity > 0) {
            $margin = $quantity * $listprice;
            $totalTax = $tax1 + $tax2 + $tax3;
            add_service_to_invoice($invoice_id, $service_id, $meter_number, $prev_md, $current_md, $quantity, $listprice, $margin, $tax1, $tax2, $tax3, $adb);
            $logger->log("Добавлена услуга со счетчика: расход=$quantity, цена=$listprice, сумма=$margin, налоги: НДС=$tax1%, НСП=$tax2% (итого: $totalTax%)");
        }
    }
}

$logger->log("Обработчик МП Таза Аймак загружен успешно");
?>
