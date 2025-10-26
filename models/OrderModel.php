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

    public function getOrderStats()
    {
        $stats = [];

        // Pending Orders
        $query = "SELECT COUNT(*) as count FROM orders WHERE status = 'pending'";
        $stats['pending'] = $this->conn->query($query)->fetch_assoc()['count'];

        // Completed Orders
        $query = "SELECT COUNT(*) as count FROM orders WHERE status = 'completed'";
        $stats['completed'] = $this->conn->query($query)->fetch_assoc()['count'];

        // Cancelled Orders
        $query = "SELECT COUNT(*) as count FROM orders WHERE status = 'cancelled'";
        $stats['cancelled'] = $this->conn->query($query)->fetch_assoc()['count'];

        // Total Orders
        $query = "SELECT COUNT(*) as count FROM orders";
        $stats['total'] = $this->conn->query($query)->fetch_assoc()['count'];

        return $stats;
    }

    public function getOrderStatsByUser($userId)
    {
        $sql = "SELECT 
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                COUNT(*) AS total
            FROM orders
            WHERE user_id = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        // convert nulls to 0
        return [
            'pending' => (int) ($result['pending'] ?? 0),
            'completed' => (int) ($result['completed'] ?? 0),
            'cancelled' => (int) ($result['cancelled'] ?? 0),
            'total' => (int) ($result['total'] ?? 0)
        ];
    }


    public function getLatestOrders($limit = 5)
    {
        $query = "
        SELECT 
            o.order_id,
            o.total_price,
            o.status,
            o.created_at,
            GROUP_CONCAT(CONCAT(g.name, ' x', oi.quantity) SEPARATOR ', ') AS order_items
        FROM (
            SELECT order_id, total_price, status, created_at
            FROM orders
            ORDER BY created_at DESC
            LIMIT ?
        ) AS o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN gallons g ON oi.gallon_id = g.gallon_id
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
    ";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC) ?: [];
    }


    public function getLatestOrdersByCustomer($customerId, $limit = 5)
    {
        $query = "SELECT 
                o.order_id, 
                o.total_price, 
                o.status, 
                o.created_at,
                GROUP_CONCAT(CONCAT(g.name, ' x', oi.quantity) SEPARATOR ', ') AS order_items
              FROM orders o
              JOIN order_items oi ON o.order_id = oi.order_id
              JOIN gallons g ON oi.gallon_id = g.gallon_id
              WHERE o.user_id = ?
              GROUP BY o.order_id
              ORDER BY o.created_at DESC
              LIMIT ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $customerId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC) ?: [];
    }


    public function updatePaymentStatus($orderId, $status, $paymentMethod)
    {
        $query = "UPDATE orders SET payment_status = ?, payment_method = ? WHERE order_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssi", $status, $paymentMethod, $orderId);

        return $stmt->execute();
    }

    public function getAllOrders()
    {
        $query = "
        SELECT 
            o.order_id,
            o.user_id,
            CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
            GROUP_CONCAT(CONCAT(g.name, ' x', oi.quantity) SEPARATOR ', ') AS products,
            o.total_price,
            DATE_FORMAT(o.created_at, '%m-%d-%Y %h:%i%p') AS date_time,
            o.status
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN gallons g ON oi.gallon_id = g.gallon_id
        GROUP BY o.order_id, o.user_id, u.first_name, u.last_name, o.total_price, o.created_at, o.status
        ORDER BY o.created_at DESC
    ";

        $result = $this->conn->query($query);
        $orders = [];

        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }

        return $orders;
    }

    public function getOrderById($orderId)
    {
        $stmt = $this->conn->prepare("
            SELECT o.*, 
                   u.first_name, u.last_name, 
                   a.label AS address_label, a.address AS full_address
            FROM orders o
            JOIN users u ON o.user_id = u.user_id
            LEFT JOIN addresses a ON o.address_id = a.address_id
            WHERE o.order_id = ?
        ");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            return null;
        }

        // Add order items din para complete view sa admin
        $stmtItems = $this->conn->prepare("
            SELECT oi.*, g.name AS gallon_name, g.image_url
            FROM order_items oi
            JOIN gallons g ON oi.gallon_id = g.gallon_id
            WHERE oi.order_id = ?
        ");
        $stmtItems->bind_param("i", $orderId);
        $stmtItems->execute();
        $order['items'] = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtItems->close();

        $order['customer_name'] = $order['first_name'] . ' ' . $order['last_name'];
        unset($order['first_name'], $order['last_name']);

        return $order;
    }

    /**
     * Get all orders filtered by status (for admin filters)
     */
    public function getOrdersByStatus($status)
    {
        $stmt = $this->conn->prepare("
            SELECT o.order_id, o.total_price, o.status, o.created_at,
                   u.first_name, u.last_name
            FROM orders o
            JOIN users u ON o.user_id = u.user_id
            WHERE o.status = ?
            ORDER BY o.created_at DESC
        ");
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($result as &$order) {
            $order['total_price'] = (float) $order['total_price'];
            $order['customer_name'] = $order['first_name'] . ' ' . $order['last_name'];
            unset($order['first_name'], $order['last_name']);
        }

        return $result;
    }

    /**
     * Update order status (for admin actions)
     */
    public function updateStatus($orderId, $newStatus)
    {
        $stmt = $this->conn->prepare("
            UPDATE orders 
            SET status = ? 
            WHERE order_id = ?
        ");
        $stmt->bind_param("si", $newStatus, $orderId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    public function getOrdersByCustomerId($customerId)
    {
        $query = "SELECT 
                o.order_id,
                o.status,
                o.total_price,
                o.payment_method,
                o.pickup_datetime,
                g.name AS gallon_name
              FROM orders o
              LEFT JOIN order_items oi ON o.order_id = oi.order_id
              LEFT JOIN gallons g ON oi.gallon_id = g.gallon_id
              WHERE o.user_id = ?
              ORDER BY o.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $result = $stmt->get_result();

        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }

        return $orders;
    }
    public function getOrdersForActiveRider()
    {
        $statuses = ['to_pickup', 'to_deliver'];
        $statusPlaceholders = implode(',', array_fill(0, count($statuses), '?'));
        $types = str_repeat('s', count($statuses));

        $sql = "SELECT 
                o.*,
                CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
                u.first_name,
                u.last_name,
                u.contact_no,
                a.address
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.user_id
            LEFT JOIN addresses a ON o.address_id = a.address_id
            WHERE o.status IN ($statusPlaceholders)
            ORDER BY o.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$statuses);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }


    // Update order status (picked_up / delivered)
    public function riderUpdateStatus($orderId, $newStatus)
    {
        if (!in_array($newStatus, ['picked_up', 'delivered'])) {
            throw new Exception("Invalid status");
        }

        $stmt = $this->conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $stmt->bind_param("si", $newStatus, $orderId);
        $stmt->execute();
        return $stmt->affected_rows;
    }

    public function getDeliveriesByRiderId($riderId)
    {
        $query = "SELECT 
                o.order_id,
                o.user_id AS customer_id,
                o.status,
                o.total_price,
                o.payment_method,
                o.created_at,
                c.first_name AS customer_first_name,
                c.last_name AS customer_last_name,
                a.label AS address_label,
                a.address AS full_address
              FROM orders o
              JOIN users c ON o.user_id = c.user_id
              LEFT JOIN addresses a ON o.address_id = a.address_id
              WHERE o.status = 'completed'
              ORDER BY o.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();

        $deliveries = [];
        while ($row = $result->fetch_assoc()) {
            $deliveries[] = [
                'order_id' => $row['order_id'],
                'customer_name' => $row['customer_first_name'] . ' ' . $row['customer_last_name'],
                'status' => $row['status'],
                'total_price' => $row['total_price'],
                'payment_method' => $row['payment_method'],
                'address' => $row['address_label']
                    ? $row['address_label'] . ' - ' . $row['full_address']
                    : $row['full_address'],
                'created_at' => $row['created_at']
            ];
        }

        return $deliveries;
    }

    public function getSalesRevenue()
    {
        $revenue = [];

        // Today's revenue
        $query = "SELECT COALESCE(SUM(total_price), 0) as total 
              FROM orders 
              WHERE DATE(created_at) = CURDATE() 
              AND status != 'cancelled'";
        $revenue['today'] = $this->conn->query($query)->fetch_assoc()['total'];

        // Last 7 days
        $query = "SELECT DATE(created_at) as date, 
                     COALESCE(SUM(total_price), 0) as total
              FROM orders
              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              AND status != 'cancelled'
              GROUP BY DATE(created_at)
              ORDER BY date ASC";
        $result = $this->conn->query($query);
        $revenue['daily'] = $result->fetch_all(MYSQLI_ASSOC);

        return $revenue;
    }

    public function getOrdersToPickup()
    {
        $query = "SELECT COUNT(*) as count FROM orders 
              WHERE status = 'preparing' 
              AND pickup_datetime <= DATE_ADD(NOW(), INTERVAL 2 HOUR)";
        return $this->conn->query($query)->fetch_assoc()['count'];
    }

    public function getOrdersToDeliver()
    {
        $query = "SELECT COUNT(*) as count FROM orders 
              WHERE status = 'ready_for_delivery'";
        return $this->conn->query($query)->fetch_assoc()['count'];
    }

    public function getTotalCustomers()
    {
        $query = "SELECT COUNT(*) as count FROM users WHERE role = 'customer'";
        return $this->conn->query($query)->fetch_assoc()['count'];
    }
}
