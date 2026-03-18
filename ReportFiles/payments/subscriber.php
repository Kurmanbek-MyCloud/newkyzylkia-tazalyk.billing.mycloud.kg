<?php
require_once 'db.php';

// Получаем параметры из GET-запроса
$system = isset($_GET['system']) ? $_GET['system'] : null;
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : null;
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
$offset = ($page - 1) * $limit;
$payment_systems = getPaymentSystems();

// Если даты не указаны, ставим NULL (или можно взять, например, последние 30 дней)
if (empty($startDate)) {
    $startDate = null; // или date('Y-m-d', strtotime('-30 days'));
}
if (empty($endDate)) {
    $endDate = null; // или date('Y-m-d');
}

// Получаем данные о платежах (даже если даты не заданы)
$paymentData = getNew( $startDate, $endDate, $sortColumn, $sortOrder, $limit, $offset);


// Рассчитываем начальный порядковый номер записи для текущей страницы
$startIndex = ($page - 1) * $limit + 1;

?>  

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список новых абонентов</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <h1>Список новых абонентов</h1>
    <form method="GET">

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

    <h2>Список новых абонентов</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>ЛС</th>
                <th>Собственник</th>
                <th>Улица</th>
                <th>№ дома</th>
                <th>Литера</th>

            </tr>
        </thead>
        <tbody>
            <?php foreach ($paymentData as $index => $payment): ?>
                <tr>
                    <td><?= $startIndex + $index ?></td>
                    <td><?= htmlspecialchars($payment['estate_number']) ?></td>
                    <td><?= htmlspecialchars($payment['cf_lastname']) ?></td>
                    <td><?= htmlspecialchars($payment['cf_streets']) ?></td>
                    <td><?= htmlspecialchars($payment['cf_house_number']) ?></td>
                    <td><?= htmlspecialchars($payment['cf_litera']) ?></td>
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
            window.location.href = 'new_sub_excel_export.php?' + $.param(params);
        });
    </script>
</body>

</html>