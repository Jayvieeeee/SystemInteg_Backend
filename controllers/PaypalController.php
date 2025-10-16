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

            // ✅ Return array, same format as OrderController
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
        try {
            if (!isset($_GET['token'])) {
                throw new ValidationException("Missing token from PayPal");
            }

            $token = $_GET['token'];
            $result = $this->paypalService->capturePayment($token);

            if (isset($result['status']) && $result['status'] === 'COMPLETED') {
                // ✅ Update 'payment_status' column in orders table
                $this->orderModel->updatePaymentStatus($result['order_id'], 'paid', 'paypal');

                return [
                    'success' => true,
                    'message' => 'Payment successful!',
                    'order_id' => $result['order_id'],
                    'payment_status' => 'paid',
                    'paypal_order_id' => $result['paypal_order_id'] ?? null
                ];
            } else {
                throw new AppException("Payment not completed");
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
