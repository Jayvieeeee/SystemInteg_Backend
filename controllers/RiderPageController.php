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
    public function getOrders($riderId)
    {
        try {
            // Pass the specific rider_id to get only their orders
            $orders = $this->orderModel->getOrdersForRider($riderId);

            // If empty result
            if (empty($orders)) {
                return [
                    "success" => false,
                    "message" => "No orders found for this rider."
                ];
            }

            return [
                "success" => true,
                "data" => $orders
            ];
        } catch (Exception $e) {
            // Log detailed error to debug.log
            $debugFile = __DIR__ . "/../logs/debug.log";
            $time = date("Y-m-d H:i:s");
            $errorMsg = "[$time] getOrders() Error: " . $e->getMessage() . PHP_EOL;
            file_put_contents($debugFile, $errorMsg, FILE_APPEND);

            // Return simplified response to API
            return [
                "success" => false,
                "message" => "Failed to fetch orders. Please try again later."
            ];
        }
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

    public function getDeliveryHistory($riderId)
    {
        $completedOrders = $this->orderModel->getDeliveriesByRiderId($riderId);
        return $completedOrders;
    }
}
