<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Устанавливаем часовой пояс GMT+6
date_default_timezone_set('Asia/Bishkek');

$operatorLogin = isset($_GET['operator_login']) ? trim($_GET['operator_login']) : null;

try {
    $tokenData = null;

    // Если передан operator_login — читаем из БД
    if ($operatorLogin) {
        chdir(__DIR__ . '/../');
        if (file_exists('config.inc.php')) {
            require_once 'config.inc.php';
            require_once 'include/utils/utils.php';

            global $adb;
            if (!isset($adb) || !$adb) {
                $adb = PearDatabase::getInstance();
            }

            if ($adb) {
                $query = "SELECT token, token_timeout, token_expires_at FROM terminal_settings WHERE operator_login = ? AND token IS NOT NULL LIMIT 1";
                $result = $adb->pquery($query, array($operatorLogin));

                if ($adb->num_rows($result) > 0) {
                    $row = $adb->fetchByAssoc($result);
                    $tokenData = [
                        'token' => $row['token'],
                        'expiresAt' => $row['token_expires_at'],
                        'opLogin' => $operatorLogin,
                        'receivedAt' => null
                    ];
                }
            }
        }
    }

    // Fallback: читаем из файла (обратная совместимость)
    if (!$tokenData) {
        $tokensFile = __DIR__ . '/tokens.json';
        if (file_exists($tokensFile)) {
            $tokenData = json_decode(file_get_contents($tokensFile), true);
        }
    }

    if (!$tokenData || empty($tokenData['token'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Token not found',
            'token' => null,
            'expiresAt' => null,
            'isExpired' => true
        ]);
        exit;
    }

    // Проверяем срок действия токена (с запасом в 60 секунд)
    $tz = new DateTimeZone('Asia/Bishkek');
    $expiresAt = new DateTime($tokenData['expiresAt'], $tz);
    $now = new DateTime('now', $tz);
    $timeBuffer = new DateInterval('PT60S'); // 60 секунд запаса
    $expiresAtWithBuffer = clone $expiresAt;
    $expiresAtWithBuffer->sub($timeBuffer);

    $isExpired = $expiresAtWithBuffer <= $now;

    // Возвращаем данные о токене (тот же формат)
    echo json_encode([
        'success' => true,
        'token' => $tokenData['token'],
        'expiresAt' => $tokenData['expiresAt'],
        'isExpired' => $isExpired,
        'opLogin' => $tokenData['opLogin'],
        'receivedAt' => isset($tokenData['receivedAt']) ? $tokenData['receivedAt'] : null,
        'timeLeft' => $isExpired ? 0 : $expiresAt->getTimestamp() - $now->getTimestamp()
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage(),
        'token' => null,
        'expiresAt' => null,
        'isExpired' => true
    ]);
}
?>
