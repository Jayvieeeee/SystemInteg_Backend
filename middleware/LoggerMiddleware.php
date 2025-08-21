<?php
require_once "BaseMiddleware.php";

class LoggerMiddleware extends BaseMiddleware {
    public function handle($request) {
        $logFile = __DIR__ . "/../logs/app.log";
        $time = date("Y-m-d H:i:s");
        $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? 'N/A';

        $entry = "[$time] $method $uri " . json_encode($request) . PHP_EOL;
        file_put_contents($logFile, $entry, FILE_APPEND);
    }
}
