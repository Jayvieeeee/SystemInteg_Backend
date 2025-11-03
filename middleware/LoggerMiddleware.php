<?php
require_once 'BaseMiddleware.php';

class LoggerMiddleware extends BaseMiddleware
{
    public function handle($request)
    {
        $logFile = __DIR__ . "/../logs/app.log";
        $time = date("Y-m-d H:i:s");
        $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? 'N/A';

        $safeRequest = $request;

        $sensitive = ['password', 'old_password', 'new_password', 'confirm_password', 'token'];
        foreach ($sensitive as $field) {
            if (isset($safeRequest[$field])) {
                $safeRequest[$field] = '***HIDDEN****';
            }
        }

        $entry = "[$time] $method $uri " . json_encode($safeRequest) . PHP_EOL;
        file_put_contents($logFile, $entry, FILE_APPEND);
    }
}
