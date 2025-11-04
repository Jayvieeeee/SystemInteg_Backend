<?php

require_once 'BaseMiddleware.php';
require_once __DIR__ . '/../exceptions/AppException.php';

class RateLimitMiddleware extends BaseMiddleware
{
    private $limit = 100;
    private $window = 60; // seconds

    public function handle($request)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $file = __DIR__ . '/../logs/api.log';
        $now = time();

        // Read existing log data
        $data = [];
        if (file_exists($file)) {
            $json = file_get_contents($file);
            $data = json_decode($json, true) ?? [];
        }

        // Initialize IP if not existing
        if (!isset($data[$ip])) {
            $data[$ip] = [];
        }

        // Filter old entries (keep only within time window)
        foreach ($data[$ip] as $key => $timestamp) {
            if ($timestamp < $now - $this->window) {
                unset($data[$ip][$key]);
            }
        }

        // Add current request timestamp with incremental index
        $nextIndex = count($data[$ip]) + 1;
        $data[$ip][$nextIndex] = $now;

        // Check if exceeds rate limit
        if (count($data[$ip]) > $this->limit) {
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
            throw new AppException("Rate limit exceeded. Please try again later.", 429);
        }

        // Save updated data
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }
}
