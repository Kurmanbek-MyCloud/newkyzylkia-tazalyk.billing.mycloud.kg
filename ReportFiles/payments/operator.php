<?php
require_once 'db.php';

// Получаем параметры из GET-запроса
$system = $_GET['system'] ?? null;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$sortColumn = $_GET['sort_column'] ?? null;
$sortOrder = $_GET['sort_order'] ?? 'DESC';
$limit = $_GET['limit'] ?? 10;
$offset = $_GET['offset'] ?? 0;

// Вызов функции получения данных
$paymentData = getPayments($system, $startDate, $endDate, $sortColumn, $sortOrder, $limit, $offset);
// var_dump($paymentData);
// exit();
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отчеты по платежам</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <h1>Отчеты по платежам</h1>
    <form method="GET">
        <select id="system-select" name="system">
            <option value="">Выберите платежного оператора</option>
            <?php if ($payment_systems) {
                foreach ($payment_systems as $payment_system) {
                    $system_name = htmlspecialchars($payment_system['cf_payment_source']);
                    $selected = ($system_name == $system) ? 'selected' : '';
                    echo '<option value="' . $system_name . '" ' . $selected . '>' . $system_name . '</option>';
                }
            } else {
                echo '<option value="">Нет доступных операторов</option>';
            } ?>
        </select>
        <div>
            <label>Дата начала: <input type="date" name="start_date"
                    value="<?= htmlspecialchars($startDate) ?>"></label>
            <label>Дата окончания: <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>"></label>
        </div>
        <label>Количество записей на странице:
            <select name="limit" onchange="this.form.submit()">
                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                <option value="250" <?= $limit == 250 ? 'selected' : '' ?>>250</option>
                <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
            </select>
        </label>
        <button type="submit">Применить фильтры</button>
        <div>
            <h3>Количество платежей: <span id="total"><?= htmlspecialchars($totalRecords['count']) ?></span></h3>
            <h3>Итоговая сумма: <span id="total-amount"><?= htmlspecialchars($totalRecords['totalAmount']) ?> сом</span>
            </h3>
        </div>
        <div>
            <button id="excel_export" type="button">Экспорт в Excel</button>
        </div>
        <div>
            <p>Страница <?= $page ?> из <?= $totalPages ?></p>
            <nav>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li><a
                                href="?page=<?php echo $page - 1; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&system=<?php echo urlencode($system); ?>&sort=<?php echo urlencode($sortColumn); ?>&order=<?php echo urlencode($sortOrder); ?>&limit=<?php echo urlencode($limit); ?>">&laquo;</a>
                        </li>
                    <?php endif; ?>

                    <?php if ($page > 2): ?>
                        <li><a
                                href="?page=1&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&system=<?php echo urlencode($system); ?>&sort=<?php echo urlencode($sortColumn); ?>&order=<?php echo urlencode($sortOrder); ?>&limit=<?php echo urlencode($limit); ?>">1</a>
                        </li>
                        <?php if ($page > 3): ?>
                            <li><span>...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($page - 1 > 0): ?>
                        <li><a
                                href="?page=<?php echo $page - 1; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&system=<?php echo urlencode($system); ?>&sort=<?php echo urlencode($sortColumn); ?>&order=<?php echo urlencode($sortOrder); ?>&limit=<?php echo urlencode($limit); ?>"><?php echo $page - 1; ?></a>
                        </li>
                    <?php endif; ?>

                    <li><strong><?php echo $page; ?></strong></li>

                    <?php if ($page + 1 <= $totalPages): ?>
                        <li><a
                                href="?page=<?php echo $page + 1; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&system=<?php echo urlencode($system); ?>&sort=<?php echo urlencode($sortColumn); ?>&order=<?php echo urlencode($sortOrder); ?>&limit=<?php echo urlencode($limit); ?>"><?php echo $page + 1; ?></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($page < $totalPages - 1): ?>
                        <?php if ($page < $totalPages - 2): ?>
                            <li><span>...</span></li>
                        <?php endif; ?>
                        <li><a
                                href="?page=<?php echo $totalPages; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&system=<?php echo urlencode($system); ?>&sort=<?php echo urlencode($sortColumn); ?>&order=<?php echo urlencode($sortOrder); ?>&limit=<?php echo urlencode($limit); ?>"><?php echo $totalPages; ?></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                        <li><a
                                href="?page=<?php echo $page + 1; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&system=<?php echo urlencode($system); ?>&sort=<?php echo urlencode($sortColumn); ?>&order=<?php echo urlencode($sortOrder); ?>&limit=<?php echo urlencode($limit); ?>">&raquo;</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </form>

    <h2>Список платежей</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Наименование абонента</th>
                <th>Платежный оператор</th>
                <th>Итоговая сумма</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($paymentData['payments'] as $index => $payment): ?>
                <tr>
                    <td><?= $startIndex + $index ?></td>
                    <td><?= htmlspecialchars($payment['cf_client_name']) ?></td>
                    <td><?= htmlspecialchars($payment['cf_payment_source']) ?></td>
                    <td><?= htmlspecialchars($payment['amount']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Список должников</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Лицевой счет</th>
                <th>Наименование абонента</th>
                <th>Номера абонента</th>
                <th>Суммы задолженности по номерам</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($debtorsData as $index => $debtor): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($debtor['cf_contract_id']) ?></td>
                    <td><?= htmlspecialchars($debtor['cf_client_name']) ?></td>
                    <td><?= htmlspecialchars(implode(', ', $debtor['cf_pay_no'])) ?></td>
                    <td><?= htmlspecialchars($debtor['amount']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        $('#excel_export').click(function (event) {
            event.preventDefault();
            let params = {
                start_date: $('input[name="start_date"]').val(),
                end_date: $('input[name="end_date"]').val(),
                system: $('select[name="system"]').val(),
                sort: '<?= $sortColumn ?>',
                order: '<?= $sortOrder ?>'
            };
            window.location.href = 'excel_export.php?' + $.param(params);
        });
    </script>
</body>

</html>