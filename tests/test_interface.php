<?php
namespace VtigerTests;

// Подключаем класс для тестирования
require_once 'TestCases.php';

// Обрабатываем POST-запрос
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные из формы
    $testData = [
        'phoneNumber' => $_POST['phoneNumber'] ?? '',
        'contactType' => $_POST['contactType'] ?? 'Физ. лицо',
        'duration' => (int) ($_POST['duration'] ?? 60),
        'usdRate' => (float) ($_POST['usdRate'] ?? 89.5),
        'testType' => $_POST['testType'] ?? 'all'
    ];

    // Создаем экземпляр класса для тестирования
    $telephonyTests = new TelephonyTests($testData);

    // Запускаем тесты
    $testResults = $telephonyTests->runTests();
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тестирование телефонии</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .tab-content {
            padding: 20px;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 0.25rem 0.25rem;
        }

        .test-result {
            margin-bottom: 20px;
        }

        .test-step {
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 0.25rem;
        }

        .test-step-info {
            background-color: #e2f0fd;
        }

        .test-step-success {
            background-color: #d4edda;
        }

        .test-step-error {
            background-color: #f8d7da;
        }

        pre {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 0.25rem;
        }
    </style>
</head>

<body>
    <div class="container mt-4">
        <h1 class="mb-4">Тестирование телефонии</h1>

        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual"
                    type="button" role="tab" aria-controls="manual" aria-selected="true">Ручное тестирование</button>
            </li>
            <!-- <li class="nav-item" role="presentation">
                <button class="nav-link" id="phpunit-tab" data-bs-toggle="tab" data-bs-target="#phpunit" type="button"
                    role="tab" aria-controls="phpunit" aria-selected="false">PHPUnit тесты</button>
            </li> -->
        </ul>

        <div class="tab-content" id="myTabContent">
            <!-- Вкладка ручного тестирования -->
            <div class="tab-pane fade show active" id="manual" role="tabpanel" aria-labelledby="manual-tab">
                <div class="row">
                    <div class="col-md-6">
                        <h3>Параметры теста</h3>
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="phoneNumber" class="form-label">Номер телефона</label>
                                <input type="text" class="form-control" id="phoneNumber" name="phoneNumber"
                                    value="<?php echo $_POST['phoneNumber'] ?? '74992627510'; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="contactType" class="form-label">Тип контакта</label>
                                <select class="form-select" id="contactType" name="contactType">
                                    <option value="Физ. лицо" <?php echo ($_POST['contactType'] ?? '') === 'Физ. лицо' ? 'selected' : ''; ?>>Физ. лицо</option>
                                    <option value="Юр. лицо" <?php echo ($_POST['contactType'] ?? '') === 'Юр. лицо' ? 'selected' : ''; ?>>Юр. лицо</option>
                                    <option value="КТЖ" <?php echo ($_POST['contactType'] ?? '') === 'КТЖ' ? 'selected' : ''; ?>>КТЖ</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="duration" class="form-label">Длительность звонка (сек)</label>
                                <input type="number" class="form-control" id="duration" name="duration"
                                    value="<?php echo $_POST['duration'] ?? '60'; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="usdRate" class="form-label">Курс доллара</label>
                                <input type="number" class="form-control" id="usdRate" name="usdRate" step="0.01"
                                    value="<?php echo $_POST['usdRate'] ?? '89.5'; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="testType" class="form-label">Тип теста</label>
                                <select class="form-select" id="testType" name="testType">
                                    <option value="all" <?php echo ($_POST['testType'] ?? '') === 'all' ? 'selected' : ''; ?>>Все тесты</option>
                                    <option value="prefix" <?php echo ($_POST['testType'] ?? '') === 'prefix' ? 'selected' : ''; ?>>Только определение префикса</option>
                                    <option value="tariff" <?php echo ($_POST['testType'] ?? '') === 'tariff' ? 'selected' : ''; ?>>Только поиск тарифа</option>
                                    <option value="cost" <?php echo ($_POST['testType'] ?? '') === 'cost' ? 'selected' : ''; ?>>Только расчет стоимости</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">Запустить тест</button>
                        </form>
                    </div>

                    <div class="col-md-6">
                        <?php if (isset($testResults)): ?>
                            <h3>Результаты теста</h3>
                            <?php if (!$testResults['success']): ?>
                                <div class="alert alert-danger">
                                    <?php echo htmlspecialchars($testResults['message']); ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <?php echo htmlspecialchars($testResults['message']); ?>
                                </div>
                            <?php endif; ?>

                            <div class="test-result">
                                <?php foreach ($testResults['steps'] as $step): ?>
                                    <div class="test-step test-step-<?php echo $step['status']; ?>">
                                        <h5><?php echo htmlspecialchars($step['name']); ?></h5>
                                        <pre><?php echo is_array($step['output']) ? json_encode($step['output'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : htmlspecialchars($step['output']); ?></pre>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Вкладка PHPUnit тестов -->
            <div class="tab-pane fade" id="phpunit" role="tabpanel" aria-labelledby="phpunit-tab">
                <h3>Запуск PHPUnit тестов</h3>
                <p>Для запуска PHPUnit тестов выполните следующую команду:</p>
                <pre>cd <?php echo dirname(__DIR__); ?> && vendor/bin/phpunit tests/TelephonyTestsAdapter.php</pre>

                <p>Или нажмите кнопку ниже для запуска тестов через веб-интерфейс:</p>
                <form action="run_phpunit.php" method="post">
                    <button type="submit" class="btn btn-primary">Запустить PHPUnit тесты</button>
                </form>

                <h4 class="mt-4">Доступные тесты:</h4>
                <ul>
                    <li><strong>testPrefixDetection</strong> - Тест определения префикса</li>
                    <li><strong>testTariffCalculation</strong> - Тест расчета тарифа</li>
                    <li><strong>testCallCostCalculation</strong> - Тест расчета стоимости звонка</li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>