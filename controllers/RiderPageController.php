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

    // 2. Update order status (Picked Up / Delivered)
    public function updateStatus($orderId)
    {
        $rawInput = file_get_contents("php://input");
        $inputData = json_decode($rawInput, true);

        $newStatus = isset($inputData['newStatus']) ? strtolower(trim($inputData['newStatus'])) : null;

        if (!in_array($newStatus, ['picked_up', 'delivered'])) {
            throw new Exception("Invalid status");
        }

        $order = $this->orderModel->getOrderById($orderId);
        if (!$order) {
            throw new Exception("Order not found");
        }

        $this->orderModel->riderUpdateStatus($orderId, $newStatus);

        return ["message" => "Status updated"];
    }

    public function getDeliveryHistory()
    {
        $completedOrders = $this->orderModel->getDeliveredOrders();
        return $completedOrders;
    }
}
