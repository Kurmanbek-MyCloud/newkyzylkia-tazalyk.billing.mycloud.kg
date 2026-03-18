<?php
/*+********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ******************************************************************************* */

class Floor_Model {
	static function listAll() {
		global $adb;
		$floors = [];

		$floors_sql_result = $adb->pquery("SELECT * FROM vtiger_floor vf 
						INNER JOIN vtiger_crmentity vc ON vc.crmid = vf.floorid 
						WHERE vc.deleted = 0", []);


		$attachments_sql = "SELECT * 
			FROM vtiger_seattachmentsrel 
			JOIN vtiger_attachments ON vtiger_seattachmentsrel.attachmentsid = vtiger_attachments.attachmentsid
			WHERE crmid = ?";
		while ($floor = $adb->fetchByAssoc($floors_sql_result)) {
			$floor_plan_result = $adb->pquery($attachments_sql, [$floor['floorid']]);
			$floor_plan_info = null;
			while ($info = $adb->fetchByAssoc($floor_plan_result)) {
				if ($info)
					$floor_plan_info = $info;
			}
			echo "<pre>";
			var_dump($floor_plan_info);
			echo "</pre>";
			// exit();

			$floor['floor_plan'] = $floor_plan_info['path'] . $floor_plan_info['attachmentsid'] . '_' . $floor_plan_info['storedname'];
			// $floor['floor_plan'] = $floor_plan_info['path'] . $floor_plan_info['attachmentsid'] . '_' . $floor_plan_info['name'];

			// echo '<pre>';
			// var_dump($floor['floor_plan']);
			// echo '</pre>';
			// exit();
			$floors[] = $floor;

			$spaces = [];

			$spaces_sql = "SELECT * FROM vtiger_crmentity AS CRM INNER JOIN vtiger_space AS S ON S.spaceid=CRM.crmid WHERE CRM.deleted=0 AND  S.floor_number = " . $floor['floorid'];
			$spaces_result = $adb->pquery($spaces_sql);

			while ($space = $adb->fetchByAssoc($spaces_result, -1)) {
				$organization_logo_result = $adb->pquery($attachments_sql, [$space['spaceid']]);
				$organization_logo_info = null;
				$space_name = $space['organization_name'];

				while ($info = $adb->fetchByAssoc($organization_logo_result, -1)) {
					if ($info)
						$organization_logo_info = $info;
				}

				$space['organization_logo'] = $organization_logo_info['path'] . $organization_logo_info['attachmentsid'] . '_' . $organization_logo_info['name'];

				$office_sql = "SELECT es.estatesid as id, 
								es.cf_object_name  as 'name', 
								es.cf_estate_status 'status', 
								ROUND(es.cf_estate_area) as 'area', 
								ROUND(es.cf_rent_price) as 'price',
								concat(cd.firstname,' ',cd.lastname) AS contact,
								cd.phone as 'renter_phone' 
								FROM vtiger_estates es
								INNER JOIN vtiger_contactdetails cd ON es.cf_contact_id = cd.contactid  
								INNER JOIN vtiger_crmentity vc ON vc.crmid = es.estatesid 
								WHERE vc.deleted = 0
								AND es.estatesid = $space_name";
				$office_sql_result = $adb->pquery($office_sql);
				// echo '<pre>';
				// var_dump($office_sql_result);
				// echo '</pre>';
				// exit();

				while ($office = $adb->fetchByAssoc($office_sql_result, -1)) {
					$id = $office['id'];
					$atach = $adb->pquery("SELECT *
					FROM vtiger_seattachmentsrel 
					JOIN vtiger_attachments ON vtiger_seattachmentsrel.attachmentsid = vtiger_attachments.attachmentsid
					INNER JOIN vtiger_estates AS O 
					WHERE crmid = O.estatesid 
					");
					$logo_result = null;
					while ($logo = $adb->fetchByAssoc($atach, -1)) {
						$logo_result[] = $logo;

					}
					if ($office['id'] === $space_name) {
						$space['logo'] = $logo_result;
						$space['office_info'] = $office;
						$space['new_status'] = $office['status'];
					}
				}
				$spaces[] = $space;
			}
			echo "<pre>";
			var_dump(count($floors));
			echo "</pre>";
			// exit();

			$last_floors_index = count($floors);
			$floors[$last_floors_index]['spaces'] = $spaces;
		}
		// echo "<pre>";
		// var_dump($floors);
		// echo "</pre>";
		// exit();
		return $floors;
	}

	static function getById($floor_id) {
		global $adb, $log;

		$sql = "SELECT * FROM vtiger_floor WHERE floorid = $floor_id";
		$result = $adb->pquery($sql, array());

		return $adb->fetch_array($result);
	}
}
