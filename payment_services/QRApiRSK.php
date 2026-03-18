<?php

class QRApiRSK {
    private $baseUrl;
    private $apiKey;

    public function __construct() {
        global $adb;
        
        $result = $adb->run_query_allrecords(
            "SELECT vp.cf_qr_url, vp.cf_payer_token 
            FROM vtiger_pymentssystem vp 
            INNER JOIN vtiger_crmentity vc ON vp.pymentssystemid = vc.crmid 
            WHERE vc.deleted = 0 
            AND vp.cf_qr_check = 'Подключен'
            AND vp.payer_title = 'Элдик Банк'
            LIMIT 1"
        );

        if (empty($result)) {
            throw new Exception('Элдик Банк: настройки QR не найдены в базе данных');
        }

        $this->baseUrl = $result[0]['cf_qr_url'];
        $this->apiKey  = $result[0]['cf_payer_token'];
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            throw new Exception('Элдик Банк: cf_qr_url или cf_payer_token пустые');
        }
    }

    private function request($method, $endpoint, $data = null) {
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'x-api-key: ' . $this->apiKey,
        ];
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($method === 'POST' && $data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        curl_close($ch);
        $result = json_decode($response, true);
        if ($httpCode === 401) {
            throw new Exception('Unauthorized: Invalid API key');
        }
        if (isset($result['err'])) {
            throw new Exception('API error: ' . $result['err']['error']);
        }
        return $result;
    }

    private function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function generateQR($account, $amount, $service) {
        if (empty($account) || empty($amount)) {
            throw new InvalidArgumentException('account и amount обязательны');
        }
        $payload = [
            'id'      => $this->generateUUID(),
            'amount'  => $amount,
            'name'    => $service,
            'account' => "$account",
            'ttl'     => 0
        ];
        return [
            'id'       => $payload['id'],
            'response' => $this->request('POST', '/api/v1/qr/generate', $payload)
        ];
    }

    public function getStatus($transactionId) {
        if (empty($transactionId)) {
            throw new InvalidArgumentException('transactionId обязателен');
        }
        return $this->request('GET', '/api/v1/qr/state/' . urlencode($transactionId));
    }

    public function getCompletedTransactions($startDateTime, $endDateTime) {
        if (empty($startDateTime) || empty($endDateTime)) {
            throw new InvalidArgumentException('startDateTime и endDateTime обязательны');
        }
        return $this->request('POST', '/api/v1/qr/completed-transactions', [
            'startDateTime' => $startDateTime,
            'endDateTime'   => $endDateTime
        ]);
    }
}