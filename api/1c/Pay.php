<?php
class Pay {
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
			case 'GET_PAYMENTS_DETAILS':
					if (empty($payment->StartDate) || empty($payment->EndDate)) {
						throw new PaymentException('Не переданы даты');
					}
				return $this->detailedPayment($payment->StartDate, $payment->EndDate);	
			case 'GET_TOTAL_PAYMENTS':
					if (empty($payment->StartDate) || empty($payment->EndDate)) {
						throw new PaymentException('Не переданы даты');
					}
					return $this->getPayments($payment->StartDate, $payment->EndDate);	
			default:
				throw new PaymentException('Неверная команда');
		}
	}

	private function getPaymentData() {
		$input = file_get_contents('php://input');
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
		$this->validateProperty($payment, 'service', 'Не определен тип оплачиваемой услуги');
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

		$type_system = $this->getServiceType($payment->service);

		$this->checkDuplicatePayment($payment->txn_id);

		$estate_data = $this->checkAccount($payment);
		$data = $this->preparePaymentData($payment, $pay_system, $type_system, $estate_data['paid_object']);

		return $this->processPayment($data);
	}
	private function getServiceType($service_id) {
		global $dbConn;
		$sql = "SELECT cf_paid_service FROM vtiger_cf_paid_service WHERE cf_paid_serviceid = ?";
		$stmt = $dbConn->prepare($sql);
		if (!$stmt) {
			throw new DatabaseException("Ошибка подготовки запроса: " . $dbConn->error);
		}
		$stmt->bind_param("i", $service_id);
		$stmt->execute();
		$result = $stmt->get_result();

		if ($result->num_rows === 0) {
			throw new PaymentException("Указанная услуга не найдена");
		}
		$row = $result->fetch_assoc();
		return $row['cf_paid_service'];
	}

	private function detailedPayment($startDate, $endDate) {
		global $dbConn;
	
		// Физ. лицо: Безналичный расчет
		$sql1 = "SELECT vp.cf_pay_date, vp.amount, vp.cf_payment_source, vp.cf_pay_no, ve.estate_number
				 FROM vtiger_payments vp
				 INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.paymentsid 
				 INNER JOIN vtiger_estates ve ON ve.estatesid = vp.cf_paid_object 
				 WHERE vc.deleted = 0 AND vp.cf_paid_service = 'Электроэнергия'
				   AND vp.cf_pay_date BETWEEN ? AND ? 
				   AND ve.cf_object_type = 'Физ. лицо'
				   AND vp.cf_payment_type = 'Безналичный расчет'
				 ORDER BY vp.cf_payment_source ASC";
	
		$stmt1 = $dbConn->prepare($sql1);
		$stmt1->bind_param("ss", $startDate, $endDate);
		$stmt1->execute();
		$result1 = $stmt1->get_result();
	
		$cashless_physical = [];
		while ($row1 = $result1->fetch_assoc()) {
			$cashless_physical[] = [
				'pay_date'       => $row1['cf_pay_date'],
				'account'       => $row1['estate_number'],
				'amount'        => (float)$row1['amount'],
				'pay_type'      => 'Приход',
				'payment_source'=> $row1['cf_payment_source'],
				'status'        => 'Выполнен',
				'pay_no'        => (int)$row1['cf_pay_no'],
			];
		}
		$stmt1->close();
	
		// Физ. лицо: Наличный расчет
		$sql2 = "SELECT vp.cf_pay_date, vp.amount, vu.id, CONCAT(vu.first_name, ' ', vu.last_name) AS full_name, vp.cf_pay_no, ve.estate_number
				 FROM vtiger_payments vp
				 INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.paymentsid 
				 INNER JOIN vtiger_estates ve ON ve.estatesid = vp.cf_paid_object 
				 INNER JOIN vtiger_crmentity vc2 ON vc2.crmid = ve.estatesid 
				 INNER JOIN vtiger_users vu ON vu.id = vc2.smownerid 
				 WHERE vc.deleted = 0 AND vp.cf_paid_service = 'Электроэнергия'
				   AND vp.cf_pay_date BETWEEN ? AND ? 
				   AND ve.cf_object_type = 'Физ. лицо'
				   AND vp.cf_payment_type = 'Наличные'";
	
		$stmt2 = $dbConn->prepare($sql2);
		$stmt2->bind_param("ss", $startDate, $endDate);
		$stmt2->execute();
		$result2 = $stmt2->get_result();
	
		$cash_physical = [];
		while ($row2 = $result2->fetch_assoc()) {
			$cash_physical[] = [
				'pay_date'    => $row2['cf_pay_date'],
				'account'     => $row2['estate_number'],
				'amount'      => (float)$row2['amount'],
				'pay_type'    => 'Приход',
				'controller_id'     => $row2['id'],
				'controller_name'  => $row2['full_name'],
				'status'      => 'Выполнен',
				'pay_no'      => (int)$row2['cf_pay_no'],
			];
		}
		$stmt2->close();
	
		// Юр. лицо: Безналичный расчет
		$sql3 = "SELECT vp.cf_pay_date, vp.amount, vp.cf_payment_source, vp.cf_pay_no, ve.estate_number
				 FROM vtiger_payments vp
				 INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.paymentsid
				 INNER JOIN vtiger_estates ve ON ve.estatesid = vp.cf_paid_object
				 WHERE vc.deleted = 0 AND vp.cf_paid_service = 'Электроэнергия'
				   AND vp.cf_pay_date BETWEEN ? AND ?
				   AND ve.cf_object_type = 'Юр. лицо'
				   AND vp.cf_payment_type = 'Безналичный расчет'
				 ORDER BY vp.cf_payment_source ASC";
	
		$stmt3 = $dbConn->prepare($sql3);
		$stmt3->bind_param("ss", $startDate, $endDate);
		$stmt3->execute();
		$result3 = $stmt3->get_result();
	
		$cashless_legal = [];
		while ($row3 = $result3->fetch_assoc()) {
			$cashless_legal[] = [
				'pay_date'    => $row3['cf_pay_date'],
				'account'       => $row3['estate_number'],
				'amount'        => (float)$row3['amount'],
				'pay_type'      => 'Приход',
				'payment_source'=> $row3['cf_payment_source'],
				'status'        => 'Выполнен',
				'pay_no'        => (int)$row3['cf_pay_no'],
			];
		}
		$stmt3->close();
	
		// Юр. лицо: Наличный расчет
		$sql4 = "SELECT vp.cf_pay_date, vp.amount, vu.id, CONCAT(vu.first_name, ' ', vu.last_name) AS full_name, vp.cf_pay_no, ve.estate_number
				 FROM vtiger_payments vp
				 INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.paymentsid
				 INNER JOIN vtiger_estates ve ON ve.estatesid = vp.cf_paid_object
				 INNER JOIN vtiger_crmentity vc2 ON vc2.crmid = ve.estatesid
				 INNER JOIN vtiger_users vu ON vu.id = vc2.smownerid
				 WHERE vc.deleted = 0 AND vp.cf_paid_service = 'Электроэнергия'
				   AND vp.cf_pay_date BETWEEN ? AND ?
				   AND ve.cf_object_type = 'Юр. лицо'
				   AND vp.cf_payment_type = 'Наличные'";
	
		$stmt4 = $dbConn->prepare($sql4);
		$stmt4->bind_param("ss", $startDate, $endDate);
		$stmt4->execute();
		$result4 = $stmt4->get_result();
	
		$cash_legal = [];
		while ($row4 = $result4->fetch_assoc()) {
			$cash_legal[] = [
				'pay_date'    => $row4['cf_pay_date'],
				'account'     => $row4['estate_number'],
				'amount'      => (float)$row4['amount'],
				'pay_type'    => 'Приход',
				'controller_id'     => $row4['id'],
				'controller_name'  => $row4['full_name'],
				'status'      => 'Выполнен',
				'pay_no'      => (int)$row4['cf_pay_no'],
			];
		}
		$stmt4->close();
	
		return [
			'cashless_physical' => $cashless_physical,
			'cash_physical'     => $cash_physical,
			'cashless_legal'    => $cashless_legal,
			'cash_legal'        => $cash_legal
		];
	}


	private function getPayments($startDate, $endDate) {
		global $dbConn;
	    
        
          $sql1 = "SELECT COUNT(*) AS total_payments, SUM(vp.amount) AS total_amount
          FROM vtiger_payments vp
          INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.paymentsid 
          INNER JOIN vtiger_estates ve ON ve.estatesid = vp.cf_paid_object 
          WHERE vc.deleted = 0 AND vp.cf_paid_service = 'Электроэнергия' AND vp.cf_pay_date BETWEEN ? AND ? 
          AND ve.cf_object_type = 'Физ. лицо' AND vp.cf_payment_type = 'Безналичный расчет'";
        
        $stmt1 = $dbConn->prepare($sql1);
        $stmt1->bind_param("ss", $startDate, $endDate);
        $stmt1->execute();
        $result1 = $stmt1->get_result();
        $row1 = $result1->fetch_assoc();
        $stmt1->close();

	
		$sql2 = "SELECT vp.cf_payment_source AS payment_system, COUNT(*) AS total_payments, SUM(vp.amount) AS total_amount
			FROM vtiger_payments vp
			INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.paymentsid 
			INNER JOIN vtiger_estates ve ON ve.estatesid = vp.cf_paid_object 
			WHERE vp.cf_pay_date BETWEEN ? AND ? AND vc.deleted = 0 AND vp.cf_paid_service = 'Электроэнергия' AND vp.cf_payment_type = 'Безналичный расчет'
			AND ve.cf_object_type = 'Физ. лицо'
			GROUP BY vp.cf_payment_source";
	
		$stmt2 = $dbConn->prepare($sql2);
		$stmt2->bind_param("ss", $startDate, $endDate);
		$stmt2->execute();
		$result2 = $stmt2->get_result();
		$bypaymentgates = [];
		while ($row2 = $result2->fetch_assoc()) {
			// $bypaymentgates[$row2['payment_system']] = "{$row2['total_payments']};{$row2['total_amount']}";


			$bypaymentgates[] = [
				'payment_system' => $row2['payment_system'], 
				'payments_count' => $row2['total_payments'], 
				'total_amount' => $row2['total_amount'],
			];
		}
		$stmt2->close();
	
		$sql3 = "SELECT COUNT(*) AS total_payments, SUM(vp.amount) AS total_amount
			FROM vtiger_payments vp
			INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.paymentsid 
			INNER JOIN vtiger_estates ve ON ve.estatesid = vp.cf_paid_object 
			WHERE vc.deleted = 0 AND vp.cf_paid_service = 'Электроэнергия' AND vp.cf_pay_date BETWEEN ? AND ? AND ve.cf_object_type = 'Физ. лицо'
			AND vp.cf_payment_type = 'Наличные'";
	
		$stmt3 = $dbConn->prepare($sql3);
		$stmt3->bind_param("ss", $startDate, $endDate);
		$stmt3->execute();
		$result3 = $stmt3->get_result();
		$row3 = $result3->fetch_assoc();
		$stmt3->close();
	
		$sql4 = "SELECT CONCAT(vu.first_name, ' ', vu.last_name) AS cashier, 
             COUNT(*) AS total_payments, 
             SUM(vp.amount) AS total_amount,
			  vu.id 
             FROM vtiger_payments vp
             INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.paymentsid 
             INNER JOIN vtiger_estates ve ON ve.estatesid = vp.cf_paid_object 
             INNER JOIN vtiger_users vu ON vu.id  = vc.smownerid 
             WHERE vp.cf_pay_date BETWEEN ? AND ? 
               AND vc.deleted = 0 AND vp.cf_paid_service = 'Электроэнергия'
               AND vp.cf_payment_type = 'Наличные'
               AND ve.cf_object_type = 'Физ. лицо'
             GROUP BY vu.first_name, vu.last_name
 ";
	
		$stmt4 = $dbConn->prepare($sql4);
		$stmt4->bind_param("ss", $startDate, $endDate);
		$stmt4->execute();
		$result4 = $stmt4->get_result();
		$bycashiers = [];
		while ($row4 = $result4->fetch_assoc()) {
			$bycashiers[] = [
				'ID' => $row4['id'], 
				'controller' => $row4['cashier'], 
				'payments_count' => $row4['total_payments'],
				'total_amount' => $row4['total_amount']
			];
		}


		
		$stmt4->close();
	
		$sql5 = "SELECT COUNT(*) AS total_payments, SUM(vp.amount) AS total_amount
			FROM vtiger_payments vp
			INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.paymentsid 
			INNER JOIN vtiger_estates ve ON ve.estatesid = vp.cf_paid_object 
			WHERE vc.deleted = 0 AND vp.cf_paid_service = 'Электроэнергия' AND vp.cf_pay_date BETWEEN ? AND ?  AND ve.cf_object_type = 'Юр. лицо'
			AND vp.cf_payment_type = 'Безналичный расчет'";
	
		$stmt5 = $dbConn->prepare($sql5);
		$stmt5->bind_param("ss", $startDate, $endDate);
		$stmt5->execute();
		$result5 = $stmt5->get_result();
		$row5 = $result5->fetch_assoc();
		$stmt5->close();
	
		$sql6 = "SELECT vp.cf_payment_source AS payment_system, COUNT(*) AS total_payments, SUM(vp.amount) AS total_amount
			FROM vtiger_payments vp
			INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.paymentsid 
			INNER JOIN vtiger_estates ve ON ve.estatesid = vp.cf_paid_object 
			WHERE vp.cf_pay_date BETWEEN ? AND ?  AND vc.deleted = 0 AND vp.cf_payment_type = 'Безналичный расчет'
			AND ve.cf_object_type = 'Юр. лицо'AND vp.cf_paid_service = 'Электроэнергия'
			GROUP BY vp.cf_payment_source";
	
		$stmt6 = $dbConn->prepare($sql6);
		$stmt6->bind_param("ss", $startDate, $endDate);
		$stmt6->execute();
		$result6 = $stmt6->get_result();
		$bypaymentgatesLegal = [];
		while ($row6 = $result6->fetch_assoc()) {
			// $bypaymentgatesLegal[$row6['payment_system']] = "{$row6['total_payments']};{$row6['total_amount']}";

			$bypaymentgatesLegal[] = [
				'payment_system' => $row6['payment_system'], 
				'payments_count' => $row6['total_payments'], 
				'total_amount' => $row6['total_amount'],
			];
		}
		$stmt6->close();



        $sql7 = "SELECT COUNT(*) AS total_payments, SUM(vp.amount) AS total_amount
        FROM vtiger_payments vp
        INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.paymentsid 
        INNER JOIN vtiger_estates ve ON ve.estatesid = vp.cf_paid_object 
        WHERE vc.deleted = 0 AND vp.cf_pay_date BETWEEN ? AND ?  AND ve.cf_object_type = 'Юр. лицо' AND vp.cf_paid_service = 'Электроэнергия'
        AND vp.cf_payment_type = 'Наличные'";
        
        $stmt7 = $dbConn->prepare($sql7);
        $stmt7->bind_param("ss", $startDate, $endDate);
        $stmt7->execute();
        $result7 = $stmt7->get_result();
        $row7 = $result7->fetch_assoc();
        $stmt7->close();
        
        $sql8 = "SELECT CONCAT(vu.first_name, ' ', vu.last_name) AS cashier, 
         COUNT(*) AS total_payments, 
         SUM(vp.amount) AS total_amount,
		 vu.id 
         FROM vtiger_payments vp
         INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.paymentsid 
         INNER JOIN vtiger_estates ve ON ve.estatesid = vp.cf_paid_object 
         INNER JOIN vtiger_users vu ON vu.id  = vc.smownerid 
         WHERE vp.cf_pay_date BETWEEN ? AND ? 
           AND vc.deleted = 0 AND vp.cf_paid_service = 'Электроэнергия'
           AND vp.cf_payment_type = 'Наличные'
           AND ve.cf_object_type = 'Юр. лицо'
         GROUP BY vu.first_name, vu.last_name
        ";
        
        $stmt8 = $dbConn->prepare($sql8);
        $stmt8->bind_param("ss", $startDate, $endDate);
        $stmt8->execute();
        $result8 = $stmt8->get_result();
		$bycashiersLegal = [];
		while ($row8 = $result8->fetch_assoc()) {
			$bycashiersLegal[] = [
				'ID'      => $row8['id'],
				'controller' => $row8['cashier'], // ФИО кассира
				'payments_count' => $row8['total_payments'],
				'total_amount' => $row8['total_amount']
			];
		}


	


	
		
        $stmt8->close();

	
		return [
		'cashless_physical' => [
        'payments_count'       => isset($row1['total_payments']) ? (int) $row1['total_payments'] : 0,
        'total_amount'           => isset($row1['total_amount']) ? (float) $row1['total_amount'] : 0.0,
        'payment_systems' => $bypaymentgates,
	
                        ],
			'cash_physical' => [
				'payments_count' => (int)($row3['total_payments'] ?? 0),
				'total_amount' => (float)($row3['total_amount'] ?? 0.0),
				'bycontroler' => $bycashiers,
			],
			'cashless_legal' => [
				'payments_count' => (int)($row5['total_payments'] ?? 0),
				'total_amount' => (float)($row5['total_amount'] ?? 0.0),
				'payment_systems' => $bypaymentgatesLegal,
			],
		'cash_legal' => [
    'payments_count' => (int)($row7['total_payments'] ?? 0),
    'total_amount' => (float)($row7['total_amount'] ?? 0.0),
    'bycontroler' => $bycashiersLegal,
         ]

		];
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

	private function preparePaymentData($payment, $pay_system, $type_system, $paid_object) {
		global $adb;

		return array(
			"cf_payment_source" => $pay_system['payer_title'],
			"cf_pay_date" => $payment->txn_date,
			"amount" => $payment->sum,
			"cf_txnid" => $payment->txn_id,
			"cf_paid_service" => $type_system,
			"cf_paid_object" => $paid_object
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
            WHERE vc.deleted = 0 AND cf_payer_token = ? AND ? IN (vp.cf_payer_ip_1, vp.cf_payer_ip_2, vp.cf_payer_ip_3, '46.251.204.111')";

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

		$type_system = $this->getServiceType($payment->service);
		$accountNumber = $payment->account;

		switch ($type_system) {
			case 'Электроэнергия':
				$table = 'vtiger_estates es';
				$fields = 'es.estatesid AS paid_object, cf_lastname AS lastname, es.cf_balance as debt';
				$addOn = 'es.estatesid';
				$and = 'es.estate_number = ?';
				break;

			case 'Телефония':
				$table = 'vtiger_contactdetails cd';
				$fields = 'cd.contactid AS paid_object, cd.cf_client_name AS lastname, cd.cf_contact_balance AS debt';
				$addOn = 'cd.contactid';
				$and = 'cd.cf_contract_id = ?';
				break;

			case 'Телеграф':
				$table = 'vtiger_telegraph tg';
				$fields = 'tg.telegraphid AS paid_object, tg.name as lastname, tg.cf_telegraph_balance as debt';
				$addOn = 'tg.telegraphid';
				$and = 'tg.cf_telegraph_number = ?';
				break;
		}

		$sql = "SELECT 
            $fields
            FROM $table
            INNER JOIN vtiger_crmentity vc ON $addOn = vc.crmid
            WHERE vc.deleted = 0
            AND $and";

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

}
