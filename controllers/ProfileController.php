<?php

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class ProfileController
{
    private $userModel;

    public function __construct(UserModel $userModel)
    {
        $this->userModel = $userModel;
    }

    public function profile()
    {
        $authUser = $this->getAuthenticatedUser(); // get user_id from JWT

        $user = $this->userModel->findById($authUser['id']);
        if (!$user) {
            throw new NotFoundException("User not found", 404);
        }

        unset($user['password']); // never return password

        return [
            'error' => false,
            'user' => $user
        ];
    }

    private function getAuthenticatedUser()
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new AuthException("Unauthorized", 401);
        }

        $token = trim(str_replace('Bearer ', '', $authHeader));

        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET_KEY, 'HS256'));
            return ['id' => $decoded->id, 'role' => $decoded->role];
        } catch (\Exception $e) {
            throw new AuthException("Invalid token", 401);
        }
    }
}
