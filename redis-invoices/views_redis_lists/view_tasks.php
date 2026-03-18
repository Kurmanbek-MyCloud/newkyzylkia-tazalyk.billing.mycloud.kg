<?php

// Файл для просмотра очередей внутри Redis
// Просто в терминале запустите команду - php view_tasks.php 

require_once __DIR__ . '/../config.php';
$redis = redisConnect();

echo "=== ЗАДАЧИ В ОЧЕРЕДИ REDIS ===\n\n";

$queueLength = $redis->lLen('task:invoice:queue');
echo "Длина очереди: $queueLength\n\n";

if ($queueLength > 0) {
    $tasks = $redis->lRange('task:invoice:queue', 0, $queueLength - 1);
    
    foreach ($tasks as $index => $task) {
        echo "--- ЗАДАЧА #" . ($index + 1) . " ---\n";
        $taskData = json_decode($task, true);
        
        if ($taskData) {
            echo "Месяцы: " . implode(', ', $taskData['months']) . "\n";
            echo "Типы лиц: " . implode(', ', $taskData['entityTypes']) . "\n";
            echo "User ID: " . $taskData['userID'] . "\n";
            echo "Источник: " . $taskData['source'] . "\n";
            echo "Object ID: " . $taskData['objectID'] . "\n";
        } else {
            echo "Ошибка декодирования JSON\n";
        }
        echo "\n";
    }
} else {
    echo "Очередь пуста\n";
}

$redis->close();
?>
