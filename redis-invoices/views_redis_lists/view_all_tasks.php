<?php

echo "=== ПРОСМОТР ВСЕХ СПИСКОВ REDIS ===\n\n";

try {
    require_once __DIR__ . '/../config.php';
    $redis = redisConnect();
    
    // Список всех списков для проверки
    $lists = [
        'task:invoice:queue' => 'Задачи в очереди',
        'task:invoice:retry' => 'Задачи на повторы', 
        'task:invoice:errors' => 'Задачи с ошибками',
        'task:invoice:success' => 'Успешные задачи'
    ];
    
    foreach ($lists as $listName => $listTitle) {
        echo "=== $listTitle ($listName) ===\n";
        
        $length = $redis->lLen($listName);
        echo "Длина списка: $length\n";
        
        if ($length > 0) {
            $tasks = $redis->lRange($listName, 0, -1);
            
            foreach ($tasks as $index => $taskJson) {
                $task = json_decode($taskJson, true);
                
                if ($task) {
                    echo "\n--- ЗАДАЧА #" . ($index + 1) . " ---\n";
                    echo "Object ID: " . ($task['objectID'] ?? 'N/A') . "\n";
                    echo "Месяцы: " . (isset($task['months']) ? implode(', ', $task['months']) : 'N/A') . "\n";
                    echo "Типы лиц: " . (isset($task['entityTypes']) ? implode(', ', $task['entityTypes']) : 'N/A') . "\n";
                    echo "User ID: " . ($task['userID'] ?? 'N/A') . "\n";
                    echo "Источник: " . ($task['source'] ?? 'N/A') . "\n";
                    
                    // Дополнительные поля для разных списков
                    if (isset($task['retryCount'])) {
                        echo "Попытка: " . $task['retryCount'] . "/3\n";
                    }
                    if (isset($task['lastError'])) {
                        echo "Последняя ошибка: " . $task['lastError'] . "\n";
                    }
                    if (isset($task['lastRetryAt'])) {
                        echo "Последний повтор: " . $task['lastRetryAt'] . "\n";
                    }
                    if (isset($task['invoiceId'])) {
                        echo "Счет ID: " . $task['invoiceId'] . "\n";
                    }
                    if (isset($task['completedAt'])) {
                        echo "Завершено: " . $task['completedAt'] . "\n";
                    }
                    if (isset($task['errorMessage'])) {
                        echo "Сообщение об ошибке: " . $task['errorMessage'] . "\n";
                    }
                    if (isset($task['failedAt'])) {
                        echo "Ошибка произошла: " . $task['failedAt'] . "\n";
                    }
                } else {
                    echo "\n--- ЗАДАЧА #" . ($index + 1) . " (ОШИБКА JSON) ---\n";
                    echo "Сырые данные: " . $taskJson . "\n";
                }
            }
        } else {
            echo "Список пуст\n";
        }
        
        echo "\n" . str_repeat("-", 50) . "\n\n";
    }
    
    // Общая статистика
    echo "=== ОБЩАЯ СТАТИСТИКА ===\n";
    $totalTasks = 0;
    foreach ($lists as $listName => $listTitle) {
        $length = $redis->lLen($listName);
        $totalTasks += $length;
        echo "$listTitle: $length\n";
    }
    echo "Всего задач: $totalTasks\n";
    
    $redis->close();
    
} catch (Exception $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
}

echo "\n=== КОНЕЦ ===\n";
?>
