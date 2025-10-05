<?php

class OrderModel
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // Create order with direct items
    public function create($data)
    {
        $this->conn->begin_transaction();
        try {
            // Insert into orders
            $stmt = $this->conn->prepare("
                INSERT INTO orders (user_id, address_id, pickup_datetime, payment_method, total_price)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "iissd",
                $data['user_id'],
                $data['address_id'],
                $data['pickup_datetime'],
                $data['payment_method'],
                $data['total_price']
            );
            $stmt->execute();
            $orderId = $this->conn->insert_id;
            $stmt->close();

            // Insert order items
            $stmtItem = $this->conn->prepare("
                INSERT INTO order_items (order_id, gallon_id, quantity, price, total_price)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($data['items'] as $item) {
                $stmtItem->bind_param(
                    "iiidd",
                    $orderId,
                    $item['gallon_id'],
                    $item['quantity'],
                    $item['price'],
                    $item['total_price']
                );
                $stmtItem->execute();
            }
            $stmtItem->close();

            $this->conn->commit();
            return $orderId;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    // Create order from cart
    public function createFromCart($userId, $cartItems, $data)
    {
        $this->conn->begin_transaction();
        try {
            // Insert into orders
            $stmt = $this->conn->prepare("
                INSERT INTO orders (user_id, address_id, pickup_datetime, payment_method, total_price)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "iissd",
                $userId,
                $data['address_id'],
                $data['pickup_datetime'],
                $data['payment_method'],
                $data['total_price']
            );
            $stmt->execute();
            $orderId = $this->conn->insert_id;
            $stmt->close();

            // Insert order items from cart
            $stmtItem = $this->conn->prepare("
                INSERT INTO order_items (order_id, gallon_id, quantity, price, total_price)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($cartItems as $item) {
                $stmtItem->bind_param(
                    "iiidd",
                    $orderId,
                    $item['gallon_id'],
                    $item['quantity'],
                    $item['price'],
                    $item['total_price']
                );
                $stmtItem->execute();
            }
            $stmtItem->close();

            // Delete selected cart items - FIXED: Use prepared statement
            $cartIds = array_column($cartItems, 'cart_id');
            if (!empty($cartIds)) {
                $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
                $stmtDelete = $this->conn->prepare("
                    DELETE FROM cart 
                    WHERE cart_id IN ($placeholders) AND user_id = ?
                ");

                // Bind parameters: cart IDs + user_id
                $types = str_repeat('i', count($cartIds)) . 'i';
                $params = array_merge($cartIds, [$userId]);
                $stmtDelete->bind_param($types, ...$params);
                $stmtDelete->execute();
                $stmtDelete->close();
            }

            $this->conn->commit();
            return $orderId;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    // Get gallon by ID (for validation + price)
    public function getGallonById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM gallons WHERE gallon_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            throw new NotFoundException("Gallon not found with ID: $id");
        }

        return $result;
    }

    // Get selected cart items - FIXED: Handle empty results
    public function getSelectedCartItems($userId, $cartItemIds)
    {
        if (empty($cartItemIds)) {
            return [];
        }

        // Convert to array if it's a string
        if (is_string($cartItemIds)) {
            $cartItemIds = explode(',', $cartItemIds);
        }

        $cartItemIds = array_map('intval', $cartItemIds);
        $placeholders = implode(',', array_fill(0, count($cartItemIds), '?'));

        $query = "
            SELECT c.cart_id, c.gallon_id, c.quantity, g.price, 
                   (c.quantity * g.price) as total_price
            FROM cart c
            JOIN gallons g ON c.gallon_id = g.gallon_id
            WHERE c.user_id = ? AND c.cart_id IN ($placeholders)
        ";

        $stmt = $this->conn->prepare($query);

        // Bind parameters: user_id + cart IDs
        $types = 'i' . str_repeat('i', count($cartItemIds));
        $params = array_merge([$userId], $cartItemIds);
        $stmt->bind_param($types, ...$params);

        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $result ?: [];
    }

    // Get order by ID - FIXED: Handle not found
    public function getById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT o.*, a.label as address_label, a.address as full_address
            FROM orders o
            LEFT JOIN addresses a ON o.address_id = a.address_id
            WHERE o.order_id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result; // Return null if not found
    }

    // Get items for an order - FIXED: Include gallon details
    public function getOrderItems($orderId)
    {
        $stmt = $this->conn->prepare("
            SELECT oi.*, g.name as gallon_name, g.image_url
            FROM order_items oi
            JOIN gallons g ON oi.gallon_id = g.gallon_id
            WHERE oi.order_id = ?
        ");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $result ?: [];
    }

    // Get all orders by user - FIXED: Include address info
    public function getOrdersByUserId($userId)
    {
        $stmt = $this->conn->prepare("
            SELECT o.*, a.label as address_label, a.address as full_address
            FROM orders o
            LEFT JOIN addresses a ON o.address_id = a.address_id
            WHERE o.user_id = ? 
            ORDER BY o.created_at DESC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $result ?: [];
    }

    // Additional helper method to check order ownership
    public function isOrderOwnedByUser($orderId, $userId)
    {
        $stmt = $this->conn->prepare("
            SELECT order_id FROM orders 
            WHERE order_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $orderId, $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return !empty($result);
    }
}
