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
            'user_id' => $user['user_id'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + (86400 * 7),
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
        $email = $data['email'] ?? null;
        $username = $data['username'] ?? null;
        $contactNo = $data['contact_no'] ?? null;

        // Validate required fields
        if (!$email || !$username || !$contactNo) {
            throw new ValidationException("Email, username, and contact number are required", 422);
        }

        // Check if email or username exists
        if ($this->userModel->exists($email, $username)) {
            throw new ConflictException("Email or username already exists", 409);
        }

        // Check if contact number already exists
        if ($this->userModel->contactExists($contactNo)) {
            throw new ConflictException("Contact number already exists", 409);
        }

        // Hash password
        $data['password'] = password_hash($data['password'] ?? '', PASSWORD_DEFAULT);

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

    public function verifyEmail()
    {
        // Get token from query string
        $token = $_GET['token'] ?? null;

        if (!$token) {
            http_response_code(400);
            return [
                "error" => true,
                "message" => "Verification token is required"
            ];
        }

        // Find user by verification token
        $user = $this->userModel->findByVerificationToken($token);

        if (!$user) {
            http_response_code(404);
            return [
                "error" => true,
                "message" => "Invalid or expired verification token"
            ];
        }

        // Check if already verified
        if ($user['is_verified']) {
            return [
                "error" => false,
                "message" => "Email already verified",
                "redirect" => "/customer/dashboard"
            ];
        }

        // Update user verification status
        $this->userModel->verifyEmail($user['id']);

        // Send welcome email after successful verification
        // Change this to sendWelcomeEmail if you have that method
        $this->emailService->sendVerificationEmail($user);

        return [
            "error" => false,
            "message" => "Email verified successfully! You can now login.",
            "redirect" => "/customer/dashboard"
        ];
    }

    public function sendVerificationEmailRequest()
    {
        try {
            // Get the authenticated user (from JWT token or session)
            $userId = $this->getAuthenticatedUserId(); // You need to implement this

            if (!$userId) {
                http_response_code(401);
                return [
                    "error" => true,
                    "message" => "Authentication required"
                ];
            }

            // Get user data
            $user = $this->userModel->findById($userId);

            if (!$user) {
                http_response_code(404);
                return [
                    "error" => true,
                    "message" => "User not found"
                ];
            }

            // Check if already verified
            if ($user['is_verified']) {
                return [
                    "error" => false,
                    "message" => "Email already verified",
                    "already_verified" => true
                ];
            }

            // Generate verification token if doesn't exist
            if (!$user['verification_token']) {
                $verificationToken = bin2hex(random_bytes(32));
                $this->userModel->updateVerificationToken($user['id'], $verificationToken);
                $user['verification_token'] = $verificationToken;
            }

            // Send verification email
            $emailSent = $this->emailService->sendVerificationEmail($user);

            if (!$emailSent) {
                http_response_code(500);
                return [
                    "error" => true,
                    "message" => "Failed to send verification email"
                ];
            }

            return [
                "error" => false,
                "success" => true,
                "message" => "Verification email sent successfully! Please check your inbox and spam folder."
            ];
        } catch (Exception $e) {
            http_response_code(500);
            return [
                "error" => true,
                "message" => "Failed to send verification email: " . $e->getMessage()
            ];
        }
    }

    // Helper method to get authenticated user ID (adjust based on your auth system)
    private function getAuthenticatedUserId()
    {
        // If using session-based auth
        // return $_SESSION['user_id'] ?? null;

        // If using JWT tokens (you'll need to decode the token)
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            // Decode JWT token and return user ID
            // return $this->jwtService->decode($token)->user_id;
        }

        return null;
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
