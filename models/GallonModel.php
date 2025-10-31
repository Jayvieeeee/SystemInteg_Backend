<?php
class GallonModel
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Get all gallons
     */
    public function getAll()
    {
        $result = $this->conn->query("SELECT * FROM gallons");
        $gallons = [];
        while ($row = $result->fetch_assoc()) {
            $gallons[] = $row;
        }
        return $gallons;
    }

    /**
     * Get gallon by ID
     */
    public function getById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM gallons WHERE gallon_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Create new gallon
     * ✅ Returns complete data including image_url
     */
    public function create($data)
    {
        $stmt = $this->conn->prepare("INSERT INTO gallons (name, image_url, size, price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssd", $data['name'], $data['image_url'], $data['size'], $data['price']);

        if ($stmt->execute()) {
            return [
                'success' => true,
                'gallon_id' => $stmt->insert_id,
                'image_url' => $data['image_url'],
                'message' => 'Gallon created successfully'
            ];
        }

        return [
            'success' => false,
            'message' => $this->conn->error ?: 'Failed to create gallon'
        ];
    }

    /**
     * Update gallon (supports partial updates)
     * ✅ FIXED: Only updates non-null fields dynamically
     */
    public function update($id, $data)
    {
        // First, get existing data
        $existing = $this->getById($id);

        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Gallon not found'
            ];
        }

        // ✅ Build dynamic SQL query based on provided fields
        $fields = [];
        $values = [];
        $types = '';

        if (isset($data['name'])) {
            $fields[] = 'name=?';
            $values[] = $data['name'];
            $types .= 's';
        }

        if (isset($data['image_url'])) {
            $fields[] = 'image_url=?';
            $values[] = $data['image_url'];
            $types .= 's';
        }

        if (isset($data['size'])) {
            $fields[] = 'size=?';
            $values[] = $data['size'];
            $types .= 's';
        }

        if (isset($data['price'])) {
            $fields[] = 'price=?';
            $values[] = $data['price'];
            $types .= 'd';
        }

        // If no fields to update, return early
        if (empty($fields)) {
            return [
                'success' => false,
                'message' => 'No fields to update'
            ];
        }

        // Add ID to the end
        $values[] = $id;
        $types .= 'i';

        // ✅ Dynamic SQL query
        $sql = "UPDATE gallons SET " . implode(', ', $fields) . " WHERE gallon_id=?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$values);

        if ($stmt->execute()) {
            // Get updated data
            $updated = $this->getById($id);

            return [
                'success' => true,
                'message' => 'Gallon updated successfully',
                'image_url' => $updated['image_url'] // ✅ Return actual image URL
            ];
        }

        return [
            'success' => false,
            'message' => $this->conn->error ?: 'Failed to update gallon'
        ];
    }

    /**
     * Delete gallon
     */
    public function delete($id)
    {
        // Check if gallon exists first
        $existing = $this->getById($id);

        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Gallon not found'
            ];
        }

        $stmt = $this->conn->prepare("DELETE FROM gallons WHERE gallon_id=?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            return [
                'success' => true,
                'message' => 'Gallon deleted successfully'
            ];
        }

        return [
            'success' => false,
            'message' => $this->conn->error ?: 'Failed to delete gallon'
        ];
    }
}
