<?php

chdir('../../../../../../');

require_once 'include/utils/utils.php';
require_once 'Logger.php';
include_once 'includes/runtime/BaseModel.php';
include_once 'includes/runtime/Globals.php';
include_once 'includes/runtime/Controller.php';
include_once 'includes/http/Request.php';
global $adb;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['action'] === 'getCounts') {
    $types = ['Юр. лицо', 'Физ. лицо', 'КТЖ'];
    $typeCounts = [];
    foreach ($types as $type) {
        $sql = "SELECT COUNT(*) as cnt
                FROM vtiger_telephone t
                INNER JOIN vtiger_contactdetails cd ON cd.contactid = t.cf_client_name_id
                INNER JOIN vtiger_crmentity vc ON t.telephoneid = vc.crmid AND vc.deleted = 0
                INNER JOIN vtiger_crmentity vc2 ON cd.contactid = vc2.crmid AND vc2.deleted = 0
                WHERE cd.cf_contact_type = ?";
        $res = $adb->pquery($sql, [$type]);
        $typeCounts[$type] = $adb->query_result($res, 0, 'cnt');
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($typeCounts, JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = (int) ($data['userID'] ?? 0);

    $result = [];

    if ($userId) {
        // 1. Получаем все населённые пункты и количество домов
        $localities = [];
        $res = $adb->pquery(
            "SELECT ve.cf_inhabited_locality AS locality, COUNT(*) AS count
             FROM vtiger_estates ve
             INNER JOIN vtiger_crmentity vc ON ve.estatesid = vc.crmid AND vc.deleted = 0
             INNER JOIN vtiger_account va ON va.accountid = ve.cf_municipal_enterprise
             INNER JOIN vtiger_crmentity vc2 ON va.accountid = vc2.crmid
             INNER JOIN vtiger_users2group vug ON vug.groupid = vc2.smownerid
             WHERE vug.userid = ?
             GROUP BY ve.cf_inhabited_locality", [$userId]
        );
        while ($row = $adb->fetchByAssoc($res)) {
            $localities[$row['locality']] = (int) $row['count'];
        }

        // 2. Для каждого населённого пункта получаем улицы и их количество
        foreach ($localities as $locality => $localityCount) {
            $streets = [];
            $res2 = $adb->pquery(
                "SELECT ve.cf_streets AS street, COUNT(*) AS count
                 FROM vtiger_estates ve
                 INNER JOIN vtiger_crmentity vc ON ve.estatesid = vc.crmid AND vc.deleted = 0
                 INNER JOIN vtiger_account va ON va.accountid = ve.cf_municipal_enterprise
                 INNER JOIN vtiger_crmentity vc2 ON va.accountid = vc2.crmid
                 INNER JOIN vtiger_users2group vug ON vug.groupid = vc2.smownerid
                 WHERE vug.userid = ? AND ve.cf_inhabited_locality = ?
                 GROUP BY ve.cf_streets", [$userId, $locality]
            );
            while ($row2 = $adb->fetchByAssoc($res2)) {
                $streets[] = [
                    'name' => $row2['street'],
                    'count' => (int) $row2['count']
                ];
            }

            // 3. (опционально) фильтруем улицы через picklist_dependency, если нужно

            $result[] = [
                'locality' => $locality,
                'count' => $localityCount,
                'streets' => $streets
            ];
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}