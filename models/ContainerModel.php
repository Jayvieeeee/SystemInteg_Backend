<?php
class ContainerModel
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // Get all containers
    public function getAll()
    {
        $result = $this->conn->query("SELECT * FROM Container");
        $containers = [];
        while ($row = $result->fetch_assoc()) {
            $containers[] = $row;
        }
        return $containers;
    }

    // Get container by ID
    public function getById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM Container WHERE container_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Create new container
    public function create($data)
    {
        $stmt = $this->conn->prepare("INSERT INTO Container (name, image_url, size, price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssd", $data['name'], $data['image_url'], $data['size'], $data['price']);
        $stmt->execute();
        return ['success' => true, 'id' => $stmt->insert_id];
    }

    // Update container
    public function update($id, $data)
    {
        $stmt = $this->conn->prepare("UPDATE Container SET name=?, image_url=?, size=?, price=? WHERE container_id=?");
        $stmt->bind_param("sssdi", $data['name'], $data['image_url'], $data['size'], $data['price'], $id);
        $stmt->execute();
        return ['success' => true];
    }

    // Delete container
    public function delete($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM Container WHERE container_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return ['success' => true];
    }
}
