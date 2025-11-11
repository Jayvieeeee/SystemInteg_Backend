<?php

require_once 'BaseMiddleware.php';
require_once __DIR__ . '/../exceptions/AppException.php';

class RateLimitMiddleware extends BaseMiddleware
{
    private $limit = 50;
    private $window = 60; // seconds

    public function handle($request)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $file = __DIR__ . '/../logs/api.log';
        $now = time();

        // Check if this is a login request
        $isLoginRequest = (isset($request['identifier']) || isset($request['password'])) &&
            (strpos($_SERVER['REQUEST_URI'] ?? '', '/login') !== false);

        if ($isLoginRequest) {
            $this->handleLoginRateLimit($request, $ip, $now);
            return;
        }

        $this->handleGeneralRateLimit($ip, $file, $now);
    }

    private function handleGeneralRateLimit($ip, $file, $now)
    {
        $data = file_exists($file)
            ? (json_decode(file_get_contents($file), true) ?? [])
            : [];

        if (!isset($data[$ip])) {
            $data[$ip] = [];
        }

        $data[$ip] = array_filter($data[$ip], function ($timestamp) use ($now) {
            return $timestamp >= $now - $this->window;
        });

        $data[$ip] = array_values($data[$ip]);

        $data[$ip][] = $now;

        $data[$ip] = array_slice($data[$ip], -$this->limit);

        if (count($data[$ip]) > $this->limit) {
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
            throw new AppException("Rate limit exceeded. Please try again later.", 429);
        }

        // Save optimized data
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function handleLoginRateLimit($request, $ip, $now)
    {
        $identifier = $request['identifier'] ?? '';
        $key = md5($identifier . $ip);
        $file = __DIR__ . '/../logs/login_attempts.log';
        $debugLog = __DIR__ . '/../logs/debug.log';
        $maxAttempts = 5;
        $lockoutTime = 900; // 15 minutes

        $this->debugLog($debugLog, "RateLimitMiddleware: Checking login attempts for key: $key");

        $data = [];
        if (file_exists($file)) {
            $json = file_get_contents($file);
            $data = json_decode($json, true) ?? [];
        }

        // Check existing attempts
        if (isset($data[$key])) {
            $userAttempts = $data[$key];
            $timeSinceLastAttempt = $now - $userAttempts['last_attempt'];

            $this->debugLog($debugLog, "Found existing attempts: {$userAttempts['attempts']}, Time since last: {$timeSinceLastAttempt}s");

            // Check if locked out
            if ($userAttempts['attempts'] >= $maxAttempts && $timeSinceLastAttempt < $lockoutTime) {
                $remainingMinutes = ceil(($lockoutTime - $timeSinceLastAttempt) / 60);

                $this->debugLog($debugLog, "BLOCKED: User is locked out. Remaining: {$remainingMinutes} minute(s)");

                throw new AppException(
                    "Too many failed login attempts. Please try again in {$remainingMinutes} minute(s).",
                    429
                );
            }

            // Reset if lockout period passed
            if ($timeSinceLastAttempt >= $lockoutTime) {
                $this->debugLog($debugLog, "Lockout period passed. Resetting attempts.");
                unset($data[$key]);
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
            }
        } else {
            $this->debugLog($debugLog, "No existing attempts found for this user");
        }

        // Store for AuthService to increment on failure
        $GLOBALS['login_attempt_key'] = $key;
        $GLOBALS['login_attempt_file'] = $file;

        $this->debugLog($debugLog, "RateLimitMiddleware: Check passed. Proceeding to authentication.");
    }

    private function debugLog($file, $message)
    {
        $time = date("Y-m-d H:i:s");
        $entry = "[$time] $message\n";
        file_put_contents($file, $entry, FILE_APPEND);
    }
}
