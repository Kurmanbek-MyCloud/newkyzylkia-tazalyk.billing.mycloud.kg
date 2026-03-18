<?php
// Устанавливаем обработчик ошибок для JSON ответов (только для POST запросов)
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        // Если это GET запрос, не устанавливаем JSON заголовок
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return false; // Позволяем стандартной обработке ошибок
        }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'PHP Error: ' . $message,
            'file' => $file,
            'line' => $line
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

// Устанавливаем обработчик фатальных ошибок
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Если это GET запрос, не устанавливаем JSON заголовок
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return; // Позволяем стандартной обработке ошибок
        }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Fatal Error: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ], JSON_UNESCAPED_UNICODE);
    }
});

session_start();
chdir('../');

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

// Функция для логирования в наш файл
function logToFile($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    
    // Пробуем записать в файл логов
    $logFile = 'redis-invoices/work_redis_log.log';
    if (file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX) === false) {
        // Если не удалось записать в файл, используем error_log
        error_log($logMessage);
    }
    
    // Также выводим в error_log для надежности
    error_log($logMessage);
}

// Функция для безопасного подключения к Redis
function connectRedis() {
    // Проверяем, установлен ли Redis
    if (!class_exists('Redis')) {
        logToFile("ОШИБКА: Redis extension не установлен", 'ERROR');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error', 
            'message' => 'Redis extension not installed'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        require_once __DIR__ . '/config.php';
        $redis = redisConnect();
        logToFile("Подключение к Redis успешно", 'INFO');
        return $redis;
    } catch (Exception $e) {
        logToFile("ОШИБКА подключения к Redis: " . $e->getMessage(), 'ERROR');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error', 
            'message' => 'Redis connection failed: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Логируем начало работы скрипта
logToFile('Скрипт загружен, пользователь: ' . ($_SESSION['authenticated_user_id'] ?? 'не авторизован'), 'INFO');

// Проверяем авторизацию пользователя
if (empty($_SESSION['authenticated_user_id'])) {
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Доступ запрещён</title><style>body{background:#f4f6fb;font-family:Segoe UI,Arial,sans-serif;} .centered{max-width:420px;margin:100px auto;padding:32px 28px 24px 28px;background:#fff;border-radius:18px;box-shadow:0 8px 32px 0 rgba(31,38,135,0.18);text-align:center;} h2{color:#b91c1c;} p{color:#2d3a4b;font-size:17px;margin-top:18px;} a{color:#2193b0;text-decoration:none;font-weight:600;}</style></head><body><div class="centered"><h2>Доступ запрещён</h2><p>Пожалуйста, <a href="/index.php?module=Users&action=Login">авторизуйтесь в биллинге</a> и затем вернитесь на эту страницу.</p></div></body></html>';
    exit;
}

// Функция для определения группы пользователя
function getUserGroupId($userId) {
    global $adb;
    
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

// Если это GET запрос, отображаем HTML страницу
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = $_SESSION['authenticated_user_id'] ?? null;
    $source = $_GET['source'] ?? 'bulk';
    $recordId = $_GET['recordId'] ?? '';
    $entityType = $_GET['entityType'] ?? '';
    
    // Получаем путь к текущему скрипту
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    $basePath = rtrim($scriptPath, '/');
    // Если путь пустой, используем текущую директорию
    if (empty($basePath)) {
        $basePath = '.';
    }
    
    echo '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Генерация счетов</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { 
            background: #1a1a2e;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        
        body::after {
            content: \'\';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><path d="M100 20 L80 80 L100 60 L120 80 Z" fill="%23007a3d"/><path d="M100 50 L70 120 L100 90 L130 120 Z" fill="%23007a3d"/><path d="M100 100 L60 180 L100 150 L140 180 Z" fill="%23007a3d"/><rect x="90" y="180" width="20" height="40" fill="%238b4513"/></svg>\'),
                url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><path d="M100 20 L80 80 L100 60 L120 80 Z" fill="%23007a3d"/><path d="M100 50 L70 120 L100 90 L130 120 Z" fill="%23007a3d"/><path d="M100 100 L60 180 L100 150 L140 180 Z" fill="%23007a3d"/><rect x="90" y="180" width="20" height="40" fill="%238b4513"/></svg>\'),
                url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><path d="M100 20 L80 80 L100 60 L120 80 Z" fill="%23007a3d"/><path d="M100 50 L70 120 L100 90 L130 120 Z" fill="%23007a3d"/><path d="M100 100 L60 180 L100 150 L140 180 Z" fill="%23007a3d"/><rect x="90" y="180" width="20" height="40" fill="%238b4513"/></svg>\'),
                url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><path d="M100 20 L80 80 L100 60 L120 80 Z" fill="%23007a3d"/><path d="M100 50 L70 120 L100 90 L130 120 Z" fill="%23007a3d"/><path d="M100 100 L60 180 L100 150 L140 180 Z" fill="%23007a3d"/><rect x="90" y="180" width="20" height="40" fill="%238b4513"/></svg>\'),
                url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><path d="M100 20 L80 80 L100 60 L120 80 Z" fill="%23007a3d"/><path d="M100 50 L70 120 L100 90 L130 120 Z" fill="%23007a3d"/><path d="M100 100 L60 180 L100 150 L140 180 Z" fill="%23007a3d"/><rect x="90" y="180" width="20" height="40" fill="%238b4513"/></svg>\'),
                url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><path d="M100 20 L80 80 L100 60 L120 80 Z" fill="%23007a3d"/><path d="M100 50 L70 120 L100 90 L130 120 Z" fill="%23007a3d"/><path d="M100 100 L60 180 L100 150 L140 180 Z" fill="%23007a3d"/><rect x="90" y="180" width="20" height="40" fill="%238b4513"/></svg>\'),
                url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><path d="M100 20 L80 80 L100 60 L120 80 Z" fill="%23007a3d"/><path d="M100 50 L70 120 L100 90 L130 120 Z" fill="%23007a3d"/><path d="M100 100 L60 180 L100 150 L140 180 Z" fill="%23007a3d"/><rect x="90" y="180" width="20" height="40" fill="%238b4513"/></svg>\'),
                url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><path d="M100 20 L80 80 L100 60 L120 80 Z" fill="%23007a3d"/><path d="M100 50 L70 120 L100 90 L130 120 Z" fill="%23007a3d"/><path d="M100 100 L60 180 L100 150 L140 180 Z" fill="%23007a3d"/><rect x="90" y="180" width="20" height="40" fill="%238b4513"/></svg>\'),
                url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><path d="M100 20 L80 80 L100 60 L120 80 Z" fill="%23007a3d"/><path d="M100 50 L70 120 L100 90 L130 120 Z" fill="%23007a3d"/><path d="M100 100 L60 180 L100 150 L140 180 Z" fill="%23007a3d"/><rect x="90" y="180" width="20" height="40" fill="%238b4513"/></svg>\'),
                url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><path d="M100 20 L80 80 L100 60 L120 80 Z" fill="%23007a3d"/><path d="M100 50 L70 120 L100 90 L130 120 Z" fill="%23007a3d"/><path d="M100 100 L60 180 L100 150 L140 180 Z" fill="%23007a3d"/><rect x="90" y="180" width="20" height="40" fill="%238b4513"/></svg>\'),
                url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><path d="M100 20 L80 80 L100 60 L120 80 Z" fill="%23007a3d"/><path d="M100 50 L70 120 L100 90 L130 120 Z" fill="%23007a3d"/><path d="M100 100 L60 180 L100 150 L140 180 Z" fill="%23007a3d"/><rect x="90" y="180" width="20" height="40" fill="%238b4513"/></svg>\'),
                url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><path d="M100 20 L80 80 L100 60 L120 80 Z" fill="%23007a3d"/><path d="M100 50 L70 120 L100 90 L130 120 Z" fill="%23007a3d"/><path d="M100 100 L60 180 L100 150 L140 180 Z" fill="%23007a3d"/><rect x="90" y="180" width="20" height="40" fill="%238b4513"/></svg>\'),
                url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><path d="M100 20 L80 80 L100 60 L120 80 Z" fill="%23007a3d"/><path d="M100 50 L70 120 L100 90 L130 120 Z" fill="%23007a3d"/><path d="M100 100 L60 180 L100 150 L140 180 Z" fill="%23007a3d"/><rect x="90" y="180" width="20" height="40" fill="%238b4513"/></svg>\'),
                url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><path d="M100 20 L80 80 L100 60 L120 80 Z" fill="%23007a3d"/><path d="M100 50 L70 120 L100 90 L130 120 Z" fill="%23007a3d"/><path d="M100 100 L60 180 L100 150 L140 180 Z" fill="%23007a3d"/><rect x="90" y="180" width="20" height="40" fill="%238b4513"/></svg>\'),
                url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><path d="M100 20 L80 80 L100 60 L120 80 Z" fill="%23007a3d"/><path d="M100 50 L70 120 L100 90 L130 120 Z" fill="%23007a3d"/><path d="M100 100 L60 180 L100 150 L140 180 Z" fill="%23007a3d"/><rect x="90" y="180" width="20" height="40" fill="%238b4513"/></svg>\'),
                url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><path d="M100 20 L80 80 L100 60 L120 80 Z" fill="%23007a3d"/><path d="M100 50 L70 120 L100 90 L130 120 Z" fill="%23007a3d"/><path d="M100 100 L60 180 L100 150 L140 180 Z" fill="%23007a3d"/><rect x="90" y="180" width="20" height="40" fill="%238b4513"/></svg>\');
            background-size: 120px 160px, 100px 140px, 140px 180px, 110px 150px, 130px 170px, 105px 145px, 125px 165px, 115px 155px, 135px 175px, 108px 148px, 128px 168px, 118px 158px, 138px 178px, 102px 142px, 122px 162px, 112px 152px, 132px 172px, 106px 146px, 126px 166px, 116px 156px;
            background-position: 2% 10%, 8% 60%, 14% 25%, 20% 75%, 26% 5%, 32% 50%, 38% 90%, 44% 15%, 50% 65%, 56% 30%, 62% 80%, 68% 10%, 74% 55%, 80% 85%, 86% 20%, 92% 70%, 6% 40%, 12% 95%, 18% 35%, 24% 80%, 30% 5%, 36% 60%, 42% 25%, 48% 75%, 54% 10%, 60% 50%, 66% 90%, 72% 20%, 78% 65%, 84% 5%, 90% 45%, 96% 85%, 4% 30%, 10% 70%, 16% 15%, 22% 55%, 28% 95%, 34% 25%, 40% 65%, 46% 10%, 52% 50%, 58% 85%, 64% 20%, 70% 60%, 76% 5%, 82% 45%, 88% 80%, 94% 15%, 0% 50%, 6% 90%, 12% 35%, 18% 75%, 24% 10%, 30% 55%, 36% 95%, 42% 30%, 48% 70%, 54% 5%, 60% 50%, 66% 85%, 72% 20%, 78% 65%, 84% 10%, 90% 45%, 96% 80%;
            background-repeat: no-repeat;
            background-attachment: fixed;
            opacity: 0.12;
            z-index: 0;
            pointer-events: none;
        }
        
        /* Снежинки */
        .snowflake {
            position: fixed;
            color: white;
            font-size: 1.5em;
            font-family: Arial;
            text-shadow: 
                0 0 10px rgba(255, 255, 255, 1),
                0 0 20px rgba(255, 255, 255, 0.8),
                0 0 30px rgba(255, 255, 255, 0.6);
            animation: snowfall linear infinite;
            pointer-events: none;
            z-index: 9999;
            font-weight: bold;
        }
        
        @keyframes snowfall {
            0% {
                transform: translateY(-100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }
        
        /* Отступ для контента под гирляндой */
        .main-wrapper { 
            display: flex; 
            gap: 20px; 
            max-width: 1400px; 
            margin: 70px auto 0 auto; 
        }
        .main-content { 
            flex: 1; 
            background: white; 
            padding: 40px; 
            border-radius: 16px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            backdrop-filter: blur(10px);
        }
        .tasks-sidebar { 
            width: 400px; 
            background: white; 
            padding: 25px; 
            border-radius: 16px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            position: sticky; 
            top: 20px; 
            height: fit-content; 
            max-height: calc(100vh - 40px);
            position: relative;
            z-index: 1;
        }
        h1 { 
            color: #667eea; 
            margin-bottom: 35px; 
            font-size: 28px; 
            font-weight: 600;
            text-align: center;
        }
        .form-group { 
            margin-bottom: 30px; 
        }
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
            display: block;
            font-size: 15px;
        }
        .months { 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 12px; 
        }
        .months label { 
            cursor: pointer;
            position: relative;
            padding: 12px 16px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            transition: all 0.3s ease;
            text-align: center;
            font-weight: 500;
            color: #495057;
        }
        .months label:hover {
            background: #e9ecef;
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        .months label input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .months label span {
            display: block;
        }
        .months label:has(input[type="checkbox"]:checked) {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .entity-type-group {
            display: flex;
            gap: 15px;
        }
        .entity-type-item {
            flex: 1;
            position: relative;
        }
        .entity-type-item label {
            display: block;
            padding: 16px 20px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            font-weight: 500;
            color: #495057;
        }
        .entity-type-item label:hover {
            background: #e9ecef;
            border-color: #667eea;
            transform: translateY(-2px);
        }
        .entity-type-item input[type="checkbox"]:checked + label,
        .entity-type-item:has(input[type="checkbox"]:checked) label {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .entity-type-item input[type="checkbox"],
        .entity-type-item input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        .entity-type-item input[type="radio"]:checked + label,
        .entity-type-item:has(input[type="radio"]:checked) label {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .object-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            margin: 8px 0;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .object-item:hover {
            background: #e9ecef;
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
        }
        .object-item input[type="checkbox"] {
            margin-right: 12px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .object-item:has(input[type="checkbox"]:checked) {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        .object-item-info {
            flex: 1;
        }
        .object-item-title {
            font-weight: 600;
            margin-bottom: 4px;
        }
        .object-item-details {
            font-size: 13px;
            opacity: 0.8;
        }
        #objectSearchInput:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        #objectsListContainer {
            position: relative;
            z-index: 1000;
            margin-top: 5px;
        }
        .locality-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .select-all-btn {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .select-all-btn:hover {
            background: #5568d3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        #localityDropdown, #streetDropdown { 
            max-height: 400px; 
            overflow-y: auto; 
            border: 2px solid #e9ecef; 
            padding: 12px; 
            border-radius: 12px; 
            margin-top: 12px;
            background: white;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }
        #localityDropdown label, #streetDropdown label { 
            display: flex;
            align-items: center;
            margin: 0;
            cursor: pointer;
            padding: 12px 16px;
            border-radius: 10px;
            transition: all 0.2s ease;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            font-weight: 500;
            font-size: 14px;
        }
        #localityDropdown label:hover, #streetDropdown label:hover {
            background: #e9ecef;
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
        }
        #localityDropdown label input[type="checkbox"],
        #streetDropdown label input[type="checkbox"] {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            cursor: pointer;
            flex-shrink: 0;
        }
        #localityDropdown label:has(input[type="checkbox"]:checked),
        #streetDropdown label:has(input[type="checkbox"]:checked) {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        #localityDropdown::-webkit-scrollbar,
        #streetDropdown::-webkit-scrollbar {
            width: 8px;
        }
        #localityDropdown::-webkit-scrollbar-track,
        #streetDropdown::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        #localityDropdown::-webkit-scrollbar-thumb,
        #streetDropdown::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }
        #localityDropdown::-webkit-scrollbar-thumb:hover,
        #streetDropdown::-webkit-scrollbar-thumb:hover {
            background: #5568d3;
        }
        .back-btn {
            margin-bottom: 25px;
            padding: 10px 30px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            background: #6c757d;
            color: white;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }
        .back-btn:hover {
            transform: translateX(-3px);
            color: white;
            text-decoration: none;
        }
        .header-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            align-items: flex-start;
        }
        .back-btn {
            margin-bottom: 0;
        }
        .monitoring-btn {
            padding: 10px 30px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .monitoring-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
        }
        .monitoring-hint {
            font-size: 11px;
            color: #6c757d;
            margin-top: 5px;
            text-align: center;
            font-style: italic;
        }
        .tasks-header { 
            font-size: 20px; 
            font-weight: 600; 
            margin-bottom: 15px; 
            color: #667eea;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .tasks-header::before {
            content: "📊";
            font-size: 24px;
        }
        .tasks-count { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 14px;
            font-weight: 600;
        }
        .tasks-subtitle { 
            font-size: 13px; 
            color: #6c757d; 
            margin-bottom: 15px; 
        }
        #taskQueueTable { 
            font-size: 13px; 
        }
        #taskQueueTable th, #taskQueueTable td { 
            padding: 10px 8px; 
        }
        .pagination-controls { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-top: 15px; 
        }
        .pagination-info { 
            font-size: 12px; 
            color: #6c757d; 
        }
        .task-group { 
            background: #f8f9fa; 
            margin-bottom: 10px; 
            padding: 12px; 
            border-radius: 10px; 
            border-left: 4px solid #667eea; 
        }
        .task-group-header { 
            font-weight: 600; 
            margin-bottom: 6px; 
            color: #333;
        }
        .task-group-details { 
            font-size: 12px; 
            color: #6c757d; 
        }
        .spinner-border { 
            display: inline-block; 
            width: 1rem; 
            height: 1rem; 
            border: 0.15em solid currentColor; 
            border-right-color: transparent; 
            border-radius: 50%; 
            animation: spinner-border 0.75s linear infinite; 
        }
        @keyframes spinner-border { 
            to { transform: rotate(360deg); } 
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            transition: all 0.3s ease;
            height: auto;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 14px 30px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        .text-muted {
            color: #6c757d !important;
            font-size: 13px;
            margin-top: 6px;
        }
        
        /* Индикатор загрузки страницы */
        .page-loader { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(255, 255, 255, 0.95); 
            z-index: 9999; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: center; 
            transition: opacity 0.3s ease;
        }
        .page-loader.hidden { 
            opacity: 0; 
            pointer-events: none; 
        }
        .page-loader-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #35aa47;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .page-loader-text {
            font-size: 18px;
            color: #35aa47;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Индикатор загрузки страницы -->
    <div class="page-loader" id="pageLoader">
        <div class="page-loader-spinner"></div>
        <div class="page-loader-text">Загрузка страницы...</div>
    </div>
    
    <div class="main-wrapper">
        <div class="main-content">
            <div class="header-buttons">
                <a href="javascript:window.close()" class="back-btn">← Назад</a>
                <div style="flex: 1; display: flex; flex-direction: column;">
                    <a href="/redis-invoices/page_status/tabs_dashboard.php" class="monitoring-btn">
                        <i class="fas fa-chart-line"></i> Мониторинг
                    </a>
                    <div class="monitoring-hint">для просмотра сгенерированных счетов нажмите на кнопку</div>
                </div>
            </div>
            <h1 id="pageTitle">Генерация счетов</h1>
            
            <form id="generateEstateInvoicesForm">
                <div class="form-group">
                    <label><i class="fas fa-tasks"></i> Режим генерации:</label>
                    <div class="entity-type-group" id="generationModeGroup">
                        <div class="entity-type-item">
                            <input type="radio" name="generationMode" value="bulk" id="modeBulk" checked>
                            <label for="modeBulk"><i class="fas fa-layer-group"></i> Массовая генерация</label>
                        </div>
                        <div class="entity-type-item">
                            <input type="radio" name="generationMode" value="single" id="modeSingle">
                            <label for="modeSingle"><i class="fas fa-home"></i> Для конкретного объекта</label>
                        </div>
                    </div>
                </div>

                <div id="objectSearchContainer" style="display: none;">
                    <div class="form-group">
                        <label><i class="fas fa-search"></i> Поиск объекта:</label>
                        <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <input type="text" class="form-control" id="objectSearchInput" placeholder="Введите ЛС или ФИО для поиска..." style="flex: 1;">
                            <button type="button" class="btn btn-primary" id="searchObjectsBtn">
                                <i class="fas fa-search"></i> Найти
                            </button>
                        </div>
                        <div id="objectsListContainer" style="max-height: 300px; overflow-y: auto; border: 2px solid #e9ecef; padding: 15px; border-radius: 12px; background: white; display: none;">
                            <div id="objectsList"></div>
                        </div>
                        <small class="text-muted" id="selectedObjectsInfo" style="display: none;"></small>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Год:</label>
                    <select class="form-control" id="invoiceYear" name="year" style="max-width: 200px;" required>
                        <!-- Годы будут добавлены через JS -->
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Месяцы:</label>
                    <div class="months">
                        <label><input type="checkbox" name="months[]" value="01"><span>Январь</span></label>
                        <label><input type="checkbox" name="months[]" value="02"><span>Февраль</span></label>
                        <label><input type="checkbox" name="months[]" value="03"><span>Март</span></label>
                        <label><input type="checkbox" name="months[]" value="04"><span>Апрель</span></label>
                        <label><input type="checkbox" name="months[]" value="05"><span>Май</span></label>
                        <label><input type="checkbox" name="months[]" value="06"><span>Июнь</span></label>
                        <label><input type="checkbox" name="months[]" value="07"><span>Июль</span></label>
                        <label><input type="checkbox" name="months[]" value="08"><span>Август</span></label>
                        <label><input type="checkbox" name="months[]" value="09"><span>Сентябрь</span></label>
                        <label><input type="checkbox" name="months[]" value="10"><span>Октябрь</span></label>
                        <label><input type="checkbox" name="months[]" value="11"><span>Ноябрь</span></label>
                        <label><input type="checkbox" name="months[]" value="12"><span>Декабрь</span></label>
                    </div>
                </div>

                <div class="form-group" id="entityTypeContainer">
                    <label><i class="fas fa-users"></i> Тип лица:</label>
                    <div class="entity-type-group" id="estateEntityTypeCheckboxes">
                        <div class="entity-type-item">
                            <input type="checkbox" name="entityType[]" value="Физ. лицо" id="entityType1">
                            <label for="entityType1"><i class="fas fa-user"></i> Физ. лицо</label>
                        </div>
                        <div class="entity-type-item">
                            <input type="checkbox" name="entityType[]" value="Юр. лицо" id="entityType2">
                            <label for="entityType2"><i class="fas fa-building"></i> Юр. лицо</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Дата и время запуска:</label>
                    <input type="datetime-local" class="form-control" id="scheduledDateTime" style="max-width: 350px;">
                    <small class="text-muted"><i class="fas fa-info-circle"></i> Оставьте пустым для немедленного запуска</small>
                </div>

                <div id="localityDropdownContainer" style="display: none;">
                    <div class="form-group">
                        <div class="locality-header">
                            <label style="margin: 0;"><i class="fas fa-map-marker-alt"></i> Населённые пункты:</label>
                            <button type="button" class="select-all-btn" id="selectAllLocalities">
                                <i class="fas fa-check-square"></i> Выбрать всё
                            </button>
                        </div>
                        <div id="localityDropdown"></div>
                    </div>
                </div>

                <div id="streetDropdownContainer" style="display: none;">
                    <div class="form-group">
                        <div class="locality-header">
                            <label style="margin: 0;"><i class="fas fa-road"></i> Улицы:</label>
                            <button type="button" class="select-all-btn" id="selectAllStreets">
                                <i class="fas fa-check-square"></i> Выбрать всё
                            </button>
                        </div>
                        <div id="streetDropdown"></div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 40px; text-align: center;">
                    <button type="button" class="btn btn-primary btn-lg" id="startEstateInvoiceGenerationButton">
                        <i class="fas fa-play-circle"></i> Запустить генерацию
                    </button>
                </div>
            </form>
        </div>
        
        <div class="tasks-sidebar">
            <div class="tasks-header">
                Задачи в очереди <span class="tasks-count" id="taskQueueCount">0</span>
            </div>
            <div class="tasks-subtitle">Задачи со статусом "Ожидает"</div>
            <div id="taskQueueContainer" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                <div id="taskQueueList"></div>
            </div>
            <div class="pagination-controls" id="paginationControls" style="display: none;">
                <button class="btn btn-sm btn-secondary" id="prevPage">← Назад</button>
                <span class="pagination-info" id="paginationInfo"></span>
                <button class="btn btn-sm btn-secondary" id="nextPage">Далее →</button>
            </div>
        </div>
    </div>
    
    <script>
        window.pageConfig = {
            userId: ' . json_encode($userId, JSON_UNESCAPED_UNICODE) . ',
            source: ' . json_encode($source, JSON_UNESCAPED_UNICODE) . ',
            recordId: ' . json_encode($recordId, JSON_UNESCAPED_UNICODE) . ',
            entityType: ' . json_encode($entityType, JSON_UNESCAPED_UNICODE) . '
        };
        
        // Скрываем индикатор загрузки после полной загрузки страницы
        window.addEventListener(\'load\', function() {
            setTimeout(function() {
                const loader = document.getElementById(\'pageLoader\');
                if (loader) {
                    loader.classList.add(\'hidden\');
                    setTimeout(function() {
                        loader.style.display = \'none\';
                    }, 300);
                }
            }, 500);
        });
    </script>
    <script>
        // Новогодние снежинки
        function createSnowflake() {
            const snowflake = document.createElement(\'div\');
            snowflake.className = \'snowflake\';
            snowflake.innerHTML = \'❄\';
            snowflake.style.left = Math.random() * 100 + \'%\';
            snowflake.style.animationDuration = (Math.random() * 3 + 2) + \'s\';
            snowflake.style.opacity = Math.random() * 0.5 + 0.7; // Более видимые (0.7-1.2)
            snowflake.style.fontSize = (Math.random() * 15 + 15) + \'px\'; // Больше размер
            document.body.appendChild(snowflake);
            
            setTimeout(() => {
                snowflake.remove();
            }, 5000);
        }
        
        // Создаем снежинки каждые 300мс
        setInterval(createSnowflake, 300);
    </script>
    <script>
        // Инициализация выбора года
        document.addEventListener(\'DOMContentLoaded\', function() {
            const yearSelect = document.getElementById(\'invoiceYear\');
            if (yearSelect) {
                const currentYear = new Date().getFullYear();
                const startYear = currentYear - 2;
                const endYear = currentYear; // Заканчиваем на текущий год (нельзя генерировать за будущий год)
                
                for (let year = startYear; year <= endYear; year++) {
                    const option = document.createElement(\'option\');
                    option.value = year;
                    option.textContent = year;
                    if (year === currentYear) {
                        option.selected = true;
                    }
                    yearSelect.appendChild(option);
                }
            }
        });
    </script>
    <script src="' . $basePath . '/invoicePage.js"></script>
</body>
</html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Простое логирование для отладки
    error_log("=== НОВЫЙ POST ЗАПРОС ===");
    error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'не установлен'));
    
    logToFile("=== НОВЫЙ POST ЗАПРОС ===", 'INFO');
    logToFile("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'не установлен'), 'INFO');
    logToFile("Raw input: " . file_get_contents('php://input'), 'INFO');
    
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    $action = $data['action'] ?? '';
    $userId = (int) ($data['userID'] ?? 0);
    
    logToFile("Raw input: " . $rawInput, 'INFO');
    logToFile("JSON decode error: " . json_last_error_msg(), 'INFO');
    logToFile("Полученные данные: " . json_encode($data, JSON_UNESCAPED_UNICODE), 'INFO');
    logToFile("Action: $action, UserID: $userId", 'INFO');

    if ($action === 'getObjectIds') {
        $entityTypes = $data['entityTypes'] ?? [];
        $localities = $data['localities'] ?? [];
        $streets = $data['streets'] ?? [];
        $params = [$userId];
        $where = '';
        if (!empty($entityTypes)) {
            $in = implode(',', array_fill(0, count($entityTypes), '?'));
            $where .= " AND ve.cf_object_type IN ($in)";
            $params = array_merge($params, $entityTypes);
        }
        if (!empty($localities)) {
            $in = implode(',', array_fill(0, count($localities), '?'));
            $where .= " AND ve.cf_inhabited_locality IN ($in)";
            $params = array_merge($params, $localities);
        }
        if (!empty($streets)) {
            $in = implode(',', array_fill(0, count($streets), '?'));
            $where .= " AND ve.cf_streets IN ($in)";
            $params = array_merge($params, $streets);
        }
        $ids = [];
        $res = $adb->pquery(
            "SELECT ve.estatesid
             FROM vtiger_estates ve
             INNER JOIN vtiger_crmentity vc ON ve.estatesid = vc.crmid AND vc.deleted = 0
             INNER JOIN vtiger_account va ON va.accountid = ve.cf_municipal_enterprise
             INNER JOIN vtiger_crmentity vc2 ON va.accountid = vc2.crmid
             INNER JOIN vtiger_users2group vug ON vug.groupid = vc2.smownerid
             WHERE vug.userid = ?$where", $params
        );
        while ($row = $adb->fetchByAssoc($res)) {
            $ids[] = $row['estatesid'];
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($ids, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'searchObjects') {
        $query = trim($data['query'] ?? '');
        $showAll = $data['showAll'] ?? false;
        
        $objects = [];
        $params = [$userId];
        $whereClause = '';
        
        // Если не показываем все и запрос слишком короткий
        if (!$showAll && (empty($query) || strlen($query) < 2)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Минимум 2 символа для поиска'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Формируем условие поиска
        if (!$showAll && !empty($query)) {
            $searchTerm = '%' . $query . '%';
            $whereClause = " AND (ve.estate_number LIKE ? OR ve.cf_lastname LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Поиск по лицевому счету и ФИО владельца (используем cf_lastname из vtiger_estates)
        $limit = $showAll ? 100 : 50; // При показе всех - больше результатов
        $res = $adb->pquery(
            "SELECT DISTINCT 
                ve.estatesid as id,
                ve.estate_number,
                ve.cf_object_type,
                ve.cf_inhabited_locality,
                ve.cf_streets,
                ve.cf_house_number,
                ve.cf_apartment_number,
                ve.cf_lastname as owner_name
             FROM vtiger_estates ve
             INNER JOIN vtiger_crmentity vc_estate ON ve.estatesid = vc_estate.crmid AND vc_estate.deleted = 0
             INNER JOIN vtiger_account va ON va.accountid = ve.cf_municipal_enterprise
             INNER JOIN vtiger_crmentity vc2 ON va.accountid = vc2.crmid
             INNER JOIN vtiger_users2group vug ON vug.groupid = vc2.smownerid
             WHERE vug.userid = ?$whereClause
             ORDER BY ve.estate_number
             LIMIT $limit", 
            $params
        );
        
        while ($row = $adb->fetchByAssoc($res)) {
            $address = trim(($row['cf_inhabited_locality'] ?? '') . ', ' . ($row['cf_streets'] ?? '') . ', ' . ($row['cf_house_number'] ?? ''));
            if ($row['cf_apartment_number']) {
                $address .= ', кв. ' . $row['cf_apartment_number'];
            }
            
            $ownerName = trim($row['owner_name'] ?? '');
            if (empty($ownerName)) {
                $ownerName = 'Владелец не указан';
            }
            
            $objects[] = [
                'id' => $row['id'],
                'estateNumber' => $row['estate_number'] ?? 'Без ЛС',
                'ownerName' => $ownerName,
                'address' => $address ?: 'Адрес не указан',
                'objectType' => $row['cf_object_type'] ?? ''
            ];
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'success', 'objects' => $objects], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'getTasks') {
        logToFile("=== НАЧАЛО ОБРАБОТКИ getTasks ===", 'INFO');
        
        // Используем userId из сессии, а не из запроса
        $sessionUserId = $_SESSION['authenticated_user_id'] ?? null;
        if (!$sessionUserId) {
            logToFile("ОШИБКА: Пользователь не авторизован", 'ERROR');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
            exit;
        }
        
        // Определяем группу пользователя из сессии
        $userGroupId = getUserGroupId($sessionUserId);
        if (!$userGroupId) {
            logToFile("ОШИБКА: Не удалось определить группу пользователя для UserID: $sessionUserId", 'ERROR');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'User group not found for user: ' . $sessionUserId]);
            exit;
        }
        
        logToFile("Используем UserID из сессии: $sessionUserId, GroupID: $userGroupId", 'INFO');
        
        $redis = connectRedis();
        
        // Формируем ключи Redis для конкретного МП
        $mpPrefix = "mp{$userGroupId}";
        $progressKey = "task:invoice:progress:{$mpPrefix}";
        $scheduledKey = "task:invoice:scheduled:{$mpPrefix}";
        $retryKey = "task:invoice:retry:{$mpPrefix}";
        $errorKey = "task:invoice:error:{$mpPrefix}";
        
        logToFile("Получаем задачи для группы: $userGroupId, ключи: progress=$progressKey, scheduled=$scheduledKey, retry=$retryKey, error=$errorKey", 'INFO');
        
        $tasks = [
            'progress' => [],
            'scheduled' => [],
            'retry' => [],
            'error' => []
        ];
        
        // Получаем задачи из очереди прогресса
        $progressLength = $redis->lLen($progressKey);
        if ($progressLength > 0) {
            $allTasks = $redis->lRange($progressKey, 0, -1);
            foreach ($allTasks as $taskJson) {
                $task = json_decode($taskJson, true);
                if ($task) {
                    $tasks['progress'][] = $task;
                }
            }
        }
        
        // Получаем задачи из очереди запланированных
        $scheduledLength = $redis->lLen($scheduledKey);
        if ($scheduledLength > 0) {
            $allTasks = $redis->lRange($scheduledKey, 0, -1);
            foreach ($allTasks as $taskJson) {
                $task = json_decode($taskJson, true);
                if ($task) {
                    $tasks['scheduled'][] = $task;
                }
            }
        }
        
        // Получаем задачи из очереди повторов
        $retryLength = $redis->lLen($retryKey);
        if ($retryLength > 0) {
            $allTasks = $redis->lRange($retryKey, 0, -1);
            foreach ($allTasks as $taskJson) {
                $task = json_decode($taskJson, true);
                if ($task) {
                    $tasks['retry'][] = $task;
                }
            }
        }
        
        // Получаем задачи из очереди ошибок
        $errorLength = $redis->lLen($errorKey);
        if ($errorLength > 0) {
            $allTasks = $redis->lRange($errorKey, 0, -1);
            foreach ($allTasks as $taskJson) {
                $task = json_decode($taskJson, true);
                if ($task) {
                    $tasks['error'][] = $task;
                }
            }
        }
        
        logToFile("Найдено задач: progress=" . count($tasks['progress']) . ", scheduled=" . count($tasks['scheduled']) . ", retry=" . count($tasks['retry']) . ", error=" . count($tasks['error']), 'INFO');
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'success',
            'userGroupId' => $userGroupId,
            'tasks' => $tasks
        ], JSON_UNESCAPED_UNICODE);
        
        logToFile("=== КОНЕЦ ОБРАБОТКИ getTasks ===", 'INFO');
        exit;
    }

    if ($action === 'deleteTask') {
        logToFile("=== НАЧАЛО ОБРАБОТКИ deleteTask ===", 'INFO');
        
        // Используем userId из сессии, а не из запроса
        $sessionUserId = $_SESSION['authenticated_user_id'] ?? null;
        if (!$sessionUserId) {
            logToFile("ОШИБКА: Пользователь не авторизован", 'ERROR');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
            exit;
        }
        
        // Определяем группу пользователя из сессии
        $userGroupId = getUserGroupId($sessionUserId);
        if (!$userGroupId) {
            logToFile("ОШИБКА: Не удалось определить группу пользователя для UserID: $sessionUserId", 'ERROR');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'User group not found for user: ' . $sessionUserId]);
            exit;
        }
        
        $taskId = $data['taskId'] ?? null;
        $queueType = $data['queueType'] ?? 'progress'; // progress, scheduled, retry, error
        
        if (!$taskId) {
            logToFile("ОШИБКА: Не указан taskId", 'ERROR');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Task ID not specified']);
            exit;
        }
        
        $redis = connectRedis();
        
        // Формируем ключи Redis для конкретного МП
        $mpPrefix = "mp{$userGroupId}";
        $queueKey = "task:invoice:queue:{$mpPrefix}";
        $progressKey = "task:invoice:progress:{$mpPrefix}";
        $scheduledKey = "task:invoice:scheduled:{$mpPrefix}";
        $retryKey = "task:invoice:retry:{$mpPrefix}";
        $errorKey = "task:invoice:error:{$mpPrefix}";
        
        // Определяем из какой очереди удалять
        $targetKey = '';
        switch ($queueType) {
            case 'progress':
                $targetKey = $progressKey;
                break;
            case 'scheduled':
                $targetKey = $scheduledKey;
                break;
            case 'retry':
                $targetKey = $retryKey;
                break;
            case 'error':
                $targetKey = $errorKey;
                break;
            default:
                $targetKey = $progressKey;
        }
        
        logToFile("Удаляем задачу из очереди: $targetKey, taskId: $taskId", 'INFO');
        
        // Получаем все задачи из очереди
        $allTasks = $redis->lRange($targetKey, 0, -1);
        $deleted = false;
        
        foreach ($allTasks as $index => $taskJson) {
            $task = json_decode($taskJson, true);
            if ($task && isset($task['objectID']) && $task['objectID'] == $taskId) {
                // Удаляем задачу по индексу
                $redis->lRem($targetKey, $taskJson, 1);
                $deleted = true;
                logToFile("Задача удалена: $taskJson", 'INFO');
                break;
            }
        }
        
        if ($deleted) {
            logToFile("Задача успешно удалена", 'INFO');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'success', 'message' => 'Task deleted successfully']);
        } else {
            logToFile("Задача не найдена", 'WARNING');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Task not found']);
        }
        
        logToFile("=== КОНЕЦ ОБРАБОТКИ deleteTask ===", 'INFO');
        exit;
    }

    if ($action === 'enqueueInvoiceTask') {
        logToFile("=== НАЧАЛО ОБРАБОТКИ enqueueInvoiceTask ===", 'INFO');
        
        // Используем userId из сессии, а не из запроса
        $sessionUserId = $_SESSION['authenticated_user_id'] ?? null;
        if (!$sessionUserId) {
            logToFile("ОШИБКА: Пользователь не авторизован", 'ERROR');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
            exit;
        }
        
        // Определяем группу пользователя из сессии
        $userGroupId = getUserGroupId($sessionUserId);
        if (!$userGroupId) {
            logToFile("ОШИБКА: Не удалось определить группу пользователя для UserID: $sessionUserId", 'ERROR');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'User group not found for user: ' . $sessionUserId]);
            exit;
        }
        
        logToFile("Используем UserID из сессии: $sessionUserId, GroupID: $userGroupId", 'INFO');
        
        $redis = connectRedis();
        
        // Формируем ключи Redis для конкретного МП
        $mpPrefix = "mp{$userGroupId}";
        $queueKey = "task:invoice:queue:{$mpPrefix}";
        $progressKey = "task:invoice:progress:{$mpPrefix}";
        $scheduledKey = "task:invoice:scheduled:{$mpPrefix}";
        
        logToFile("Используем ключи Redis: queue=$queueKey, progress=$progressKey, scheduled=$scheduledKey", 'INFO');

        $tasks = [];
        logToFile("Source: " . ($data['source'] ?? 'не указан'), 'INFO');

        if ($data['source'] === 'single') {
            // Поддерживаем как один recordId, так и массив objectIds
            $objectIds = [];
            if (isset($data['objectIds']) && is_array($data['objectIds'])) {
                $objectIds = $data['objectIds'];
            } elseif (isset($data['recordId'])) {
                $objectIds = [$data['recordId']];
            }
            
            if (empty($objectIds)) {
                logToFile("ОШИБКА: Не указаны объекты для генерации", 'ERROR');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'message' => 'No objects specified']);
                exit;
            }
            
            // Создаем задачу для каждого объекта
            foreach ($objectIds as $objectId) {
                $task = [
                    'months' => $data['months'],
                    'year' => isset($data['year']) ? intval($data['year']) : date('Y'),
                    'entityTypes' => $data['entityTypes'],
                    'userID' => $sessionUserId, // Используем userId из сессии
                    'source' => 'single',
                    'objectID' => $objectId,
                    'scheduledTime' => $data['scheduledTime'] ?? null,
                    'createdAt' => date('Y-m-d H:i:s')
                ];
                $tasks[] = $task;
            }
            logToFile("Создано SINGLE задач: " . count($tasks), 'INFO');
        } elseif ($data['source'] === 'bulk' && !empty($data['objectIds'])) {
            logToFile("Количество objectIds для bulk: " . count($data['objectIds']), 'INFO');
            foreach ($data['objectIds'] as $objectId) {
                $task = [
                    'months' => $data['months'],
                    'year' => isset($data['year']) ? intval($data['year']) : date('Y'),
                    'entityTypes' => $data['entityTypes'],
                    'userID' => $sessionUserId, // Используем userId из сессии
                    'source' => 'bulk',
                    'objectID' => $objectId,
                    'scheduledTime' => $data['scheduledTime'] ?? null,
                    'createdAt' => date('Y-m-d H:i:s')
                ];
                $tasks[] = $task;
            }
            logToFile("Создано BULK задач: " . count($tasks), 'INFO');
        } else {
            logToFile("ОШИБКА: Неизвестный source или пустые objectIds", 'ERROR');
        }

        logToFile("Всего задач для добавления в очередь: " . count($tasks), 'INFO');

        foreach ($tasks as $index => $task) {
            $taskJson = json_encode($task, JSON_UNESCAPED_UNICODE);
            
            // Добавляем задачу в очередь прогресса (для отслеживания)
            $progressTask = $task;
            $progressTask['processed'] = 0;
            $progressTask['total'] = $task['count'] ?? 1;
            $progressTask['status'] = 'Ожидает';
            $progressTaskJson = json_encode($progressTask, JSON_UNESCAPED_UNICODE);
            
            $progressResult = $redis->rPush($progressKey, $progressTaskJson);
            logToFile("Задача #$index добавлена в очередь прогресса ($progressKey). Результат: $progressResult", 'INFO');
            
            // Проверяем, нужно ли отложить выполнение
            if (!empty($task['scheduledTime'])) {
                $scheduledTime = strtotime($task['scheduledTime']);
                $currentTime = time();
                
                if ($scheduledTime > $currentTime) {
                    // Задача отложена - добавляем в отдельную очередь для отложенных задач
                    $result = $redis->rPush($scheduledKey, $taskJson);
                    logToFile("Задача #$index добавлена в очередь отложенных задач ($scheduledKey, запуск: {$task['scheduledTime']}). Результат: $result", 'INFO');
                } else {
                    // Время уже наступило - добавляем в обычную очередь
                    $result = $redis->rPush($queueKey, $taskJson);
                    logToFile("Задача #$index добавлена в очередь ($queueKey, время наступило). Результат: $result", 'INFO');
                }
            } else {
                // Немедленное выполнение
                $result = $redis->rPush($queueKey, $taskJson);
                logToFile("Задача #$index добавлена в очередь ($queueKey, немедленное выполнение). Результат: $result", 'INFO');
            }
        }

        $queueLength = $redis->lLen($queueKey);
        logToFile("Текущая длина очереди ($queueKey): $queueLength", 'INFO');

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'ok', 'count' => count($tasks)]);
        logToFile("=== КОНЕЦ ОБРАБОТКИ enqueueInvoiceTask ===", 'INFO');
        exit;
    }

    $result = [];

    // Если это не POST запрос с action, обрабатываем как запрос населенных пунктов
    error_log("=== ДОСТИГЛИ ОБРАБОТКИ НАСЕЛЕННЫХ ПУНКТОВ ===");
    error_log("User ID: $userId");
    
    logToFile("=== ДОСТИГЛИ ОБРАБОТКИ НАСЕЛЕННЫХ ПУНКТОВ ===", 'INFO');
    logToFile("User ID: $userId", 'INFO');
    
    if ($userId) {
        $entityTypes = $data['entityTypes'] ?? [];
        logToFile("=== ОБРАБОТКА ЗАПРОСА НАСЕЛЕННЫХ ПУНКТОВ ===", 'INFO');
        logToFile("User ID: $userId", 'INFO');
        logToFile("Entity Types: " . json_encode($entityTypes, JSON_UNESCAPED_UNICODE), 'INFO');
        logToFile("Все данные запроса: " . json_encode($data, JSON_UNESCAPED_UNICODE), 'INFO');
        
        // 1. Получаем все населённые пункты и количество домов
        $localities = [];
        $whereTypes = '';
        $params = [$userId];
        if (!empty($entityTypes)) {
            $in = implode(',', array_fill(0, count($entityTypes), '?'));
            $whereTypes = " AND ve.cf_object_type IN ($in)";
            $params = array_merge($params, $entityTypes);
        }
        
        logToFile("SQL WHERE: $whereTypes", 'INFO');
        logToFile("SQL PARAMS: " . json_encode($params, JSON_UNESCAPED_UNICODE), 'INFO');
        
        // Полный SQL запрос для отладки
        $fullSql = "SELECT ve.cf_inhabited_locality AS locality, COUNT(*) AS count
             FROM vtiger_estates ve
             INNER JOIN vtiger_crmentity vc ON ve.estatesid = vc.crmid AND vc.deleted = 0
             INNER JOIN vtiger_account va ON va.accountid = ve.cf_municipal_enterprise
             INNER JOIN vtiger_crmentity vc2 ON va.accountid = vc2.crmid
             INNER JOIN vtiger_users2group vug ON vug.userid = ?
             WHERE vc2.smownerid = vug.groupid$whereTypes
             GROUP BY ve.cf_inhabited_locality";
        logToFile("Полный SQL: $fullSql", 'INFO');
        
        $res = $adb->pquery($fullSql, $params);
        logToFile("Количество найденных населенных пунктов: " . $adb->num_rows($res), 'INFO');
        
        // Проверим, есть ли ошибка в запросе
        if ($adb->database->ErrorMsg()) {
            logToFile("Ошибка SQL: " . $adb->database->ErrorMsg(), 'ERROR');
        }
        while ($row = $adb->fetchByAssoc($res)) {
            $localities[$row['locality']] = (int) $row['count'];
            logToFile("Населенный пункт: " . $row['locality'] . " (количество: " . $row['count'] . ")", 'INFO');
        }

        // 2. Для каждого населённого пункта получаем улицы и их количество
        foreach ($localities as $locality => $localityCount) {
            $streets = [];
            $params2 = [$userId, $locality];
            $whereTypes2 = '';
            if (!empty($entityTypes)) {
                $in2 = implode(',', array_fill(0, count($entityTypes), '?'));
                $whereTypes2 = " AND ve.cf_object_type IN ($in2)";
                $params2 = array_merge($params2, $entityTypes);
            }
            $res2 = $adb->pquery(
                "SELECT ve.cf_streets AS street, COUNT(*) AS count
                 FROM vtiger_estates ve
                 INNER JOIN vtiger_crmentity vc ON ve.estatesid = vc.crmid AND vc.deleted = 0
                 INNER JOIN vtiger_account va ON va.accountid = ve.cf_municipal_enterprise
                 INNER JOIN vtiger_crmentity vc2 ON va.accountid = vc2.crmid
                 INNER JOIN vtiger_users2group vug ON vug.groupid = vc2.smownerid
                 WHERE vug.userid = ? AND ve.cf_inhabited_locality = ?$whereTypes2
                 GROUP BY ve.cf_streets", $params2
            );
            while ($row2 = $adb->fetchByAssoc($res2)) {
                $streets[] = [
                    'name' => $row2['street'],
                    'count' => (int) $row2['count']
                ];
            }

            // 3. (опционально) фильтруем улицы через picklist_dependency, если нужно

            $result[] = [
                'locality' => $locality,
                'count' => $localityCount,
                'streets' => $streets
            ];
        }
        
        logToFile("Финальный результат: " . json_encode($result, JSON_UNESCAPED_UNICODE), 'INFO');
        logToFile("Количество населенных пунктов в результате: " . count($result), 'INFO');
    } else {
        logToFile("User ID не указан!", 'WARNING');
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    logToFile("=== КОНЕЦ ОБРАБОТКИ ЗАПРОСА НАСЕЛЕННЫХ ПУНКТОВ ===", 'INFO');
    exit;
}