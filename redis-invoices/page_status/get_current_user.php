<?php
// API для получения ID текущего пользователя

header('Content-Type: application/json');

// Подключаемся к базе данных Vtiger
$rootPath = dirname(dirname(__DIR__)) . '/';
chdir($rootPath);

require_once 'include/utils/utils.php';
include_once 'includes/runtime/BaseModel.php';
include_once 'includes/runtime/Globals.php';
include_once 'includes/runtime/Controller.php';
include_once 'includes/http/Request.php';
global $adb;

try {
    // Получаем ID текущего пользователя из сессии
    $currentUserId = null;
    
    // Проверяем различные способы получения ID пользователя
    if (isset($_SESSION['authenticated_user_id'])) {
        $currentUserId = $_SESSION['authenticated_user_id'];
    } elseif (isset($_SESSION['user_id'])) {
        $currentUserId = $_SESSION['user_id'];
    } elseif (isset($_SESSION['vtiger_user_id'])) {
        $currentUserId = $_SESSION['vtiger_user_id'];
    } elseif (isset($_COOKIE['user_id'])) {
        $currentUserId = $_COOKIE['user_id'];
    } else {
        // Если в сессии нет, пытаемся получить из глобальных переменных Vtiger
        if (isset($current_user) && isset($current_user->id)) {
            $currentUserId = $current_user->id;
        } elseif (isset($current_user_id)) {
            $currentUserId = $current_user_id;
        }
    }
    
    if ($currentUserId) {
        echo json_encode([
            'success' => true,
            'userId' => $currentUserId
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'User ID not found in session'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error getting user ID: ' . $e->getMessage()
    ]);
}
?>
