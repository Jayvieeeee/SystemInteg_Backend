<?php

class XenditController
{
    private $xenditService;
    private $orderModel;

    public function __construct(OrderModel $orderModel, XenditService $xenditService)
    {
        $this->orderModel = $orderModel;
        $this->xenditService = $xenditService;
    }

    // POST /payments/xendit/create  (auth required)
    // index.php passes ($request, $userId)
    public function create($request, $userId = null)
    {
        try {
            if (!isset($request['order_id'])) {
                throw new ValidationException("Missing order_id");
            }

            $orderId = $request['order_id'];

            $order = $this->orderModel->getById($orderId);
            if (!$order) {
                throw new NotFoundException("Order not found");
            }

            // Only the owner can pay for the order (skipped if your orders table has no user_id)
            if ($userId && isset($order['user_id']) && (string) $order['user_id'] !== (string) $userId) {
                throw new NotFoundException("Order not found");
            }

            if (($order['payment_status'] ?? null) === 'paid') {
                throw new ConflictException("Order is already paid");
            }

            $invoice = $this->xenditService->createInvoice(
                $orderId,
                $order['total_price'],
                "Sismoya order #$orderId",
                $order['email'] ?? null
            );

            if (empty($invoice['invoice_url'])) {
                throw new Exception("Failed to create Xendit invoice");
            }

            return [
                'success' => true,
                'message' => 'Xendit invoice created successfully',
                'order_id' => $orderId,
                'total_amount' => (float) $order['total_price'],
                'xendit' => [
                    'invoice_url' => $invoice['invoice_url'],
                    'invoice_id' => $invoice['id'],
                    'external_id' => $invoice['external_id'],
                    'expiry_date' => $invoice['expiry_date']
                ]
            ];
        } catch (Exception $e) {
            error_log("Xendit create error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    // POST /payments/xendit/webhook  (NO auth, called by Xendit)
    // index.php passes ($request)
    public function webhook($request)
    {
        // 1. Verify the callback token
        $token = $_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '';
        if (!$this->xenditService->isValidCallbackToken($token)) {
            error_log("Xendit webhook: invalid callback token");
            throw new AuthException("Invalid callback token");
        }

        error_log("Xendit webhook received: " . json_encode($request));

        $invoiceId = $request['id'] ?? null;
        $externalId = $request['external_id'] ?? null;

        // Not an invoice event we understand: acknowledge so Xendit stops retrying
        if (!$invoiceId || !$externalId || !$this->xenditService->parseOrderId($externalId)) {
            return ['success' => true, 'message' => 'Ignored'];
        }

        try {
            // 2. Never trust the payload alone: ask Xendit for the real invoice state
            $invoice = $this->xenditService->getInvoice($invoiceId);
            $this->applyInvoice($invoice);
        } catch (Exception $e) {
            // A non-2xx makes Xendit retry later
            error_log("Xendit webhook processing error: " . $e->getMessage());
            http_response_code(500);
            return ['success' => false, 'message' => 'Processing failed'];
        }

        return ['success' => true];
    }

    // GET /payments/xendit/status/{orderId}?ref=sismoya-...  (auth required)
    // index.php passes ($userId, $orderId)
    public function status($userId, $orderId)
    {
        try {
            $ref = $_GET['ref'] ?? null;
            if (!$ref) {
                throw new ValidationException("Missing ref");
            }

            // The ref must belong to the order in the URL
            if ((string) $this->xenditService->parseOrderId($ref) !== (string) $orderId) {
                throw new NotFoundException("Payment not found");
            }

            $order = $this->orderModel->getById($orderId);
            if (!$order) {
                throw new NotFoundException("Order not found");
            }

            if ($userId && isset($order['user_id']) && (string) $order['user_id'] !== (string) $userId) {
                throw new NotFoundException("Order not found");
            }

            $invoice = $this->xenditService->findInvoiceByExternalId($ref);
            if (!$invoice) {
                throw new NotFoundException("Invoice not found");
            }

            $status = $this->applyInvoice($invoice);

            return [
                'success' => true,
                'data' => [
                    'order_id' => $orderId,
                    'status' => $status,   // paid | pending | expired
                    'amount' => (float) $order['total_price']
                ]
            ];
        } catch (Exception $e) {
            error_log("Xendit status error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    // Shared logic. Safe to run many times for the same invoice (idempotent).
    private function applyInvoice($invoice)
    {
        $xenditStatus = strtoupper($invoice['status'] ?? '');
        $orderId = $this->xenditService->parseOrderId($invoice['external_id'] ?? '');

        if (!$orderId) {
            throw new Exception("Invoice has no valid order reference");
        }

        if ($xenditStatus === 'EXPIRED') {
            return 'expired';
        }

        if (!in_array($xenditStatus, ['PAID', 'SETTLED'], true)) {
            return 'pending';
        }

        $order = $this->orderModel->getById($orderId);
        if (!$order) {
            throw new Exception("Order not found: $orderId");
        }

        // Already paid: do nothing (duplicate webhook or status check)
        if (($order['payment_status'] ?? null) === 'paid') {
            return 'paid';
        }

        // The amount paid must match the order total
        $paidAmount = (float) ($invoice['paid_amount'] ?? $invoice['amount'] ?? 0);
        if (abs($paidAmount - (float) $order['total_price']) > 0.01) {
            error_log("Xendit amount mismatch for order $orderId. Paid: $paidAmount, Expected: " . $order['total_price']);
            throw new Exception("Amount mismatch");
        }

        $this->orderModel->updatePaymentStatus($orderId, 'paid', 'xendit');
        error_log("Xendit: order $orderId marked as paid (invoice " . ($invoice['id'] ?? '') . ")");

        return 'paid';
    }
}