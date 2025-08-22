<?php
require_once __DIR__ . '/../exceptions/AuthException.php';
require_once __DIR__ . '/../config/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware extends BaseMiddleware
{
    public function handle($request)
    {
        // check if may Authorization header
        if (!isset($request['headers']['Authorization'])) {
            throw new AuthException("Missing Authorization header");
        }

        $authHeader = $request['headers']['Authorization'];

        // format: Bearer <token>
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            throw new AuthException("Invalid Authorization header format");
        }

        $token = $matches[1];

        try {
            // decode token gamit JWT_SECRET_KEY
            $decoded = JWT::decode($token, new Key(JWT_SECRET_KEY, 'HS256'));

            // lagay user info sa request para magamit sa controller or role middleware
            $request['userId'] = $decoded->id ?? null;
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
