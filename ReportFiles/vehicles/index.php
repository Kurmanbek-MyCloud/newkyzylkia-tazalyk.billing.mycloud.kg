<?php
require_once 'db.php';

// Получаем параметры из GET-запроса
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : null;
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100; // Количество записей на странице
$offset = ($page - 1) * $limit;

$paidFilter = isset($_GET['paid_filter']) ? $_GET['paid_filter'] : 'true'; // По умолчанию - показать оплаченные
$unpaidFilter = isset($_GET['unpaid_filter']) ? $_GET['unpaid_filter'] : 'true'; // По умолчанию - показать не оплаченные

// Если даты не указаны, запрос не выполняется
if (!empty($startDate) || !empty($endDate)) {
    // Получаем данные о машинах с учетом фильтров
    $vehicleData = getCars($startDate, $endDate, $sortColumn, $sortOrder, $limit, $offset, $paidFilter === 'true', $unpaidFilter === 'true');

    // Подсчитываем общее количество записей для пагинации
    $totalRecords = countCars($startDate, $endDate, $paidFilter === 'true', $unpaidFilter === 'true');
}

$totalPages = ceil($totalRecords['count'] / $limit);
// Рассчитываем начальный порядковый номер записи для текущей страницы
$startIndex = ($page - 1) * $limit + 1;

?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Отчет по машинам</title>
    <link rel="stylesheet" href="styles.css"> <!-- Подключение стилей -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <h1>Унаалар боюнча отчет</h1>

    <form method="GET">
        <label>Дата начала: <input type="date" name="start_date"
                value="<?php echo htmlspecialchars($startDate); ?>"></label>
        <label>Дата окончания: <input type="date" name="end_date"
                value="<?php echo htmlspecialchars($endDate); ?>"></label>
        <!-- Добавляем выбор количества записей на странице -->
        <div><label>Бир беттеги жазуулардын саны:
                <select name="limit" onchange="this.form.submit()">
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                    <option value="250" <?php echo $limit == 250 ? 'selected' : ''; ?>>250</option>
                    <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500</option>
                </select>
            </label>
            <button type="submit">Применить фильтры</button>
        </div>
        <div>
            <label><input type="checkbox" id="paid-filter" name="paid_filter" value="true" <?php echo $paidFilter === 'true' ? 'checked' : ''; ?>>Оплачен</label>
            <label><input type="checkbox" id="unpaid-filter" name="unpaid_filter" value="true" <?php echo $unpaidFilter === 'true' ? 'checked' : ''; ?>>Не оплачен</label>
        </div>
        <div>
            <h3>Унаалардын саны: <span id="total-count"><?= htmlspecialchars($totalRecords['count']) ?></span></h3>
            <h3>Төлөмдөрдүн суммасы: <span id="total-amount"><?= htmlspecialchars($totalRecords['totalAmount']) ?>
                    сом</span>
            </h3>
        </div>
        <div>
            <button id="excel_export" type="button">Экспорт в Excel</button>
        </div>
        <div>
            <p>Страница <?php echo $page; ?> из <?php echo $totalPages; ?></p>
            <nav>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li><a
                                href="?page=<?php echo $page - 1; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&sort=<?php echo $sortColumn; ?>&order=<?php echo $sortOrder; ?>&paid_filter=<?php echo urlencode($paidFilter); ?>&unpaid_filter=<?php echo urlencode($unpaidFilter); ?>&limit=<?php echo urlencode($limit); ?>">&laquo;</a>
                        </li>
                    <?php endif; ?>

                    <?php if ($page > 2): ?>
                        <li><a
                                href="?page=1&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&sort=<?php echo $sortColumn; ?>&order=<?php echo $sortOrder; ?>&paid_filter=<?php echo urlencode($paidFilter); ?>&unpaid_filter=<?php echo urlencode($unpaidFilter); ?>&limit=<?php echo urlencode($limit); ?>">1</a>
                        </li>
                        <?php if ($page > 3): ?>
                            <li><span>...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($page - 1 > 0): ?>
                        <li><a
                                href="?page=<?php echo $page - 1; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&sort=<?php echo $sortColumn; ?>&order=<?php echo $sortOrder; ?>&paid_filter=<?php echo urlencode($paidFilter); ?>&unpaid_filter=<?php echo urlencode($unpaidFilter); ?>&limit=<?php echo urlencode($limit); ?>"><?php echo $page - 1; ?></a>
                        </li>
                    <?php endif; ?>

                    <li><strong><?php echo $page; ?></strong></li>

                    <?php if ($page + 1 <= $totalPages): ?>
                        <li><a
                                href="?page=<?php echo $page + 1; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&sort=<?php echo $sortColumn; ?>&order=<?php echo $sortOrder; ?>&paid_filter=<?php echo urlencode($paidFilter); ?>&unpaid_filter=<?php echo urlencode($unpaidFilter); ?>&limit=<?php echo urlencode($limit); ?>"><?php echo $page + 1; ?></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($page < $totalPages - 1): ?>
                        <?php if ($page < $totalPages - 2): ?>
                            <li><span>...</span></li>
                        <?php endif; ?>
                        <li><a
                                href="?page=<?php echo $totalPages; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&sort=<?php echo $sortColumn; ?>&order=<?php echo $sortOrder; ?>&paid_filter=<?php echo urlencode($paidFilter); ?>&unpaid_filter=<?php echo urlencode($unpaidFilter); ?>&limit=<?php echo urlencode($limit); ?>"><?php echo $totalPages; ?></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                        <li><a
                                href="?page=<?php echo $page + 1; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&sort=<?php echo $sortColumn; ?>&order=<?php echo $sortOrder; ?>&paid_filter=<?php echo urlencode($paidFilter); ?>&unpaid_filter=<?php echo urlencode($unpaidFilter); ?>&limit=<?php echo urlencode($limit); ?>">&raquo;</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th><a
                        href="?sort=car_number&order=<?php echo $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&paid_filter=<?php echo urlencode($paidFilter); ?>&unpaid_filter=<?php echo urlencode($unpaidFilter); ?>&limit=<?php echo urlencode($limit); ?>&page=<?php echo $page; ?>">Унаанын
                        номери</a></th>
                <th><a
                        href="?sort=invoicestatus&order=<?php echo $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&paid_filter=<?php echo urlencode($paidFilter); ?>&unpaid_filter=<?php echo urlencode($unpaidFilter); ?>&limit=<?php echo urlencode($limit); ?>&page=<?php echo $page; ?>">Төлөм
                        статусу</a></th>
                <th><a
                        href="?sort=check_in&order=<?php echo $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&paid_filter=<?php echo urlencode($paidFilter); ?>&unpaid_filter=<?php echo urlencode($unpaidFilter); ?>&limit=<?php echo urlencode($limit); ?>&page=<?php echo $page; ?>">Кирүү
                        убактысы</a></th>
                <th><a
                        href="?sort=check_out&order=<?php echo $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&paid_filter=<?php echo urlencode($paidFilter); ?>&unpaid_filter=<?php echo urlencode($unpaidFilter); ?>&limit=<?php echo urlencode($limit); ?>&page=<?php echo $page; ?>">Чыгуу
                        убактысы</a></th>
                <th><a
                        href="?sort=shed_status&order=<?php echo $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&paid_filter=<?php echo urlencode($paidFilter); ?>&unpaid_filter=<?php echo urlencode($unpaidFilter); ?>&limit=<?php echo urlencode($limit); ?>&page=<?php echo $page; ?>">Навес</a>
                </th>
                <th><a
                        href="?sort=seller_status&order=<?php echo $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&paid_filter=<?php echo urlencode($paidFilter); ?>&unpaid_filter=<?php echo urlencode($unpaidFilter); ?>&limit=<?php echo urlencode($limit); ?>&page=<?php echo $page; ?>">Сатуучу</a>
                </th>
                <th><a
                        href="?sort=amount&order=<?php echo $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&paid_filter=<?php echo urlencode($paidFilter); ?>&unpaid_filter=<?php echo urlencode($unpaidFilter); ?>&limit=<?php echo urlencode($limit); ?>&page=<?php echo $page; ?>">Сумма</a>
                </th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($vehicleData['cars'] as $index => $car): ?>
                <tr>
                    <td><?php echo $startIndex + $index; ?></td> <!-- Выводим порядковый номер -->
                    <td><?php echo htmlspecialchars($car['car_number']); ?></td>
                    <td><?php echo htmlspecialchars($car['invoicestatus']); ?></td>
                    <td><?php echo htmlspecialchars($car['check_in']); ?></td>
                    <td><?php echo htmlspecialchars($car['check_out']); ?></td>
                    <td><?php echo htmlspecialchars($car['shed_status']); ?></td>
                    <td><?php echo htmlspecialchars($car['seller_status']); ?></td>
                    <td><?php echo htmlspecialchars($car['amount']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>



    <script>
        // Обработчик для изменения фильтров
        $('#paid-filter, #unpaid-filter').on('change', function () {
            const paidChecked = $('#paid-filter').is(':checked');
            const unpaidChecked = $('#unpaid-filter').is(':checked');

            const url = new URL(window.location.href);
            url.searchParams.set('paid_filter', paidChecked);
            url.searchParams.set('unpaid_filter', unpaidChecked);
            url.searchParams.set('page', 1); // Сбрасываем на первую страницу
            window.location.href = url.href; // Перенаправляем на обновленный URL
        });
        $('#excel_export').click(function (event) {
            event.preventDefault(); // предотвращаем стандартное поведение кнопки
            let startDate = $('input[name="start_date"]').val();
            let endDate = $('input[name="end_date"]').val();
            let paidFilter = $('#paid-filter').is(':checked') ? 'true' : 'false';
            let unpaidFilter = $('#unpaid-filter').is(':checked') ? 'true' : 'false';

            // Формируем URL для запроса на сервер
            let url = 'excel_export.php';
            let params = {
                start_date: startDate,
                end_date: endDate,
                sort: '<?php echo $sortColumn; ?>',
                order: '<?php echo $sortOrder; ?>',
                paid_filter: paidFilter,
                unpaid_filter: unpaidFilter
            };

            // Переходим по URL с параметрами для генерации отчета
            let query = $.param(params); // Преобразуем объект параметров в строку запроса
            window.location.href = url + '?' + query;
        });
    </script>
</body>

</html>