<?php

require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../libs/php-jwt-main/src/JWT.php';
require_once __DIR__ . '/../libs/php-jwt-main/src/Key.php';
require_once __DIR__ . '/../services/EmailService.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class AuthController
{
    private $userModel;
    private $emailService;

    public function __construct(UserModel $userModel, EmailService $emailService)
    {
        $this->userModel = $userModel;
        $this->emailService = $emailService;
    }

    public function login($data)
    {
        $identifier = $data['identifier'] ?? '';

        if (empty($identifier)) {
            throw new AuthException("Username or email is required", 422);
        }

        $user = $this->userModel->findByEmailUsername($identifier);

        if (!$user || !password_verify($data['password'] ?? '', $user['password'])) {
            throw new AuthException("Incorrect username or password", 401);
        }

        unset($user['password']);

        $payload = [
            'iss' => 'my-app',
            'sub' => $user['user_id'],
            'iat' => time(),
            'exp' => time() + 3600 //1 hour expiry
        ];

        $jwt = JWT::encode($payload, JWT_SECRET_KEY, 'HS256');

        return [
            "error" => false,
            "message" => "Login successful",
            "user" => $user,
            "token" => $jwt
        ];
    }

    public function register($data)
    {
        if ($this->userModel->exists($data['email'], $data['username'])) {
            throw new ConflictException("Email or username already exists", 409);
        }

        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['role'] = $data['role'] ?? 'customer';
        $data['contact_no'] = $data['contact_no'] ?? '';

        $user = $this->userModel->create($data);

        // Send welcome email
        $this->emailService->sendWelcomeEmail($user);

        return ["error" => false, "message" => "Registration successful", "user" => $user];
    }

    public function forgotPassword($data)
    {
        $email = $data['email'] ?? '';
        if (empty($email)) {
            throw new ValidationException("Email is required", 422);
        }

        $user = $this->userModel->findByEmailUsername($email);
        if (!$user) {
            throw new NotFoundException("User not found", 404);
        }

        // generate 6-digit numeric code
        $code = random_int(100000, 999999);
        $exp = time() + 900; // 15 mins expiration

        // save code and expiration to DB
        $this->userModel->saveResetCode($user['user_id'], $code, $exp);

        // Send password reset email using EmailService
        $this->emailService->sendPasswordResetEmail($user, $code);

        return [
            'error' => false,
            'message' => 'Reset code sent to your email'
        ];
    }

    public function verifyResetCode($data)
    {
        $code = $data['code'] ?? '';
        if (empty($code)) {
            throw new ValidationException("Code is required", 422);
        }

        $user = $this->userModel->findByResetCode($code);
        if (!$user) {
            throw new AuthException("Invalid or expired code", 401);
        }

        $this->userModel->markResetVerified($user['user_id']);

        return [
            'error' => false,
            'message' => 'Code verified successfully'
        ];
    }

    public function resetPassword($data)
    {
        $newPassword = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';

        if (empty($newPassword) || empty($confirmPassword)) {
            throw new ValidationException("New password and confirm password are required", 422);
        }

        if ($newPassword !== $confirmPassword) {
            throw new ValidationException("Passwords do not match", 422);
        }

        $user = $this->userModel->findVerifiedResetUser();
        if (!$user) {
            throw new AuthException("No verified reset request found", 401);
        }

        $this->userModel->updatePassword($user['user_id'], password_hash($newPassword, PASSWORD_DEFAULT));
        $this->userModel->deleteResetCode($user['user_id']);

        return [
            'error' => false,
            'message' => 'Password successfully updated'
        ];
    }
}
