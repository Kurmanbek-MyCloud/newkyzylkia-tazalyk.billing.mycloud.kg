<?php
session_start();

$rootPath = dirname(dirname(__DIR__)) . '/';
chdir($rootPath);

// Подключаем файлы Vtiger
require_once 'include/utils/utils.php';
require_once 'Logger.php';
include_once 'includes/runtime/BaseModel.php';
include_once 'includes/runtime/Globals.php';
include_once 'includes/runtime/Controller.php';
include_once 'includes/http/Request.php';
require_once 'include/database/PearDatabase.php';
require_once 'include/utils/UserInfoUtil.php';
require_once 'modules/Users/Users.php';

// Инициализируем глобальные переменные
global $current_user, $adb;

header('Content-Type: application/json');

// Функция для определения группы пользователя из базы данных
function getUserGroupId($userId) {
    global $adb;
    
    if (!$userId) {
        return null;
    }
    
    // Получаем groupid пользователя
    $result = $adb->pquery(
        "SELECT DISTINCT vug.groupid 
         FROM vtiger_users2group vug 
         WHERE vug.userid = ?", 
        array($userId)
    );
    
    if ($adb->num_rows($result) > 0) {
        $row = $adb->fetchByAssoc($result);
        return $row['groupid'];
    }
    
    return null;
}

try {
    require_once __DIR__ . '/../config.php';
    $redis = redisConnect();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $listType = $input['listType'] ?? null;
    
    // Используем userId из сессии (текущий авторизованный пользователь)
    $userId = $_SESSION['authenticated_user_id'] ?? null;
    
    if (!$listType) {
        echo json_encode(['success' => false, 'message' => 'Тип списка не указан']);
        exit;
    }
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Пользователь не авторизован']);
        exit;
    }
    
    // Определяем группу пользователя из базы данных
    $userGroupId = getUserGroupId($userId);
    if (!$userGroupId) {
        echo json_encode(['success' => false, 'message' => 'Группа пользователя не найдена для userId: ' . $userId]);
        exit;
    }
    
    $mpPrefix = "mp{$userGroupId}";
    
    $listName = '';
    $listTitle = '';
    
    switch ($listType) {
        case 'queued':
            $listName = "task:invoice:queue:{$mpPrefix}";
            $listTitle = 'задач в очереди';
            break;
        case 'successful':
            $listName = "task:invoice:success:{$mpPrefix}";
            $listTitle = 'успешных задач';
            break;
        case 'errors':
            $listName = "task:invoice:errors:{$mpPrefix}";
            $listTitle = 'задач с ошибками';
            break;
        case 'retry':
            $listName = "task:invoice:retry:{$mpPrefix}";
            $listTitle = 'задач на повторы';
            break;
        case 'progress':
            $listName = "task:invoice:progress:{$mpPrefix}";
            $listTitle = 'задач в очереди прогресса';
            break;
        case 'scheduled':
            $listName = "task:invoice:scheduled:{$mpPrefix}";
            $listTitle = 'запланированных задач';
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Неверный тип списка']);
            exit;
    }
    
    // Получаем количество задач перед очисткой
    $count = $redis->lLen($listName);
    
    // Очищаем список
    $redis->del($listName);
    
    echo json_encode([
        'success' => true, 
        'message' => "Очищено $count $listTitle",
        'deletedCount' => $count
    ]);
    
} catch (Exception $e) {
    // Логируем ошибку
    error_log("Clear list error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>
