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

        // format: Bearer <token>
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            throw new AuthException("Invalid Authorization header format");
        }

        $token = $matches[1];

        try {
            // allow slight clock skew
            JWT::$leeway = 60; // 60 seconds
            $decoded = JWT::decode($token, new Key(JWT_SECRET_KEY, 'HS256'));

            // use sub as userId
            $request['userId'] = $decoded->sub ?? null;
            $request['userRole'] = $decoded->role ?? null;

            if (!$request['userId']) {
                throw new AuthException("Invalid token payload");
            }
        } catch (Exception $e) {
            throw new AuthException("Invalid or expired token");
        }

        return $request;
    }
}
