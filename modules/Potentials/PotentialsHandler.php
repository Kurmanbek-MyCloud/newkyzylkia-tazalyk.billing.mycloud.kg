<?php
// Обновление веса в рейсе и мешке при обновлении записи.
function UpdateWeight($ws_entity){ 
    global $adb;
    $module = $ws_entity->getModuleName();
    $id_record = explode('x',$ws_entity->getId())[1];
    if ($module == 'Potentials') {
        $contactId = explode('x',$ws_entity->get('related_to'))[1];
        $boxId = explode('x',$ws_entity->get('cf_boxes_to'))[1];
        if ($boxId) {
            UpdateWeightInBox($boxId, $adb);
            $flightId = foundIdFlight($boxId, $adb);
            UpdateWeightInFlight($flightId, $adb);
        }
        if ($contactId) {
            UpdateContactInfo($contactId, $adb);
        }
        
    }
    else if ($module == 'Boxes') {
        $flightId = explode('x',$ws_entity->get('cf_flights_to'))[1];
        if ($flightId) {
            UpdateWeightInFlight($flightId, $adb);
        }
    }

}
function UpdateWeightInBox($id, $adb) {
    $sql = "UPDATE vtiger_boxes 
    set cf_box_weight = (select sum(cf_parcel_weight) from vtiger_potential vp 
    inner join vtiger_crmentity vc on vc.crmid = vp.potentialid
    where cf_boxes_to = '$id' and vc.deleted = 0), cf_parcel_quantity = (select count(cf_parcel_weight) from vtiger_potential vp2
    inner join vtiger_crmentity vc2 on vc2.crmid = vp2.potentialid
    where cf_boxes_to = '$id' and vc2.deleted = 0)
    where boxesid = '$id'";
    $adb->pquery($sql, array());
}

function UpdateWeightInFlight($id, $adb) {
    $sql = "UPDATE vtiger_flights 
    set cf_boxes_weight = (select sum(cf_box_weight) from vtiger_boxes vb
    inner join vtiger_crmentity vc on vc.crmid = vb.boxesid
    where cf_flights_to = '$id' and vc.deleted = 0), cf_remain_weight = cf_freight_weight - (select sum(cf_box_weight) from vtiger_boxes vb2
    inner join vtiger_crmentity vc2 on vc2.crmid = vb2.boxesid
    where cf_flights_to = '$id' and vc2.deleted = 0)
    where flightsid = '$id'";
    $adb->pquery($sql, array());
}

function foundIdFlight($id, $adb) {
    $sql = "SELECT cf_flights_to FROM vtiger_boxes vb 
    where boxesid = '$id'";
    return $adb->run_query_allrecords($sql)[0]['cf_flights_to'];
}

function UpdateContactInfo($id, $adb) {
    $sql = "UPDATE vtiger_contactdetails vcm
    JOIN (
        SELECT vc.contactid,
               COALESCE(SUM(vp.forecast_amount), 0) AS total_forecast_amount_all,
               COALESCE(SUM(CASE WHEN vp.sales_stage = 'Создан' THEN vp.forecast_amount ELSE 0 END), 0) AS total_forecast_amount_unpaid
        FROM vtiger_contactdetails vc
        LEFT JOIN vtiger_potential vp ON vp.related_to = vc.contactid
        LEFT JOIN vtiger_crmentity vc2 ON vc2.crmid = vp.potentialid
        WHERE vc.contactid = '$id' AND vc2.deleted = 0
        GROUP BY vc.contactid
    ) AS sub ON vcm.contactid = sub.contactid
    SET vcm.cf_all_parcel_amount = sub.total_forecast_amount_all,
        vcm.cf_unpaid_parcel_amount = sub.total_forecast_amount_unpaid,
        vcm.cf_balance = sub.total_forecast_amount_all - sub.total_forecast_amount_unpaid
    WHERE vcm.contactid = '$id';";
    $adb->pquery($sql, array());
}
