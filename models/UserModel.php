<?php
require_once __DIR__ . '/../config/db.php';

class UserModel
{
    public function exists($email, $username)
    {
        global $conn;
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? OR username=?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    public function create($data)
    {
        global $conn;
        $stmt = $conn->prepare("INSERT INTO users
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
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        unset($user['password']);
        return $user;
    }

    public function findByEmailUsername($value)
    {
        global $conn;
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? OR username=?");
        $stmt->bind_param("ss", $value, $value);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // ---------------- PASSWORD RESET HELPERS ----------------
    public function saveResetCode($userId, $code, $exp)
    {
        global $conn;

        if (is_int($exp)) {
            $exp = date("Y-m-d H:i:s", $exp);
        }

        $stmt = $conn->prepare("UPDATE users 
                                SET reset_code = ?, reset_code_exp = ?, reset_verified = 0 
                                WHERE user_id = ?");
        $stmt->bind_param("ssi", $code, $exp, $userId);
        if (!$stmt->execute()) {
            error_log("DB Error in saveResetCode: " . $stmt->error);
            return false;
        }
        return $stmt->affected_rows > 0;
    }

    public function findByResetCode($code)
    {
        global $conn;
        $now = date("Y-m-d H:i:s");

        $stmt = $conn->prepare("SELECT * FROM users 
                                WHERE reset_code = ? 
                                AND reset_code_exp > ?");
        $stmt->bind_param("ss", $code, $now);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function markResetVerified($userId)
    {
        global $conn;
        $stmt = $conn->prepare("UPDATE users 
                                SET reset_verified = 1 
                                WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }

    public function findVerifiedResetUser()
    {
        global $conn;
        $now = date("Y-m-d H:i:s");
        $stmt = $conn->prepare("SELECT * FROM users 
                                WHERE reset_verified = 1 
                                AND reset_code_exp > ? 
                                LIMIT 1");
        $stmt->bind_param("s", $now);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function deleteResetCode($userId)
    {
        global $conn;
        $stmt = $conn->prepare("UPDATE users 
                                SET reset_code = NULL, reset_code_exp = NULL, reset_verified = 0 
                                WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }

    public function updatePassword($userId, $hashedPassword)
    {
        global $conn;
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);
        $stmt->execute();
    }
}
