<?php

class OrderModel
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // Add this method sa Order model
    private function generateOrderId()
    {
        // Generate 4 random letters + 4 random digits (e.g., ABCD1234)
        $letters = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 4));
        $digits = rand(1000, 9999);
        return $letters . $digits;
    }

    public function create($data)
    {
        $this->conn->begin_transaction();
        try {
            // Generate unique order_id
            do {
                $orderId = $this->generateOrderId();
                $checkStmt = $this->conn->prepare("SELECT order_id FROM orders WHERE order_id = ?");
                $checkStmt->bind_param("s", $orderId);
                $checkStmt->execute();
                $exists = $checkStmt->get_result()->num_rows > 0;
                $checkStmt->close();
            } while ($exists);

            // ✅ Get active rider (kung meron)
            $riderId = $this->getActiveRiderId();

            // ✅ Insert order + include rider_id
            $stmt = $this->conn->prepare("
            INSERT INTO orders (order_id, user_id, address_id, pickup_datetime, payment_method, total_price, rider_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
            $stmt->bind_param(
                "siissdi",
                $orderId,
                $data['user_id'],
                $data['address_id'],
                $data['pickup_datetime'],
                $data['payment_method'],
                $data['total_price'],
                $riderId
            );
            $stmt->execute();
            $stmt->close();

            // Insert order items
            $stmtItem = $this->conn->prepare("
            INSERT INTO order_items (order_id, gallon_id, quantity, price, total_price)
            VALUES (?, ?, ?, ?, ?)
        ");
            foreach ($data['items'] as $item) {
                $stmtItem->bind_param(
                    "siidd",
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

    private function getActiveRiderId()
    {
        $stmt = $this->conn->prepare("
        SELECT user_id FROM users 
        WHERE role = 'rider' AND status = 'active' 
        LIMIT 1
    ");
        $stmt->execute();
        $result = $stmt->get_result();
        $rider = $result->fetch_assoc();
        return $rider ? $rider['user_id'] : null;
    }

    // Create order from cart - FIXED: order_id is now varchar
    public function createFromCart($userId, $cartItems, $data)
    {
        $this->conn->begin_transaction();
        try {
            // Generate unique order_id
            do {
                $orderId = $this->generateOrderId();
                $checkStmt = $this->conn->prepare("SELECT order_id FROM orders WHERE order_id = ?");
                $checkStmt->bind_param("s", $orderId);
                $checkStmt->execute();
                $exists = $checkStmt->get_result()->num_rows > 0;
                $checkStmt->close();
            } while ($exists);

            // Insert into orders with generated order_id
            $stmt = $this->conn->prepare("
                INSERT INTO orders (order_id, user_id, address_id, pickup_datetime, payment_method, total_price)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "siissd",
                $orderId,
                $userId,
                $data['address_id'],
                $data['pickup_datetime'],
                $data['payment_method'],
                $data['total_price']
            );
            $stmt->execute();
            $stmt->close();

            // Insert order items from cart
            $stmtItem = $this->conn->prepare("
                INSERT INTO order_items (order_id, gallon_id, quantity, price, total_price)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($cartItems as $item) {
                $stmtItem->bind_param(
                    "siidd",
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

    // Get selected cart items
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

    // Get order by ID - FIXED: order_id is varchar
    public function getById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT o.*, a.label as address_label, a.address as full_address
            FROM orders o
            LEFT JOIN addresses a ON o.address_id = a.address_id
            WHERE o.order_id = ?
        ");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result;
    }

    // Get items for an order - FIXED: order_id is varchar
    public function getOrderItems($orderId)
    {
        $stmt = $this->conn->prepare("
            SELECT oi.*, g.name as gallon_name, g.image_url
            FROM order_items oi
            JOIN gallons g ON oi.gallon_id = g.gallon_id
            WHERE oi.order_id = ?
        ");
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $result ?: [];
    }

    // Get all orders by user
    public function getOrdersByUserId($userId)
    {
        $stmt = $this->conn->prepare("
        SELECT 
            o.order_id,
            o.user_id,
            o.address_id,
            o.pickup_datetime,
            o.payment_method,
            o.payment_status,
            o.total_price,
            o.status,
            o.created_at,
            o.updated_at,
            o.delivered_at,
            o.rider_id,
            a.label as address_label, 
            a.address as full_address,
            GROUP_CONCAT(CONCAT(g.name, ' x', oi.quantity) SEPARATOR ', ') AS products
        FROM orders o
        LEFT JOIN addresses a ON o.address_id = a.address_id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN gallons g ON oi.gallon_id = g.gallon_id
        WHERE o.user_id = ?
        GROUP BY 
            o.order_id,
            o.user_id,
            o.address_id,
            o.pickup_datetime,
            o.payment_method,
            o.payment_status,
            o.total_price,
            o.status,
            o.created_at,
            o.updated_at,
            o.delivered_at,
            o.rider_id,
            a.label,
            a.address
        ORDER BY o.created_at DESC
    ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $result ?: [];
    }

    // Check order ownership - FIXED: order_id is varchar
    public function isOrderOwnedByUser($orderId, $userId)
    {
        $stmt = $this->conn->prepare("
            SELECT order_id FROM orders 
            WHERE order_id = ? AND user_id = ?
        ");
        $stmt->bind_param("si", $orderId, $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return !empty($result);
    }

    public function getOrderStats()
    {
        $stats = [];

        $query = "SELECT COUNT(*) as count FROM orders WHERE status = 'pending'";
        $stats['pending'] = $this->conn->query($query)->fetch_assoc()['count'];

        $query = "SELECT COUNT(*) as count FROM orders WHERE status = 'preparing'";
        $stats['preparing'] = $this->conn->query($query)->fetch_assoc()['count'];

        $query = "SELECT COUNT(*) as count FROM orders WHERE status = 'to_pickup'";
        $stats['to_pickup'] = $this->conn->query($query)->fetch_assoc()['count'];

        $query = "SELECT COUNT(*) as count FROM orders WHERE status = 'to_deliver'";
        $stats['to_deliver'] = $this->conn->query($query)->fetch_assoc()['count'];

        $query = "SELECT COUNT(*) as count FROM orders WHERE status = 'delivered'";
        $stats['delivered'] = $this->conn->query($query)->fetch_assoc()['count'];

        $query = "SELECT COUNT(*) as count FROM orders WHERE status = 'cancelled'";
        $stats['cancelled'] = $this->conn->query($query)->fetch_assoc()['count'];

        $query = "SELECT COUNT(*) as count FROM orders WHERE status = 'delivered' AND DATE(delivered_at) = CURDATE()";
        $stats['completed_today'] = $this->conn->query($query)->fetch_assoc()['count'];

        $query = "SELECT COUNT(*) as count FROM orders";
        $stats['total'] = $this->conn->query($query)->fetch_assoc()['count'];

        return $stats;
    }

    public function getOrderStatsByUser($userId)
    {
        $sql = "SELECT 
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                COUNT(*) AS total
            FROM orders
            WHERE user_id = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return [
            'pending' => (int) ($result['pending'] ?? 0),
            'delivered' => (int) ($result['delivered'] ?? 0),
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
                CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
                GROUP_CONCAT(CONCAT(g.name, ' x', oi.quantity) SEPARATOR ', ') AS order_items
              FROM orders o
              JOIN order_items oi ON o.order_id = oi.order_id
              JOIN gallons g ON oi.gallon_id = g.gallon_id
              JOIN users u ON o.user_id = u.user_id
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

    // Update payment status - FIXED: order_id is varchar
    public function updatePaymentStatus($orderId, $status, $paymentMethod)
    {
        $query = "UPDATE orders SET payment_status = ?, payment_method = ? WHERE order_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sss", $status, $paymentMethod, $orderId);

        return $stmt->execute();
    }

    public function getAllOrders()
    {
        $query = "
        SELECT 
            o.order_id,
            o.user_id,
            CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
            u.contact_no,
            a.address,
            GROUP_CONCAT(CONCAT(g.name, ' x', oi.quantity) SEPARATOR ', ') AS products,
            o.total_price,
            o.pickup_datetime,
            o.payment_method,
            o.payment_status,
            DATE_FORMAT(o.created_at, '%m-%d-%Y %h:%i%p') AS date_time,
            o.status,
            o.delivered_at
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN gallons g ON oi.gallon_id = g.gallon_id
        JOIN addresses a ON o.address_id = a.address_id
        GROUP BY 
            o.order_id, 
            o.user_id, 
            u.first_name, 
            u.last_name, 
            u.contact_no, 
            a.address, 
            o.total_price, 
            o.pickup_datetime,
            o.payment_method,
            o.payment_status,
            o.created_at, 
            o.status
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
               GROUP_CONCAT(CONCAT(g.name, ' x', oi.quantity) SEPARATOR ', ') AS products,
               a.label AS address_label, a.address AS full_address
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN gallons g ON oi.gallon_id = g.gallon_id
        LEFT JOIN addresses a ON o.address_id = a.address_id
        WHERE o.order_id = ?
        GROUP BY o.order_id
    ");
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            return null;
        }

        // Add order items
        $stmtItems = $this->conn->prepare("
        SELECT oi.*, g.name AS gallon_name, g.image_url
        FROM order_items oi
        JOIN gallons g ON oi.gallon_id = g.gallon_id
        WHERE oi.order_id = ?
    ");
        $stmtItems->bind_param("s", $orderId);
        $stmtItems->execute();
        $order['items'] = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtItems->close();

        $order['customer_name'] = $order['first_name'] . ' ' . $order['last_name'];
        unset($order['first_name'], $order['last_name']);

        return $order;
    }
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

    // Update order status - FIXED: order_id is varchar
    public function updateStatus($orderId, $newStatus)
    {
        $stmt = $this->conn->prepare("
            UPDATE orders 
            SET status = ? 
            WHERE order_id = ?
        ");
        $stmt->bind_param("ss", $newStatus, $orderId);
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
            GROUP_CONCAT(CONCAT(g.name, ' x', oi.quantity) SEPARATOR ', ') AS products
          FROM orders o
          LEFT JOIN order_items oi ON o.order_id = oi.order_id
          LEFT JOIN gallons g ON oi.gallon_id = g.gallon_id
          WHERE o.user_id = ?
          GROUP BY o.order_id, o.status, o.total_price, o.payment_method, o.pickup_datetime
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

    // public function getOrdersForActiveRider()
    // {
    //     try {
    //         // Step 1: Get active rider
    //         $riderQuery = "SELECT user_id FROM users WHERE role = 'rider' AND status = 'active' LIMIT 1";
    //         $riderResult = $this->conn->query($riderQuery);
    //         $riderRow = $riderResult->fetch_assoc();

    //         if (!$riderRow) {
    //             return []; // no active rider
    //         }

    //         $activeRiderId = $riderRow['user_id'];

    //         // Step 2: Define statuses
    //         $statuses = ['to_pickup', 'to_deliver'];
    //         $statusPlaceholders = implode(',', array_fill(0, count($statuses), '?'));
    //         $types = str_repeat('s', count($statuses)) . 'i'; // 's' for status, 'i' for rider_id

    //         // Step 3: Query orders assigned to active rider
    //         $sql = "SELECT 
    //                 o.order_id,
    //                 o.user_id,
    //                 o.address_id,
    //                 o.pickup_datetime,
    //                 o.payment_method,
    //                 o.payment_status,
    //                 o.total_price,
    //                 o.created_at,
    //                 o.updated_at,
    //                 o.status AS status,
    //                 CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
    //                 u.contact_no AS contact_number,
    //                 a.address,
    //                 g.gallon_id,
    //                 g.name AS gallon_name,
    //                 g.image_url,
    //                 oi.quantity
    //             FROM orders o
    //             LEFT JOIN users u ON o.user_id = u.user_id
    //             LEFT JOIN addresses a ON o.address_id = a.address_id
    //             LEFT JOIN order_items oi ON o.order_id = oi.order_id
    //             LEFT JOIN gallons g ON oi.gallon_id = g.gallon_id
    //             WHERE o.status IN ($statusPlaceholders)
    //             AND o.rider_id = ?
    //             ORDER BY o.created_at DESC";

    //         $stmt = $this->conn->prepare($sql);

    //         // Merge statuses + rider_id into one array
    //         $params = array_merge($statuses, [$activeRiderId]);

    //         // Use argument unpacking properly
    //         $stmt->bind_param($types, ...$params);
    //         $stmt->execute();
    //         $result = $stmt->get_result();

    //         $rawData = $result->fetch_all(MYSQLI_ASSOC);

    //         // Step 4: Group per order_id
    //         $orders = [];
    //         foreach ($rawData as $row) {
    //             $orderId = $row['order_id'];

    //             if (!isset($orders[$orderId])) {
    //                 $orders[$orderId] = [
    //                     'order_id' => $row['order_id'],
    //                     'pickup_datetime' => $row['pickup_datetime'],
    //                     'payment_method' => $row['payment_method'],
    //                     'payment_status' => $row['payment_status'],
    //                     'total_price' => $row['total_price'],
    //                     'status' => $row['status'],
    //                     'customer_name' => $row['customer_name'],
    //                     'contact_number' => $row['contact_number'],
    //                     'address' => $row['address'],
    //                     'items' => []
    //                 ];
    //             }

    //             if (!empty($row['gallon_id'])) {
    //                 $orders[$orderId]['items'][] = [
    //                     'gallon_id' => $row['gallon_id'],
    //                     'gallon_name' => $row['gallon_name'],
    //                     'image_url' => $row['image_url'],
    //                     'quantity' => $row['quantity']
    //                 ];
    //             }
    //         }

    //         return array_values($orders);
    //     } catch (Exception $e) {
    //         $debugFile = __DIR__ . "/../logs/debug.log";
    //         file_put_contents($debugFile, "[" . date("Y-m-d H:i:s") . "] getOrdersForActiveRider() Error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    //         throw $e;
    //     }
    // }


    public function getOrdersForRider($riderId)
    {
        try {
            // Step 1: Verify this rider exists and is active
            $riderQuery = "SELECT user_id, status FROM users WHERE user_id = ? AND role = 'rider' LIMIT 1";
            $stmt = $this->conn->prepare($riderQuery);
            $stmt->bind_param('i', $riderId);
            $stmt->execute();
            $result = $stmt->get_result();
            $riderRow = $result->fetch_assoc();

            if (!$riderRow) {
                return []; // Rider doesn't exist or not a rider
            }

            // Optional: Check if rider is active (uncomment if needed)
            // if ($riderRow['status'] !== 'active') {
            //     return []; // Rider is not active
            // }

            // Step 2: Define statuses
            $statuses = ['to_pickup', 'to_deliver'];
            $statusPlaceholders = implode(',', array_fill(0, count($statuses), '?'));

            // Step 3: Query orders assigned to THIS SPECIFIC rider
            $sql = "SELECT 
                    o.order_id,
                    o.user_id,
                    o.address_id,
                    o.rider_id,
                    o.pickup_datetime,
                    o.payment_method,
                    o.payment_status,
                    o.total_price,
                    o.created_at,
                    o.updated_at,
                    o.status AS status,
                    CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
                    u.contact_no AS contact_number,
                    a.address,
                    g.gallon_id,
                    g.name AS gallon_name,
                    g.image_url,
                    oi.quantity
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.user_id
                LEFT JOIN addresses a ON o.address_id = a.address_id
                LEFT JOIN order_items oi ON o.order_id = oi.order_id
                LEFT JOIN gallons g ON oi.gallon_id = g.gallon_id
                WHERE o.status IN ($statusPlaceholders)
                AND o.rider_id = ?
                ORDER BY o.created_at DESC";

            $stmt = $this->conn->prepare($sql);

            // Bind status parameters + rider_id
            $types = str_repeat('s', count($statuses)) . 'i';
            $params = array_merge($statuses, [$riderId]);
            $stmt->bind_param($types, ...$params);

            $stmt->execute();
            $result = $stmt->get_result();
            $rawData = $result->fetch_all(MYSQLI_ASSOC);

            // Step 4: Group per order_id
            $orders = [];
            foreach ($rawData as $row) {
                $orderId = $row['order_id'];

                if (!isset($orders[$orderId])) {
                    $orders[$orderId] = [
                        'order_id' => $row['order_id'],
                        'rider_id' => $row['rider_id'],
                        'pickup_datetime' => $row['pickup_datetime'],
                        'payment_method' => $row['payment_method'],
                        'payment_status' => $row['payment_status'],
                        'total_price' => $row['total_price'],
                        'status' => $row['status'],
                        'customer_name' => $row['customer_name'],
                        'contact_number' => $row['contact_number'],
                        'address' => $row['address'],
                        'items' => []
                    ];
                }

                if (!empty($row['gallon_id'])) {
                    $orders[$orderId]['items'][] = [
                        'gallon_id' => $row['gallon_id'],
                        'gallon_name' => $row['gallon_name'],
                        'image_url' => $row['image_url'],
                        'quantity' => $row['quantity']
                    ];
                }
            }

            return array_values($orders);
        } catch (Exception $e) {
            $debugFile = __DIR__ . "/../logs/debug.log";
            file_put_contents($debugFile, "[" . date("Y-m-d H:i:s") . "] getOrdersForRider() Error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            throw $e;
        }
    }

    // Update order status (rider) - FIXED: order_id is varchar
    public function riderUpdateStatus($orderId, $newStatus)
    {
        if (!in_array($newStatus, ['picked_up', 'delivered'])) {
            throw new Exception("Invalid status");
        }

        if ($newStatus === 'delivered') {
            $stmt = $this->conn->prepare("
            UPDATE orders 
            SET status = ?, delivered_at = NOW() 
            WHERE order_id = ?
        ");
        } else {
            $stmt = $this->conn->prepare("
            UPDATE orders 
            SET status = ? 
            WHERE order_id = ?
        ");
        }

        $stmt->bind_param("ss", $newStatus, $orderId);
        $stmt->execute();
        return $stmt->affected_rows;
    }

    public function getDeliveriesByRiderId($riderId)
    {
        $query = "
        SELECT 
            o.order_id,
            o.user_id AS customer_id,
            o.status,
            o.total_price,
            o.payment_method,
            o.payment_status,
            DATE_FORMAT(o.created_at, '%m-%d-%Y %h:%i %p') AS created_at,
            c.first_name AS customer_first_name,
            c.last_name AS customer_last_name,
            a.label AS address_label,
            a.address AS full_address
        FROM orders o
        JOIN users c ON o.user_id = c.user_id
        LEFT JOIN addresses a ON o.address_id = a.address_id
        WHERE o.status = 'delivered'
        AND o.rider_id = ?
        ORDER BY o.created_at DESC
    ";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $riderId);
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
                'payment_status' => $row['payment_status'],
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
              WHERE status = 'to_pickup'";
        return $this->conn->query($query)->fetch_assoc()['count'];
    }

    public function getOrdersToDeliver()
    {
        $query = "SELECT COUNT(*) as count FROM orders 
              WHERE status = 'to_deliver'";
        return $this->conn->query($query)->fetch_assoc()['count'];
    }

    public function getTotalCustomers()
    {
        $query = "SELECT COUNT(*) as count FROM users WHERE role = 'customer'";
        return $this->conn->query($query)->fetch_assoc()['count'];
    }

    public function getDeliveredOrders()
    {
        $sql = "SELECT 
            o.*,
            CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
            u.first_name,
            u.last_name,
            u.contact_no,
            a.address,
            DATE_FORMAT(o.updated_at, '%m/%d/%y %h:%i%p') AS delivered_at
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.user_id
        LEFT JOIN addresses a ON o.address_id = a.address_id
        WHERE o.status = 'delivered'
        ORDER BY o.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Get order items for each order
        foreach ($orders as &$order) {
            $stmtItems = $this->conn->prepare("
            SELECT oi.*, g.name AS gallon_name, g.image_url
            FROM order_items oi
            JOIN gallons g ON oi.gallon_id = g.gallon_id
            WHERE oi.order_id = ?
        ");
            $stmtItems->bind_param("s", $order['order_id']);
            $stmtItems->execute();
            $order['items'] = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtItems->close();
        }

        return $orders;
    }

    // Cancel order (customer) - FIXED: order_id is varchar
    public function cancelOrderCustomer($orderId, $userId)
    {
        // Check if order exists and belongs to the user
        $stmt = $this->conn->prepare("
        SELECT status FROM orders
        WHERE order_id = ? AND user_id = ?
    ");
        $stmt->bind_param("si", $orderId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Order not found or not owned by user'];
        }

        $order = $result->fetch_assoc();

        // Only pending orders can be cancelled
        if ($order['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Only pending orders can be cancelled'];
        }

        // Update status to cancelled
        $update = $this->conn->prepare("
        UPDATE orders 
        SET status = 'cancelled' 
        WHERE order_id = ? AND user_id = ?
    ");
        $update->bind_param("si", $orderId, $userId);
        $update->execute();

        if ($update->affected_rows > 0) {
            return ['success' => true, 'message' => 'Order cancelled successfully'];
        }

        return ['success' => false, 'message' => 'Failed to cancel order'];
    }
    public function isTimeslotAvailable($pickupDatetime)
    {
        $stmt = $this->conn->prepare("
        SELECT COUNT(DISTINCT address_id) as unique_addresses
        FROM orders
        WHERE pickup_datetime = ?
          AND status != 'cancelled'
    ");

        $stmt->bind_param("s", $pickupDatetime);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        $uniqueAddresses = $row['unique_addresses'];

        // Maximum 5 different addresses per timeslot
        return $uniqueAddresses < 3;
    }
}
