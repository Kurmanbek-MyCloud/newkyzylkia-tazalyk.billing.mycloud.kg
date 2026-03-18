<?php
// Скачивание логов
$workerLogFile = '../../../../../../redis-invoices/worker.log';
$mpLogFile = '../../../../../../redis-invoices/mp_handlers/tamchy_tazasuu.log';

$timestamp = date('Y-m-d_H-i-s');
$zipFileName = "worker_logs_$timestamp.zip";

// Создаем временный ZIP файл
$zip = new ZipArchive();
$tempZipFile = tempnam(sys_get_temp_dir(), 'logs_');

if ($zip->open($tempZipFile, ZipArchive::CREATE) === TRUE) {
    // Добавляем логи воркера
    if (file_exists($workerLogFile)) {
        $zip->addFile($workerLogFile, 'worker.log');
    }
    
    // Добавляем логи МП
    if (file_exists($mpLogFile)) {
        $zip->addFile($mpLogFile, 'mp_tamchy_tazasuu.log');
    }
    
    $zip->close();
    
    // Отправляем файл
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
    header('Content-Length: ' . filesize($tempZipFile));
    
    readfile($tempZipFile);
    unlink($tempZipFile);
} else {
    header('Content-Type: text/plain');
    echo "Ошибка при создании ZIP файла";
}
?>
