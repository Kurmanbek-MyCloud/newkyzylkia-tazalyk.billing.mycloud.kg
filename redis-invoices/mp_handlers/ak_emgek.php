<?php

/**
 * Обработчик для МП Ак Эмгек (groupid = 89)
 */

$rootPath = dirname(dirname(__DIR__)) . '/';
chdir($rootPath);

require_once 'include/utils/utils.php';
require_once 'Logger.php';
include_once 'includes/runtime/BaseModel.php';
include_once 'includes/runtime/Globals.php';
include_once 'includes/runtime/Controller.php';
include_once 'includes/http/Request.php';

require_once $rootPath . 'include/database/PearDatabase.php';
$adb = new PearDatabase();
$adb->connect();

require_once $rootPath . 'modules/Users/Users.php';
$current_user = Users::getActiveAdminUser();
$logger = new CustomLogger('redis-invoices/mp_handlers/ak_emgek');

/**
 * Проверка подключения к БД и переподключение если соединение умерло.
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
 * Обработка счетов для МП Ак Эмгек
 */
function processAkEmgekInvoice($task, $logger) {
    global $adb, $current_user;

    ensureDbConnection($logger);

    $logger->log("=== НАЧАЛО ОБРАБОТКИ МП Ак Эмгек ===");

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

    $deactivated = $adb->query_result($res, 0, 'cf_deactivated');

    if ($deactivated) {
        $logger->log("❌ Object ID $estatesid деактивирован, пропускаем");
        return ['success' => false, 'error' => 'Объект деактивирован'];
    }

    foreach ($months as $monthName) {
        $theme = $monthName . " " . $year . " года";

        // Получаем услуги для объекта
        $services_data = $adb->pquery(
            "SELECT DISTINCT s.serviceid, s.unit_price
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

        $invoiceId = createInvoice($estatesid, $theme, $monthName, $logger);

        if (!$invoiceId) {
            $logger->log("❌ ОШИБКА: Не удалось создать счет для объекта $estatesid");
            return ['success' => false, 'error' => 'Не удалось создать счет'];
        }

        $servicesProcessed = 0;

        for ($j = 0; $j < $servicesCount; $j++) {
            $service_id = $adb->query_result($services_data, $j, 'serviceid');
            $listprice = $adb->query_result($services_data, $j, 'unit_price');

            try {
                $taxes = get_service_tax_percentages($service_id, $adb);
                $tax1 = $taxes[0];
                $tax2 = $taxes[1];
                $tax3 = $taxes[2];

                // Фиксированная услуга
                $margin = $listprice;
                add_service_to_invoice($invoiceId, $service_id, 1, 0, 0, 1, $listprice, $margin, $tax1, $tax2, $tax3, $adb);
                $servicesProcessed++;
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
            $logger->log("❌ ОШИБКА: Итоги нулевые для $monthName $year (объект $estatesid, услуг: $servicesProcessed/$servicesCount)");
            return ['success' => false, 'error' => "Счет получился нулевым для $monthName $year года."];
        }
    }

    $logger->log("=== КОНЕЦ ОБРАБОТКИ МП Ак Эмгек ===");
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
                array(89, $invoiceId)
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
function add_service_to_invoice($invoice_id, $service_id, $meter_number, $prev_md, $current_md, $quantity, $listprice, $margin, $tax1, $tax2, $tax3, $adb) {
    try {
        $sql = "INSERT INTO vtiger_inventoryproductrel(id, productid, accrual_base, previous_reading, current_reading, quantity, listprice, margin, tax1, tax2, tax3) VALUES(?,?,?,?,?,?,?,?,?,?,?)";
        $params = array($invoice_id, $service_id, 0, $prev_md, $current_md, $quantity, $listprice, $margin, $tax1, $tax2, $tax3);
        $adb->pquery($sql, $params);
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

$logger->log("Обработчик МП Ак Эмгек загружен успешно");
?>
