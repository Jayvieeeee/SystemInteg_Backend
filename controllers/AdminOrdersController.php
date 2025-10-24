<?php

class AdminOrdersController
{
    private $orderModel;

    public function __construct(OrderModel $orderModel)
    {
        $this->orderModel = $orderModel;
    }

    /**
     * GET /admin/orders
     * Show all orders for admin order management page.
     */
    public function index()
    {
        $orders = $this->orderModel->getAllOrders();

        return [
            'success' => true,
            'data' => $orders
        ];
    }

    /**
     * GET /admin/orders/status/{status}
     * Filter orders by status (Pending, Preparing, To Pick-up, etc.)
     */
    public function filterByStatus($status)
    {
        $orders = $this->orderModel->getOrdersByStatus($status);

        return [
            'success' => true,
            'data' => $orders
        ];
    }

    /**
     * GET /admin/orders/{id}
     * Get specific order details (optional)
     */
    public function show($id)
    {
        $order = $this->orderModel->getOrderById($id);

        if (!$order) {
            throw new NotFoundException("Order not found");
        }

        return [
            'success' => true,
            'data' => $order
        ];
    }

    /**
     * PUT /admin/orders/{id}/status
     * Update order status (for buttons like “Mark as Preparing” or “Mark as To Deliver”)
     */
    // Update order status (Smart logic for Admin buttons) - corrected
    public function updateStatus($request = [], $userId = null, $orderId = null)
    {
        $action = $request['action'] ?? null;

        if (!$orderId) {
            throw new ValidationException("Order ID is required", 422);
        }

        if (!$action) {
            throw new ValidationException("Action is required", 422);
        }

        // Fetch current order status
        $order = $this->orderModel->getOrderById($orderId);
        if (!$order) {
            throw new NotFoundException("Order not found");
        }

        $currentStatus = strtolower($order['status']);
        $newStatus = null;

        switch ($action) {
            case 'approve':
                if ($currentStatus === 'pending') {
                    $newStatus = 'to_pickup';
                } else {
                    throw new ConflictException("Cannot approve order from status: $currentStatus");
                }
                break;

            case 'cancel':
                if (in_array($currentStatus, ['completed', 'cancelled'])) {
                    throw new ConflictException("Cannot cancel order from status: $currentStatus");
                }
                $newStatus = 'cancelled';
                break;

            case 'mark_preparing':
                if ($currentStatus === 'picked_up') {
                    $newStatus = 'preparing';
                } else {
                    throw new ConflictException("Cannot mark as preparing from status: $currentStatus");
                }
                break;

            case 'mark_to_deliver':
                if ($currentStatus === 'preparing') {
                    $newStatus = 'to_deliver';
                } else {
                    throw new ConflictException("Cannot mark as to deliver from status: $currentStatus");
                }
                break;

            default:
                throw new ValidationException("Invalid action provided", 422);
        }

        $ok = $this->orderModel->updateStatus($orderId, $newStatus);

        if (!$ok) {
            throw new AppException("Failed to update order status", 500);
        }

        return [
            'success' => true,
            'message' => "Order #$orderId updated to '$newStatus'"
        ];
    }
}
