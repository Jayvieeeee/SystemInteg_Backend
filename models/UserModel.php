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

    public function contactExists($contactNo)
    {
        $stmt = $this->conn->prepare(
            "SELECT 1 FROM users WHERE contact_no = ? AND status = 'active'"
        );
        $stmt->bind_param("s", $contactNo);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }


    public function create($data)
    {
        $stmt = $this->conn->prepare("INSERT INTO users
        (first_name,last_name,email,contact_no,username,password,role,status,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?, NOW(), NOW())");

        $role = $data['role'] ?? 'customer';     // default to customer if not set
        $status = $data['status'] ?? 'active';   // default to active if not set

        $stmt->bind_param(
            "ssssssss",
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['contact_no'],
            $data['username'],
            $data['password'],
            $role,
            $status
        );

        if (!$stmt->execute()) {
            throw new Exception("DB Insert failed: " . $stmt->error);
        }

        $user_id = $stmt->insert_id;
        return $this->findById($user_id);
    }

    public function findByVerificationToken($token)
    {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE verification_token = ? AND deleted_at IS NULL");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function verifyEmail($userId)
    {
        $stmt = $this->conn->prepare("UPDATE users SET 
        is_verified = 1, 
        verification_token = NULL,
        email_verified_at = NOW(),
        updated_at = NOW()
        WHERE id = ?");

        $stmt->bind_param("i", $userId);

        if (!$stmt->execute()) {
            throw new Exception("Email verification failed: " . $stmt->error);
        }

        return true;
    }

    public function updateVerificationToken($userId, $token)
    {
        $stmt = $this->conn->prepare("UPDATE users SET verification_token = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $token, $userId);
        return $stmt->execute();
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

    public function findByIdWithPassword($userId)
    {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE user_id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc(); // DO NOT REMOVE password here
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

    public function updateProfile($userId, $data)
    {
        $fields = [];
        $params = [];
        $types = "";

        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
            $types .= "s"; // assume all string except id
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE user_id = ?";
        $params[] = $userId;
        $types .= "i";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }

    public function changePassword($userId, $newPassword)
    {
        $stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $newPassword, $userId);

        return $stmt->execute();
    }


    public function getAllCustomers()
    {
        $query = "SELECT 
                  u.user_id AS customer_id, 
                  CONCAT(u.first_name, ' ', u.last_name) AS name, 
                  u.email, 
                  u.contact_no, 
                  (
                      SELECT a.address
                      FROM addresses a
                      WHERE a.user_id = u.user_id
                      ORDER BY a.is_default DESC, a.created_at DESC
                      LIMIT 1
                  ) AS address,
                  (
                      SELECT COUNT(*)
                      FROM orders o
                      WHERE o.user_id = u.user_id
                  ) AS total_orders
              FROM users u
              WHERE u.role = 'customer'";

        $result = $this->conn->query($query);

        $customers = [];
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }

        return $customers;
    }

    public function getCustomerById($id)
    {
        $query = "SELECT 
                  u.user_id AS customer_id, 
                  CONCAT(u.first_name, ' ', u.last_name) AS name, 
                  u.email, 
                  u.contact_no, 
                  (
                      SELECT a.address
                      FROM addresses a
                      WHERE a.user_id = u.user_id
                      ORDER BY a.is_default DESC, a.created_at DESC
                      LIMIT 1
                  ) AS address,
                  (
                      SELECT COUNT(*)
                      FROM orders o
                      WHERE o.user_id = u.user_id
                  ) AS total_orders
              FROM users u
              WHERE u.user_id = ? AND u.role = 'customer'";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    public function getAllByRole($role)
    {
        $stmt = $this->conn->prepare("SELECT user_id, first_name, last_name, contact_no, email, status, created_at 
                                  FROM users WHERE role = ?");
        $stmt->bind_param("s", $role);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function updateRiderStatus($userId, $status)
    {
        $stmt = $this->conn->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("si", $status, $userId);
        return $stmt->execute();
    }
}
