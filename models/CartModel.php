<?php
class CartModel
{
    private $conn;
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // Get cart items for a user
    public function getCartItems($userId)
    {
        $stmt = $this->conn->prepare("
            SELECT c.cart_item_id, c.gallon_id, c.quantity,g.name, g.size, g.price, g.image_url, (c.quantity * g.price) AS total_price
            FROM cart_items c
            JOIN gallons g ON c.gallon_id = g.gallon_id
            WHERE c.user_id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Add or update cart item
    public function addToCart($userId, $gallonId, $quantity = 1)
    {
        // Check if item already exists in cart
        $stmt = $this->conn->prepare("SELECT quantity FROM cart_items WHERE user_id = ? AND gallon_id = ?");
        $stmt->bind_param("ii", $userId, $gallonId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Update quantity (replace or add, here we replace)
            $update = $this->conn->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND gallon_id = ?");
            $update->bind_param("iii", $quantity, $userId, $gallonId);
            $update->execute();
        } else {
            // Insert new item
            $insert = $this->conn->prepare("INSERT INTO cart_items (user_id, gallon_id, quantity) VALUES (?, ?, ?)");
            $insert->bind_param("iii", $userId, $gallonId, $quantity);
            $insert->execute();
        }

        return $this->getCartItems($userId);
    }

    // Remove items from cart
    public function removeItems($userId, $cartItemIds = [])
    {
        if (empty($cartItemIds)) return false;

        $placeholders = implode(',', array_fill(0, count($cartItemIds), '?'));
        $types = str_repeat('i', count($cartItemIds) + 1); // +1 for user_id
        $params = array_merge([$userId], $cartItemIds);

        $stmt = $this->conn->prepare("DELETE FROM cart_items WHERE user_id = ? AND cart_item_id IN ($placeholders)");

        // bind params dynamically
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    public function updateQuantity($userId, $cartItemId, $quantity)
    {
        $stmt = $this->conn->prepare("
        UPDATE cart_items 
        SET quantity = ? 
        WHERE cart_item_id = ? AND user_id = ?
    ");
        $stmt->bind_param("iii", $quantity, $cartItemId, $userId);
        return $stmt->execute();
    }

    public function decreaseQuantity($userId, $cartItemId)
    {
        // First check current quantity
        $checkStmt = $this->conn->prepare("
        SELECT quantity FROM cart_items 
        WHERE user_id = ? AND cart_item_id = ?
    ");
        $checkStmt->bind_param('ii', $userId, $cartItemId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $item = $result->fetch_assoc();

        if (!$item) {
            return false;
        }

        if ($item['quantity'] > 1) {
            // Decrease quantity
            $updateStmt = $this->conn->prepare("
            UPDATE cart_items 
            SET quantity = quantity - 1 
            WHERE user_id = ? AND cart_item_id = ?
        ");
            $updateStmt->bind_param('ii', $userId, $cartItemId);
            return $updateStmt->execute();
        } else {
            // Remove item completely
            $deleteStmt = $this->conn->prepare("
            DELETE FROM cart_items 
            WHERE user_id = ? AND cart_item_id = ?
        ");
            $deleteStmt->bind_param('ii', $userId, $cartItemId);
            return $deleteStmt->execute();
        }
    }
}
