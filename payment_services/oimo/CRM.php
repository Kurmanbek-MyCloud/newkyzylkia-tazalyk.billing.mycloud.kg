<?php
chdir('../../');
require_once 'user_privileges/user_privileges_1.php';
require_once 'includes/main/WebUI.php';
require_once 'include/utils/utils.php';
require_once 'vtlib/Vtiger/Module.php';


$current_user = Users::getActiveAdminUser();
$user = Users_Record_Model::getCurrentUserModel();



class CRM {
	public function createPayment($data) {

		global $adb;
		$payments = Vtiger_Record_Model::getCleanInstance("Payments");
		$payments->set('cf_payment_type', 'Безналичный расчет');
		$payments->set('cf_pay_type', 'Приход');
		$payments->set('cf_status', 'Выполнен');
		foreach ($data as $key => $value) {
			$payments->set($key, $value);
		}

		$maxAttempts = 3;
		$attempt = 0;
		$success = false;
		$lastError = '';
		while ($attempt < $maxAttempts) {
			try {
				$payments->set('mode', 'create');
				$payments->save();
				$dataId = $payments->getId();
				if ($dataId) {
					$success = true;
					break;
				}
			} catch (Exception $e) {
				$lastError = $e->getMessage();
			}
			$attempt++;
			usleep(200000 * ($attempt + 1));
		}

		if ($success) {
			return [
				'cf_txnid' => $data['cf_txnid'],
				'amount' => $data['amount'],
				'comment' => "Платеж успешно сохранен в системе.",
				'action' => "pay"
			];
		} else {
			// если не удалось после всех попыток — кидаем PaymentException, чтобы main.php обработал и залогировал
			throw new PaymentException('Платеж ' . $data['cf_txnid'] . ' не создан из-за временной ошибки: ' . $lastError);
		}
	}
}

?>