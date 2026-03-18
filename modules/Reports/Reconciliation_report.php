<?php
header('Content-Type: text/html; charset=utf-8');
chdir('../../');
if (!isset($_GET['id'])) {
    echo "ID is not set.";
    exit();
} else {
    $id = $_GET['id'];
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    require_once 'include/utils/utils.php';
    require_once 'includes/runtime/BaseModel.php';
    require_once 'includes/runtime/Globals.php';
    include_once 'includes/runtime/Controller.php';
    include_once 'includes/http/Request.php';

    // global $current_user;
    global $adb;

    // $current_user = Users::getActiveAdminUser();

    $estate_data = $adb->pquery("SELECT * FROM vtiger_estates es 
                      INNER JOIN vtiger_crmentity vc ON es.estatesid = vc.crmid 
                      INNER JOIN vtiger_contactdetails cd ON cd.contactid = es.cf_contact_id 
                      AND es.estatesid = ?", array($id));

    $lastname = $adb->query_result($estate_data, 0, 'lastname');
    $estate_number = $adb->query_result($estate_data, 0, 'estate_number');
    $inhabited_locality = $adb->query_result($estate_data, 0, 'cf_inhabited_locality');
    $streets = $adb->query_result($estate_data, 0, 'cf_streets');
    $house_number = $adb->query_result($estate_data, 0, 'cf_house_number');
    $apartment_number = $adb->query_result($estate_data, 0, 'cf_apartment_number');
    $litera = $adb->query_result($estate_data, 0, 'cf_litera');

    $invsumQuery = $adb->pquery("SELECT 
                    SUM(inv.total) AS invoice_sum,
                    COUNT(*) AS invoice_count
                FROM 
                    vtiger_invoice inv 
                    INNER JOIN vtiger_crmentity vc ON vc.crmid = inv.invoiceid 
                WHERE 
                    vc.deleted = 0 
                    AND inv.invoicestatus NOT IN ('Cancel')
                    AND inv.invoicedate < $start_date
                    AND inv.cf_estate_id = ?", array($id));

    if ($adb->num_rows($invsumQuery) > 0) {
        $invoice_sum = $adb->query_result($invsumQuery, 0, 'invoice_sum');
        $invoice_count = $adb->query_result($invsumQuery, 0, 'invoice_count');
    }

    $paysumQuery = $adb->pquery("SELECT 
                                SUM(vp.amount) AS pay_amount,
                                COUNT(*) AS pay_count 
                             FROM 
                                vtiger_payments AS vp
                                INNER JOIN vtiger_crmentity AS vc ON vc.crmid = vp.paymentsid  
                             WHERE 
                                vc.deleted = 0 
                                AND vp.cf_pay_type = ? 
                                AND vp.cf_status != ? 
                                AND vp.cf_pay_date < $start_date
                                AND vp.cf_paid_object = ?",
        array('Приход', 'Отменен', $id));

    if ($adb->num_rows($paysumQuery) > 0) {
        $pay_amount = $adb->query_result($paysumQuery, 0, 'pay_amount');
        $pay_count = $adb->query_result($paysumQuery, 0, 'pay_count');
    }
    // Обеспечиваем, что значения не NULL
    $invoice_sum = (float) ($invoice_sum ?: 0);
    $invoice_count = (int) ($invoice_count ?: 0);
    $pay_amount = (float) ($pay_amount ?: 0);
    $pay_count = (int) ($pay_count ?: 0);

    $balance_start = round(($invoice_sum - $pay_amount), 2);
    // var_dump($start_date);
    // var_dump($balance_query);
    // exit();

    $query = "SELECT 
            vi.invoicedate AS date,
            vi.total AS amount,
            vi.subject as name,
            'invoice' AS type
        FROM vtiger_invoice vi
        INNER JOIN vtiger_crmentity vc ON vi.invoiceid = vc.crmid 
        WHERE vc.deleted = 0
        AND vi.cf_estate_id = ?
        AND vi.invoicestatus NOT IN ('Cancel')
        AND vi.invoicedate BETWEEN ? AND ?
        UNION
        SELECT 
            p.cf_pay_date AS date,
            p.amount,
            p.cf_payment_source as name,
            'payment' AS type
        FROM vtiger_payments p
        INNER JOIN vtiger_crmentity vc ON p.paymentsid = vc.crmid 
        WHERE vc.deleted = 0
        AND p.cf_paid_object = ?
        AND p.cf_pay_type = 'Приход'
        AND p.cf_status != 'Отменен'
        AND p.cf_pay_date BETWEEN ? AND ?
        ORDER BY date";

    $data = $adb->pquery($query, array($id, $start_date, $end_date, $id, $start_date, $end_date));

    $transactions = [];

    while ($row = $adb->fetch_array($data)) {
        $formatted_date = DateTime::createFromFormat('Y-m-d', $row['date'])->format('d-m-Y');
        $transactions[] = [
            'date' => $formatted_date,
            'amount' => round($row['amount'], 2),
            'name' => $row['name'],
            'type' => $row['type']
        ];
    }
    // echo '<pre>';
    // var_dump($transactions);
    // echo '</pre>';
    // exit();

    $total_invoices = array_sum(array_column(array_filter($transactions, function ($transaction) {
        return $transaction['type'] == 'invoice';
    }), 'amount'));
    $total_payments = array_sum(array_column(array_filter($transactions, function ($transaction) {
        return $transaction['type'] == 'payment';
    }), 'amount'));
    $balance_end = $balance_start + $total_invoices - $total_payments;
    $balance_start = round($balance_start, 2);
    $balance_end = round($balance_end, 2);

    // echo '<pre>';
    // // var_dump($invoices);
    // // var_dump($payments);
    // var_dump($balance_start);
    // var_dump($total_invoices);
    // var_dump($total_payments);
    // var_dump($balance_end);
    // echo '</pre>';
    // exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <title>Reconciliation Report</title>
</head>

<body>
    <div class="container" style="text-align: center;">
        <h2>Акт Сверки</h2>
        <p>Абонент: <?php echo $lastname; ?>, ЛС: <b><?php echo $estate_number; ?></b></p>
        <p>Населенный пункт: <?php echo $inhabited_locality; ?></p>
        <?php if (!empty($streets) || !empty($house_number) || !empty($apartment_number) || !empty($litera)): ?>
            <p>
                Улица: <?php echo !empty($streets) ? $streets : ''; ?>
                <?php if (!empty($house_number)): ?> Д.№: <?php echo $house_number; ?>     <?php endif; ?>
                <?php if (!empty($apartment_number)): ?> Кв.№: <?php echo $apartment_number; ?>     <?php endif; ?>
                <?php if (!empty($litera)): ?>         <?php echo $litera; ?>     <?php endif; ?>
            </p>
        <?php endif; ?>


        <p style="text-align: right;">Сальдо на начало <?php echo $start_date; ?> <br> <?php echo $balance_start; ?> сом
        </p>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th colspan="2">Счета</th>
                    <th colspan="2">Оплаты</th>
                </tr>
                <tr>
                    <th></th>
                    <th>Тема счета</th>
                    <th>Сумма</th>
                    <th>Способ оплаты</th>
                    <th>Сумма</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($transactions as $transaction) {
                    echo "<tr>";
                    echo "<td>" . $transaction['date'] . "</td>";
                    if ($transaction['type'] == 'invoice') {
                        echo "<td>" . $transaction['name'] . "</td>";
                        echo "<td>" . $transaction['amount'] . "</td>";
                        echo "<td></td>";
                        echo "<td></td>";
                    } else {
                        echo "<td></td>";
                        echo "<td></td>";
                        echo "<td>" . $transaction['name'] . "</td>";
                        echo "<td>" . $transaction['amount'] . "</td>";
                    }
                    echo "</tr>";
                }
                ?>
                <tr>
                    <td></td>
                    <td>Сумма счетов:</td>
                    <td><?php echo $total_invoices; ?></td>
                    <td>Сумма оплат:</td>
                    <td><?php echo $total_payments; ?></td>
                </tr>
            </tbody>
        </table>
        <p style="text-align: right;">Сальдо на конец <?php echo $end_date; ?> <br> <?php echo $balance_end; ?> сом </p>
        <button id="downloadExcel" class="btn btn-primary">Скачать Excel</button>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script>
        document.getElementById('downloadExcel').addEventListener('click', function () {
            var wb = XLSX.utils.book_new();
            wb.Props = {
                Title: "Reconciliation Report",
                Subject: "Reconciliation",
                Author: "Your Company",
                CreatedDate: new Date()
            };
            wb.SheetNames.push("Report");

            var ws_data = [
                ["Акт Сверки"],
                ["Абонент", "<?php echo $lastname; ?>", "ЛС", "<?php echo $estate_number; ?>"],
                ["Сальдо на начало месяца:", "<?php echo $balance_start; ?>"],
                [],
                ["Дата", "Тема счета", "Сумма", "Способ оплаты", "Сумма"]
            ];

            <?php foreach ($transactions as $transaction) { ?>
                var row = ["<?php echo $transaction['date']; ?>"];
                if ("<?php echo $transaction['type']; ?>" == "invoice") {
                    row.push("<?php echo $transaction['name']; ?>", "<?php echo $transaction['amount']; ?>", "", "");
                } else {
                    row.push("", "", "", "<?php echo $transaction['name']; ?>", "<?php echo $transaction['amount']; ?>");
                }
                ws_data.push(row);
            <?php } ?>

            ws_data.push(["", "Сумма счетов:", "<?php echo $total_invoices; ?>", "Сумма оплат:", "<?php echo $total_payments; ?>"]);
            ws_data.push(["", "Сальдо на конец:", "<?php echo $balance_end; ?>", "", ""]);

            var ws = XLSX.utils.aoa_to_sheet(ws_data);
            wb.Sheets["Report"] = ws;
            var wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'binary' });

            function s2ab(s) {
                var buf = new ArrayBuffer(s.length);
                var view = new Uint8Array(buf);
                for (var i = 0; i < s.length; i++) view[i] = s.charCodeAt(i) & 0xFF;
                return buf;
            }

            saveAs(new Blob([s2ab(wbout)], { type: "application/octet-stream" }), 'reconciliation_report.xlsx');
        });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
</body>

</html>