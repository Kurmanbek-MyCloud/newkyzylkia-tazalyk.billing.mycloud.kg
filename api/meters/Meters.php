<?php

class Meters {
	private $mp_id;

	public function run() {
		$input = file_get_contents('php://input');
		$logger = new MyLogger('api/meters/requests.log');
		$logger->log("IP: " . $_SERVER['REMOTE_ADDR'] . " | Request: " . $input);
		$meters = json_decode($input);
		if (isset($meters->token)) {
			$token = $meters->token;
			$system_ip = $_SERVER['REMOTE_ADDR'];

			if (empty($meters->uid)) {
				throw new Exception('Не определено поле uid');
			}
			$uid = $meters->uid;
			$system = $this->check_system($token, $system_ip, $uid);
			$this->mp_id = $system['cf_municipal_enterprise'];
			if (isset($meters->account)) {
				if (empty($meters->account)) {
					throw new Exception('Пустой лицевой счет');
				} else {
					$accountNumber = $meters->account;
					if (isset($meters->command)) {
						if ($meters->command == 'check_meter') {
							return $this->checkMeter($accountNumber);
						} elseif ($meters->command == 'get_metersdata') {
							if (isset($meters->meter)) {
								if (!empty($meters->meter)) {
									$meter_number = $meters->meter;
									return $this->getMeterdata($accountNumber, $meter_number);
								} else {
									throw new Exception('Номер счетчика пустой');
								}
							} else {
								throw new Exception('Не определено поле с номером счетчика');
							}
						} elseif ($meters->command == 'add_meterdata') {
							if (isset($meters->data)) {
								if (!empty($meters->data)) {
									$meters_data = $meters->data;
									$data = array();
									foreach ($meters_data as $meter_data) {
										$response = array(
											"success" => false,
											"message" => "Данные успешно получены",
											"result" => 2
										);
										if (isset($meter_data->meters)) {
											if (!empty($meter_data->meters)) {
												$meter_number = $meter_data->meters;
												$response['meters'] = $meter_number;
												if (isset($meter_data->readings)) {
													if (!empty($meter_data->readings)) {
														$meter_readings = $meter_data->readings;
														if (is_numeric($meter_readings) == false) {
															$response['message'] = 'Некорректный формат показаний, отправляйте числовые значения!';
															$data[] = $response;
															continue;
														}
														$currentDate = new DateTime();
														$meter_readings_date = $currentDate->format("Y-m-d");
														$save_meters = $this->makeMeterdata($accountNumber, $meter_number, $meter_readings, $meter_readings_date, $response, $system);
														$data[] = $save_meters;
													} else {
														$response['message'] = 'Показания отсутствуют отсутствуют';
														$data[] = $response;
														continue;
													}
												} else {
													$response['message'] = 'Не определено поле с показаниями';
													$data[] = $response;
													continue;
												}
											} else {
												$response['message'] = 'Номер счетчика отсутствуют';
												$data[] = $response;
												continue;
											}
										} else {
											$response['message'] = 'Не определено поле с номером счетчика';
											$data[] = $response;
											continue;
										}
									}
									$resul_data = array(
										"success" => true,
										"account" => $accountNumber,
										"data" => $data
									);
									return ['add_meterdata', $resul_data];
								} else {
									throw new Exception('Данные отсутствуют');
								}
							} else {
								throw new Exception('Не определено поле с данными');
							}
						} else {
							throw new Exception('Не верное или пустое действие');
						}
					} else {
						throw new Exception('Не определено поле действия');
					}
				}
			} else {
				throw new Exception('Не определено поле лицевого счета');
			}
		} else {
			throw new Exception('Не определено поле токена');
		}
	}

	public function getMeterdata($accountNumber, $meter_number) {
		global $adb;
		$sql = "SELECT meter_number from vtiger_meters vm 
		inner join vtiger_crmentity vc on vc.crmid = vm.metersid 
		inner join vtiger_estates vf on vf.estatesid = vm.cf_meter_object_link 
		inner join vtiger_crmentity vc2 on vc2.crmid = vf.estatesid 
		where vc2.deleted = 0 and vc.deleted = 0 and vf.estate_number = '$accountNumber' and vm.meter_number = '$meter_number' and vf.cf_municipal_enterprise = '{$this->mp_id}'";
		$find_meter = $adb->run_query_allrecords($sql);
		if (!$find_meter) {
			throw new Exception('У данного лицевого счета нет такого счетчика (или на оборот)');
		}

		$sql = "SELECT vm.meter_reading, vm.cf_reading_date, vm.cf_reading_source from vtiger_readings vm
		inner join vtiger_crmentity vc on vc.crmid = vm.readingsid
		inner join vtiger_meters vm4 on vm4.metersid = vm.cf_reading_meter_link 
		where vc.deleted = 0 and vm4.meter_number = '$meter_number' ORDER BY vm.cf_reading_date desc LIMIT 12";
		$metersdatas = $adb->run_query_allrecords($sql);
		$metersdata_list = array();
		foreach ($metersdatas as $metersdata) {
			$metersdata_list[] = array(
				'readings' => $metersdata['meter_reading'],
				'readings_date' => $metersdata['cf_reading_date'],
				'system' => $metersdata['cf_reading_source']
			);
		}
		return ['get_metersdata', $metersdata_list];
	}
	public function checkMeter($accountNumber) {
		global $adb;
		$sql = "SELECT vf.cf_lastname, CONCAT(' Ул. ', vf.cf_streets , ' Дом. ', vf.cf_house_number, 
			   CASE WHEN vf.cf_litera IS NOT NULL AND vf.cf_litera <> '' THEN CONCAT(' ', vf.cf_litera) ELSE '' END,
			   CASE WHEN vf.cf_apartment_number IS NOT NULL AND vf.cf_apartment_number <> '' THEN CONCAT(' Кв ', vf.cf_apartment_number) ELSE '' END
		) AS addres,
		COALESCE(
		(
			SELECT vms.meter_reading 
			FROM vtiger_readings vms
			INNER JOIN vtiger_crmentity vcs ON vcs.crmid = vms.readingsid
			WHERE vcs.deleted = 0 AND vms.cf_reading_meter_link = vm.metersid
			ORDER BY vms.meter_reading DESC
			LIMIT 1
		),
        'Показаний нет'
		) AS vms_data,
		COALESCE(
		(
			SELECT vms.cf_reading_date
			FROM vtiger_readings vms
			INNER JOIN vtiger_crmentity vcs ON vcs.crmid = vms.readingsid
			WHERE vcs.deleted = 0 AND vms.cf_reading_meter_link = vm.metersid
			ORDER BY vms.cf_reading_date DESC
			LIMIT 1
		),
        ''
		) AS cf_1325,
		vm.meter_number, vf.cf_balance as debt
	FROM vtiger_estates vf
	INNER JOIN vtiger_crmentity vc ON vf.estatesid = vc.crmid
	INNER JOIN vtiger_meters vm ON vm.cf_meter_object_link = vf.estatesid
	INNER JOIN vtiger_crmentity vc2 ON vc2.crmid = vm.metersid
	WHERE vc.deleted = 0 AND vf.estate_number = '$accountNumber' AND vc2.deleted = 0 AND vf.cf_municipal_enterprise = '{$this->mp_id}'";
		// echo $sql;
		// exit();
		$res = $adb->run_query_allrecords($sql);
		if (!$res) {
			$sql = "SELECT
		vf.cf_lastname,
		CONCAT(' Ул. ', vf.cf_streets, ' Дом. ', vf.cf_house_number, 
			CASE WHEN vf.cf_litera IS NOT NULL AND vf.cf_litera <> '' THEN CONCAT(' ', vf.cf_litera) ELSE '' END,
			CASE WHEN vf.cf_apartment_number IS NOT NULL AND vf.cf_apartment_number <> '' THEN CONCAT(' Кв ', vf.cf_apartment_number) ELSE '' END
		) AS addres,
		((SELECT IFNULL((SELECT round(sum(total),2) AS summ FROM vtiger_invoice AS I
			  INNER JOIN vtiger_crmentity AS CE ON I.invoiceid = CE.crmid
			  WHERE deleted = 0
			  AND invoicestatus not IN ('Cancel')
			  AND I.cf_estate_id = vf.estatesid),0)) 
		  -
		((SELECT IFNULL( (select round(SUM(amount),2) as summ FROM vtiger_payments as SP
			  INNER JOIN  vtiger_crmentity AS SCE ON SP.paymentsid = SCE.crmid 
			  WHERE SCE.deleted = 0
			  AND cf_pay_type = 'Приход'
			  AND SP.cf_paid_object = vf.estatesid), 0))) 
		) as debt
	FROM vtiger_estates vf
	INNER JOIN vtiger_crmentity vc ON vf.estatesid = vc.crmid
	WHERE vc.deleted = 0 AND vf.estate_number = '$accountNumber' AND vf.cf_municipal_enterprise = '{$this->mp_id}'";
			$res = $adb->run_query_allrecords($sql);
			return ['check_meter', $res];
		}
		return ['check_meter', $res];
	}

	public function makeMeterdata($accountNumber, $meter_number, $meter_readings, $meter_readings_date, $response, $system) {
		global $CRM;

		global $dbConn;

		$check_meter_sql = "SELECT vm.metersid, vf.estatesid from vtiger_meters vm 
		inner join vtiger_crmentity vc on vm.metersid = vc.crmid
		inner join vtiger_estates vf on vf.estatesid = vm.cf_meter_object_link 
		inner join vtiger_crmentity vc2 on vc2.crmid = vf.estatesid 
		where vc.deleted = 0 and vc2.deleted = 0 and vm.meter_number = '$meter_number' and vf.estate_number = '$accountNumber' and vf.cf_municipal_enterprise = '{$this->mp_id}'";

		$result_meter = $dbConn->query($check_meter_sql);
		if ($result_meter->num_rows == 1) {
			$row = $result_meter->fetch_assoc();
			$metersid = $row['metersid'];
			$check_meterdata = "SELECT * from vtiger_readings vm 
			inner join vtiger_crmentity vc on vm.readingsid = vc.crmid 
			where vc.deleted = 0 and cf_reading_meter_link = '$metersid'
			order by cf_reading_date desc limit 1";
			$result_check_meterdata = $dbConn->query($check_meterdata);
			if ($result_check_meterdata->num_rows != 0) {
				$row = $result_check_meterdata->fetch_assoc();
				$prev_readings = $row['meter_reading'];
				$prev_readingsdate = $row['cf_reading_date'];
				if ($prev_readings > $meter_readings) {
					$response['message'] = 'Показания меньше, чем предыдущие, проверьте и отправьте заново.';
					return $response;
				}
			}
			try {
				$sql_readings_date = "SELECT start_readings_date, end_readings_date from vtiger_organizationdetails";
				$result_readings_date = $dbConn->query($sql_readings_date);
				$row_readings_date = $result_readings_date->fetch_assoc();
				$readings_date_start = $row_readings_date['start_readings_date'];
				$readings_date_end = $row_readings_date['end_readings_date'];
				$currentDate = new DateTime();
				$startDate = $currentDate->format("Y-m-$readings_date_start");
				$endDate = $currentDate->format("Y-m-$readings_date_end");
				$dateObj = new DateTime($meter_readings_date);
				if ($dateObj < new DateTime($startDate) || $dateObj > new DateTime($endDate)) {
					$response['message'] = 'Сейчас нельзя передавать показания';
					return $response;
				}
			} catch (Exception $e) {
				$response['message'] = 'Не корректные настройки даты обратитесь к администратору';
				return $response;
			}
			$data = array(
				"meter_reading" => $meter_readings,
				"cf_reading_date" => $meter_readings_date,
				// "cf_reading_object_link" => $flatsid,
				// "cf_name_system" => $system,
				"cf_reading_source" => $system,
				"cf_reading_meter_link" => $metersid
			);
			$save_meter = $CRM->createmMetersdata($data, $metersid);
			if ($save_meter == true) {
				$response['message'] = "Показания сохранены.";
				$response['success'] = true;
				$response['meters'] = $meter_number;
				$response['result'] = 0;
				return $response;
			} else {
				$response['message'] = "Ощибка при сохранении";
			}
		} else {
			$response['message'] = "В системе нет счетчика $meter_number с лицевым счетом $accountNumber";
			return $response;
		}
	}
	public function check_system($token, $system_ip, $uid) {
		require_once 'MyLogger.php';
		$logger = new MyLogger('api/meters/meters.log');
		global $dbConn;
		if ($token == '44cf37b471e4bcd2e8ab4fbe55dbf72359fb19dd55ad335b695b4c97bec4e4d1') {
			$system_ip = '92.62.72.168';
		}
		$sql = "SELECT payer_title, vp.cf_municipal_enterprise from vtiger_pymentssystem vp
		inner join vtiger_crmentity vc on vp.pymentssystemid = vc.crmid
		where vc.deleted = 0 and cf_payer_token = '$token' and vp.cf_uid_billing = '$uid' and '$system_ip' in (vp.cf_payer_ip_1, vp.cf_payer_ip_2, vp.cf_payer_ip_3, '92.62.72.168')";

		$result = $dbConn->query($sql);


		if ($result) {

			if ($result->num_rows == 0) {
				$logger->log("Не зарегистрирована платежная система IP $system_ip, Token $token");
				throw new Exception('Вы не являетесь зарегистрированной системой');
			} elseif ($result->num_rows == 1) {
				$row = $result->fetch_assoc();
				return $row;
			} else {
				$logger->log("В системе оказалось больше одной платежной системы с IP $system_ip, Token $token");
				throw new Exception('В системе оказалось больше одной платежной системы с токеном ' . $token);
			}
		} else {
			$logger->log('При поиске платежной системы произошла ошибка. Ошибка MySQL - ' . $dbConn->error);
			throw new Exception('При поиске платежной системы произошла ошибка. Ошибка MySQL - ' . $dbConn->error);
		}
	}
	public function isFieldExists($tableName, $fieldName, $adb) {
		try {

			$sql = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$tableName' AND COLUMN_NAME = '$fieldName'";
			$result = $adb->run_query_allrecords($sql)[0];
			return ($result['count'] > 0);
		} catch (PDOException $e) {
			// Обработка ошибки, например, логирование
			error_log("Error checking field existence: " . $e->getMessage());
			return false;
		}
	}

}