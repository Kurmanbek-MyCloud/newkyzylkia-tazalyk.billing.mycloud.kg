<?php
namespace VtigerTests;

// Подключаем класс TelephonyTests
require_once __DIR__ . '/TestCases.php';

// Путь к PHPUnit
$phpunit = __DIR__ . '/../vendor/bin/phpunit';
$testFile = __DIR__ . '/TelephonyTestsAdapter.php';

// Проверяем наличие PHPUnit
if (!file_exists($phpunit)) {
    // Если PHPUnit не найден в vendor, пробуем использовать глобальную установку
    $phpunit = 'phpunit';
}

// Запускаем PHPUnit с указанным тестовым файлом
$command = "$phpunit --colors=always $testFile";
$output = [];
$returnStatus = 0;

// Выполняем команду и получаем вывод
exec($command, $output, $returnStatus);

// Объединяем вывод в строку
$outputString = implode("\n", $output);

// Определяем, успешно ли прошли тесты
$success = $returnStatus === 0;
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Результаты PHPUnit тестов</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        pre {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 0.25rem;
            white-space: pre-wrap;
        }

        .test-output {
            margin-top: 20px;
        }

        .success {
            color: #28a745;
        }

        .failure {
            color: #dc3545;
        }

        .data-source-info {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="container mt-4">
        <h1 class="mb-4">Результаты PHPUnit тестов</h1>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <h4 class="alert-heading">Тесты выполнены успешно!</h4>
                <p>Все тесты прошли без ошибок.</p>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <h4 class="alert-heading">Ошибка при выполнении тестов!</h4>
                <p>Некоторые тесты завершились с ошибками. Подробности смотрите ниже.</p>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5>Информация о тестах</h5>
            </div>
            <div class="card-body">
                <p><strong>Команда:</strong> <?php echo htmlspecialchars($command); ?></p>
                <p><strong>Статус выполнения:</strong> <?php echo $success ? 'Успешно' : 'Ошибка'; ?></p>
                <p><strong>Код возврата:</strong> <?php echo $returnStatus; ?></p>
                <p><strong>Источник данных:</strong> Реальные данные</p>
            </div>
        </div>

        <div class="test-output">
            <h3>Вывод тестов:</h3>
            <pre><?php echo htmlspecialchars($outputString); ?></pre>
        </div>

        <div class="mt-4">
            <a href="test_interface.php" class="btn btn-primary">Вернуться к интерфейсу тестирования</a>
        </div>
    </div>
</body>

</html>