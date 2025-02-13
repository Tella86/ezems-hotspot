<?php
class MpesaPayment {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Generate M-Pesa access token
    private function getAccessToken() {
        $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
        $ch = curl_init('https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $result = json_decode($response);
        curl_close($ch);

        if (isset($result->access_token)) {
            return $result->access_token;
        } else {
            die("Failed to get M-Pesa access token.");
        }
    }

    // Initiate STK Push
    public function initiatePayment($phone, $amount, $account_ref) {
        $access_token = $this->getAccessToken();
        $timestamp = date('YmdHis');
        $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

        $curl_post_data = [
            'BusinessShortCode' => MPESA_SHORTCODE,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerBuyGoodsOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => '7136632',
            'PhoneNumber' => $phone,
            'CallBackURL' => MPESA_CALLBACK_URL,
            'AccountReference' => $account_ref,
            'TransactionDesc' => 'Internet Package Purchase'
        ];

        $ch = curl_init('https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response);
    }
}
?>
