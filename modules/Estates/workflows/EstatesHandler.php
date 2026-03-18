<?php
function updateDebt($ws_entity)
{
    global $adb;
    $ws_id = $ws_entity->getId();
    $module = $ws_entity->getModuleName();
    if (empty($ws_id) || empty($module)) {
        return;
    }
    $crmid = vtws_getCRMEntityId($ws_id);

    if ($crmid <= 0) {
        return;
    }
    $setype = '';
    // Определяем object_id и setype
    if ($module === 'Payments') {
        // cf_paid_object → объект (Estates/Contacts и т.п.)
        $field = 'cf_paid_object';
        if ($ws_entity->get($field) !== null) {
            $object_id = explode("x", $ws_entity->get($field))[1];
            $result = $adb->pquery("SELECT setype FROM vtiger_crmentity WHERE crmid = ?", [$object_id]);
            $setype = ($adb->num_rows($result) > 0) ? $adb->query_result($result, 0, 'setype') : '';
        }

    } elseif ($module === 'Invoice') {
        // cf_estate_id → объект (Estates/Contacts и т.п.)
        $field = 'cf_estate_id';
        if ($ws_entity->get($field) !== null) {
            $object_id = explode("x", $ws_entity->get($field))[1];
            $result = $adb->pquery("SELECT setype FROM vtiger_crmentity WHERE crmid = ?", [$object_id]);
            $setype = ($adb->num_rows($result) > 0) ? $adb->query_result($result, 0, 'setype') : '';
        }

    } elseif ($module === 'Penalty') {
        $invoiceWsId = $ws_entity->get('cf_to_ivoice');
        if (!empty($invoiceWsId)) {
            $invoiceCrmId = explode("x", $invoiceWsId)[1];

            $res = $adb->pquery("SELECT cf_estate_id 
                                FROM vtiger_invoice 
                                WHERE invoiceid = ?", [$invoiceCrmId]);

            if ($adb->num_rows($res) > 0) {
                $object_id = $adb->query_result($res, 0, 'cf_estate_id');
                // Узнаём setype по найденному object_id
                $resultSetype = $adb->pquery("SELECT setype FROM vtiger_crmentity WHERE crmid = ?", [$object_id]);
                if ($adb->num_rows($resultSetype) > 0) {
                    $setype = $adb->query_result($resultSetype, 0, 'setype');
                }
            }
        }
    } else {
        $moduleSetypes = [
            'Estates' => 'Estates', // Электричество
            'Contacts' => 'Contacts', // Телефония
            'Telegraph' => 'Telegraph' // Телеграф
        ];
        if (isset($moduleSetypes[$module])) {
            $object_id = $crmid;
            $setype = $moduleSetypes[$module];
        }
    }
    if (empty($setype)) {
        return;
    }

    $invsumQuery = $adb->pquery("SELECT 
                                    SUM(sub.total_with_penalty) AS invoice_sum,
                                    COUNT(*) AS invoice_count
                                FROM (SELECT inv.invoiceid,
                                        -- Складываем сумму инвойса и суммарную пеню по нему
                                        inv.total + COALESCE(SUM(vp.penalty_amount), 0) AS total_with_penalty
                                    FROM vtiger_invoice inv
                                    INNER JOIN vtiger_crmentity vc ON vc.crmid = inv.invoiceid
                                    -- LEFT JOIN, чтобы инвойс попал в выборку даже если пени нет
                                    LEFT JOIN vtiger_penalty vp ON inv.invoiceid = vp.cf_to_ivoice
                                    LEFT JOIN vtiger_crmentity vc2 ON vp.penaltyid = vc2.crmid AND vc2.deleted = 0
                                    WHERE vc.deleted = 0
                                    AND inv.invoicestatus NOT IN ('Cancel')
                                    AND inv.cf_estate_id = ?
                                    GROUP BY inv.invoiceid) AS sub", array($object_id));

    if ($adb->num_rows($invsumQuery) > 0) {
        $invoice_sum = $adb->query_result($invsumQuery, 0, 'invoice_sum');
        $invoice_count = $adb->query_result($invsumQuery, 0, 'invoice_count');
    }

    $paysumQuery = $adb->pquery("SELECT 
                                SUM(vp.amount) AS pay_amount,
                                COUNT(*) AS pay_count 
                             FROM 
                                vtiger_payments AS vp
                                INNER JOIN vtiger_crmentity AS vc ON vc.crmid = vp.paymentsid  
                             WHERE 
                                vc.deleted = 0 
                                AND vp.cf_pay_type = ? 
                                AND vp.cf_status != ? 
                                AND vp.cf_paid_object = ?",
        array('Приход', 'Отменен', $object_id)
    );

    if ($adb->num_rows($paysumQuery) > 0) {
        $pay_amount = $adb->query_result($paysumQuery, 0, 'pay_amount');
        $pay_count = $adb->query_result($paysumQuery, 0, 'pay_count');
    }
    // Обеспечиваем, что значения не NULL
    $invoice_sum = (float) ($invoice_sum ?: 0);
    $invoice_count = (int) ($invoice_count ?: 0);
    $pay_amount = (float) ($pay_amount ?: 0);
    $pay_count = (int) ($pay_count ?: 0);

    $result = round(($invoice_sum - $pay_amount), 2);

    // Обновляем данные
    $updateQueries = [
        'Estates' => "UPDATE vtiger_estates 
                      SET 
                        cf_balance = ?, 
                        cf_payment_qnt = ?, 
                        cf_invoice_qnt = ?, 
                        cf_payment_amnt = ?, 
                        cf_invoice_amnt = ? 
                      WHERE estatesid = ?",
        'Contacts' => "UPDATE vtiger_contactdetails 
                       SET 
                        cf_contact_balance = ?, 
                        cf_payment_qnt = ?, 
                        cf_invoice_qnt = ?, 
                        cf_payment_amnt = ?, 
                        cf_invoice_amnt = ? 
                       WHERE contactid = ?"
    ];

    if (isset($updateQueries[$setype])) {
        $adb->pquery($updateQueries[$setype], [$result, $pay_count, $invoice_count, $pay_amount, $invoice_sum, $object_id]);
    }

    // Статус оплачено в счетах
    // Получаем все платежи для объекта
    $paymentsQuery = $adb->pquery("
        SELECT vp.paymentsid, vp.amount 
        FROM vtiger_payments vp 
        INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.paymentsid 
        WHERE vc.deleted = 0 
        AND vp.cf_paid_object = ?
        AND vp.cf_pay_type = ?
        AND vp.cf_status != ?
        ORDER BY vp.paymentsid ASC
    ", [$object_id, 'Приход', 'Отменен']);
    
    $totalPaymentAmount = 0;
    if ($adb->num_rows($paymentsQuery) > 0) {
        while ($row = $adb->fetchByAssoc($paymentsQuery)) {
            $totalPaymentAmount += (float)$row['amount'];
        }
    }
    
    // Получаем все счета для объекта
    $invoicesQuery = $adb->pquery("
        SELECT 
            inv.invoiceid,
            inv.total
        FROM vtiger_invoice inv
        INNER JOIN vtiger_crmentity vc ON vc.crmid = inv.invoiceid
        WHERE vc.deleted = 0 
        AND inv.invoicestatus NOT IN ('Cancel')
        AND inv.cf_estate_id = ?
        ORDER BY inv.invoiceid ASC
    ", [$object_id]);
    
    $remainingPayment = $totalPaymentAmount;
    
    // Распределяем платежи по счетам последовательно
    if ($adb->num_rows($invoicesQuery) > 0) {
        while ($invoiceRow = $adb->fetchByAssoc($invoicesQuery)) {
            $invoiceId = $invoiceRow['invoiceid'];
            $invoiceTotal = (float)$invoiceRow['total'];
            
            // Проверяем, хватает ли оставшихся платежей для оплаты счета
            if ($remainingPayment >= $invoiceTotal && $invoiceTotal > 0) {
                // Счет оплачен
                $adb->pquery("
                    UPDATE vtiger_invoice 
                    SET invoicestatus = ? 
                    WHERE invoiceid = ?
                ", ['Paid', $invoiceId]);
                
                $remainingPayment -= $invoiceTotal;
            } else {
                // Счет не оплачен
                $adb->pquery("
                    UPDATE vtiger_invoice 
                    SET invoicestatus = ? 
                    WHERE invoiceid = ?
                ", ['Неоплачен', $invoiceId]);
            }
        }
    }

}
function generateEstateLs($ws_entity)
{
    // chdir('/var/www/1.billing.mycloud.kg');
    // require_once 'Logger.php';
    // $logger = new CustomLogger('createInvoice/createInvoice_tamchy_tazasuu.log');

    global $adb;
    $estate_id = explode('x', $ws_entity->getId())[1];
    $cf_object_type = $ws_entity->data['cf_object_type'];

    $cf_municipal_enterprise_raw = $ws_entity->data['cf_municipal_enterprise'];
    list(, $cf_municipal_enterprise) = explode('x', $cf_municipal_enterprise_raw);
    // $logger->log($cf_municipal_enterprise);


    if ($cf_municipal_enterprise == 8) {

        if ($cf_object_type == 'Физ. лицо') {
            $max_ls = $adb->run_query_field("
        SELECT MAX(CAST(SUBSTRING_INDEX(es.estate_number, '-', 1) AS UNSIGNED))
        FROM vtiger_estates es
        INNER JOIN vtiger_crmentity vc ON es.estatesid = vc.crmid 
        WHERE vc.deleted = 0
        AND es.cf_object_type = 'Физ. лицо' 
        AND es.cf_municipal_enterprise = 8
    ");

            $max_ls_int = intval($max_ls);

            // Если еще не было номеров — начинаем с 10001
            if ($max_ls_int < 10001) {
                $max_ls_int = 10001;
            } else {
                $max_ls_int++;
            }

            // Проверяем, не вышел ли за предел
            if ($max_ls_int > 49999) {
                throw new Exception("Достигнут лимит номеров (49999) для типа 'Физ. лицо'");
            }

            $max_ls_str = $max_ls_int;

        } elseif ($cf_object_type == 'Юр. лицо') {
            $max_ls = $adb->run_query_field("
        SELECT MAX(CAST(es.estate_number AS UNSIGNED))
        FROM vtiger_estates es
        INNER JOIN vtiger_crmentity vc ON es.estatesid = vc.crmid 
        WHERE vc.deleted = 0
        AND es.cf_object_type = 'Юр. лицо' 
        AND es.cf_municipal_enterprise = 8
    ");

            $max_ls_int = intval($max_ls);

            if ($max_ls_int < 10001) {
                $max_ls_int = 10001;
            } else {
                $max_ls_int++;
            }

            if ($max_ls_int > 49999) {
                throw new Exception("Достигнут лимит номеров (49999) для типа 'Юр. лицо'");
            }

            $max_ls_str = $max_ls_int;
        }

        // Обновляем запись
        $adb->pquery("
    UPDATE vtiger_estates es
    SET es.estate_number = ?
    WHERE es.estatesid = ?
", array($max_ls_str, $estate_id));

    } elseif ($cf_municipal_enterprise == 9) {

        if ($cf_object_type == 'Физ. лицо') {
            $max_ls = $adb->run_query_field("
        SELECT MAX(CAST(SUBSTRING_INDEX(es.estate_number, '-', 1) AS UNSIGNED))
        FROM vtiger_estates es
        INNER JOIN vtiger_crmentity vc ON es.estatesid = vc.crmid 
        WHERE vc.deleted = 0
        AND es.cf_object_type = 'Физ. лицо' 
        AND es.cf_municipal_enterprise = 9
    ");

            $max_ls_int = intval($max_ls);

            // Если еще не было номеров — начинаем с 10001
            if ($max_ls_int < 50001) {
                $max_ls_int = 50001;
            } else {
                $max_ls_int++;
            }

            // Проверяем, не вышел ли за предел
            if ($max_ls_int > 99999) {
                throw new Exception("Достигнут лимит номеров (99999) для типа 'Физ. лицо'");
            }

            $max_ls_str = $max_ls_int;

        } elseif ($cf_object_type == 'Юр. лицо') {
            $max_ls = $adb->run_query_field("
        SELECT MAX(CAST(es.estate_number AS UNSIGNED))
        FROM vtiger_estates es
        INNER JOIN vtiger_crmentity vc ON es.estatesid = vc.crmid 
        WHERE vc.deleted = 0
        AND es.cf_object_type = 'Юр. лицо' 
        AND es.cf_municipal_enterprise = 9
    ");

            $max_ls_int = intval($max_ls);

            if ($max_ls_int < 50001) {
                $max_ls_int = 50001;
            } else {
                $max_ls_int++;
            }

            if ($max_ls_int > 99999) {
                throw new Exception("Достигнут лимит номеров (99999) для типа 'Юр. лицо'");
            }

            $max_ls_str = $max_ls_int;
        }

        // Обновляем запись
        $adb->pquery("
    UPDATE vtiger_estates es
    SET es.estate_number = ?
    WHERE es.estatesid = ?
", array($max_ls_str, $estate_id));

    }
}

function addService($ws_entity)
{
    //  echo "test";
    //  exit();
    global $adb;
    $estate_id = explode('x', $ws_entity->getId())[1];
    $cf_object_type = $ws_entity->data['cf_object_type'];
    // var_dump($estate_id);
    // var_dump($cf_object_type);
    // exit();


    if ($cf_object_type == 'Физ. лицо') {
        $adb->pquery("DELETE FROM vtiger_crmentityrel WHERE  `crmid`= $estate_id AND `module`='Estates' AND `relmodule`='Services'", array());

        $adb->pquery("INSERT INTO vtiger_crmentityrel (crmid, module, relcrmid, relmodule) VALUES ('$estate_id', 'Estates', '58269', 'Services')", array());

    } elseif ($cf_object_type == 'Юр. лицо') {
        $adb->pquery("DELETE FROM vtiger_crmentityrel WHERE  `crmid`= $estate_id AND `module`='Estates' AND `relmodule`='Services'", array());

        $adb->pquery("INSERT INTO vtiger_crmentityrel (crmid, module, relcrmid, relmodule) VALUES ('$estate_id', 'Estates', '61044', 'Services')", array());

    }
}

?>