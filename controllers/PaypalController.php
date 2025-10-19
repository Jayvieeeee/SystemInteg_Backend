<?php

class PaypalController
{
    private $paypalService;
    private $orderModel;

    public function __construct(OrderModel $orderModel, PaypalService $paypalService)
    {
        $this->paypalService = $paypalService;
        $this->orderModel = $orderModel;
    }

    public function create($request)
    {
        try {
            if (!isset($request['order_id'])) {
                throw new ValidationException("Missing order_id");
            }

            $orderId = $request['order_id'];

            // Check if order exists
            $order = $this->orderModel->getById($orderId);
            if (!$order) {
                throw new NotFoundException("Order not found");
            }

            // Create PayPal order
            $paypalOrder = $this->paypalService->createOrder($orderId, $order['total_price']);

            return [
                'success' => true,
                'message' => 'PayPal order created successfully',
                'order_id' => $orderId,
                'total_amount' => (float) $order['total_price'],
                'paypal' => [
                    'approve_link' => $paypalOrder['approve_link'],
                    'paypal_order_id' => $paypalOrder['id']
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function confirm()
    {
        // Enable error logging
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);

        try {
            // Log request details
            error_log("========== PayPal Confirm START ==========");
            error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
            error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
            error_log("GET params: " . json_encode($_GET));
            error_log("Query string: " . ($_SERVER['QUERY_STRING'] ?? 'empty'));

            // Validate token
            if (!isset($_GET['token'])) {
                error_log("ERROR: Missing token parameter");
                throw new Exception("Missing PayPal token parameter");
            }

            // Validate PayerID
            if (!isset($_GET['PayerID'])) {
                error_log("ERROR: Missing PayerID parameter");
                throw new Exception("Missing PayerID parameter");
            }

            $paypalOrderId = $_GET['token'];
            $payerId = $_GET['PayerID'];

            error_log("Token: $paypalOrderId");
            error_log("PayerID: $payerId");

            // Capture payment
            error_log("Calling PayPal capturePayment...");
            $result = $this->paypalService->capturePayment($paypalOrderId);
            error_log("PayPal API Response: " . json_encode($result));

            // Check status
            if (!isset($result['status'])) {
                error_log("ERROR: No status in PayPal response");
                throw new Exception("Invalid PayPal response - no status field");
            }

            if ($result['status'] !== 'COMPLETED') {
                error_log("ERROR: Payment status is " . $result['status']);
                throw new Exception("Payment not completed. Status: " . $result['status']);
            }

            error_log("Payment status: COMPLETED");

            // Get order reference
            $referenceId = $result['purchase_units'][0]['reference_id'] ?? null;

            if (!$referenceId) {
                error_log("ERROR: No reference_id in PayPal response");
                error_log("Purchase units: " . json_encode($result['purchase_units'] ?? []));
                throw new Exception("Order reference not found in PayPal response");
            }

            error_log("Reference ID (Order ID): $referenceId");

            // Update order status
            error_log("Updating order payment status...");
            $updateResult = $this->orderModel->updatePaymentStatus($referenceId, 'paid', 'paypal');
            error_log("Update result: " . json_encode($updateResult));

            // Get order details
            error_log("Fetching order details...");
            $orderDetails = $this->orderModel->getById($referenceId);

            if (!$orderDetails) {
                error_log("ERROR: Order not found in database: $referenceId");
                throw new Exception("Order not found: $referenceId");
            }

            error_log("Order details: " . json_encode($orderDetails));

            // Success response
            $responseData = [
                'success' => true,
                'message' => 'Payment completed successfully',
                'data' => [
                    'order_id' => $referenceId,
                    'amount' => $orderDetails['total_price'] ?? 0,
                    'token' => $paypalOrderId,
                    'payer_id' => $payerId,
                    'status' => 'completed'
                ]
            ];

            error_log("SUCCESS! Sending response: " . json_encode($responseData));
            error_log("========== PayPal Confirm END (SUCCESS) ==========");

            header('Content-Type: application/json');
            echo json_encode($responseData);
            exit;
        } catch (Exception $e) {
            error_log("========== PayPal Confirm ERROR ==========");
            error_log("Exception: " . $e->getMessage());
            error_log("File: " . $e->getFile());
            error_log("Line: " . $e->getLine());
            error_log("Stack trace: " . $e->getTraceAsString());
            error_log("========== PayPal Confirm END (ERROR) ==========");

            header('Content-Type: application/json');
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => get_class($e),
                'debug' => [
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ]
            ]);
            exit;
        }
    }

    public function cancel()
    {
        try {
            $token = $_GET['token'] ?? null;

            error_log("PayPal payment cancelled. Token: " . ($token ?? 'none'));

            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Payment was cancelled',
                'data' => [
                    'token' => $token,
                    'status' => 'cancelled'
                ]
            ]);
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }
}
