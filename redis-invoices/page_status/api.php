<?php
// API для получения данных в реальном времени
error_reporting(0); // Подавляем вывод ошибок в тело ответа

// Перехватываем фатальные ошибки и всегда возвращаем JSON
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_level() > 0) { ob_end_clean(); }
        if (!headers_sent()) { header('Content-Type: application/json'); }
        echo json_encode(['error' => 'PHP Fatal: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']]);
    }
});

session_start();

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

// Получаем userId из сессии (приоритет сессии)
$userId = null;

// Сначала пробуем получить из сессии (правильный способ)
if (!empty($_SESSION['authenticated_user_id'])) {
    $userId = $_SESSION['authenticated_user_id'];
}
// Если нет в сессии, пробуем из GET параметра (только для отладки)
elseif (!empty($_GET['userid'])) {
    $userId = intval($_GET['userid']);
}

if (!$userId) {
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Доступ запрещён</title><style>body{background:#f4f6fb;font-family:Segoe UI,Arial,sans-serif;} .centered{max-width:420px;margin:100px auto;padding:32px 28px 24px 28px;background:#fff;border-radius:18px;box-shadow:0 8px 32px 0 rgba(31,38,135,0.18);text-align:center;} h2{color:#b91c1c;} p{color:#2d3a4b;font-size:17px;margin-top:18px;} a{color:#2193b0;text-decoration:none;font-weight:600;}</style></head><body><div class="centered"><h2>Доступ запрещён</h2><p>Пожалуйста, <a href="/index.php?module=Users&action=Login">авторизуйтесь в биллинге</a> и затем вернитесь на эту страницу.</p></div></body></html>';
    exit;
}

// Получаем groupid пользователя
$result = $adb->pquery(
    "SELECT DISTINCT vug.groupid 
     FROM vtiger_users2group vug 
     WHERE vug.userid = ?", 
    array($userId)
);

$userGroupId = null;
if ($adb->num_rows($result) > 0) {
    $row = $adb->fetchByAssoc($result);
    $userGroupId = $row['groupid'];
}

if (!$userGroupId) {
    echo json_encode([
        'error' => 'User group not found for userId: ' . $userId,
        'user_id' => $userId,
        'user_group_id' => null,
        'stats' => [
            'queue_length' => 0,
            'retry_length' => 0,
            'error_length' => 0,
            'success_length' => 0,
            'progress_length' => 0,
            'scheduled_length' => 0
        ],
        'queued_tasks' => [],
        'retry_tasks' => [],
        'error_tasks' => [],
        'success_tasks' => [],
        'progress_tasks' => [],
        'scheduled_tasks' => [],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Подключаем Redis
require_once __DIR__ . '/../config.php';
try {
    $redis = redisConnect();
} catch (Exception $e) {
    echo json_encode(['error' => 'Redis connection failed']);
    exit;
}

// Формируем ключи Redis для конкретного МП
$mpPrefix = "mp{$userGroupId}";
$queueKey = "task:invoice:queue:{$mpPrefix}";
$retryKey = "task:invoice:retry:{$mpPrefix}";
$errorKey = "task:invoice:errors:{$mpPrefix}";
$successKey = "task:invoice:success:{$mpPrefix}";
$progressKey = "task:invoice:progress:{$mpPrefix}";
$scheduledKey = "task:invoice:scheduled:{$mpPrefix}";

// Получаем статистику списков для конкретного МП
$queueLength = $redis->lLen($queueKey);
$retryLength = $redis->lLen($retryKey);
$errorLength = $redis->lLen($errorKey);
$successLength = $redis->lLen($successKey);
$progressLength = $redis->lLen($progressKey);
$scheduledLength = $redis->lLen($scheduledKey);

// Получаем задачи из всех списков
$queuedTasks = [];
$retryTasks = [];
$errorTasks = [];
$successTasks = [];
$progressTasks = [];
$scheduledTasks = [];

// Ограничиваем количество загружаемых задач для ускорения (последние 100 из каждой очереди)
$maxTasks = 100;

// Задачи в очереди
if ($queueLength > 0) {
    $start = max(0, $queueLength - $maxTasks);
    $allTasks = $redis->lRange($queueKey, $start, -1);
    foreach ($allTasks as $taskJson) {
        $task = json_decode($taskJson, true);
        if ($task) {
            $queuedTasks[] = $task;
        }
    }
}

// Задачи на повторы
if ($retryLength > 0) {
    $start = max(0, $retryLength - $maxTasks);
    $allTasks = $redis->lRange($retryKey, $start, -1);
    foreach ($allTasks as $taskJson) {
        $task = json_decode($taskJson, true);
        if ($task) {
            $retryTasks[] = $task;
        }
    }
}

// Задачи с ошибками
if ($errorLength > 0) {
    $start = max(0, $errorLength - $maxTasks);
    $allTasks = $redis->lRange($errorKey, $start, -1);
    foreach ($allTasks as $taskJson) {
        $task = json_decode($taskJson, true);
        if ($task) {
            $errorTasks[] = $task;
        }
    }
}

// Успешные задачи
if ($successLength > 0) {
    $start = max(0, $successLength - $maxTasks);
    $allTasks = $redis->lRange($successKey, $start, -1);
    foreach ($allTasks as $taskJson) {
        $task = json_decode($taskJson, true);
        if ($task) {
            $successTasks[] = $task;
        }
    }
}

// Задачи для отслеживания прогресса
// Если основная очередь и повторы пусты — не грузим устаревшие записи прогресса
$queueIsEmpty = ($queueLength == 0 && $retryLength == 0);

if ($progressLength > 0 && !$queueIsEmpty) {
    $start = max(0, $progressLength - $maxTasks);
    $allTasks = $redis->lRange($progressKey, $start, -1);
    foreach ($allTasks as $taskJson) {
        $task = json_decode($taskJson, true);
        if ($task) {
            $progressTasks[] = $task;
        }
    }
}

// Запланированные задачи
if ($scheduledLength > 0) {
    $start = max(0, $scheduledLength - $maxTasks);
    $allTasks = $redis->lRange($scheduledKey, $start, -1);
    foreach ($allTasks as $taskJson) {
        $task = json_decode($taskJson, true);
        if ($task) {
            $scheduledTasks[] = $task;
        }
    }
}

$redis->close();

// Убрано чтение логов для ускорения загрузки
// Логи можно читать отдельным запросом при необходимости

// Статистика из Redis списков
$stats = [
    'queue_length' => $queueLength,
    'retry_length' => $retryLength,
    'error_length' => $errorLength,
    'success_length' => $successLength,
    'progress_length' => $progressLength,
    'scheduled_length' => $scheduledLength
];

// Возвращаем данные
$response = [
    'user_id' => $userId,
    'user_group_id' => $userGroupId,
    'mp_prefix' => $mpPrefix,
    'stats' => $stats,
    'queue_completed' => $queueIsEmpty,
    'queued_tasks' => $queuedTasks,
    'retry_tasks' => $retryTasks,
    'error_tasks' => $errorTasks,
    'success_tasks' => $successTasks,
    'progress_tasks' => $progressTasks,
    'scheduled_tasks' => $scheduledTasks,
    'timestamp' => date('Y-m-d H:i:s')
];

// Убрано избыточное логирование для ускорения работы

echo json_encode($response);
?>
