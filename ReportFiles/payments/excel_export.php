<?php
require_once 'db.php'; // Подключение к базе данных

// Получаем параметры из URL
$system = isset($_GET['system']) ? $_GET['system'] : null;
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$sortColumn = isset($_GET['sort_column']) ? $_GET['sort_column'] : null; // По умолчанию сортируем по дате
$sortOrder = isset($_GET['sort_order']) ? $_GET['sort_order'] : null; // По умолчанию сортируем по возрастанию
// Получаем данные для экспорта с учетом фильтров
$paymentData = getPayments($system, $startDate, $endDate, $sortColumn, $sortOrder, null, null);

// Создание Excel файла
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="report.xlsx"');

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// Создаем новый объект Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
// Устанавливаем заголовки таблицы
$headers = [
    'Дата платежа',
    'Сумма',
    'Платежный оператор',
    'ЛС',
    'Вид оплаты',
    // 'Оплачиваемая услуга'
];
$sheet->fromArray($headers, null, 'A1');

// Заполнение данными
$rowNumber = 2; // Начинаем со второй строки
$totalAmount = 0; // Переменная для хранения общей суммы
foreach ($paymentData['payments'] as $payment) {
    $sheet->fromArray([
        $payment['cf_pay_date'],
        $payment['amount'],
        $payment['cf_payment_source'],
        $payment['estate_number'],
        $payment['cf_payment_type'],
        // $payment['cf_paid_service'],
    ], null, 'A' . $rowNumber);

    // Суммируем суммы
    $totalAmount += (float) $payment['amount'];
    $rowNumber++;
}

// Добавляем количество машин и сумму оплат в конец таблицы
$sheet->setCellValue('A' . $rowNumber, 'Количество платежей:');
$sheet->setCellValue('B' . $rowNumber, count($paymentData['payments'])); // Количество машин
$rowNumber++;
$sheet->setCellValue('A' . $rowNumber, 'Сумма оплат:');
$sheet->setCellValue('B' . $rowNumber, $totalAmount); // Общая сумма

foreach (range('A', 'E') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}
// Создаем Writer и сохраняем файл
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;