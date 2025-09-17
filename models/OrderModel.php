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
            $orderId = $stmt->insert_id;
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
            $orderId = $stmt->insert_id;
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

            // Delete selected cart items
            $cartIds = implode(',', array_map('intval', array_column($cartItems, 'cart_id')));
            $this->conn->query("DELETE FROM cart WHERE cart_id IN ($cartIds) AND user_id = " . intval($userId));

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
        return $result;
    }

    // Get selected cart items
    public function getSelectedCartItems($userId, $cartItemIds)
    {
        $ids = implode(',', array_map('intval', explode(',', $cartItemIds)));
        $query = "
            SELECT c.cart_id, c.gallon_id, c.quantity, g.price, 
                   (c.quantity * g.price) as total_price
            FROM cart c
            JOIN gallons g ON c.gallon_id = g.gallon_id
            WHERE c.user_id = ? AND c.cart_id IN ($ids)
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    // Get order by ID
    public function getById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM orders WHERE order_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    // Get items for an order
    public function getOrderItems($orderId)
    {
        $stmt = $this->conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    // Get all orders by user
    public function getOrdersByUserId($userId)
    {
        $stmt = $this->conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }
}
