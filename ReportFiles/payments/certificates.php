<?php
require_once 'db.php';
require_once 'vendor/autoload.php'; 

// Получаем значения для отчета и фильтров
$reportType = $_GET['report_type'] ?? 'consumption';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$limit = is_numeric($_GET['limit'] ?? null) ? (int)$_GET['limit'] : 10;
$page = is_numeric($_GET['page'] ?? null) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Получаем данные для населенных пунктов
$settlements = getSettlements();
$settlementFilter = $_GET['settlement'] ?? null;

// Определяем доступные отчеты
$reports = [
    'consumption' => 'Расход электроэнергии',
    'consumption_addresses' => 'Расход по адресам',
    'consumption_subscribers' => 'Расход по абонентам',
    'penalty_total' => 'Общее начисление пени',
    'penalty_subscribers' => 'Начисление пени по абонентам',
    'penalty_calculation' => 'Полный расчет пени',
    'debt_total' => 'Общая дебиторская задолженность',
    'debt_addresses' => 'Дебиторская задолженность по адресам',
    'debt_subscribers' => 'Дебиторская задолженность по абонентам',
    'hallway_lighting' => 'Расход подъездного освещения',
    'street_lighting' => 'Расход уличного освещения',
    'household_list' => 'Список бытовых абонентов',
    'single_phase_list' => 'Список абонентов с однофазным вводом',
    'three_phase_list' => 'Список абонентов с трехфазным вводом',
    'three_phase_meters' => 'Список трехфазных электросчетчиков',
    'single_phase_meters' => 'Список однофазных электросчетчиков'
];

// Получаем данные отчета
$paymentData = getReportData($reportType, $limit, $offset, $settlementFilter, $startDate, $endDate);

// Инициализация переменных для сумм
$totalConsumption = 0;
$totalAmount = 0;
$totalMopConsumption = 0;
$totalMopAmount = 0;
$totalApartments = 0;
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Справки</title>
    <link rel="stylesheet" href="./styles.css">
</head>
<style>
.input {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
    margin: 5px;
    font-size: 16px;
}

.input[type="number"] {
    -moz-appearance: textfield;  
    appearance: textfield;      
}

.input[type="number"]::-webkit-outer-spin-button,
.input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
</style>
<body>
<h1>Выберите справку</h1>
<form method="GET">
    <label>Тип справки:</label>
    <select name="report_type" onchange="this.form.submit()">
        <?php foreach ($reports as $key => $label): ?>
            <option value="<?= $key ?>" <?= $reportType == $key ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Населенный пункт:</label>
    <select name="settlement" onchange="this.form.submit()">
        <option value="">Выберите населенный пункт</option>
        <?php foreach ($settlements as $settlement): ?>
            <option value="<?= htmlspecialchars($settlement['settlement']) ?>" <?= $settlementFilter == $settlement['settlement'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($settlement['settlement']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Дата начала: <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>"></label>
    <label>Дата окончания: <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>"></label>

    <label>Лимит:</label>
    <input class="input" type="number" name="limit" value="<?= $limit ?>" min="1" max="1000" onchange="this.form.submit()">

    <button type="submit">Обновить</button>
</form>

<h2><?= $reports[$reportType] ?? 'Отчет' ?></h2>

<table border="1" cellpadding="5" cellspacing="0" width="100%">
    <thead>
        <tr>
            <th>#</th>
            <th>Улица</th>
            <th>Дом</th>
            <th>Фаза</th>
            <th>Расход кВт/час</th>
            <th>Расход сумма</th>
            <th>МОП кВт/час</th>
            <th>МОП сумма</th>
            <th>Кол-во квартир</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($paymentData as $index => $row): ?>
            <tr>
                <td><?= $index + 1 ?></td>
                <td><?= htmlspecialchars($row['street']) ?></td>
                <td><?= htmlspecialchars($row['house_number']) ?></td>
                <td><?= htmlspecialchars($row['phase']) ?></td>
                <td><?= htmlspecialchars($row['consumption']) ?></td>
                <td><?= htmlspecialchars($row['amount']) ?></td>
                <td><?= htmlspecialchars($row['mop_consumption']) ?></td>
                <td><?= htmlspecialchars($row['mop_amount']) ?></td>
                <td><?= htmlspecialchars($row['apartments']) ?></td>
            </tr>
            <?php 
            $totalConsumption += $row['consumption'];
            $totalAmount += $row['amount'];
            $totalMopConsumption += $row['mop_consumption'];
            $totalMopAmount += $row['mop_amount'];
            $totalApartments += $row['apartments'];
            ?>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="4">Итого</th>
            <th><?= $totalConsumption ?></th>
            <th><?= $totalAmount ?></th>
            <th><?= $totalMopConsumption ?></th>
            <th><?= $totalMopAmount ?></th>
            <th><?= $totalApartments ?></th>
        </tr>
    </tfoot>
</table>
</body>
</html>
