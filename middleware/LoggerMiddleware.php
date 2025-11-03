<?php
require_once 'BaseMiddleware.php';

class LoggerMiddleware extends BaseMiddleware
{
    public function handle($request)
    {
        // Define log paths safely
        $basePath = dirname(__DIR__); // one level up from middleware/
        $logDir = $basePath . '/logs';
        $appLog = $logDir . '/app.log';
        $debugLog = $logDir . '/debug.log';

        // Ensure directory exists (prevents "No such file or directory" errors)
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        // Redirect PHP error_log() output to debug.log (for runtime errors)
        ini_set('error_log', $debugLog);

        $time = date("Y-m-d H:i:s");
        $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? 'N/A';

        // Sanitize sensitive request fields
        $safeRequest = $request;
        $sensitive = ['password', 'confirm_password', 'token'];
        foreach ($sensitive as $field) {
            if (isset($safeRequest[$field])) {
                $safeRequest[$field] = '***HIDDEN****';
            }
        }

        // --- Main Request Log (human-readable request summary)
        $entry = "[$time] $method $uri " . json_encode($safeRequest, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($appLog, $entry, FILE_APPEND);

        // --- Debug Log (internal tracing + PHP error capture)
        $debugEntry = "[$time] LoggerMiddleware triggered for $uri" . PHP_EOL;
        file_put_contents($debugLog, $debugEntry, FILE_APPEND);

        // Optional: test direct error log redirection
        // error_log("LoggerMiddleware active for $uri");
    }
}
