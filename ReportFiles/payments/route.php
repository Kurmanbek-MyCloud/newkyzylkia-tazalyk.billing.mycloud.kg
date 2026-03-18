<?php
require_once 'db.php';
require_once 'vendor/autoload.php'; // Библиотека для PDF (например, Dompdf)

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// Получение параметров
$system = $_GET['system'] ?? null;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$sortColumn = $_GET['sort_column'] ?? null;
$sortOrder = $_GET['sort_order'] ?? 'DESC';
$limit = is_numeric($_GET['limit'] ?? null) ? (int)$_GET['limit'] : 10;
$page = is_numeric($_GET['page'] ?? null) ? (int)$_GET['page'] : 1;
$streetFilter = $_GET['streets'] ?? null; // Улица, выбранная пользователем
$houseNumberFilter = $_GET['house_number'] ?? null; // Номер дома, выбранный пользователем
$offset = ($page - 1) * $limit;

if (isset($_GET['export_pdf']) && $_GET['export_pdf'] == 1) {
    // Получаем все данные с фильтрами, передаем фильтры улицы и дома
    $paymentData = getRoute($limit, $offset, $streetFilter, $houseNumberFilter); // передаем все фильтры
    exportToPDF($paymentData); // Вызываем функцию экспорта
    exit;
}







// Расчёт смещения

// Получение данных
$payment_systems = getPaymentSystems();
$paymentData = getRoute($limit, $offset, $streetFilter, $houseNumberFilter); // передаем все фильтры
$totalRecords = countPayments($system, $startDate, $endDate);
$totalPages = ceil($totalRecords['count'] / $limit);
$streets = getStreets(); // Получение улиц
$houseNumbers = !empty($streetFilter) ? getHouseNumbers($streetFilter) : [];

?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Маршрутный лист</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
<h1>Маршрутный лист</h1>
<form method="GET">

    <label>Улица:</label>
    <select name="streets" onchange="this.form.submit()">
        <option value="">Выберите улицу</option>
        <?php foreach ($streets as $street): ?>
            <option value="<?= htmlspecialchars($street['street']) ?>" <?= $streetFilter == $street['street'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($street['street']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Номер дома:</label>
    <select name="house_number" onchange="this.form.submit()">
        <option value="">Выберите дом</option>
        <?php if (!empty($houseNumbers)): ?>
            <?php foreach ($houseNumbers as $house): ?>
                <option value="<?= htmlspecialchars($house['house_number']) ?>" <?= $houseNumberFilter == $house['house_number'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($house['house_number']) ?>
                </option>
            <?php endforeach; ?>
        <?php endif; ?>
    </select>

    <label>Количество записей на странице:</label>
    <input type="number" class="limit-input" name="limit" value="<?= isset($limit) ? (int)$limit : 10 ?>" min="1" placeholder="Введите число">
    <button type="submit">Обновить</button>

    <button type="submit" name="export_pdf" value="1">Выгрузить</button>

</form>

<h2>Маршрутный лист</h2>
<table border="1" cellpadding="5" cellspacing="0" width="100%">
    <thead>
        <tr>
            <th width="5%">№</th>
            <th width="10%">ЛС</th>
            <th width="15%">Улица</th>
            <th width="10%">№ дома</th>
            <th width="10%">Кв (Литера)</th>
            <th width="10%">Производитель счетчика</th>
            <th width="5%">Значность</th>
            <th width="10%">Номер счетчика</th>
            <th width="10%">Последнее показание</th>
            <th width="10%">Текущее показание</th>
            <th width="15%">ФИО абонента</th>
            <th width="10%">Текущий баланс</th>
            <th width="10%">Дата последнего платежа</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($paymentData)): ?>
            <?php foreach ($paymentData as $index => $row): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($row['estate_number']) ?></td>
                    <td><?= htmlspecialchars($row['street']) ?></td>
                    <td><?= htmlspecialchars($row['house_number']) ?></td>
                    <td><?= htmlspecialchars($row['apartment']) ?></td>
                    <td><?= htmlspecialchars($row['meter_manufacturer']) ?></td>
                    <td><?= htmlspecialchars($row['capacity']) ?></td>
                    <td><?= htmlspecialchars($row['meter_number']) ?></td>
                    <td><?= htmlspecialchars($row['last_reading']) ?></td>
                    <td><?= htmlspecialchars($row['current_reading'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['subscriber_name']) ?></td>
                    <td><?= htmlspecialchars($row['cf_balance']) ?></td>
                    <td><?= htmlspecialchars($row['last_payment_date']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="13">Данные отсутствуют</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>