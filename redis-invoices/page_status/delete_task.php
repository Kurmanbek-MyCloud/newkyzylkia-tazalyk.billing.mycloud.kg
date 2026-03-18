<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config.php';
    $redis = redisConnect();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $objectId = $input['objectId'] ?? null;
    $listType = $input['listType'] ?? null; // 'success' или 'errors'
    
    if (!$objectId || !$listType) {
        echo json_encode(['success' => false, 'message' => 'Object ID и тип списка не указаны']);
        exit;
    }
    
    $listName = '';
    switch ($listType) {
        case 'success':
        case 'successful':
            $listName = 'task:invoice:success';
            break;
        case 'errors':
            $listName = 'task:invoice:errors';
            break;
        case 'retry':
            $listName = 'task:invoice:retry';
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Неверный тип списка']);
            exit;
    }
    
    // Ищем задачу в списке
    $tasks = $redis->lRange($listName, 0, -1);
    $taskToDelete = null;
    $taskIndex = -1;
    
    foreach ($tasks as $index => $taskJson) {
        $task = json_decode($taskJson, true);
        if ($task && $task['objectID'] == $objectId) {
            $taskToDelete = $task;
            $taskIndex = $index;
            break;
        }
    }
    
    if (!$taskToDelete) {
        echo json_encode(['success' => false, 'message' => 'Задача не найдена в списке']);
        exit;
    }
    
    // Удаляем задачу из списка
    $redis->lRem($listName, json_encode($taskToDelete), 1);
    
    echo json_encode(['success' => true, 'message' => 'Задача удалена из списка']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>
