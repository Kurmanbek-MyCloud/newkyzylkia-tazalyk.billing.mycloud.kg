<?php
chdir('../../');
require_once 'user_privileges/user_privileges_1.php';
require_once 'includes/main/WebUI.php';
require_once 'include/utils/utils.php';
require_once 'vtlib/Vtiger/Module.php';


$current_user = Users::getActiveAdminUser();
$user = Users_Record_Model::getCurrentUserModel();



class CRM
{
	public function createRequest($accountNumber, $phone, $description)
	{

		try {

			global $adb;
			$request = Vtiger_Record_Model::getCleanInstance("HelpDesk");
			$request->set('ticket_title', $accountNumber);
			$request->set('ticketstatus', 'Open');
			$request->set('ticketcategories', 'Инцидент');
			$request->set('ticketpriorities', 'Normal');
			$request->set('description', $description);
			$request->set('cf_1464', $phone);
			$request->set('assigned_user_id', '1');
			$request->set('mode', 'create');
			$request->save();

			$dataId = $request->getId();
			
			if ($dataId != null) {
				return $dataId;
			} else {
				return false;
			}
		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}
	}
}

?>