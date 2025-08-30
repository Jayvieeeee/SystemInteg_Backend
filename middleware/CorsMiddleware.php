<?php
require_once 'BaseMiddleware.php';

class CorsMiddleware extends BaseMiddleware
{
    public function handle($request)
    {
        // Allow specific origin (your frontend URL)
        header("Access-Control-Allow-Origin: *");
        // Allow credentials (if needed, e.g., cookies, authentication)
        header("Access-Control-Allow-Credentials: true");
        // Allow specific methods
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        // Allow specific headers
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit; // ✅ This is correct for OPTIONS requests only
        }

        return $request; // ✅ Let other requests continue
    }
}
