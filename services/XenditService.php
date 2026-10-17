<?php

class XenditService
{
    private $secretKey;
    private $callbackToken;
    private $baseUrl;
    private $currency;
    private $invoiceDuration;
    private $successUrl;
    private $failureUrl;

    public function __construct()
    {
        $config = include __DIR__ . '/../config/xendit.php';
        if (!is_array($config) || empty($config['secret_key'])) {
            throw new Exception("Xendit config not loaded properly.");
        }

        $this->secretKey       = $config['secret_key'];
        $this->callbackToken   = $config['callback_token'];
        $this->baseUrl         = rtrim($config['base_url'], '/');
        $this->currency        = $config['currency'] ?? 'PHP';
        $this->invoiceDuration = $config['invoice_duration'] ?? 86400;
        $this->successUrl      = $config['success_url'];
        $this->failureUrl      = $config['failure_url'];
    }

    // Generic request helper (Basic auth: secret key as username, empty password)
    private function request($method, $path, $body = null)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->baseUrl . $path,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $this->secretKey . ":",
            CURLOPT_HTTPHEADER     => [
                "Content-Type: application/json",
                "Accept: application/json"
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Xendit request error: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("Xendit API error ($httpCode) on $method $path: " . $response);
            $message = is_array($data) && isset($data['message']) ? $data['message'] : 'Request failed';
            throw new Exception('Xendit error: ' . $message);
        }

        return $data;
    }

    // 1. Create a hosted invoice (customer picks GCash, Maya, card, bank, etc. on Xendit's page)
    public function createInvoice($orderId, $amount, $description = null, $payerEmail = null)
    {
        // Must be unique per invoice. The order id is embedded so the webhook can find the order.
        $externalId = 'sismoya-' . $orderId . '-' . time();

        $query = http_build_query([
            'provider' => 'xendit',
            'order_id' => $orderId,
            'ref'      => $externalId
        ]);

        $data = [
            'external_id'          => $externalId,
            'amount'               => (float) number_format($amount, 2, '.', ''),
            'currency'             => $this->currency,
            'description'          => $description ?: "Payment for order #$orderId",
            'invoice_duration'     => $this->invoiceDuration,
            'success_redirect_url' => $this->successUrl . '?' . $query,
            'failure_redirect_url' => $this->failureUrl . '?' . $query,
        ];

        if (!empty($payerEmail)) {
            $data['payer_email'] = $payerEmail;
        }

        $invoice = $this->request('POST', '/v2/invoices', $data);

        return [
            'id'          => $invoice['id'] ?? null,
            'external_id' => $externalId,
            'invoice_url' => $invoice['invoice_url'] ?? null,
            'status'      => $invoice['status'] ?? null,
            'expiry_date' => $invoice['expiry_date'] ?? null
        ];
    }

    // 2. Get an invoice by Xendit invoice id
    public function getInvoice($invoiceId)
    {
        return $this->request('GET', '/v2/invoices/' . rawurlencode($invoiceId));
    }

    // 3. Find an invoice by our external_id (used by the status endpoint)
    public function findInvoiceByExternalId($externalId)
    {
        $list = $this->request('GET', '/v2/invoices?external_id=' . rawurlencode($externalId));

        if (is_array($list) && !empty($list[0])) {
            return $list[0];
        }
        return null;
    }

    // 4. Check the x-callback-token header sent by Xendit
    public function isValidCallbackToken($token)
    {
        if (empty($token) || empty($this->callbackToken)) {
            return false;
        }
        return hash_equals($this->callbackToken, $token);
    }

    // 5. Extract our order id from "sismoya-{orderId}-{timestamp}"
    public function parseOrderId($externalId)
    {
        if (is_string($externalId) && preg_match('/^sismoya-(.+)-\d{9,}$/', $externalId, $m)) {
            return $m[1];
        }
        return null;
    }
}