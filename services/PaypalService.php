<?php
class PayPalService
{
    private $clientId;
    private $secret;
    private $baseUrl;

    public function __construct()
    {
        // load config file
        $config = include __DIR__ . '/../config/paypal.php'; // path sa config file
        if (!is_array($config)) {
            throw new Exception("PayPal config not loaded properly.");
        }

        $this->clientId = $config['client_id'];
        $this->secret = $config['secret'];
        $this->baseUrl = $config['base_url'];
    }

    // 1. Generate Access Token
    public function getAccessToken()
    {
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

    public function createOrder($orderId, $amount)
    {
        $accessToken = $this->getAccessToken();

        $data = [
            "intent" => "CAPTURE",
            "purchase_units" => [[
                "reference_id" => $orderId,
                "amount" => [
                    "currency_code" => "PHP",
                    "value" => number_format($amount, 2, '.', '')
                ]
            ]],
            "application_context" => [
                "return_url" => "https://sismoya.bsit3b.site/payment-success",
                "cancel_url" => "https://sismoya.bsit3b.site/payment-cancel"
            ]
        ];


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . "/v2/checkout/orders");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $accessToken"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('PayPal create order error: ' . curl_error($ch));
        }
        curl_close($ch);

        // decode JSON result
        $paypalResponse = json_decode($result, true);

        $approveLink = null;
        $paypalOrderId = $paypalResponse['id'] ?? null;

        if (isset($paypalResponse['links']) && is_array($paypalResponse['links'])) {
            foreach ($paypalResponse['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    $approveLink = $link['href'];
                    break;
                }
            }
        }

        return [
            'id' => $paypalOrderId,
            'approve_link' => $approveLink
        ];
    }

    public function capturePayment($paypalOrderId)
    {
        $url = $this->baseUrl . "/v2/checkout/orders/{$paypalOrderId}/capture";

        $accessToken = $this->getAccessToken(); // reuse token generator

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer $accessToken"
            ],
            CURLOPT_RETURNTRANSFER => true
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('PayPal capture error: ' . curl_error($ch));
        }
        curl_close($ch);

        return json_decode($response, true);
    }
}
