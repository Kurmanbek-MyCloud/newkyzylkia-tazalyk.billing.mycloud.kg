<?php

echo "=== ЗАДАЧИ НА ПОВТОРЫ (task:invoice:retry) ===\n\n";

try {
    require_once __DIR__ . '/../config.php';
    $redis = redisConnect();
    
    $length = $redis->lLen('task:invoice:retry');
    echo "Длина списка задач на повторы: $length\n\n";
    
    if ($length > 0) {
        $tasks = $redis->lRange('task:invoice:retry', 0, -1);
        
        foreach ($tasks as $index => $taskJson) {
            $task = json_decode($taskJson, true);
            
            if ($task) {
                echo "--- ПОВТОР #" . ($index + 1) . " ---\n";
                echo "Object ID: " . ($task['objectID'] ?? 'N/A') . "\n";
                echo "Месяцы: " . (isset($task['months']) ? implode(', ', $task['months']) : 'N/A') . "\n";
                echo "Типы лиц: " . (isset($task['entityTypes']) ? implode(', ', $task['entityTypes']) : 'N/A') . "\n";
                echo "User ID: " . ($task['userID'] ?? 'N/A') . "\n";
                echo "Источник: " . ($task['source'] ?? 'N/A') . "\n";
                
                if (isset($task['retryCount'])) {
                    echo "🔄 Попытка: " . $task['retryCount'] . "/3\n";
                } else {
                    echo "🔄 Попытка: НЕ УКАЗАНА\n";
                }
                
                if (isset($task['lastError'])) {
                    echo "Последняя ошибка: " . $task['lastError'] . "\n";
                } else {
                    echo "Последняя ошибка: НЕ УКАЗАНА\n";
                }
                
                if (isset($task['lastRetryAt'])) {
                    echo "Последний повтор: " . $task['lastRetryAt'] . "\n";
                } else {
                    echo "Последний повтор: НЕ УКАЗАН\n";
                }
                
                echo "\n";
            } else {
                echo "--- ЗАДАЧА #" . ($index + 1) . " (ОШИБКА JSON) ---\n";
                echo "Сырые данные: " . $taskJson . "\n\n";
            }
        }
    } else {
        echo "Список задач на повторы пуст\n";
    }
    
    $redis->close();
    
} catch (Exception $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
}

echo "=== КОНЕЦ ===\n";
?>
