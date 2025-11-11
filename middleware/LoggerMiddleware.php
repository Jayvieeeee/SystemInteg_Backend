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

        $this->rotateLog($logFile, 5); // 5MB max

        file_put_contents($logFile, $entry, FILE_APPEND);

        // Debug log for login attempts
        $isLoginRequest = (isset($request['identifier']) || isset($request['password'])) &&
            (strpos($uri, '/login') !== false);

        if ($isLoginRequest) {
            $this->logLoginDebug($request, $uri);
        }
    }

    private function logLoginDebug($request, $uri)
    {
        $debugLogFile = __DIR__ . "/../logs/debug.log";
        $time = date("Y-m-d H:i:s");
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $identifier = $request['identifier'] ?? 'N/A';
        $key = md5($identifier . $ip);
        $loginAttemptsFile = __DIR__ . '/../logs/login_attempts.log';

        $debugEntry = "\n" . str_repeat("=", 80) . "\n";
        $debugEntry .= "[$time] LOGIN REQUEST DEBUG\n";
        $debugEntry .= str_repeat("=", 80) . "\n";
        $debugEntry .= "Identifier: $identifier\n";
        $debugEntry .= "IP: $ip\n";
        $debugEntry .= "Key: $key\n";
        $debugEntry .= "URI: $uri\n";

        // Read current attempts
        if (file_exists($loginAttemptsFile)) {
            $attemptsData = json_decode(file_get_contents($loginAttemptsFile), true) ?? [];

            if (isset($attemptsData[$key])) {
                $attempts = $attemptsData[$key]['attempts'] ?? 0;
                $lastAttempt = $attemptsData[$key]['last_attempt'] ?? 0;
                $timeSince = time() - $lastAttempt;

                $debugEntry .= "Current Attempts: $attempts\n";
                $debugEntry .= "Last Attempt: " . date("Y-m-d H:i:s", $lastAttempt) . "\n";
                $debugEntry .= "Time Since Last: {$timeSince}s\n";

                if ($attempts >= 5) {
                    $debugEntry .= "STATUS:  LOCKED OUT (5+ attempts)\n";
                } else {
                    $debugEntry .= "STATUS: ✓ Allowed (attempts: $attempts/5)\n";
                }
            } else {
                $debugEntry .= "Current Attempts: 0 (new user)\n";
                $debugEntry .= "STATUS: ✓ Allowed (first attempt)\n";
            }
        } else {
            $debugEntry .= "Current Attempts: 0 (no log file)\n";
            $debugEntry .= "STATUS: ✓ Allowed (first attempt)\n";
        }

        $debugEntry .= str_repeat("=", 80) . "\n";

        $this->rotateLog($debugLogFile, 5); // 5MB max

        file_put_contents($debugLogFile, $debugEntry, FILE_APPEND);
    }

    private function rotateLog($file, $maxSizeMB = 5)
    {
        if (!file_exists($file)) {
            return;
        }

        $maxSize = $maxSizeMB * 1024 * 1024; // Convert MB to bytes

        if (filesize($file) > $maxSize) {
            // Rename old log to .1 (overwrite previous .1 each rotation)
            @rename($file, $file . '.1');
            // Create a new empty log file
            file_put_contents($file, "");
        }
    }
}
