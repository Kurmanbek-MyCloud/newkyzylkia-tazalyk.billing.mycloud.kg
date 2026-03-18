<?php

echo "=== ЗАДАЧИ С ОШИБКАМИ (task:invoice:errors) ===\n\n";

try {
    require_once __DIR__ . '/../config.php';
    $redis = redisConnect();
    
    $length = $redis->lLen('task:invoice:errors');
    echo "Длина списка задач с ошибками: $length\n\n";
    
    if ($length > 0) {
        $tasks = $redis->lRange('task:invoice:errors', 0, -1);
        
        foreach ($tasks as $index => $taskJson) {
            $task = json_decode($taskJson, true);
            
            if ($task) {
                echo "--- ОШИБКА #" . ($index + 1) . " ---\n";
                echo "Object ID: " . ($task['objectID'] ?? 'N/A') . "\n";
                echo "Месяцы: " . (isset($task['months']) ? implode(', ', $task['months']) : 'N/A') . "\n";
                echo "Типы лиц: " . (isset($task['entityTypes']) ? implode(', ', $task['entityTypes']) : 'N/A') . "\n";
                echo "User ID: " . ($task['userID'] ?? 'N/A') . "\n";
                echo "Источник: " . ($task['source'] ?? 'N/A') . "\n";
                
                if (isset($task['errorMessage'])) {
                    echo "❌ Ошибка: " . $task['errorMessage'] . "\n";
                } else {
                    echo "❌ Ошибка: НЕ УКАЗАНА\n";
                }
                
                if (isset($task['failedAt'])) {
                    echo "Ошибка произошла: " . $task['failedAt'] . "\n";
                } else {
                    echo "Ошибка произошла: НЕ УКАЗАНО\n";
                }
                
                echo "\n";
            } else {
                echo "--- ЗАДАЧА #" . ($index + 1) . " (ОШИБКА JSON) ---\n";
                echo "Сырые данные: " . $taskJson . "\n\n";
            }
        }
    } else {
        echo "Список задач с ошибками пуст\n";
    }
    
    $redis->close();
    
} catch (Exception $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
}

echo "=== КОНЕЦ ===\n";
?>
