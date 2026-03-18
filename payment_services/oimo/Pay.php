<?php
class Pay {
	private $mp_id;

	public function run() {
		$payment = $this->getPaymentData();
		$pay_system = $this->checkSystem($payment);

		switch ($payment->command) {
			case 'check':
				$this->validateCheck($payment);
				return $this->checkAccount($payment);
			case 'pay':
				$this->validatePayment($payment);
				return $this->makePayment($payment, $pay_system);
			case 'show_service':
				return $this->showService();
			case 'check_pay_status':
				return $this->checkPaymentStatus($payment);
			default:
				throw new PaymentException('Неверная команда');
		}
	}

	private function getPaymentData() {
		$input = file_get_contents('php://input');
		$logger = new MyLogger('payment_services/oimo/requests.log');
		$logger->log("IP: " . $_SERVER['REMOTE_ADDR'] . " | Request: " . $input);
		$payment = json_decode($input);
		if (empty($payment->token)) {
			throw new PaymentException('Не определено поле токена');
		}

		if (empty($payment->command)) {
			throw new PaymentException('Не определено поле команды');
		}

		return $payment;
	}
	private function checkSystem($payment) {

		$payment_token = $payment->token;
		$system_ip = $_SERVER['REMOTE_ADDR'];

		return $this->check_system($payment_token, $system_ip);
	}
	private function validateCheck($payment) {
		$this->validateProperty($payment, 'account', 'Пустой лицевой счет');
	}

	private function validatePayment($payment) {
		$this->validateProperty($payment, 'txn_id', 'Индентификатор платежа пустой');
		$this->validateProperty($payment, 'txn_date', 'Не определено поле даты');
		$this->validateProperty($payment, 'sum', 'Не определено поле суммы');
		$this->validateProperty($payment, 'service', 'Не определен тип оплачиваемой услуги');
		$this->validateProperty($payment, 'account', 'Пустой лицевой счет');

		if (strtotime($payment->txn_date) === false) {
			throw new PaymentException("Неправильный формат даты");
		}
	}

	private function validateProperty($payment, $property, $message) {
		if (empty($payment->$property)) {
			throw new PaymentException($message);
		}
	}
	private function makePayment($payment, $pay_system) {

		$this->checkDuplicatePayment($payment->txn_id);

		$estate_data = $this->checkAccount($payment);
		$data = $this->preparePaymentData($payment, $pay_system, $estate_data['paid_object']);

		return $this->processPayment($data);
	}

	private function checkDuplicatePayment($txn_id) {
		global $dbConn;
		$sql = "SELECT p.cf_txnid FROM vtiger_payments p
            INNER JOIN vtiger_crmentity vc ON p.paymentsid = vc.crmid 
            WHERE vc.deleted = 0 AND p.cf_txnid = ?";

		$stmt = $dbConn->prepare($sql);
		if (!$stmt) {
			throw new DatabaseException('Ошибка подготовки запроса: ' . $dbConn->error);
		}
		$stmt->bind_param('s', $txn_id);
		$stmt->execute();
		$result = $stmt->get_result();

		if ($result->num_rows > 0) {
			throw new PaymentException('В системе уже есть оплата с данным идентификатором платежа. Txn_id - ' . $txn_id);
		}
		$stmt->close();
	}

	private function preparePaymentData($payment, $pay_system, $paid_object) {
		global $adb;
		$billing_id = $this->getBillingId($payment);
		return array(
			"cf_payment_source" => $pay_system['payer_title'],
			"cf_pay_date" => $payment->txn_date,
			"amount" => $payment->sum,
			"cf_txnid" => $payment->txn_id,
			"cf_paid_object" => $paid_object,
			"assigned_user_id" => $billing_id['group_id']
		);
	}
	private function processPayment($data) {
		global $CRM;
		return $CRM->createPayment($data);
	}

	private function check_system($payment_token, $system_ip) {
		require_once 'MyLogger.php';
		$logger = new MyLogger('payment_services/oimo/payments.log');
		global $dbConn;

		$sql = "SELECT payer_title FROM vtiger_pymentssystem vp 
            INNER JOIN vtiger_crmentity vc ON vp.pymentssystemid = vc.crmid 
            WHERE vc.deleted = 0 AND cf_payer_token = ? AND ? IN (vp.cf_payer_ip_1, vp.cf_payer_ip_2, vp.cf_payer_ip_3, '92.62.72.168')";

		$stmt = $dbConn->prepare($sql);
		if (!$stmt) {
			throw new DatabaseException('Ошибка подготовки запроса: ' . $dbConn->error);
		}
		$stmt->bind_param("ss", $payment_token, $system_ip);
		$stmt->execute();
		$result = $stmt->get_result();

		if ($result->num_rows == 0) {
			throw new DatabaseException("Не зарегистрированная платежная система IP $system_ip, Token $payment_token");
		} elseif ($result->num_rows > 1) {
			throw new DatabaseException('В системе оказалось больше одной платежной системы с токеном - ' . $payment_token . 'IP - ' . $system_ip);
		}
		$data = $result->fetch_assoc();
		$stmt->close();
		return $data;
	}

	private function showService() {
		global $dbConn;

		$sql = "SELECT cf_paid_serviceid as id, cf_paid_service as service  FROM vtiger_cf_paid_service";

		$result = $dbConn->query($sql);

		if ($result) {

			if ($result->num_rows == 0) {
				throw new PaymentException('Услуги отсутствуют');
			} else {
				$rows = array();
				while ($row = $result->fetch_assoc()) {
					$rows[] = $row;
				}
				return $rows;
			}
		} else {
			throw new DatabaseException('При поиске услуг произошла ошибка. Ошибка MySQL - ' . $dbConn->error);
		}
	}
	private function checkAccount($payment) {
		global $dbConn;

		$accountNumber = $payment->account;

		$sql = "SELECT es.estatesid AS paid_object, es.cf_lastname AS lastname, es.cf_balance as debt, es.cf_municipal_enterprise
				FROM vtiger_estates es
				INNER JOIN vtiger_crmentity vc ON es.estatesid = vc.crmid AND vc.deleted = 0
				WHERE es.estate_number = ?";

		$stmt = $dbConn->prepare($sql);
		if (!$stmt) {
			throw new DatabaseException('Ошибка подготовки запроса: ' . $dbConn->error);
		}

		$stmt->bind_param("s", $accountNumber);
		$stmt->execute();
		$result = $stmt->get_result();

		if ($result->num_rows == 0) {
			$stmt->close();
			throw new PaymentException('Абонента с данным лицевым счетом не существует. ЛС - ' . $accountNumber);
		} elseif ($result->num_rows > 1) {
			$stmt->close();
			throw new PaymentException('В системе оказалось больше одного абонента с данным лицевым счетом. ЛС - ' . $accountNumber);
		}

		$row = $result->fetch_assoc();
		$stmt->close();
		$this->mp_id = $row['cf_municipal_enterprise'];
		$lastname = $row['lastname'];
		$debt = round($row['debt'], 2);
		if ($debt < 0) {
			$lastname .= ': Переплата ' . abs($debt) . ' сом';
		} elseif ($debt > 0) {
			$lastname .= ': Задолженность ' . $debt . ' сом';
		}
		$data['action'] = "check";
		$data['comment'] = $lastname;
		$data['paid_object'] = $row['paid_object'];
		return $data;
	}

	public function checkPaymentStatus($payment) {
		global $dbConn;

		if (empty($payment->txn_id)) {
			throw new PaymentException('Индентификатор платежа пустой');
		}
		$sql = "SELECT p.amount, 
				p.cf_status, 
				p.cf_pay_date,
				p.cf_txnid
				FROM vtiger_payments p
				INNER JOIN vtiger_crmentity vc ON p.paymentsid = vc.crmid 
				WHERE vc.deleted = 0
                AND p.cf_txnid = ?";
		$stmt = $dbConn->prepare($sql);
		if (!$stmt) {
			throw new DatabaseException("Ошибка подготовки запроса: " . $dbConn->error);
		}
		$stmt->bind_param("s", $payment->txn_id);
		$stmt->execute();
		$result = $stmt->get_result();

		if ($result->num_rows == 0) {
			throw new PaymentException('Оплата с данным идентификатором платежа не найдена. Txn_id - ' . $payment->txn_id);
		} elseif ($result->num_rows > 1) {
			$stmt->close();
			throw new PaymentException('Обнаружено более одного платежа с таким же идентификатором. Txn_id - ' . $payment->txn_id);
		}
		$data = $result->fetch_assoc();
		$stmt->close();

		$data['action'] = "check_pay_status";
		$data['comment'] = "Платеж с данным идентификатором сохранен в системе.";

		return $data;

	}

	public function getBillingId($payment) {
		global $dbConn;

		$sql = "SELECT vp.cf_municipal_enterprise, vc.smownerid as group_id FROM vtiger_pymentssystem vp
				INNER JOIN vtiger_crmentity vc ON vp.pymentssystemid = vc.crmid AND vc.deleted = 0
				WHERE vp.cf_payer_token = ? AND vp.cf_municipal_enterprise = ?";
		$stmt = $dbConn->prepare($sql);
		if (!$stmt) {
			throw new DatabaseException("Ошибка подготовки запроса: " . $dbConn->error);
		}
		$stmt->bind_param("ss", $payment->token, $this->mp_id);
		$stmt->execute();
		$result = $stmt->get_result();
		if ($result->num_rows == 0) {
			throw new PaymentException('Платежная система с данным токеном не найдена. Token - ' . $payment->token);
		}
		$row = $result->fetch_assoc();
		$stmt->close();
		return [
			'cf_municipal_enterprise' => $row['cf_municipal_enterprise'],
			'group_id' => $row['group_id']
		];
	}

}
