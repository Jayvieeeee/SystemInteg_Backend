<?php

class UserModel
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function exists($email, $username)
    {
        $stmt = $this->conn->prepare("SELECT 1 FROM users WHERE email=? OR username=?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    public function create($data)
    {
        $stmt = $this->conn->prepare("INSERT INTO users
            (first_name,last_name,email,contact_no,username,password,role,status,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,1,NOW(),NOW())");
        $stmt->bind_param(
            "sssssss",
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['contact_no'],
            $data['username'],
            $data['password'],
            $data['role']
        );
        if (!$stmt->execute()) {
            throw new Exception("DB Insert failed: " . $stmt->error);
        }

        $user_id = $stmt->insert_id;
        return $this->findById($user_id);
    }

    public function findById($userId)
    {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE user_id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            unset($user['password'], $user['reset_code'], $user['reset_code_exp']);
        }
        return $user;
    }

    public function findByEmailUsername($value)
    {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email=? OR username=?");
        $stmt->bind_param("ss", $value, $value);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // PASSWORD RESET HELPERS
    public function saveResetCode($userId, $code, $exp)
    {
        if (is_int($exp)) {
            $exp = date("Y-m-d H:i:s", $exp);
        }
        $stmt = $this->conn->prepare("UPDATE users 
                                SET reset_code = ?, reset_code_exp = ?, reset_verified = 0 
                                WHERE user_id = ?");
        $stmt->bind_param("ssi", $code, $exp, $userId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to save reset code: " . $stmt->error);
        }
        return $stmt->affected_rows > 0;
    }

    public function findByResetCode($code)
    {
        $now = date("Y-m-d H:i:s");
        $stmt = $this->conn->prepare("SELECT * FROM users 
                                WHERE reset_code = ? 
                                AND reset_code_exp > ?");
        $stmt->bind_param("ss", $code, $now);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function markResetVerified($userId)
    {
        $stmt = $this->conn->prepare("UPDATE users SET reset_verified = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to mark reset verified: " . $stmt->error);
        }
    }

    public function findVerifiedResetUser()
    {
        $now = date("Y-m-d H:i:s");
        $stmt = $this->conn->prepare("SELECT * FROM users 
                                WHERE reset_verified = 1 
                                AND reset_code_exp > ? 
                                LIMIT 1");
        $stmt->bind_param("s", $now);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function deleteResetCode($userId)
    {
        $stmt = $this->conn->prepare("UPDATE users 
                                SET reset_code = NULL, reset_code_exp = NULL, reset_verified = 0 
                                WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete reset code: " . $stmt->error);
        }
    }

    public function updatePassword($userId, $hashedPassword)
    {
        $stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update password: " . $stmt->error);
        }
    }
}
