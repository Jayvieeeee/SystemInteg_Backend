<?php
class AddressModel
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getUserAddresses($userId)
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM addresses 
            WHERE user_id = ? 
            ORDER BY is_default DESC, created_at DESC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $addresses = [];
        while ($row = $result->fetch_assoc()) {
            $addresses[] = $row;
        }

        return $addresses;
    }

    public function getDefaultAddress($userId)
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM addresses 
            WHERE user_id = ? AND is_default = TRUE 
            LIMIT 1
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // If no default address, get the first address
            $addresses = $this->getUserAddresses($userId);
            return count($addresses) > 0 ? $addresses[0] : false;
        }

        return $result->fetch_assoc();
    }

    public function getAddressById($addressId, $userId)
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM addresses 
            WHERE address_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $addressId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return false;
        }

        return $result->fetch_assoc();
    }

    public function create($data)
    {
        $stmt = $this->conn->prepare("
            INSERT INTO addresses (user_id, label, address, is_default)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "issi",
            $data['user_id'],
            $data['label'],
            $data['address'],
            $data['is_default']
        );

        if ($stmt->execute()) {
            return $stmt->insert_id; // address_id ang ibabalik
        } else {
            throw new Exception("Failed to create address: " . $stmt->error);
        }
    }

    public function update($addressId, $data, $userId)
    {
        $stmt = $this->conn->prepare("
            UPDATE addresses 
            SET label = ?, address = ?, is_default = ?
            WHERE address_id = ? AND user_id = ?
        ");
        $stmt->bind_param(
            "ssiii",
            $data['label'],
            $data['address'],
            $data['is_default'],
            $addressId,
            $userId
        );

        return $stmt->execute();
    }

    public function delete($addressId, $userId)
    {
        $stmt = $this->conn->prepare("
            DELETE FROM addresses 
            WHERE address_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $addressId, $userId);

        return $stmt->execute();
    }

    public function setDefaultAddress($addressId, $userId)
    {
        // First, remove default from all addresses of this user
        $stmt1 = $this->conn->prepare("
            UPDATE addresses 
            SET is_default = FALSE 
            WHERE user_id = ?
        ");
        $stmt1->bind_param("i", $userId);
        $stmt1->execute();

        // Then set the specified address as default
        $stmt2 = $this->conn->prepare("
            UPDATE addresses 
            SET is_default = TRUE 
            WHERE address_id = ? AND user_id = ?
        ");
        $stmt2->bind_param("ii", $addressId, $userId);

        return $stmt2->execute();
    }
}
