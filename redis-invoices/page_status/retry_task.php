<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config.php';
    $redis = redisConnect();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $objectId = $input['objectId'] ?? null;
    
    if (!$objectId) {
        echo json_encode(['success' => false, 'message' => 'Object ID не указан']);
        exit;
    }
    
    // Ищем задачу в списке ошибок
    $errorTasks = $redis->lRange('task:invoice:errors', 0, -1);
    $taskToRetry = null;
    $taskIndex = -1;
    
    foreach ($errorTasks as $index => $taskJson) {
        $task = json_decode($taskJson, true);
        if ($task && $task['objectID'] == $objectId) {
            $taskToRetry = $task;
            $taskIndex = $index;
            break;
        }
    }
    
    if (!$taskToRetry) {
        echo json_encode(['success' => false, 'message' => 'Задача не найдена в списке ошибок']);
        exit;
    }
    
    // Удаляем задачу из списка ошибок
    $redis->lRem('task:invoice:errors', $taskJson, 1);
    
    // Добавляем задачу обратно в очередь
    $redis->lPush('task:invoice:queue', json_encode($taskToRetry));
    
    echo json_encode(['success' => true, 'message' => 'Задача отправлена на повтор']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>
