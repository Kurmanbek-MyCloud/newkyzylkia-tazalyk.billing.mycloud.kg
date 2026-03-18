<?php

function createReading($entityData) {
    $reading = $entityData->get('cf_reading_verification_date');
    $reading_date = $entityData->get('cf_meter_verification_date');
    $assigned_user_id = explode('x', $entityData->get('assigned_user_id'))[1];
    $metersID = explode('x', $entityData->get('id'))[1];

    if (($reading === '' || is_null($reading)) || empty($reading_date)) {
        return;
    }

    $newReading = Vtiger_Record_Model::getCleanInstance("Readings");
    $newReading->set('assigned_user_id', $assigned_user_id);
    $newReading->set('cf_reading_meter_link', $metersID);
    $newReading->set('meter_reading', $reading);
    $newReading->set('cf_reading_date', $reading_date);
    $newReading->set('cf_used_in_bill', 1);
    $newReading->set('cf_reading_source', 'Ручной ввод');
    $newReading->save();
}
?>