<?php
class MpesaPayment {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get M-Pesa access token
     */
    private function getAccessToken() {
        $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
        $url = (MPESA_ENV === 'api') 
            ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        
        if ($http_code !== 200 || empty($result['access_token'])) {
            error_log("Access Token Error: HTTP Code: $http_code, Response: " . print_r($response, true));
            return null;
        }

        return $result['access_token'];
    }

    /**
     * Initiate STK Push Payment
     */
    public function initiatePayment($phone, $amount, $account_ref) {
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            return ["status" => "error", "message" => "Authentication failed"];
        }

        $timestamp = date('YmdHis');
        $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

        $post_data = [
            'BusinessShortCode' => MPESA_SHORTCODE,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => '7136632',
            'PhoneNumber' => $phone,
            'CallBackURL' => MPESA_CALLBACK_URL,
            'AccountReference' => $account_ref,
            'TransactionDesc' => 'Internet Package Purchase'
        ];

        $json_data = json_encode($post_data, JSON_PRETTY_PRINT);
        if (!$json_data) {
            error_log("JSON Encoding Error: " . json_last_error_msg());
            return ["status" => "error", "message" => "Invalid JSON format"];
        }

        $stk_url = (MPESA_ENV === 'api') 
            ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' ; 

        $ch = curl_init($stk_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("cURL Error: " . $error);
            return ["status" => "error", "message" => "Request failed"];
        }

        if ($http_code !== 200) {
            error_log("HTTP Code: " . $http_code . ", Response: " . print_r($response, true));
            return ["status" => "error", "message" => "Invalid response from M-Pesa"];
        }

        return json_decode($response, true);
    }
}
?>
