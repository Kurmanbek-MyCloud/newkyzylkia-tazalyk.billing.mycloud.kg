<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Bishkek');
require_once 'DataBase.php';
require_once 'MyLogger.php';
require_once 'Meters.php';
require_once 'CRM.php';
$logger = new MyLogger('meters.log');

try {
	$dbConn = DataBase::getConn();
	$meter = new Meters();
	$CRM = new CRM();

	$response = $meter->run();


	if ($response[0] == 'check_meter') {
		if (is_array($response[1])) {
			$filteredData = array();
			if ($response[1] == []) {
				print json_encode(array('success' => true, 'message' => 'По данному лицевому счету счетчиков нет', 'result' => 1));
			} else {
				foreach ($response[1] as $item) {
					$filteredData[] = array(
						'meter' => $item['meter_number'],
						'last_readings' => $item['vms_data'],
						'last_readings_date' => $item['cf_1325'],
						'lastname' => $item['lastname'],
						'addres' => $item['addres'],
					);
				}
				$json = $filteredData;
				print json_encode(array('success' => true, 'debt' => $response[1][0]['debt'], 'data' => $json, 'result' => 0));
			}
		} else {
			print json_encode(array('success' => false, 'comment' => 'Ошибка декодирования', 'result' => 2));
		}
	} else if ($response[0] == 'add_meterdata') {
		print json_encode($response[1]);
	} else if ($response[0] == 'get_metersdata') {
		if ($response[1] == []) {
			print json_encode(array('success' => true, 'message' => 'По данному счетчику показания отсутствуют', 'result' => 1));
		} else {
			$json = $response[1];
			print json_encode(array('success' => true, 'data' => $json, 'result' => 0));
		}
	} else if (is_array($response)) {
		$a = $response['amount'];
		$amount = sprintf("%.2f", $a);
		if ((is_int($response['cf_txnid']) || ctype_digit($response['cf_txnid'])) && $response['cf_txnid'] >= 0) {
			$logger->log($response['command'] . " сумма " . $amount . " номер платежа " . $response['cf_txnid']);
			print json_encode(array('success' => true, 'txn_id' => $response['cf_txnid'], 'sum' => $amount, 'comment' => $response['command'], 'result' => 0));
		} else {
			print json_encode(array('success' => true, 'comment' => $response, 'result' => 0));
		}
	} else {
		$logger->log($response);
		print json_encode(array('success' => true, 'comment' => $response, 'result' => 0));
	}
	$dbConn->close();
} catch (Exception $e) {

	$message = $e->getMessage();
	$logger->log($message);

	if (strpos($message, 'Абонента с данным лицевым счетом не существует') === false) {
		$status = 2;
	} else {
		$message = 'Абонент не найден';
		$status = 1;
	}

	print json_encode(array('success' => false, 'message' => $message, 'result' => $status));
}
exit();