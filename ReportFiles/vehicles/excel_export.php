<?php
require_once 'db.php';

// Получаем параметры из GET-запроса
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : null;
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$paidFilter = isset($_GET['paid_filter']) ? $_GET['paid_filter'] : 'true'; // По умолчанию - показать оплаченные
$unpaidFilter = isset($_GET['unpaid_filter']) ? $_GET['unpaid_filter'] : 'true'; // По умолчанию - показать не оплаченные

// Получаем данные о машинах с учетом фильтров
$vehicleData = getCars($startDate, $endDate, $sortColumn, $sortOrder, null, null, $paidFilter === 'true', $unpaidFilter === 'true');

// Создание Excel файла
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="report.xlsx"');

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Создаем новый Spreadsheet объект
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Заголовки столбцов
$headers = [
    'Машинанын номери',
    'Төлөм статусу',
    'Кирүү убактысы',
    'Чыгуу убактысы',
    'Навес',
    'Сатуучу',
    'Сумма'
];
$sheet->fromArray($headers, null, 'A1');

// Заполнение данными
$rowNumber = 2; // Начинаем со второй строки
$totalAmount = 0; // Переменная для хранения общей суммы
foreach ($vehicleData['cars'] as $car) {
    $sheet->fromArray([
        $car['car_number'],
        $car['invoicestatus'],
        $car['check_in'],
        $car['check_out'],
        $car['shed_status'],
        $car['seller_status'],
        $car['amount'],
    ], null, 'A' . $rowNumber);

    // Суммируем суммы
    $totalAmount += (float) $car['amount'];
    $rowNumber++;
}

// Добавляем количество машин и сумму оплат в конец таблицы
$sheet->setCellValue('A' . $rowNumber, 'Количество машин:');
$sheet->setCellValue('B' . $rowNumber, count($vehicleData['cars'])); // Количество машин
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