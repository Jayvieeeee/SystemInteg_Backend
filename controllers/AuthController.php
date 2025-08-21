<?php
require_once __DIR__ . '/../models/UserModel.php';

class AuthController {

    private $userModel;

    public function __construct() {
        $this->userModel = new UserModel();
    }

    public function login($data) {
        $identifier = $data['identifier'] ?? '';

        if(empty($identifier)) {
            throw new AuthException("Username or email is required", 422);
        }
        
        $user = $this->userModel->findByEmailUsername($identifier);
        if(!$user) {
            throw new AuthException("User not found", 401);
        }

        if(!password_verify($data['password'] ?? '', $user['password'])) {
            throw new AuthException("Incorrect password", 401);
        }

        unset($user['password']);
        return[
            "error" => false,
            "message" => "Login successful",
            "user" => $user
        ];
    }

    public function register($data) {
        if ($this->userModel->exists($data['email'], $data['username'])) {
            throw new ConflictException("Email or username already exists", 409);
        }

        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['role'] = $data['role'] ?? 'customer';
        $data['contact_no'] = $data['contact_no'] ?? '';

        $user = $this->userModel->create($data);
        return ["error"=>false, "message"=>"Registration successful", "user"=>$user];
    }
}
