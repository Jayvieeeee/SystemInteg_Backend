<?php
require_once __DIR__ . '/../config/db.php';

class UserModel {

    public function exists($email, $username) {
        global $conn;
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? OR username=?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    public function create($data) {
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
        $stmt->bind_param("i",$user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        unset($user['password']);
        return $user;
    }

    public function findByEmailUsername($value) {
        global $conn;
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? OR username=?");
        $stmt->bind_param("ss",$value,$value);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}
