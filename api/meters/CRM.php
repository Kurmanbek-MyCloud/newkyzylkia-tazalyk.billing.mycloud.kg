<?php
chdir('../../');
require_once 'user_privileges/user_privileges_1.php';
require_once 'includes/main/WebUI.php';
require_once 'include/utils/utils.php';
require_once 'vtlib/Vtiger/Module.php';


$current_user = Users::getActiveAdminUser();
$user = Users_Record_Model::getCurrentUserModel();



class CRM {
	public function createmMetersdata($data, $metersid) {

		try {

			global $adb;
			$metersdata = Vtiger_Record_Model::getCleanInstance("MetersData");
			$metersdata->set('assigned_user_id', '1');
			foreach ($data as $key => $value) {
				$metersdata->set($key, $value);
			}

			$metersdata->set('mode', 'create');
			$metersdata->save();

			$dataId = $metersdata->getId();

			if ($dataId != null) {
				$currentDate = date('Y-m-d');
				$year = date('Y', strtotime($currentDate));
				$month = date('m', strtotime($currentDate));
				$sql = " UPDATE vtiger_readings vm 
				inner join vtiger_crmentity vc on vc.crmid = vm.readingsid 
				set vc.deleted = 1
				where vc.deleted = 0 and cf_reading_meter_link = ? and MONTH(vm.cf_reading_date) = ? and YEAR(vm.cf_reading_date) = ? and vm.readingsid != ?";
				$adb->pquery($sql, array($metersid, $month, $year, $dataId));
				return true;
			} else {
				return false;
			}
		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}
	}
}

?>