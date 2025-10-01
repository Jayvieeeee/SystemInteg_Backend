<?php
class PayPalService {
    private $clientId;
    private $secret;
    private $baseUrl;

    public function __construct() {
        $this->clientId = PAYPAL_CLIENT_ID;
        $this->secret = PAYPAL_SECRET;
        $this->baseUrl = PAYPAL_BASE_URL;
    }

    // 1. Generate Access Token
    public function getAccessToken() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . "/v1/oauth2/token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERPWD, $this->clientId . ":" . $this->secret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
        curl_setopt($ch, CURLOPT_POST, 1);

        $headers = [];
        $headers[] = "Accept: application/json";
        $headers[] = "Accept-Language: en_US";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('Error: ' . curl_error($ch));
        }
        curl_close($ch);

        $json = json_decode($result, true);
        return $json['access_token'] ?? null;
    }

    // 2. Create Order
    public function createOrder($amount) {
        $token = $this->getAccessToken();

        $data = [
            "intent" => "CAPTURE",
            "purchase_units" => [[
                "amount" => [
                    "currency_code" => "USD",
                    "value" => $amount
                ]
            ]]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . "/v2/checkout/orders");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $token
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('Error: ' . curl_error($ch));
        }
        curl_close($ch);

        return json_decode($result, true);
    }

    // 3. Capture Order
    public function captureOrder($orderId) {
        $token = $this->getAccessToken();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . "/v2/checkout/orders/" . $orderId . "/capture");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $token
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('Error: ' . curl_error($ch));
        }
        curl_close($ch);

        return json_decode($result, true);
    }
}
