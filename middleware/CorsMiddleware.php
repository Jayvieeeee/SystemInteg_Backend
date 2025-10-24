<?php
require_once 'BaseMiddleware.php';

class CorsMiddleware extends BaseMiddleware
{
    // Static method for preflight handling (called before routing)
    public static function handlePreflight()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    // Instance method for middleware chain (if needed)
    public function handle($request)
    {
        // Headers already set by static method
        return $request;
    }
}
