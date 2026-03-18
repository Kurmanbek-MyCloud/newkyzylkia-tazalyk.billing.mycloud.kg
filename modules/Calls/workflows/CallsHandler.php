<?php
/**
 * Логика определения типа звонка:
 * 
 * Данный обработчик вызывается только тогда когда в поле "Номер телефона" не указан связанный номер!!!
 * 
 * 1. Если оба номера пустые - "Неопределенный"
 * 
 * 2. Если number_a > 6 и number_b = 6 - "Входящий"
 * 
 * 3. Если number_a = 6:
 *    - Проверяем в таблице vtiger_telephone
 *    - Если не найден - "Неизвестный номер"
 *    - Если найден:
 *      * Если number_b = 6 - "Внутренний"
 *      * Если number_b > 6 - "Исходящий"
 *      * В остальных случаях - "Неопределенный"
 * 
 * 4. Во всех остальных случаях - "Неопределенный"
 */

// exit(__DIR__);
require_once __DIR__ . '/../../../Logger.php';
// exit('CallsHandler.php');

function assignCallToContact($ws_entity) {
    global $adb;

    $logFilePath = __DIR__ . '/callsHandlers.log';
    $logger = new CustomLogger($logFilePath);

    $rawCrmId = $ws_entity->getId();
    $crmid = (strpos($rawCrmId, 'x') !== false) ? explode("x", $rawCrmId)[1] : $rawCrmId;

    if (!is_numeric($crmid) || $crmid <= 0) {
        return;
    }
    $call_ID = $ws_entity->get('call_id');
    $number_a = $ws_entity->get('cf_number_a');
    $number_b = $ws_entity->get('cf_number_b');

    if (empty($number_a) && empty($number_b)) {
        $adb->pquery("UPDATE vtiger_calls 
                      SET cf_calltype = ? 
                      WHERE callsid = ?", ['Неопределенный', $crmid]);
        // $logger->log("Звонок callID: {$call_ID} определен как Неопределенный (оба номера пустые)");
        return;
    }

    // Проверяем длины номеров
    $number_a_length = strlen($number_a);
    $number_b_length = strlen($number_b);
    $is_number_a_six = $number_a_length === 6 && ctype_digit($number_a);
    $is_number_b_six = $number_b_length === 6 && ctype_digit($number_b);

    // Если number_a больше 6 и number_b 6 знаков - входящий
    if ($number_a_length > 6 && $is_number_b_six) {
        $adb->pquery("UPDATE vtiger_calls 
                      SET cf_calltype = ? 
                      WHERE callsid = ?", ['Входящий', $crmid]);
        // $logger->log("Звонок callID: {$call_ID} определен как Входящий (number_a > 6, number_b = 6)");
        return;
    }

    // Проверяем number_a в таблице только если он 6-значный
    $linked_crmid = null;
    if ($is_number_a_six) {
        $res_a = $adb->pquery("SELECT telephoneid FROM vtiger_telephone WHERE client_phone = ?", [$number_a]);
        if ($adb->num_rows($res_a) > 0) {
            $linked_crmid = $adb->query_result($res_a, 0, 'telephoneid');
        } else {
            $adb->pquery("UPDATE vtiger_calls 
                      SET cf_calltype = ? 
                      WHERE callsid = ?", ['Неизвестный номер', $crmid]);
            // $logger->log("Звонок callID: {$call_ID} определен как Неизвестный номер (number_a не найден в таблице)");
            return;
        }
    }

    // Определяем тип звонка
    if ($is_number_a_six && $is_number_b_six) {
        // Оба номера 6-значные - внутренний
        $adb->pquery("UPDATE vtiger_calls 
                      SET cf_calltype = ?, cf_phone_id = ? 
                      WHERE callsid = ?", ['Внутренний', $linked_crmid, $crmid]);
        // $logger->log("Звонок callID: {$call_ID} определен как Внутренний (оба номера 6-значные)");
    } elseif ($is_number_a_six && $number_b_length > 6) {
        // number_a = 6 и найден в таблице, number_b > 6 - исходящий
        $adb->pquery("UPDATE vtiger_calls 
                      SET cf_calltype = ?, cf_phone_id = ? 
                      WHERE callsid = ?", ['Исходящий', $linked_crmid, $crmid]);
        // $logger->log("Звонок callID: {$call_ID} определен как Исходящий (number_a = 6 и найден, number_b > 6)");
    } else {
        // Все остальные случаи - неопределенный
        $adb->pquery("UPDATE vtiger_calls 
                      SET cf_calltype = ? 
                      WHERE callsid = ?", ['Неопределенный', $crmid]);
        // $logger->log("Звонок callID: {$call_ID} определен как Неопределенный (нестандартная комбинация номеров)");
    }
}
?>