<?php
require_once 'db.php';

$streetFilter = $_GET['street'] ?? null;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$sortColumn = $_GET['sort_column'] ?? 'cf_contract_id';
$sortOrder = $_GET['sort_order'] ?? 'DESC';
$limit = $_GET['limit'] ?? 50;
$offset = $_GET['offset'] ?? 0;
$houseFilter = $_GET['house'] ?? null;
$onlyDebtors = isset($_GET['only_debtors']) && $_GET['only_debtors'] == '1';
$mp = $_GET['mp'] ?? null;
$municipals = getMunicipalEnterprises();


// Получаем данные
$paymentData = getDebtorb($startDate, $endDate, $sortColumn, $sortOrder, $limit, $offset, $streetFilter, $houseFilter, $onlyDebtors);
$streets = getStreets();
$houses = $streetFilter ? getHouseNumbers($streetFilter) : [];

// Итоговые суммы
$totals = [
    'startBalanceTotal' => array_sum(array_column($paymentData, 'start_balance')),
    'creditTotal' => array_sum(array_column($paymentData, 'invoice_amount')) + array_sum(array_column($paymentData, 'penalty_amount')),
    'debitTotal' => abs(array_sum(array_column($paymentData, 'payment_amount'))),
    'finalBalanceTotal' => array_sum(array_column($paymentData, 'end_balance')),
    'debtTotal' => array_sum(array_filter(array_column($paymentData, 'end_balance'), function ($value) {
        return $value > 0;
    }))
];
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отчеты по должникам</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
    th.asc::after {
        content: " 🔼";
    }
    th.desc::after {
        content: " 🔽";
    }
    </style>
</head>

<body>
    <h1>Отчеты по должникам</h1>
    <form method="GET">
        <div>
            <label>Дата начала:
                <input type="date" name="start_date" value="<?= htmlspecialchars($startDate ?? '') ?>">
            </label>
            <label>Дата окончания:
                <input type="date" name="end_date" value="<?= htmlspecialchars($endDate ?? '') ?>">
            </label>
        </div>

        <!-- Селектор улицы -->
        <label>Улица:
            <select name="street" onchange="this.form.submit()">
                <option value="">Все улицы</option>
                <?php foreach ($streets as $street): ?>
                    <option value="<?= htmlspecialchars($street['street'] ?? '') ?>" <?= isset($_GET['street']) && $_GET['street'] == $street['street'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($street['street'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <?php if (!empty($houses)): ?>
    <label>Дом:
        <select name="house" onchange="this.form.submit()">
            <option value="">Все дома</option>
            <?php foreach ($houses as $house): ?>
                <option value="<?= htmlspecialchars($house['house_number']) ?>" <?= $houseFilter === $house['house_number'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($house['house_number']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
<?php endif; ?>


        <!-- Селектор количества записей на странице -->
        <label>Количество записей на странице:
            <select name="limit" onchange="this.form.submit()">
                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                <option value="250" <?= $limit == 250 ? 'selected' : '' ?>>250</option>
                <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
            </select>
        </label>

        <label>
    <input type="checkbox" name="only_debtors" value="1" <?= $onlyDebtors ? 'checked' : '' ?> onchange="this.form.submit()">
    Только должники
</label>
<select name="mp" class="form-control">
    <option value="">Все МП</option>
    <?php foreach ($municipals as $m): ?>
        <option value="<?= $m['accountid'] ?>" <?= $mp == $m['accountid'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($m['accountname']) ?>
        </option>
    <?php endforeach; ?>
</select>


        <button type="submit">Применить фильтры</button>

        <!-- Итоговые суммы -->
        <div>
            <h3>Начальный баланс:
                <span><?= number_format($totals['startBalanceTotal'], 2, '.', ' ') ?> </span>
            </h3>
            <h3>+ Начисления:
                <span><?= number_format($totals['creditTotal'], 2, '.', ' ') ?> </span>
            </h3>
            <h3>- Платежи:
                <span><?= number_format($totals['debitTotal'], 2, '.', ' ') ?> </span>
            </h3>
            <h3>= Конечный баланс:
                <span style="font-weight: bold;">
                    <?= number_format($totals['finalBalanceTotal'], 2, '.', ' ') ?> 
                </span>
            </h3>
            <h3>Общая задолженность за период:
                <span style="font-weight: bold; color: #ff6b6b">
                    <?= number_format($totals['debtTotal'], 2, '.', ' ') ?> 
                </span>
            </h3>
        </div>

        <!-- Кнопка для экспорта в Excel -->
        <div>
            <button id="excel_export" type="button">Экспорт в Excel</button>
        </div>
    </form>

    <h2>Список должников</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th><a href="?<?= http_build_query(array_merge($_GET, ['sort_column' => 'cf_contract_id', 'sort_order' => ($sortColumn === 'cf_contract_id' && $sortOrder === 'ASC') ? 'DESC' : 'ASC'])) ?>">Лицевой счет</a></th>
                <th><a href="?<?= http_build_query(array_merge($_GET, ['sort_column' => 'cf_streets', 'sort_order' => ($sortColumn === 'cf_streets' && $sortOrder === 'ASC') ? 'DESC' : 'ASC'])) ?>">Улица</a></th>
                <th><a href="?<?= http_build_query(array_merge($_GET, ['sort_column' => 'cf_house_number', 'sort_order' => ($sortColumn === 'cf_house_number' && $sortOrder === 'ASC') ? 'DESC' : 'ASC'])) ?>">Номер дома</a></th>
                <th>Литера</th>
                <th><a href="?<?= http_build_query(array_merge($_GET, ['sort_column' => 'cf_client_name', 'sort_order' => ($sortColumn === 'cf_client_name' && $sortOrder === 'ASC') ? 'DESC' : 'ASC'])) ?>">Наименование абонента</a></th>
                <th><a href="?<?= http_build_query(array_merge($_GET, ['sort_column' => 'start_balance', 'sort_order' => ($sortColumn === 'start_balance' && $sortOrder === 'ASC') ? 'DESC' : 'ASC'])) ?>">Начальный баланс</a></th>
                <th><a href="?<?= http_build_query(array_merge($_GET, ['sort_column' => 'cf_payment_type', 'sort_order' => ($sortColumn === 'cf_payment_type' && $sortOrder === 'ASC') ? 'DESC' : 'ASC'])) ?>">Вид оплаты</a></th>
                <th><a href="?<?= http_build_query(array_merge($_GET, ['sort_column' => 'invoice_amount', 'sort_order' => ($sortColumn === 'invoice_amount' && $sortOrder === 'ASC') ? 'DESC' : 'ASC'])) ?>">Начисления</a></th>
                <th><a href="?<?= http_build_query(array_merge($_GET, ['sort_column' => 'payment_amount', 'sort_order' => ($sortColumn === 'payment_amount' && $sortOrder === 'ASC') ? 'DESC' : 'ASC'])) ?>">Платежи</a></th>
                <th><a href="?<?= http_build_query(array_merge($_GET, ['sort_column' => 'penalty_amount', 'sort_order' => ($sortColumn === 'penalty_amount' && $sortOrder === 'ASC') ? 'DESC' : 'ASC'])) ?>">Пени</a></th>
                <th><a href="?<?= http_build_query(array_merge($_GET, ['sort_column' => 'end_balance', 'sort_order' => ($sortColumn === 'end_balance' && $sortOrder === 'ASC') ? 'DESC' : 'ASC'])) ?>">Конечный баланс</a></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($paymentData as $index => $payment): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td>
                        <a href="/index.php?module=Estates&view=Detail&record=<?= $payment['estatesid'] ?>&app=MARKETING"
                            target="_blank">
                            <?= htmlspecialchars($payment['cf_contract_id']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($payment['cf_streets']) ?></td>
                    <td><?= htmlspecialchars($payment['cf_house_number']) ?></td>
                    <td><?= htmlspecialchars($payment['cf_litera']) ?></td>
                    <td><?= htmlspecialchars($payment['cf_client_name']) ?></td>
                    <td style="color: <?= $payment['start_balance'] > 0 ? '#ff6b6b' : '#51cf66' ?>">
                        <?= number_format($payment['start_balance'], 2, '.', ' ') ?> 
                    </td>
                    <td><?= htmlspecialchars($payment['cf_payment_type']) ?></td>
                    <td style="color: #ff6b6b">
                        <a href="/index.php?module=Estates&relatedModule=Invoice&view=Detail&record=<?= $payment['estatesid'] ?>&mode=showRelatedList&relationId=184&tab_label=Invoice&app=MARKETING"
                            target="_blank">
                            <?= number_format($payment['invoice_amount'], 2, '.', ' ') ?> 
                        </a>
                    </td>
                    <td style="color: #51cf66">
                        <a href="/index.php?module=Estates&relatedModule=Payments&view=Detail&record=<?= $payment['estatesid'] ?>&mode=showRelatedList&relationId=182&tab_label=Payments&app=MARKETING"
                            target="_blank">
                            <?= number_format(abs($payment['payment_amount']), 2, '.', ' ') ?> 
                        </a>
                    </td>
                    <td style="color: <?= $payment['penalty_amount'] > 0 ? '#ff6b6b' : '#000000' ?>">
                        <?= number_format($payment['penalty_amount'], 2, '.', ' ') ?> 
                    </td>
                    <td style="color: <?= $payment['end_balance'] > 0 ? '#ff6b6b' : '#51cf66' ?>; font-weight: bold;">
                        <?= number_format($payment['end_balance'], 2, '.', ' ') ?> 
                    </td>
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
        street: $('select[name="street"]').val(),
        house: $('select[name="house"]').val(),
        mp: $('select[name="mp"]').val(),
        limit: 100000, // выгружаем всё
        offset: 0,
        sort_column: '<?= $sortColumn ?>',
        sort_order: '<?= $sortOrder ?>',
        only_debtors: $('input[name="only_debtors"]').is(':checked') ? '1' : '0'
    };

    window.location.href = '/ReportFiles/excel_export/export_debtorb.php?' + $.param(params);
});


    </script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const getCellValue = (row, index) =>
            row.children[index].textContent.trim().replace(/\s+сом$/, '').replace(/\s/g, '');

        const comparer = (idx, asc, isNumeric) => (a, b) => {
            const v1 = getCellValue(asc ? a : b, idx);
            const v2 = getCellValue(asc ? b : a, idx);
            return isNumeric ? parseFloat(v1) - parseFloat(v2) : v1.localeCompare(v2);
        };

        document.querySelectorAll('table thead th').forEach((th, i) => {
            th.style.cursor = 'pointer';
            th.addEventListener('click', () => {
                const table = th.closest('table');
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const isNumeric = th.textContent.match(/баланс|Пени|Платежи|Начисления|Номер|#/i);
                const asc = !th.classList.contains('asc');

                rows.sort(comparer(i, asc, isNumeric));
                rows.forEach(row => tbody.appendChild(row));

                // Обновим классы
                table.querySelectorAll('th').forEach(th => th.classList.remove('asc', 'desc'));
                th.classList.add(asc ? 'asc' : 'desc');
            });
        });
    });
    </script>
</body>

</html>