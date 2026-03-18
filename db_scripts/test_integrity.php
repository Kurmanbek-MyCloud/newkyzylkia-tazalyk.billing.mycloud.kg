<?php
/**
 * test_integrity.php — Полная проверка целостности кода и БД
 * Запуск: php test_integrity.php
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

$pass = 0;
$fail = 0;
$warn = 0;

function check_pass($msg) { global $pass; echo "  [OK]   $msg\n"; $pass++; }
function check_fail($msg) { global $fail; echo "  [FAIL] $msg\n"; $fail++; }
function check_warn($msg) { global $warn; echo "  [WARN] $msg\n"; $warn++; }

echo "\n============================================================\n";
echo "  Полная проверка целостности (PHP + БД)\n";
echo "============================================================\n\n";

// ===== 1. Конфиг =====
echo "--- 1. Конфигурация ---\n";

$configFile = dirname(__DIR__) . '/config.inc.php';
if (file_exists($configFile)) {
    check_pass("config.inc.php существует");
    include($configFile);
    if (isset($dbconfig) && !empty($dbconfig['db_server'])) {
        check_pass("dbconfig загружен (server: {$dbconfig['db_server']})");
    } else {
        check_fail("dbconfig не загружен из config.inc.php");
    }
} else {
    check_fail("config.inc.php не найден");
    echo "\nТестирование прервано.\n";
    exit(1);
}

// ===== 2. Подключение к БД =====
echo "\n--- 2. Подключение к БД ---\n";

$port = str_replace(':', '', $dbconfig['db_port'] ?? '3306');
$conn = @new mysqli(
    $dbconfig['db_server'],
    $dbconfig['db_username'],
    $dbconfig['db_password'],
    $dbconfig['db_name'],
    (int)$port
);

if ($conn->connect_error) {
    check_fail("Подключение к БД: " . $conn->connect_error);
    echo "\nТестирование прервано — нет подключения к БД.\n";
    exit(1);
} else {
    check_pass("Подключение к БД работает");
}

$conn->set_charset('utf8');

function query_val($conn, $sql) {
    $r = $conn->query($sql);
    if (!$r) return null;
    $row = $r->fetch_row();
    return $row ? $row[0] : null;
}

// ===== 3. Основные таблицы =====
echo "\n--- 3. Основные таблицы с данными ---\n";

$tables = [
    'vtiger_estates' => 'Абоненты',
    'vtiger_invoice' => 'Счета',
    'vtiger_payments' => 'Платежи',
    'vtiger_meters' => 'Счётчики',
    'vtiger_readings' => 'Показания',
    'vtiger_users' => 'Пользователи',
    'vtiger_crmentity' => 'CRM сущности',
];

foreach ($tables as $tbl => $desc) {
    $cnt = query_val($conn, "SELECT COUNT(*) FROM $tbl");
    if ($cnt === null) {
        check_fail("$tbl ($desc) — таблица не найдена!");
    } elseif ($cnt == 0) {
        check_warn("$tbl ($desc) — пустая");
    } else {
        check_pass("$tbl ($desc) — $cnt записей");
    }
}

// ===== 4. CF-таблицы =====
echo "\n--- 4. Целостность CF-таблиц ---\n";

$cfPairs = [
    ['vtiger_estates', 'vtiger_estatescf', 'estatesid'],
    ['vtiger_payments', 'vtiger_paymentscf', 'paymentsid'],
    ['vtiger_meters', 'vtiger_meterscf', 'metersid'],
    ['vtiger_readings', 'vtiger_readingscf', 'readingsid'],
    ['vtiger_invoice', 'vtiger_invoicecf', 'invoiceid'],
];

foreach ($cfPairs as [$main, $cf, $key]) {
    $mainCnt = query_val($conn, "SELECT COUNT(*) FROM $main");
    $cfCnt = query_val($conn, "SELECT COUNT(*) FROM $cf");
    $orphans = query_val($conn, "SELECT COUNT(*) FROM $cf WHERE $key NOT IN (SELECT $key FROM $main)");
    if ($mainCnt == $cfCnt && $orphans == 0) {
        check_pass("$cf = $main ($mainCnt записей, 0 сирот)");
    } else {
        check_fail("$cf: $cfCnt vs $main: $mainCnt (сирот: $orphans)");
    }
}

// ===== 5. Висячие записи =====
echo "\n--- 5. Висячие записи ---\n";

$invCharges = query_val($conn, "SELECT COUNT(*) FROM vtiger_inventorychargesrel WHERE recordid NOT IN (SELECT invoiceid FROM vtiger_invoice)");
$invProducts = query_val($conn, "SELECT COUNT(*) FROM vtiger_inventoryproductrel WHERE id NOT IN (SELECT invoiceid FROM vtiger_invoice)");

if ($invCharges == 0) check_pass("vtiger_inventorychargesrel — 0 висячих");
else check_fail("vtiger_inventorychargesrel — $invCharges висячих");

if ($invProducts == 0) check_pass("vtiger_inventoryproductrel — 0 висячих");
else check_fail("vtiger_inventoryproductrel — $invProducts висячих");

// ===== 6. Миграции =====
echo "\n--- 6. Миграции схемы ---\n";

$prevCol = query_val($conn, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='{$dbconfig['db_name']}' AND TABLE_NAME='vtiger_inventoryproductrel' AND COLUMN_NAME='prev_reading_id'");
$curCol = query_val($conn, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='{$dbconfig['db_name']}' AND TABLE_NAME='vtiger_inventoryproductrel' AND COLUMN_NAME='cur_reading_id'");

if ($prevCol == 1 && $curCol == 1) {
    check_pass("vtiger_inventoryproductrel: prev_reading_id, cur_reading_id — есть");
} else {
    check_fail("vtiger_inventoryproductrel: столбцы prev_reading_id/cur_reading_id не найдены");
}

// ===== 7. Composer autoload =====
echo "\n--- 7. Composer autoload и библиотеки ---\n";

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
    check_pass("vendor/autoload.php загружен");
} else {
    check_fail("vendor/autoload.php не найден!");
}

$libraries = [
    'Smarty' => 'Smarty',
    'PhpOffice\\PhpSpreadsheet\\Spreadsheet' => 'PhpSpreadsheet',
    'TCPDF' => 'TCPDF',
    'Monolog\\Logger' => 'Monolog',
    'Endroid\\QrCode\\QrCode' => 'Endroid QR Code',
    'Picqer\\Barcode\\BarcodeGeneratorPNG' => 'Picqer Barcode',
    'Dompdf\\Dompdf' => 'Dompdf',
    'HTMLPurifier' => 'HTMLPurifier',
];

foreach ($libraries as $class => $name) {
    if (class_exists($class)) {
        check_pass("$name — класс $class доступен");
    } else {
        check_fail("$name — класс $class не найден!");
    }
}

// ===== 8. Ключевые файлы проекта =====
echo "\n--- 8. Ключевые файлы ---\n";

$root = dirname(__DIR__);
$files = [
    'config.inc.php' => 'Основной конфиг',
    'config.security.php' => 'Конфиг безопасности',
    'includes/Loader.php' => 'Autoloader vtiger',
    'test/logo/oimo_billing_logo.png' => 'Логотип компании',
    'vendor/autoload.php' => 'Composer autoload',
    'modules/Vtiger/models/CompanyDetails.php' => 'CompanyDetails (логотип)',
    'modules/Invoice/Invoice.php' => 'Модуль Invoice',
    'modules/Estates/Estates.php' => 'Модуль Estates',
    'modules/Payments/Payments.php' => 'Модуль Payments',
    'modules/Meters/Meters.php' => 'Модуль Meters',
    'modules/Readings/Readings.php' => 'Модуль Readings',
    'ReportFiles/payments/debtorb.php' => 'Отчёт по должникам',
    'ReportFiles/excel_export/export_debtorb.php' => 'Excel экспорт должников',
];

foreach ($files as $file => $desc) {
    $path = "$root/$file";
    if (file_exists($path)) {
        check_pass("$file — $desc");
    } else {
        check_fail("$file — $desc НЕ НАЙДЕН!");
    }
}

// ===== 9. Нет hardcoded путей в ключевых файлах =====
echo "\n--- 9. Проверка hardcoded путей ---\n";

$filesToCheck = [
    'ReportFiles/payments/debtorb.php',
    'ReportFiles/excel_export/export_debtorb.php',
];

foreach ($filesToCheck as $file) {
    $path = "$root/$file";
    if (!file_exists($path)) continue;
    $content = file_get_contents($path);
    if (preg_match('/\/var\/www\/[a-z].*\.mycloud/', $content)) {
        check_fail("$file — содержит hardcoded путь /var/www/...");
    } elseif (preg_match('/https?:\/\/[a-z0-9]+\.billing\.mycloud/', $content)) {
        check_fail("$file — содержит hardcoded URL");
    } else {
        check_pass("$file — нет hardcoded путей/URL");
    }
}

// ===== 10. adodb не в composer =====
echo "\n--- 10. Безопасность composer.json ---\n";

$composerJson = file_get_contents("$root/composer.json");
$composer = json_decode($composerJson, true);

if (isset($composer['require']['adodb/adodb-php'])) {
    check_fail("composer.json содержит adodb/adodb-php (конфликт с vtiger!)");
} else {
    check_pass("adodb/adodb-php отсутствует в composer.json");
}

if (isset($composer['require']['ext-imap'])) {
    check_fail("composer.json содержит ext-imap (не установлен)");
} else {
    check_pass("ext-imap отсутствует в composer.json");
}

// Проверяем что vtiger adodb на месте
if (file_exists("$root/libraries/adodb_vtigerfix/adodb.inc.php")) {
    check_pass("libraries/adodb_vtigerfix/adodb.inc.php — на месте");
} else {
    check_fail("libraries/adodb_vtigerfix/adodb.inc.php — НЕ НАЙДЕН!");
}

// ===== 11. Размер БД =====
echo "\n--- 11. Размер БД ---\n";
$size = query_val($conn, "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) FROM information_schema.tables WHERE table_schema = '{$dbconfig['db_name']}'");
echo "  Размер: {$size} MB\n";

// ===== Итоги =====
$conn->close();

echo "\n============================================================\n";
echo "  Результаты: $pass пройдено, $fail ошибок, $warn предупреждений\n";
if ($fail == 0) {
    echo "  Всё в порядке!\n";
} else {
    echo "  Есть проблемы — проверь ошибки выше\n";
}
echo "============================================================\n\n";

exit($fail > 0 ? 1 : 0);
