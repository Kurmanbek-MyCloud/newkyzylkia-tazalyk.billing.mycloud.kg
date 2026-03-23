<?php

// Отключаем все предупреждения PHP 8.x для совместимости с Vtiger
error_reporting(0);
ini_set('display_errors', 0);

// Увеличиваем лимит памяти для обработки больших объемов данных
ini_set('memory_limit', '1024M'); // 1GB памяти

// ===== НАСТРОЙКИ ВОРКЕРА =====
$MP_ID = 89; // ID МП для обработки
$HANDLER_FILE = dirname(__DIR__) . '/mp_handlers/ak_emgek.php'; // Путь к файлу генерации
$HANDLER_FUNCTION = 'processAkEmgekInvoice'; // Имя функции генерации
define('IDLE_DISCONNECT_SECONDS', 1800); // 30 минут простоя — отключаем БД
// =============================

// Воркер для обработки задач генерации счетов из Redis очереди
echo "=== ЗАПУСК ВОРКЕРА МП $MP_ID ===\n";

// Устанавливаем глобальные обработчики ошибок
set_error_handler(function($severity, $message, $file, $line) {
    // Показываем только критические ошибки (не предупреждения)
    if ($severity === E_ERROR || $severity === E_PARSE || $severity === E_CORE_ERROR || $severity === E_COMPILE_ERROR) {
        echo "❌ PHP ОШИБКА: $message в файле $file на строке $line\n";
        echo "📋 Уровень ошибки: $severity\n";
    }
    return true; // Продолжаем выполнение
});

set_exception_handler(function($exception) {
    echo "❌ НЕПЕРЕХВАЧЕННОЕ ИСКЛЮЧЕНИЕ: " . $exception->getMessage() . "\n";
    echo "📋 Файл: " . $exception->getFile() . "\n";
    echo "📋 Строка: " . $exception->getLine() . "\n";
    echo "📋 Стек вызовов: " . $exception->getTraceAsString() . "\n";
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null) {
        echo "❌ ФАТАЛЬНАЯ ОШИБКА: " . $error['message'] . "\n";
        echo "📋 Файл: " . $error['file'] . "\n";
        echo "📋 Строка: " . $error['line'] . "\n";
        echo "📋 Тип: " . $error['type'] . "\n";
    }
});

// Меняем рабочую директорию на директорию проекта
$projectDir = dirname(__DIR__);
echo "🔄 Меняем рабочую директорию на: $projectDir\n";
if (chdir($projectDir)) {
    echo "✅ Рабочая директория изменена на: " . getcwd() . "\n";
} else {
    echo "❌ ОШИБКА: Не удалось изменить рабочую директорию!\n";
    exit(1);
}

// Переходим в корень проекта Vtiger
$rootPath = dirname(dirname(__DIR__)) . '/';
chdir($rootPath);

// Подключаем только Logger для логирования
require_once 'Logger.php';

$logger = new CustomLogger("redis-invoices/workers/worker_{$MP_ID}");

echo "=== ВОРКЕР МП $MP_ID ГОТОВ ===\n";

// Подключаем файл создания счетов
echo "🔄 Подключаем файл создания счетов...\n";
if (file_exists($HANDLER_FILE)) {
    echo "✅ Файл создания счетов найден: $HANDLER_FILE\n";
    require_once $HANDLER_FILE;
    echo "✅ Файл создания счетов подключен\n";
} else {
    echo "❌ ОШИБКА: Файл создания счетов не найден: $HANDLER_FILE\n";
    exit(1);
}

echo "🔄 Переходим к подключению к Redis...\n";

require_once __DIR__ . '/../config.php';

// Подключение к Redis
try {
    echo "🔄 Подключаемся к Redis...\n";
    $redis = redisConnect();
    $logger->log("Подключение к Redis успешно");
    echo "✅ Подключение к Redis: OK\n";

    echo "🔄 Проверяем ключи в Redis...\n";
    $allKeys = $redis->keys('*');
    echo "📋 Всего ключей в Redis: " . count($allKeys) . "\n";
    echo "📋 Ключи: " . implode(', ', $allKeys) . "\n";

    echo "🔄 Переходим к основному циклу...\n";

} catch (Exception $e) {
    $logger->log("ОШИБКА подключения к Redis: " . $e->getMessage());
    echo "❌ ОШИБКА подключения к Redis: " . $e->getMessage() . "\n";
    exit(1);
}

// Функция для проверки запланированных задач
function checkScheduledTasks($redis, $mpId) {
    $scheduledKey = "task:invoice:scheduled:mp{$mpId}";
    $queueKey = "task:invoice:queue:mp{$mpId}";
    $currentTime = time();
    $movedCount = 0;

    // Получаем все задачи из очереди запланированных
    $scheduledTasks = $redis->lRange($scheduledKey, 0, -1);
    $tasksToMove = [];

    foreach ($scheduledTasks as $index => $taskJson) {
        $task = json_decode($taskJson, true);
        if ($task && !empty($task['scheduledTime'])) {
            $scheduledTime = strtotime($task['scheduledTime']);

            if ($scheduledTime <= $currentTime) {
                // Время наступило - перемещаем в основную очередь
                $tasksToMove[] = $taskJson;
                $redis->lRem($scheduledKey, $taskJson, 1);
                $movedCount++;

                $logger = new CustomLogger("redis-invoices/workers/worker_{$mpId}");
                $logger->log("⏰ Запланированная задача Object ID {$task['objectID']} перемещена в очередь (МП: $mpId, запланировано: {$task['scheduledTime']})");
            }
        }
    }

    // Перемещаем задачи в основную очередь
    foreach ($tasksToMove as $taskJson) {
        $redis->rPush($queueKey, $taskJson);
    }

    return $movedCount;
}

// Функция для обработки задач из очереди повторов
function processRetryTasks($redis, $mpId, $logger) {
    $retryKey = "task:invoice:retry:mp{$mpId}";
    $queueKey = "task:invoice:queue:mp{$mpId}";
    $retryCount = 0;

    // Получаем все задачи из очереди повторов
    $retryTasks = $redis->lRange($retryKey, 0, -1);

    foreach ($retryTasks as $taskJson) {
        $task = json_decode($taskJson, true);
        if ($task) {
            // Проверяем, прошло ли достаточно времени с последней попытки
            $lastRetryAt = $task['lastRetryAt'] ?? 0;
            $timeSinceLastRetry = time() - strtotime($lastRetryAt);

            // Если прошло больше 30 секунд, возвращаем в очередь
            if ($timeSinceLastRetry >= 30) {
                echo "🔄 Возвращаем задачу Object ID {$task['objectID']} из повторов в очередь (прошло {$timeSinceLastRetry}с)\n";
                $logger->log("🔄 Возвращаем задачу Object ID {$task['objectID']} из повторов в очередь (прошло {$timeSinceLastRetry}с)");

                // Удаляем из очереди повторов
                $redis->lRem($retryKey, $taskJson, 1);

                // Добавляем в основную очередь
                $redis->rPush($queueKey, $taskJson);
                $retryCount++;
            }
        }
    }

    return $retryCount;
}

// Основной цикл воркера
echo "🔄 Запускаем основной цикл воркера для МП $MP_ID...\n";
echo "📋 Начинаем итерацию цикла...\n";
$iterationCount = 0;
$lastTaskTime = time();
$hadTasks = false;

while (true) {
    $iterationCount++;
    echo "🔄 Итерация #$iterationCount\n";
    try {
        echo "🔄 Проверяем запланированные задачи...\n";
        // Сначала проверяем запланированные задачи
        $movedScheduled = checkScheduledTasks($redis, $MP_ID);
        if ($movedScheduled > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Перемещено запланированных задач в очередь: $movedScheduled\n";
        }

        echo "🔄 Проверяем задачи из очереди повторов...\n";
        // Обрабатываем задачи из очереди повторов
        $movedRetries = processRetryTasks($redis, $MP_ID, $logger);
        if ($movedRetries > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Перемещено задач из повторов в очередь: $movedRetries\n";
        }

        // Проверяем очередь для конкретного МП
        $queueKey = "task:invoice:queue:mp{$MP_ID}";
        $queueLength = $redis->lLen($queueKey);

        if ($queueLength == 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Нет задач в очереди для МП $MP_ID\n";

            // Очередь пуста — при длительном простое закрываем соединение с БД
            if ((time() - $lastTaskTime > IDLE_DISCONNECT_SECONDS) && isset($adb->database)) {
                $memoryBefore = memory_get_usage(true) / 1024 / 1024;

                $adb->disconnect();
                gc_collect_cycles();

                $memoryAfter = memory_get_usage(true) / 1024 / 1024;
                $freed = round($memoryBefore - $memoryAfter, 1);

                $logger->log(sprintf(
                    "Очередь пуста более %d мин. Закрыл соединение с БД. Память: было %.1fMB → стало %.1fMB%s",
                    IDLE_DISCONNECT_SECONDS / 60,
                    $memoryBefore,
                    $memoryAfter,
                    $freed > 0 ? " (освобождено {$freed}MB)" : ''
                ));

                // Если были задачи — перезапускаем воркер для полного освобождения памяти.
                // Supervisor/systemd автоматически перезапустит процесс.
                if ($hadTasks) {
                    $logger->log("Были обработаны задачи, перезапускаю воркер для освобождения памяти");
                    exit(0);
                }
            }

            echo "💤 Ждем 5 секунд...\n";
            sleep(5);
            continue;
        }

        echo "📋 Найдено задач в очереди МП $MP_ID: $queueLength\n";
        echo "[" . date('Y-m-d H:i:s') . "] МП $MP_ID: найдено задач в очереди: $queueLength\n";
        $logger->log("МП $MP_ID: найдено задач в очереди: $queueLength");

        // Берем задачу из очереди МП
        echo "🔄 Берем задачу из очереди: $queueKey\n";
        $taskJson = $redis->lPop($queueKey);

        if ($taskJson) {
            echo "✅ Задача получена: " . substr($taskJson, 0, 100) . "...\n";
            $task = json_decode($taskJson, true);
            if (!$task) {
                echo "❌ ОШИБКА: Не удалось декодировать JSON задачи\n";
                echo "📋 Сырой JSON: " . $taskJson . "\n";
                continue;
            }
            $logger->log("Обрабатываем задачу: " . $taskJson);
            echo "🔄 Обрабатываем задачу для Object ID: " . $task['objectID'] . "\n";
            echo "📋 Данные задачи: " . json_encode($task) . "\n";

            // Задача уже в правильной очереди для данного МП, обрабатываем её
            echo "🔄 Обрабатываем задачу для МП $MP_ID\n";

            // Вызываем функцию создания счета
            echo "🚀 Запускаем создание счета...\n";
            try {
                $result = call_user_func($HANDLER_FUNCTION, $task, $logger);

                echo "📊 Результат создания счета: " . json_encode($result) . "\n";

                if ($result['success']) {
                    echo "✅ Счет создан успешно, перемещаем в успешные\n";
                    // Перемещаем в успешные
                    moveTaskToSuccess($task, $result['invoiceId'], $redis, $logger, $MP_ID);
                } else {
                    echo "❌ Создание счета неуспешно, перемещаем в повторы/ошибки\n";
                    // Перемещаем в повторы или ошибки
                    handleTaskFailure($task, $result['error'], $redis, $logger, $MP_ID);
                }
            } catch (Exception $e) {
                $logger->log("Ошибка при создании счета: " . $e->getMessage());
                handleTaskFailure($task, $e->getMessage(), $redis, $logger, $MP_ID);
            }

            // Обновляем время последней задачи и флаг
            $lastTaskTime = time();
            $hadTasks = true;
        } else {
            echo "❌ Задача не найдена в очереди: $queueKey\n";
            echo "📋 Длина очереди: " . $redis->lLen($queueKey) . "\n";
            echo "📋 Проверяем содержимое очереди...\n";
            $queueContents = $redis->lRange($queueKey, 0, -1);
            echo "📋 Содержимое очереди: " . json_encode($queueContents) . "\n";
            echo "📋 Количество элементов в очереди: " . count($queueContents) . "\n";
            echo "💤 Ждем следующую итерацию...\n";
        }

    } catch (Exception $e) {
        $logger->log("ОШИБКА в основном цикле воркера: " . $e->getMessage());
        echo "❌ ОШИБКА: " . $e->getMessage() . "\n";
        echo "📋 Стек вызовов: " . $e->getTraceAsString() . "\n";
        echo "📋 Итерация: #$iterationCount\n";
        echo "📋 Время ошибки: " . date('Y-m-d H:i:s') . "\n";
        echo "📋 Проверяем состояние Redis...\n";
        try {
            $redis->ping();
            echo "✅ Redis доступен\n";
        } catch (Exception $redisError) {
            echo "❌ Redis недоступен: " . $redisError->getMessage() . "\n";
        }
        echo "💤 Ждем 10 секунд перед повтором...\n";
        echo "📋 Следующая итерация будет #" . ($iterationCount + 1) . "\n";
        echo "📋 Время ожидания: " . date('Y-m-d H:i:s') . "\n";
        echo "📋 Продолжаем работу...\n";
        echo "📋 Конец обработки ошибки\n";
        sleep(10); // Ждем дольше при ошибке
    }
}


/**
 * Переместить задачу в список успешных
 */
function updateProgressInQueue($task, $redis, $logger, $groupid) {
    try {
        echo "🔄 Обновляем прогресс задачи Object ID {$task['objectID']} для МП $groupid...\n";
        // Формируем ключ для очереди прогресса конкретного МП
        $progressKey = "task:invoice:progress:mp{$groupid}";

        // Получаем общее количество задач в очереди прогресса
        $totalTasks = $redis->lLen($progressKey);
        echo "📊 Всего задач в очереди прогресса: $totalTasks\n";

        if ($totalTasks == 0) {
            echo "⚠️ Очередь прогресса пуста для Object ID {$task['objectID']}\n";
            return;
        }

        // Обрабатываем задачи порциями для экономии памяти
        $batchSize = 500; // Размер порции
        $updated = false;
        $taskMonths = is_array($task['months']) ? implode(',', $task['months']) : $task['months'];
        $taskEntityTypes = is_array($task['entityTypes']) ? implode(',', $task['entityTypes']) : $task['entityTypes'];

        // Обрабатываем порциями
        for ($start = 0; $start < $totalTasks && !$updated; $start += $batchSize) {
            $end = min($start + $batchSize - 1, $totalTasks - 1);

            // Загружаем только текущую порцию задач
            $progressTasks = $redis->lRange($progressKey, $start, $end);

            foreach ($progressTasks as $relativeIndex => $progressTaskJson) {
                $absoluteIndex = $start + $relativeIndex;
                $progressTask = json_decode($progressTaskJson, true);

                if (!$progressTask) {
                    continue;
                }

                // Ищем задачу по objectID и другим параметрам
                // Сравниваем массивы как строки для более надежного поиска
                $progressMonths = is_array($progressTask['months']) ? implode(',', $progressTask['months']) : $progressTask['months'];
                $progressEntityTypes = is_array($progressTask['entityTypes']) ? implode(',', $progressTask['entityTypes']) : $progressTask['entityTypes'];

                if ($progressTask['objectID'] == $task['objectID'] &&
                    $progressMonths == $taskMonths &&
                    $progressEntityTypes == $taskEntityTypes) {

                    echo "✅ Найдена задача в очереди прогресса: Object ID {$task['objectID']}\n";
                    // Обновляем прогресс
                    $progressTask['processed'] = ($progressTask['processed'] ?? 0) + 1;
                    $progressTask['status'] = $progressTask['processed'] >= $progressTask['total'] ? 'Завершено' : 'В процессе';
                    $progressTask['lastUpdated'] = date('Y-m-d H:i:s');
                    echo "📊 Обновлен прогресс: {$progressTask['processed']}/{$progressTask['total']}, статус: {$progressTask['status']}\n";

                    // Заменяем задачу в очереди прогресса
                    $redis->lSet($progressKey, $absoluteIndex, json_encode($progressTask));
                    $updated = true;
                    $logger->log("📊 Прогресс обновлен для Object ID {$task['objectID']}: {$progressTask['processed']}/{$progressTask['total']}");
                    break;
                }
            }

            // Освобождаем память после обработки порции
            unset($progressTasks);

            // Принудительная очистка памяти, если доступна
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        if (!$updated) {
            echo "⚠️ Не удалось найти задачу в очереди прогресса для Object ID {$task['objectID']}\n";
            echo "🔍 Искали: months=" . $taskMonths . ", entityTypes=" . $taskEntityTypes . "\n";
            echo "📊 Всего задач в очереди прогресса: $totalTasks\n";
            $logger->log("⚠️ Не удалось найти задачу в очереди прогресса для Object ID {$task['objectID']}");
            $logger->log("🔍 Искали: months=" . $taskMonths . ", entityTypes=" . $taskEntityTypes);
            $logger->log("📊 Всего задач в очереди прогресса: $totalTasks");
        }
    } catch (Exception $e) {
        echo "❌ ОШИБКА при обновлении прогресса: " . $e->getMessage() . "\n";
        $logger->log("ОШИБКА при обновлении прогресса: " . $e->getMessage());
    }
}

function moveTaskToSuccess($task, $invoiceId, $redis, $logger, $groupid) {
    try {
        echo "✅ Перемещаем задачу Object ID {$task['objectID']} в успешные (Invoice ID: $invoiceId)\n";
        // Обновляем прогресс перед перемещением
        updateProgressInQueue($task, $redis, $logger, $groupid);

        $task['invoiceId'] = $invoiceId;
        $task['completedAt'] = date('Y-m-d H:i:s');

        $successKey = "task:invoice:success:mp{$groupid}";
        $redis->lPush($successKey, json_encode($task));
        echo "✅ Задача Object ID {$task['objectID']} перемещена в успешные (Счет ID: $invoiceId, МП: $groupid)\n";
        $logger->log("✅ Задача Object ID {$task['objectID']} перемещена в успешные (Счет ID: $invoiceId, МП: $groupid)");
    } catch (Exception $e) {
        echo "❌ ОШИБКА при перемещении в успешные: " . $e->getMessage() . "\n";
        $logger->log("ОШИБКА при перемещении в успешные: " . $e->getMessage());
    }
}

/**
 * Переместить задачу в список ошибок
 */
function moveTaskToErrors($task, $errorMessage, $redis, $logger, $groupid) {
    try {
        echo "❌ Перемещаем задачу Object ID {$task['objectID']} в ошибки: $errorMessage\n";
        $task['errorMessage'] = $errorMessage;
        $task['failedAt'] = date('Y-m-d H:i:s');

        $errorKey = "task:invoice:errors:mp{$groupid}";
        $redis->lPush($errorKey, json_encode($task));
        echo "❌ Задача Object ID {$task['objectID']} перемещена в ошибки (МП: $groupid): $errorMessage\n";
        $logger->log("❌ Задача Object ID {$task['objectID']} перемещена в ошибки (МП: $groupid): $errorMessage");
    } catch (Exception $e) {
        echo "❌ ОШИБКА при перемещении в ошибки: " . $e->getMessage() . "\n";
        $logger->log("ОШИБКА при перемещении в ошибки: " . $e->getMessage());
    }
}

/**
 * Обработать неудачу задачи (1 попытка повтора, затем ошибка)
 */
function handleTaskFailure($task, $errorMessage, $redis, $logger, $groupid) {
    $retryCount = $task['retryCount'] ?? 0;
    $retryCount++;

    echo "🔄 Обрабатываем неудачу задачи Object ID {$task['objectID']} (попытка $retryCount/2)\n";

    if ($retryCount <= 1) {
        echo "🔄 Перемещаем в повторы (попытка $retryCount/2)\n";
        // Обновляем прогресс в очереди прогресса
        updateProgressInQueue($task, $redis, $logger, $groupid);

        // Перемещаем в повторы
        $task['retryCount'] = $retryCount;
        $task['lastError'] = $errorMessage;
        $task['lastRetryAt'] = date('Y-m-d H:i:s');

        $retryKey = "task:invoice:retry:mp{$groupid}";
        $redis->lPush($retryKey, json_encode($task));
        echo "🔄 Задача Object ID {$task['objectID']} перемещена в повторы (попытка $retryCount/2, МП: $groupid): $errorMessage\n";
        $logger->log("🔄 Задача Object ID {$task['objectID']} перемещена в повторы (попытка $retryCount/2, МП: $groupid): $errorMessage");

        // НЕ возвращаем задачу в очередь автоматически - она будет обработана отдельным процессом
        echo "🔄 Задача Object ID {$task['objectID']} оставлена в очереди повторов для последующей обработки\n";
        $logger->log("🔄 Задача Object ID {$task['objectID']} оставлена в очереди повторов для последующей обработки");
    } else {
        echo "❌ Превышено максимальное количество попыток, перемещаем в ошибки\n";
        echo "📋 Количество попыток: $retryCount, максимальное: 2\n";
        // Перемещаем в ошибки
        echo "🔄 Перемещаем задачу в ошибки...\n";

        // Используем реальную ошибку: сначала из текущего вызова, потом из lastError, потом дефолт
        $finalErrorMessage = $errorMessage;
        if (empty($finalErrorMessage) && isset($task['lastError'])) {
            $finalErrorMessage = $task['lastError'];
        }
        if (empty($finalErrorMessage)) {
            $finalErrorMessage = 'Неизвестная ошибка';
        }

        echo "📋 Сообщение об ошибке: $finalErrorMessage\n";
        echo "📋 Данные задачи: " . json_encode($task) . "\n";
        echo "🔄 Вызываем moveTaskToErrors...\n";
        moveTaskToErrors($task, $finalErrorMessage, $redis, $logger, $groupid);
    }
}

?>
