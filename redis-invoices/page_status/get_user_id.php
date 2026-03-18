<?php
// Безопасное получение ID пользователя из сессии Vtiger

header('Content-Type: application/json');

// Подключаемся к базе данных Vtiger
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

// Инициализируем глобальные переменные, такие как $adb для доступа к БД.
global $current_user, $adb;

// Отладочная информация
$debugInfo = [
    'session_started' => session_status() === PHP_SESSION_ACTIVE,
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'authenticated_user_id' => $_SESSION['authenticated_user_id'] ?? 'not_set',
    'user_id' => $_SESSION['user_id'] ?? 'not_set',
    'vtiger_user_id' => $_SESSION['vtiger_user_id'] ?? 'not_set',
    'AUTHUSERID' => $_SESSION['AUTHUSERID'] ?? 'not_set'
];

// Проверяем, авторизован ли пользователь
if (!empty($_SESSION['authenticated_user_id'])) {
    // Получаем ID текущего пользователя из сессии
    $current_user = new Users();
    $current_user->retrieveCurrentUserInfoFromFile($_SESSION['authenticated_user_id']);
    
    $currentUserId = $_SESSION['authenticated_user_id'];
    
    // Проверяем, что пользователь существует в базе
    $result = $adb->pquery("SELECT id, user_name FROM vtiger_users WHERE id = ?", array($currentUserId));
    if ($adb->num_rows($result) > 0) {
        $userData = $adb->fetchByAssoc($result);
        echo json_encode([
            'success' => true,
            'userId' => $currentUserId,
            'userName' => $userData['user_name'],
            'debug' => $debugInfo
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'User not found in database',
            'debug' => $debugInfo
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'User not authenticated'
    ]);
}
?>