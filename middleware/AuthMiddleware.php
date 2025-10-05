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

        // Debug: log the header
        error_log("🔐 Auth Header: " . ($headers ?: 'NOT FOUND'));
        return $headers;
    }

    public function handle($request)
    {
        $authHeader = $this->getAuthorizationHeader();

        if (!$authHeader) {
            error_log("❌ No authorization header found");
            throw new AuthException("Missing Authorization header");
        }

        // format: Bearer <token>
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            error_log("❌ Invalid authorization header format: " . $authHeader);
            throw new AuthException("Invalid Authorization header format");
        }

        $token = $matches[1];

        // Enhanced token debugging
        error_log("🔐 Raw Token: " . $token);
        error_log("🔐 Token length: " . strlen($token));

        // Check token structure
        $segments = explode('.', $token);
        error_log("🔐 Number of segments: " . count($segments));

        if (count($segments) !== 3) {
            error_log("❌ Token has wrong number of segments. Expected 3, got: " . count($segments));
            error_log("❌ Token segments: " . json_encode(array_map('strlen', $segments)));
            throw new AuthException("Invalid token structure: wrong number of segments");
        }

        try {
            // Verify JWT_SECRET_KEY is set
            if (!defined('JWT_SECRET_KEY') || empty(JWT_SECRET_KEY)) {
                error_log("❌ JWT_SECRET_KEY is not defined or empty");
                throw new AuthException("Server configuration error");
            }

            error_log("🔐 JWT Secret Key length: " . strlen(JWT_SECRET_KEY));

            // allow slight clock skew
            JWT::$leeway = 60; // 60 seconds

            // Debug: Try to manually decode parts first
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $header = json_decode(base64_decode($parts[0]), true);
                $payload = json_decode(base64_decode($parts[1]), true);

                error_log("🔐 Token Header: " . json_encode($header));
                error_log("🔐 Token Payload: " . json_encode($payload));

                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("❌ JSON decode error in token segments");
                    throw new AuthException("Invalid token encoding");
                }
            }

            $decoded = JWT::decode($token, new Key(JWT_SECRET_KEY, 'HS256'));

            // Debug: log the decoded token
            error_log("✅ Decoded token: " . print_r($decoded, true));

            // ✅ Use user_id from payload
            $request['user_id'] = $decoded->user_id ?? null;
            $request['role'] = $decoded->role ?? null;

            error_log("✅ Extracted user_id: " . ($request['user_id'] ?? 'NULL'));
            error_log("✅ Extracted role: " . ($request['role'] ?? 'NULL'));

            if (!$request['user_id']) {
                error_log("❌ Token payload missing user_id");
                throw new AuthException("Invalid token payload: missing user_id");
            }

            error_log("✅ Auth successful for user_id: " . $request['user_id']);
        } catch (Exception $e) {
            error_log("❌ JWT decode error: " . $e->getMessage());
            error_log("❌ Error type: " . get_class($e));
            throw new AuthException("Invalid or expired token: " . $e->getMessage());
        }

        return $request;
    }
}
