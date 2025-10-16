<?php
require_once __DIR__ . '/../exceptions/AuthException.php';
require_once __DIR__ . '/../config/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware extends BaseMiddleware
{
    private function getAuthorizationHeader()
    {
        $headers = null;

        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            foreach ($requestHeaders as $key => $value) {
                if (strcasecmp($key, 'Authorization') === 0) {
                    $headers = trim($value);
                    break;
                }
            }
        }

        return $headers;
    }

    public function handle($request)
    {
        $authHeader = $this->getAuthorizationHeader();

        if (!$authHeader) {
            throw new AuthException("Missing Authorization header");
        }

        // Format: Bearer <token>
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            throw new AuthException("Invalid Authorization header format");
        }

        $token = $matches[1];

        try {
            // Verify JWT_SECRET_KEY is set
            if (!defined('JWT_SECRET_KEY') || empty(JWT_SECRET_KEY)) {
                throw new AuthException("Server configuration error");
            }

            // Allow slight clock skew
            JWT::$leeway = 60; // 60 seconds

            $decoded = JWT::decode($token, new Key(JWT_SECRET_KEY, 'HS256'));

            // Extract user info from token
            $request['user_id'] = $decoded->user_id ?? null;
            $request['role'] = $decoded->role ?? null;

            if (!$request['user_id']) {
                throw new AuthException("Invalid token payload: missing user_id");
            }
        } catch (Exception $e) {
            throw new AuthException("Invalid or expired token: " . $e->getMessage());
        }

        return $request;
    }
}
