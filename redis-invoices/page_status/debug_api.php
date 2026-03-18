<?php
session_start();

$rootPath = dirname(dirname(__DIR__)) . '/';
chdir($rootPath);

require_once 'include/database/PearDatabase.php';
require_once 'include/utils/UserInfoUtil.php';
require_once 'modules/Users/Users.php';
require_once 'include/utils/utils.php';
require_once 'Logger.php';
require_once 'includes/runtime/BaseModel.php';
require_once 'includes/runtime/Globals.php';
require_once 'includes/runtime/Controller.php';
require_once 'includes/http/Request.php';

global $current_user, $adb;

echo "<h2>Debug API</h2>";

// Проверяем авторизацию
if (!empty($_SESSION['authenticated_user_id'])) {
    $userId = $_SESSION['authenticated_user_id'];
    echo "<p>✅ Пользователь авторизован: $userId</p>";
    
    // Получаем groupid
    $result = $adb->pquery(
        "SELECT DISTINCT vug.groupid 
         FROM vtiger_users2group vug 
         WHERE vug.userid = ?", 
        array($userId)
    );
    
    if ($adb->num_rows($result) > 0) {
        $row = $adb->fetchByAssoc($result);
        $userGroupId = $row['groupid'];
        echo "<p>✅ Group ID: $userGroupId</p>";
        
        // Подключаем Redis
        try {
            require_once __DIR__ . '/../config.php';
            $redis = redisConnect();
            echo "<p>✅ Redis подключен</p>";
            
            // Формируем ключи
            $mpPrefix = "mp{$userGroupId}";
            $queueKey = "task:invoice:queue:{$mpPrefix}";
            $retryKey = "task:invoice:retry:{$mpPrefix}";
            $errorKey = "task:invoice:errors:{$mpPrefix}";
            $successKey = "task:invoice:success:{$mpPrefix}";
            $progressKey = "task:invoice:progress:{$mpPrefix}";
            
            // Получаем длины списков
            $queueLength = $redis->lLen($queueKey);
            $retryLength = $redis->lLen($retryKey);
            $errorLength = $redis->lLen($errorKey);
            $successLength = $redis->lLen($successKey);
            $progressLength = $redis->lLen($progressKey);
            
            echo "<h3>Данные для API:</h3>";
            $apiData = [
                'user_id' => $userId,
                'user_group_id' => $userGroupId,
                'mp_prefix' => $mpPrefix,
                'stats' => [
                    'queue_length' => $queueLength,
                    'retry_length' => $retryLength,
                    'error_length' => $errorLength,
                    'success_length' => $successLength,
                    'progress_length' => $progressLength
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            echo "<pre>";
            echo json_encode($apiData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            echo "</pre>";
            
            $redis->close();
            
        } catch (Exception $e) {
            echo "<p>❌ Ошибка Redis: " . $e->getMessage() . "</p>";
        }
        
    } else {
        echo "<p>❌ Group ID не найден</p>";
    }
} else {
    echo "<p>❌ Пользователь не авторизован</p>";
    echo "<p>Доступные ключи сессии:</p>";
    echo "<ul>";
    foreach (array_keys($_SESSION) as $key) {
        echo "<li>$key</li>";
    }
    echo "</ul>";
}
?>
