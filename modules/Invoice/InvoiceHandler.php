<?php

/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */
include_once 'include/Webservices/Revise.php';
include_once 'include/Webservices/Retrieve.php';

class InvoiceHandler extends VTEventHandler {

    function handleEvent($eventName, $entityData) {
        // Простой тест на срабатывание
        file_put_contents(dirname(__FILE__) . '/test.txt',
            date('Y-m-d H:i:s') . " Event: $eventName\n",
            FILE_APPEND
        );
        $moduleName = $entityData->getModuleName();

        // Validate the event target
        if ($moduleName != 'Invoice') {
            return;
        }

        //Get Current User Information
        global $current_user, $currentModule;

        /**
         * Adjust the balance amount against total & received amount
         * NOTE: beforesave the total amount will not be populated in event data.
         */
        if ($eventName == 'vtiger.entity.aftersave') {
            // Trigger from other module (due to indirect save) need to be ignored - to avoid inconsistency.
            if ($currentModule != 'Invoice')
                return;
            $entityDelta = new VTEntityDelta();
            $oldCurrency = $entityDelta->getOldValue($entityData->getModuleName(), $entityData->getId(), 'currency_id');
            $newCurrency = $entityDelta->getCurrentValue($entityData->getModuleName(), $entityData->getId(), 'currency_id');
            $oldConversionRate = $entityDelta->getOldValue($entityData->getModuleName(), $entityData->getId(), 'conversion_rate');
            $db = PearDatabase::getInstance();
            $wsid = vtws_getWebserviceEntityId('Invoice', $entityData->getId());
            $wsrecord = vtws_retrieve($wsid, $current_user);
            if ($oldCurrency != $newCurrency && $oldCurrency != '') {
                if ($oldConversionRate != '') {
                    $wsrecord['received'] = floatval(((float) $wsrecord['received'] / $oldConversionRate) * (float) $wsrecord['conversion_rate']);
                }
            }
            $wsrecord['balance'] = floatval((float) $wsrecord['hdnGrandTotal'] - (float) $wsrecord['received']);
            if ($wsrecord['balance'] == 0) {
                $wsrecord['invoicestatus'] = 'Paid';
            }
            $query = "UPDATE vtiger_invoice SET balance=?,received=? WHERE invoiceid=?";
            $db->pquery($query, array($wsrecord['balance'], $wsrecord['received'], $entityData->getId()));
        }
        // Добавляем обработку изменения статуса показания во время удаления счета
        if ($eventName == 'vtiger.entity.beforedelete') {
            try {
                $invoiceId = $entityData->getId();
                // var_dump($invoiceId);
                // exit;
                // Сбрасываем использование показаний
                Readings_Record_Model::resetReadingsForInvoice($invoiceId);
            } catch (Exception $e) {
                global $log;
                $log->debug("Ошибка при сбросе показаний: " . $e->getMessage());
            }
        }
    }

}

?>