<?php

echo "=== УСПЕШНЫЕ ЗАДАЧИ (task:invoice:success) ===\n\n";

try {
    require_once __DIR__ . '/../config.php';
    $redis = redisConnect();
    
    $length = $redis->lLen('task:invoice:success');
    echo "Длина списка успешных задач: $length\n\n";
    
    if ($length > 0) {
        $tasks = $redis->lRange('task:invoice:success', 0, -1);
        
        foreach ($tasks as $index => $taskJson) {
            $task = json_decode($taskJson, true);
            
            if ($task) {
                echo "--- УСПЕШНАЯ ЗАДАЧА #" . ($index + 1) . " ---\n";
                echo "Object ID: " . ($task['objectID'] ?? 'N/A') . "\n";
                echo "Месяцы: " . (isset($task['months']) ? implode(', ', $task['months']) : 'N/A') . "\n";
                echo "Типы лиц: " . (isset($task['entityTypes']) ? implode(', ', $task['entityTypes']) : 'N/A') . "\n";
                echo "User ID: " . ($task['userID'] ?? 'N/A') . "\n";
                echo "Источник: " . ($task['source'] ?? 'N/A') . "\n";
                
                if (isset($task['invoiceId'])) {
                    echo "✅ Счет ID: " . $task['invoiceId'] . "\n";
                } else {
                    echo "❌ Счет ID: НЕ УКАЗАН\n";
                }
                
                if (isset($task['completedAt'])) {
                    echo "Завершено: " . $task['completedAt'] . "\n";
                } else {
                    echo "Завершено: НЕ УКАЗАНО\n";
                }
                
                echo "\n";
            } else {
                echo "--- ЗАДАЧА #" . ($index + 1) . " (ОШИБКА JSON) ---\n";
                echo "Сырые данные: " . $taskJson . "\n\n";
            }
        }
    } else {
        echo "Список успешных задач пуст\n";
    }
    
    $redis->close();
    
} catch (Exception $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
}

echo "=== КОНЕЦ ===\n";
?>
