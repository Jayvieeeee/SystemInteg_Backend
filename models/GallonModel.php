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
        $result = $this->conn->query("SELECT * FROM Gallons");
        $gallons = [];
        while ($row = $result->fetch_assoc()) {
            $gallons[] = $row;
        }
        return $gallons;
    }

    // Get gallon by ID
    public function getById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM Gallons WHERE gallon_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Create new gallon
    public function create($data)
    {
        $stmt = $this->conn->prepare("INSERT INTO Gallons (name, image_url, size, price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssd", $data['name'], $data['image_url'], $data['size'], $data['price']);
        $stmt->execute();
        return ['success' => true, 'id' => $stmt->insert_id];
    }

    // Update gallon
    public function update($id, $data)
    {
        $stmt = $this->conn->prepare("UPDATE Gallons SET name=?, image_url=?, size=?, price=? WHERE gallon_id=?");
        $stmt->bind_param("sssdi", $data['name'], $data['image_url'], $data['size'], $data['price'], $id);
        $stmt->execute();
        return ['success' => true];
    }

    // Delete gallon
    public function delete($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM Gallons WHERE gallon_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return ['success' => true];
    }
}
