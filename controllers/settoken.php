<?php
// Устанавливаем часовой пояс GMT+6 в самом начале
date_default_timezone_set('Asia/Bishkek');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Логирование входящих запросов
function logRequest($data) {
    $logFile = 'token_requests.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] " . json_encode($data) . "\n", FILE_APPEND);
}

// Получаем данные из POST запроса
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Логируем входящий callback
logRequest(['type' => 'CALLBACK_RECEIVED', 'raw_input' => $input]);

if (!$data) {
    logRequest(['type' => 'CALLBACK_ERROR', 'error' => 'Invalid JSON data', 'input' => $input]);
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid JSON data'
    ]);
    exit;
}

// Проверяем обязательные поля
$requiredFields = ['@MsgNum', 'OpLogin', 'Token', 'TokenTimeout', 'ServerTime'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        // Логируем только ошибки
        logRequest(['type' => 'ERROR - Missing required field', 'field' => $field, 'data' => $data]);
        http_response_code(400);
        echo json_encode([
            'error' => "Missing required field: $field"
        ]);
        exit;
    }
}

// Сохраняем токен в файл или базу данных
$tokenData = [
    'msgNum' => $data['@MsgNum'],
    'opLogin' => $data['OpLogin'],
    'token' => $data['Token'],
    'tokenTimeout' => $data['TokenTimeout'],
    'serverTime' => $data['ServerTime'],
    'receivedAt' => date('Y-m-d H:i:s'),
    'expiresAt' => date('Y-m-d H:i:s', time() + $data['TokenTimeout'])
];

// Сохраняем токен в БД (terminal_settings) по operator_login
$dbSaved = false;
try {
    chdir(__DIR__ . '/../');
    if (file_exists('config.inc.php')) {
        require_once 'config.inc.php';
        require_once 'include/utils/utils.php';

        global $adb;
        if (!isset($adb) || !$adb) {
            $adb = PearDatabase::getInstance();
        }

        if ($adb) {
            $updateQuery = "UPDATE terminal_settings SET token = ?, token_timeout = ?, token_expires_at = ? WHERE operator_login = ?";
            $updateResult = $adb->pquery($updateQuery, array(
                $data['Token'],
                $data['TokenTimeout'],
                $tokenData['expiresAt'],
                $data['OpLogin']
            ));
            $dbSaved = ($updateResult !== false);
            logRequest(['type' => 'CALLBACK_DB_SAVE', 'operator' => $data['OpLogin'], 'db_saved' => $dbSaved]);
        }
    }
} catch (Exception $e) {
    logRequest(['type' => 'CALLBACK_DB_ERROR', 'operator' => $data['OpLogin'], 'error' => $e->getMessage()]);
}

// Fallback: сохраняем в файл (на переходный период)
$saveResult = file_put_contents('tokens.json', json_encode($tokenData, JSON_PRETTY_PRINT));

// Проверяем успешность сохранения хотя бы одним способом
if ($saveResult === false && !$dbSaved) {
    logRequest(['type' => 'ERROR - Failed to save token', 'tokenData' => $tokenData]);
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to save token'
    ]);
    exit;
}

// Логируем успешное сохранение
logRequest(['type' => 'CALLBACK_SAVED', 'operator' => $data['OpLogin'], 'token' => $data['Token'], 'timeout' => $data['TokenTimeout'], 'expires_at' => $tokenData['expiresAt']]);

// Формируем ответ согласно протоколу
$response = [
    '@MsgNum' => $data['@MsgNum'],
    'ServerTime' => date('d.m.Y H:i:s T'),
    'Response' => [
        'Code' => '00',
        'Description' => 'OK',
        'Info' => 'Token successfully received and stored'
    ]
];

echo json_encode($response);
?>