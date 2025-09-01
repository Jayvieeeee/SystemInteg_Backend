<?php

require_once 'BaseMiddleware.php';
require_once __DIR__ . "/../exceptions/AppException.php";

class RateLimitMiddleware extends BaseMiddleware
{
    private $limit = 5;
    private $window = 60; // time window in seconds

    public function handle($request)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $file = __DIR__ . "/../logs/api.log";

        $now = time();
        $data = [];

        // Load existing log
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?? [];
        }

        // if no entry sa IP, initialize array
        if (!isset($data[$ip])) {
            $data[$ip] = [];
        }

        // Remove expired requests (older than $window)
        $data[$ip] = array_filter($data[$ip], fn($t) => $t > ($now - $this->window));


        if (count($data[$ip]) >= $this->limit) {
            throw new AppException("Too many requests. Please try again later.", 429);
        }

        // Add current request timestamp
        $data[$ip][] = $now;

        // Save back to file
        file_put_contents($file, json_encode($data));
    }
}
