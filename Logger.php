<?php

class CustomLogger {

    private $logFile;

    public function __construct(string $name) {
        $logsDir = __DIR__ . '/logs/' . dirname($name);
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        $this->logFile = __DIR__ . '/logs/' . $name . '.log';
    }

    public function log(string $message): void {
        date_default_timezone_set('Asia/Bishkek');
        $result = file_put_contents(
            $this->logFile,
            date('Y-m-d H:i:s') . ': ' . $message . "\n",
            FILE_APPEND
        );
        if ($result === false) {
            error_log("CustomLogger: не удалось записать в файл {$this->logFile}");
        }
    }

}

// Старый код Logger.php
// class CustomLogger {

//     public $logFile;

//     public function __construct($logFile) {
//         $this->logFile = $logFile;
//     }

//     public function log($message) {
//         date_default_timezone_set('Asia/Bishkek');
//         $text = date('Y-m-d H:i:s') . ': ' . $message . "\n";
//         $open = fopen($this->logFile, 'a');
//         if ($open !== false) {
//             fwrite($open, $text);
//             fclose($open);
//         }
//     }

// }


