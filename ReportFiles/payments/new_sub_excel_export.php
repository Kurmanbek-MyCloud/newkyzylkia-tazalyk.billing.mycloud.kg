<?php
require_once 'db.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Получаем параметры из GET-запроса
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : null;
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Получаем данные о новых абонентах
$subscribers = getNew($startDate, $endDate, $sortColumn, $sortOrder, null, null);

// Устанавливаем заголовки для скачивания файла
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="new_subscribers.xlsx"');

// Создаем новый Excel-файл
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Устанавливаем заголовки столбцов
$headers = [
    '№',
    'Лицевой счет',
    'ФИО собственника',
    'Улица',
    '№ дома',
    'Литера',
];
$sheet->fromArray($headers, null, 'A1');

// Заполняем таблицу данными
$rowNumber = 2;
foreach ($subscribers as $index => $subscriber) {
    $sheet->fromArray([
        $index + 1,
        $subscriber['estate_number'],
        $subscriber['cf_lastname'],
        $subscriber['cf_streets'],
        $subscriber['cf_house_number'],
        $subscriber['cf_litera'],
    ], null, 'A' . $rowNumber);
    $rowNumber++;
}

// Автоматически подстраиваем ширину столбцов
foreach (range('A', 'H') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Создаем Excel-файл и отправляем в браузер
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
