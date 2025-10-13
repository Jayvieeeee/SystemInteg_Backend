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

        $user = $this->userModel->findById($authUser['user_id']);
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
            throw new AuthException("Unauthorized: No token provided", 401);
        }

        $token = trim(str_replace('Bearer ', '', $authHeader));

        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET_KEY, 'HS256'));

            if (!isset($decoded->user_id)) {
                throw new AuthException("Invalid token payload: missing user_id", 401);
            }

            return [
                'user_id' => $decoded->user_id,
                'role' => $decoded->role
            ];
        } catch (\Exception $e) {
            throw new AuthException("Invalid or expired token", 401);
        }
    }

    public function updateProfile()
    {
        $authUser = $this->getAuthenticatedUser();
        $rawInput = file_get_contents("php://input");
        $inputData = json_decode($rawInput, true);

        if (!$inputData) {
            throw new ValidationException("Invalid JSON input", 422);
        }

        $allowedFields = ['first_name', 'last_name', 'email', 'contact_no'];
        $updateData = [];
        $updatedFields = [];

        foreach ($allowedFields as $field) {
            if (isset($inputData[$field])) {
                $value = trim($inputData[$field]);

                // simple validation
                if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new ValidationException("Invalid email format", 422);
                }
                if (empty($value)) {
                    throw new ValidationException(ucfirst(str_replace("_", " ", $field)) . " cannot be empty", 422);
                }

                $updateData[$field] = $value;
                $updatedFields[] = $field;
            }
        }

        if (empty($updateData)) {
            throw new ValidationException("No valid fields to update", 422);
        }

        $updated = $this->userModel->updateProfile($authUser['user_id'], $updateData);

        if (!$updated) {
            throw new AppException("Failed to update " . implode(", ", $updatedFields), 400);
        }

        return [
            'error' => false,
            'message' => ucfirst(str_replace("_", " ", implode(", ", $updatedFields))) . " updated successfully",
            'updated_fields' => $updatedFields
        ];
    }

    public function changePassword()
    {
        $authUser = $this->getAuthenticatedUser();
        $rawInput = file_get_contents("php://input");
        $inputData = json_decode($rawInput, true);

        if (!$inputData) {
            throw new ValidationException("Invalid JSON input", 422);
        }

        // Required fields
        $oldPassword = $inputData['old_password'] ?? '';
        $newPassword = $inputData['new_password'] ?? '';
        $confirmPassword = $inputData['confirm_password'] ?? '';

        if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new ValidationException("All password fields are required", 422);
        }

        if ($newPassword !== $confirmPassword) {
            throw new ValidationException("New passwords do not match", 422);
        }

        // Check old password
        $user = $this->userModel->findByIdWithPassword($authUser['user_id']);
        if (!$user || !password_verify($oldPassword, $user['password'])) {
            throw new ValidationException("Old password is incorrect", 422);
        }

        // Hash new password and update
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updated = $this->userModel->changePassword($authUser['user_id'], $hashedPassword);

        if (!$updated) {
            throw new AppException("Failed to change password", 500);
        }

        return [
            'error' => false,
            'message' => 'Password changed successfully'
        ];
    }
}
