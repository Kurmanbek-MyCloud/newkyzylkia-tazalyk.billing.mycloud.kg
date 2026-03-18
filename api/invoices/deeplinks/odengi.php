<?php

$passw = $password;
$mktime = round(microtime(true) * 1000);
$accountNumber = 'null';
$amount *= 100;


$json = '{"cmd":"createInvoice","version":1005,"sid":"' . $service_id . '","mktime":"' . $mktime . '","lang":"ru","data":{"order_id":"sss","desc":"ddd","amount":' . $amount . ',"currency":"KGS","test":1,"long_term":null,"user_to":' . $accountNumber . ',"fields_other":null,"transtype":null,"result_url":null}}';

// Шифруем JSON запрос алгоритмом HMAC-MD5 с использованием пароля
$hash = hash_hmac('md5', $json, $passw);

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://mw-api-test.dengi.kg/api/json/json.php',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => '{"cmd":"createInvoice","version":1005,"sid":"' . $service_id . '","mktime":"' . $mktime . '","lang":"ru","data":{"order_id":"sss","desc":"ddd","amount":' . $amount . ',"currency":"KGS","test":1,"long_term":null,"user_to":' . $accountNumber . ',"fields_other":null,"transtype":null,"result_url":null},
  "hash": "' . $hash . '"
}

',
  CURLOPT_HTTPHEADER => array(
    'Content-Type: application/json',
    'Cookie: PHPSESSID=t975iqlio3lk854c7u5afkojnp'
  ),
));

$response = curl_exec($curl);
$response_array = json_decode($response, true);

curl_close($curl);
// var_dump($response_array['data']['link_app']);
$link = $response_array['data']['link_app'];




