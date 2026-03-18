<?php
error_reporting(E_ALL);
require_once __DIR__ . '/../../vendor/autoload.php';
require_once dirname(__DIR__) . '/payments/db.php';
ini_set('memory_limit', '512M');
set_time_limit(300);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Проверяем и очищаем параметры
$startDate = isset($_GET['start_date']) && preg_match('/\d{4}-\d{2}-\d{2}/', $_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) && preg_match('/\d{4}-\d{2}-\d{2}/', $_GET['end_date']) ? $_GET['end_date'] : null;
$sortColumn = isset($_GET['sort_column']) && in_array($_GET['sort_column'], ['cf_contract_id', 'cf_streets', 'cf_house_number', 'cf_client_name', 'start_balance','cf_payment_type', 'invoice_amount', 'payment_amount', 'penalty_amount', 'end_balance']) ? $_GET['sort_column'] : 'cf_contract_id';
$sortOrder = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC','DESC']) ? strtoupper($_GET['sort_order']) : 'DESC';
$limit = isset($_GET['limit']) ? max(1, min(10000, (int)$_GET['limit'])) : 5000;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
$streetFilter = !empty($_GET['street']) ? $_GET['street'] : null;
$houseFilter = !empty($_GET['house']) ? $_GET['house'] : null;
$onlyDebtors = isset($_GET['only_debtors']) && $_GET['only_debtors'] == '1';
$mp = !empty($_GET['mp']) ? (int)$_GET['mp'] : null;

// Получаем данные
$paymentData = getDebtorb($startDate, $endDate, $sortColumn, $sortOrder, $limit, $offset, $streetFilter, $houseFilter, $onlyDebtors, $mp);

// Если данных нет
if (empty($paymentData)) {
    echo "Нет данных для экспорта.";
    exit;
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Отчет по должникам');

// Заголовки таблицы
$headers = ['№', 'Лицевой счет', 'Улица', 'Номер дома', 'Литера', 'Абонент', 'Начальный баланс','Вид оплаты', 'Начисления', 'Платежи', 'Пени', 'Конечный баланс'];
$sheet->fromArray($headers, null, 'A1');

// Заполнение данными
$row = 2;
foreach ($paymentData as $index => $payment) {
    $sheet->setCellValue('A' . $row, $index + 1);
    $sheet->setCellValue('B' . $row, $payment['cf_contract_id']);
    $sheet->setCellValue('C' . $row, $payment['cf_streets']);
    $sheet->setCellValue('D' . $row, $payment['cf_house_number']);
    $sheet->setCellValue('E' . $row, $payment['cf_litera']);
    $sheet->setCellValue('F' . $row, $payment['cf_client_name']);
    $sheet->setCellValue('G' . $row, number_format(floatval($payment['start_balance']), 2, '.', ''));
    $sheet->setCellValue('H' . $row, $payment['cf_payment_type']);
    $sheet->setCellValue('I' . $row, number_format(floatval($payment['invoice_amount']), 2, '.', ''));
    $sheet->setCellValue('J' . $row, number_format(abs(floatval($payment['payment_amount'])), 2, '.', ''));
    $sheet->setCellValue('K' . $row, number_format(floatval($payment['penalty_amount']), 2, '.', ''));
    $sheet->setCellValue('L' . $row, number_format(floatval($payment['end_balance']), 2, '.', ''));
    $row++;
}

// Автоматическая настройка ширины колонок
foreach (range('A', 'M') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Отправка файла на клиент
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Отчет по должникам.xlsx"');
header('Cache-Control: max-age=0, must-revalidate');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

?>
