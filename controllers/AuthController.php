<?php

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

        unset($user['password'], $user['reset_code'], $user['reset_code_exp'], $user['reset_verified']);

        $payload = [
            'iss' => 'my-app',
            'sub' => $user['user_id'],
            'user_id' => $user['user_id'], // 👈 add this!
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + 3600,
            'jti' => bin2hex(random_bytes(16))
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
        // Check if email or username exists (active users only)
        if ($this->userModel->exists($data['email'], $data['username'])) {
            throw new ConflictException("Email or username already exists", 409);
        }

        // Check if contact number already exists (active users only)
        if ($this->userModel->contactExists($data['contact_no'])) {
            throw new ConflictException("Contact number already exists", 409);
        }

        // Hash password
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

        // Default role
        $data['role'] = 'customer';

        // Create user
        $user = $this->userModel->create($data);

        // Send welcome email
        $this->emailService->sendWelcomeEmail($user);

        return [
            "error" => false,
            "message" => "Registration successful",
            "user" => $user
        ];
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

        $code = random_int(100000, 999999);
        $exp = time() + 900; // 15 mins expiration

        $this->userModel->saveResetCode($user['user_id'], $code, $exp);


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

    public function resendResetCode($request)
    {
        $userId = $request['userId'] ?? null;
        if (!$userId) throw new AuthException("Unauthorized", 401);

        $user = $this->userModel->findById($userId);
        if (!$user) throw new NotFoundException("User not found", 404);

        // generate new code
        $code = random_int(100000, 999999);
        $exp = time() + 900; // 15 mins

        $this->userModel->saveResetCode($userId, $code, $exp);
        $this->emailService->sendPasswordResetEmail($user, $code);

        return [
            'error' => false,
            'message' => 'New reset code sent to your email'
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

    public function logout()
    {

        return [
            'error' => false,
            'message' => 'Logout successful'
        ];
    }
}
