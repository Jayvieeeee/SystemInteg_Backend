<?php

class RiderPageController
{
    private $orderModel;

    // constructor with dependency injection
    public function __construct(OrderModel $orderModel)
    {
        $this->orderModel = $orderModel;
    }

    // 1. Get orders for active rider (To Pick Up & To Deliver)
    public function getOrders()
    {
        $orders = $this->orderModel->getOrdersForActiveRider();
        return $orders;
    }

    public function updateStatus($request, $userId, $orderId)
    {
        // Remove these lines since $request already has the data
        // $rawInput = file_get_contents("php://input");
        // $inputData = json_decode($rawInput, true);

        // Use $request instead of $inputData
        $newStatus = isset($request['newStatus']) ? strtolower(trim($request['newStatus'])) : null;

        if (!in_array($newStatus, ['picked_up', 'delivered'])) {
            throw new Exception("Invalid status");
        }

        $order = $this->orderModel->getOrderById($orderId);
        if (!$order) {
            throw new Exception("Order not found");
        }

        $affectedRows = $this->orderModel->riderUpdateStatus($orderId, $newStatus);

        return [
            "message" => "Status updated",
            "debug" => [
                "order_id" => $orderId,
                "requested_status" => $newStatus,
                "status_before" => $order['status'],
                "affected_rows" => $affectedRows
            ]
        ];
    }

    public function getDeliveryHistory()
    {
        $completedOrders = $this->orderModel->getDeliveredOrders();
        return $completedOrders;
    }
}
