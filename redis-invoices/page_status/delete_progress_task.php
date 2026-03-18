<?php
// API для удаления задачи из очереди прогресса

header('Content-Type: application/json');

// Функция для определения группы пользователя
function getUserGroupId($userId) {
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

// Проверяем, что это POST запрос
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Только POST запросы разрешены']);
    exit;
}

// Получаем данные из JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['objectId']) || !isset($input['months']) || !isset($input['entityTypes'])) {
    echo json_encode(['success' => false, 'message' => 'Недостаточно данных']);
    exit;
}

$objectId = $input['objectId'];
$userId = $input['userId'] ?? $_SESSION['authenticated_user_id'] ?? null;

if (!$userId) {
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Доступ запрещён</title><style>body{background:#f4f6fb;font-family:Segoe UI,Arial,sans-serif;} .centered{max-width:420px;margin:100px auto;padding:32px 28px 24px 28px;background:#fff;border-radius:18px;box-shadow:0 8px 32px 0 rgba(31,38,135,0.18);text-align:center;} h2{color:#b91c1c;} p{color:#2d3a4b;font-size:17px;margin-top:18px;} a{color:#2193b0;text-decoration:none;font-weight:600;}</style></head><body><div class="centered"><h2>Доступ запрещён</h2><p>Пожалуйста, <a href="/index.php?module=Users&action=Login">авторизуйтесь в биллинге</a> и затем вернитесь на эту страницу.</p></div></body></html>';
    exit;
}

// Определяем группу пользователя
$userGroupId = getUserGroupId($userId);
if (!$userGroupId) {
    echo json_encode(['success' => false, 'message' => 'Группа пользователя не найдена']);
    exit;
}

$mpPrefix = "mp{$userGroupId}";
$months = $input['months'];
$entityTypes = $input['entityTypes'];

try {
    require_once __DIR__ . '/../config.php';
    $redis = redisConnect();
    
    // Получаем все задачи из очереди прогресса
    $progressTasks = $redis->lRange("task:invoice:progress:{$mpPrefix}", 0, -1);
    $found = false;
    
    foreach ($progressTasks as $index => $taskJson) {
        $task = json_decode($taskJson, true);
        
        // Ищем задачу по objectID, месяцам и типам
        if ($task['objectID'] == $objectId && 
            $task['months'] == explode(',', $months) && 
            $task['entityTypes'] == explode(',', $entityTypes)) {
            
            // Удаляем задачу из очереди прогресса
            $redis->lRem("task:invoice:progress:{$mpPrefix}", $taskJson, 1);
            $found = true;
            break;
        }
    }
    
    if ($found) {
        echo json_encode(['success' => true, 'message' => 'Задача удалена из очереди прогресса']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Задача не найдена в очереди прогресса']);
    }
    
    $redis->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>
