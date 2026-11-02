<?php
require_once __DIR__ . '/../exceptions/AuthException.php';
require_once __DIR__ . '/../config/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware extends BaseMiddleware
{
    private $userModel;

    public function __construct($userModel = null)
    {
        $this->userModel = $userModel;
    }

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
            throw new AuthException("Unauthorized: Missing Authorization header", 401);
        }

        if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
            throw new AuthException("Unauthorized: Invalid Authorization header format", 401);
        }

        $token = $matches[1];

        try {
            if (!defined('JWT_SECRET_KEY') || empty(JWT_SECRET_KEY)) {
                throw new AuthException("Server configuration error", 500);
            }

            JWT::$leeway = 60;

            $decoded = JWT::decode($token, new Key(JWT_SECRET_KEY, 'HS256'));
            $userId = isset($decoded->user_id) ? (int) $decoded->user_id : null;
            $role = $decoded->role ?? null;

            if (!$userId) {
                throw new AuthException("Invalid token payload: missing user_id", 401);
            }

            if (!$this->userModel instanceof UserModel) {
                $request['user_id'] = $userId;
                $request['role'] = $role;
                return $request;
            }

            $user = $this->userModel->findById($userId);

            if (!$user) {
                throw new AuthException("Unauthorized: user no longer exists", 401);
            }

            if (($user['status'] ?? 'inactive') !== 'active') {
                throw new AuthException("Unauthorized: account is inactive or suspended", 401);
            }

            $request['user_id'] = $userId;
            $request['role'] = $user['role'] ?? $role;

            return $request;
        } catch (AuthException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new AuthException("Invalid or expired token", 401);
        }
    }
}
