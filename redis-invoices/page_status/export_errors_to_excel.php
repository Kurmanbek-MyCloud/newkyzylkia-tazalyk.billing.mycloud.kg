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

// Получаем userId из параметра или сессии
$userId = null;

if (!empty($_GET['userid'])) {
    $userId = intval($_GET['userid']);
} elseif (!empty($_SESSION['authenticated_user_id'])) {
    $userId = $_SESSION['authenticated_user_id'];
}

if (!$userId) {
    die('Ошибка: Пользователь не авторизован');
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
    die('Ошибка: Не удалось определить группу пользователя');
}

// Подключаем Redis
require_once __DIR__ . '/../config.php';
try {
    $redis = redisConnect();
} catch (Exception $e) {
    die('Ошибка подключения к Redis: ' . $e->getMessage());
}

// Формируем ключ Redis для ошибок конкретного МП
$mpPrefix = "mp{$userGroupId}";
$errorKey = "task:invoice:errors:{$mpPrefix}";

// Получаем задачи с ошибками
$errorTasks = [];
$errorLength = $redis->lLen($errorKey);

if ($errorLength > 0) {
    $allTasks = $redis->lRange($errorKey, 0, -1);
    foreach ($allTasks as $taskJson) {
        $task = json_decode($taskJson, true);
        if ($task) {
            $errorTasks[] = $task;
        }
    }
}

$redis->close();

// Если нет ошибок, возвращаем сообщение
if (empty($errorTasks)) {
    die('Нет ошибок для выгрузки');
}

// Подготавливаем данные для Excel
$excelData = [];

// Заголовки
$excelData[] = [
    'Статус',
    'Причина ошибки',
    'Лицевой счет',
    'ID объекта'
];

// Обрабатываем каждую ошибку
foreach ($errorTasks as $task) {
    $objectId = $task['objectID'] ?? 'N/A';
    $errorMessage = $task['errorMessage'] ?? 'Неизвестная ошибка';
    $status = 'Ошибка';
    
    // Получаем лицевой счет по ID объекта
    $estateNumber = 'N/A';
    if ($objectId !== 'N/A') {
        $res = $adb->pquery(
            "SELECT ve.estate_number 
             FROM vtiger_estates ve 
             INNER JOIN vtiger_crmentity vc ON vc.crmid = ve.estatesid 
             WHERE vc.deleted = 0 AND ve.estatesid = ?", 
            array($objectId)
        );
        
        if ($adb->num_rows($res) > 0) {
            $estateNumber = $adb->query_result($res, 0, 'estate_number');
        }
    }
    
    $excelData[] = [
        $status,
        $errorMessage,
        $estateNumber,
        $objectId
    ];
}

// Устанавливаем заголовки для скачивания Excel файла
$filename = 'Ошибки_генерации_счетов_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Добавляем BOM для корректного отображения кириллицы в Excel
echo "\xEF\xBB\xBF";

// Открываем поток вывода
$output = fopen('php://output', 'w');

// Записываем данные
foreach ($excelData as $row) {
    fputcsv($output, $row, ';'); // Используем точку с запятой как разделитель для Excel
}

fclose($output);
exit;
?>

