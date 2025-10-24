<?php
class GallonModel
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // Get all gallons
    public function getAll()
    {
        $result = $this->conn->query("SELECT * FROM gallons");
        $gallons = [];
        while ($row = $result->fetch_assoc()) {
            $gallons[] = $row;
        }
        return $gallons;
    }

    // Get gallon by ID
    public function getById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM gallons WHERE gallon_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }


    // Create new gallon
    public function create($data)
    {
        $stmt = $this->conn->prepare("INSERT INTO gallons (name, image_url, size, price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssd", $data['name'], $data['image_url'], $data['size'], $data['price']);

        if ($stmt->execute()) {
            return [
                'success' => true,
                'gallon_id' => $stmt->insert_id,
                'image_url' => $data['image_url'] // ✅ Add this
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to create gallon'
        ];
    }

    // Update gallon
    public function update($id, $data)
    {
        $stmt = $this->conn->prepare("UPDATE gallons SET name=?, image_url=?, size=?, price=? WHERE gallon_id=?");
        $stmt->bind_param("sssdi", $data['name'], $data['image_url'], $data['size'], $data['price'], $id);
        $stmt->execute();
        return ['success' => true];
    }

    // Delete gallon
    public function delete($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM gallons WHERE gallon_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return ['success' => true];
    }
}
