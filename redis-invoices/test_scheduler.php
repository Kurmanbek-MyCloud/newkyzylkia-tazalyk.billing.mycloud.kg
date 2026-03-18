<?php
// Тестовый скрипт для проверки планировщика задач

echo "=== ТЕСТ ПЛАНИРОВЩИКА ЗАДАЧ ===\n";

require_once __DIR__ . '/config.php';

// Подключение к Redis
try {
    $redis = redisConnect();
    echo "✅ Подключение к Redis успешно\n";
} catch (Exception $e) {
    echo "❌ ОШИБКА подключения к Redis: " . $e->getMessage() . "\n";
    exit(1);
}

// Проверяем очереди для группы 58
$groupid = 58;
$scheduledKey = "task:invoice:scheduled:mp{$groupid}";
$queueKey = "task:invoice:queue:mp{$groupid}";

echo "\n=== ПРОВЕРКА ОЧЕРЕДЕЙ ДЛЯ ГРУППЫ $groupid ===\n";

// Проверяем запланированные задачи
$scheduledLength = $redis->lLen($scheduledKey);
echo "📅 Запланированных задач: $scheduledLength\n";

if ($scheduledLength > 0) {
    $scheduledTasks = $redis->lRange($scheduledKey, 0, -1);
    echo "\n📋 Список запланированных задач:\n";
    
    foreach ($scheduledTasks as $index => $taskJson) {
        $task = json_decode($taskJson, true);
        if ($task) {
            $scheduledTime = $task['scheduledTime'] ?? 'Не указано';
            $currentTime = date('Y-m-d H:i:s');
            $scheduledTimestamp = strtotime($scheduledTime);
            $currentTimestamp = time();
            
            $status = $scheduledTimestamp <= $currentTimestamp ? '⏰ ГОТОВА К ВЫПОЛНЕНИЮ' : '⏳ ОЖИДАЕТ';
            
            echo "  " . ($index + 1) . ". Object ID: {$task['objectID']}\n";
            echo "     Запланировано: $scheduledTime\n";
            echo "     Текущее время: $currentTime\n";
            echo "     Статус: $status\n";
            echo "     Месяцы: " . implode(', ', $task['months']) . "\n";
            echo "     Типы: " . implode(', ', $task['entityTypes']) . "\n\n";
        }
    }
}

// Проверяем основную очередь
$queueLength = $redis->lLen($queueKey);
echo "🚀 Задач в основной очереди: $queueLength\n";

if ($queueLength > 0) {
    $queueTasks = $redis->lRange($queueKey, 0, 4); // Показываем первые 5
    echo "\n📋 Первые задачи в очереди:\n";
    
    foreach ($queueTasks as $index => $taskJson) {
        $task = json_decode($taskJson, true);
        if ($task) {
            echo "  " . ($index + 1) . ". Object ID: {$task['objectID']}\n";
            echo "     Месяцы: " . implode(', ', $task['months']) . "\n";
            echo "     Типы: " . implode(', ', $task['entityTypes']) . "\n";
            if (!empty($task['scheduledTime'])) {
                echo "     Запланировано: {$task['scheduledTime']}\n";
            }
            echo "\n";
        }
    }
}

// Проверяем прогресс
$progressKey = "task:invoice:progress:mp{$groupid}";
$progressLength = $redis->lLen($progressKey);
echo "📊 Задач в прогрессе: $progressLength\n";

// Проверяем успешные
$successKey = "task:invoice:success:mp{$groupid}";
$successLength = $redis->lLen($successKey);
echo "✅ Успешных задач: $successLength\n";

echo "\n=== РЕКОМЕНДАЦИИ ===\n";
if ($scheduledLength > 0) {
    echo "1. Запустите воркер: php worker.php\n";
    echo "2. Воркер будет проверять запланированные задачи каждые 5 секунд\n";
    echo "3. Когда время наступит, задачи автоматически перейдут в основную очередь\n";
} else {
    echo "Нет запланированных задач для проверки\n";
}

echo "\n=== КОНЕЦ ТЕСТА ===\n";
?>
