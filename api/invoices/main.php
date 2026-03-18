<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Bishkek');
require_once 'DataBase.php';
require_once 'MyLogger.php';
require_once 'Invoices.php';
require_once 'CRM.php';
$logger = new MyLogger('invoices_api.log');
require 'config.inc.php';
try {
    $dbConn = DataBase::getConn();
    $invoice = new Invoices();
    $CRM = new CRM();

    $response = $invoice->run();


    if ($response[0] == 'check_invoice') {
        if (is_array($response[1])) {
            $filteredData = array();
            if ($response[1] == []) {
                print json_encode(array('success' => false, 'message' => 'По данному лицевому счету - счетов нет', 'result' => 1));
            } else {
                $json = $response[2];
                print json_encode(array(
                    'success' => true,
                    'name' => $response[1][0]['cf_lastname'],
                    'count_people' => $response[1][0]['cf_1261'],
                    'land' => $response[1][0]['cf_1450'],
                    'street' => $response[1][0]['cf_streets'],
                    'debt' => $response[1][0]['debt'],
                    'personal_account' => $response[1][0]['estate_number'],
                    'invoices' => $json,
                    'result' => 0
                ));
            }
        } else {
            print json_encode(array('success' => false, 'comment' => 'Ошибка декодирования', 'result' => 2));
        }
    } elseif ($response[0] == 'add_request') {
        if ($response[1]) {
            $id = $response[1];
            print json_encode(array('success' => true, 'comment' => "Обрашение сохранено уникальный индентификатор: $id", 'result' => 0));
        } else {
            print json_encode(array('success' => false, 'comment' => 'Ошибка при сохранение данных', 'result' => 2));
        }
    } elseif ($response[0] == 'check_client') {
        if ($response[1]) {
            $id = $response[1];
            print json_encode(array('success' => true, 'comment' => "Абонент найден", 'result' => 0));
        } else {
            print json_encode(array('success' => false, 'comment' => 'Ошибка данных обратитесь к администратору', 'result' => 2));
        }
    } elseif ($response[0] == 'deeplink_list') {
        if ($response[1]) {
            print json_encode(array(
                'success' => true,
                'systems' => $response[1],
                'result' => 0
            ));
        } else {
            print json_encode(array('success' => false, 'comment' => 'Ошибка  обратитесь к администратору', 'result' => 2));
        }
    } elseif ($response[0] == 'get_deeplink') {

        if ($response[1]) {
            print json_encode(array(
                'success' => true,
                'link' => $response[1],
                'result' => 0
            ));
        } else {
            print json_encode(array('success' => false, 'comment' => 'Ошибка  обратитесь к администратору', 'result' => 2));
        }
    } elseif ($response[0] == 'check_payments') {
        if (is_array($response[1])) {
            if ($response[1] == []) {
                print json_encode(array('success' => false, 'message' => 'По данному лицевому счету - платежей нет', 'result' => 1));
            } else {
                $json = $response[2];
                print json_encode(array(
                    'success' => true,
                    'name' => $response[1][0]['lastname'],
                    'personal_account' => $response[1][0]['cf_1420'],
                    'payments' => $json,
                    'result' => 0
                ));
            }
        } else {
            print json_encode(array('success' => false, 'comment' => 'Ошибка декодирования', 'result' => 2));
        }
    } else {
        $logger->log($response);
        print json_encode(array('success' => false, 'comment' => 'Ошибка обратитесь к администратору', 'result' => 2));
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