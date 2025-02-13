<?php
require_once 'config.php';
require_once 'MpesaPayment.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['phone']) || !isset($data['package_id']) || !isset($data['package_price']) || !isset($data['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
        exit;
    }

    $phone = $data['phone'];
    $package_id = $data['package_id'];
    $package_price = $data['package_price'];
    $user_id = $data['user_id'];

    try {
        // Initialize database connection
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_error) {
            throw new Exception('Database connection failed: ' . $db->connect_error);
        }

        // Create MpesaPayment instance
        $mpesa = new MpesaPayment($db);

        // Initiate STK Push
        $response = $mpesa->initiatePayment($phone, $package_price, "Package ID $package_id");

        if (isset($response->ResponseCode) && $response->ResponseCode == "0") {
            $transaction_id = $response->CheckoutRequestID; // Store STK transaction ID

            // Insert into payments table
            $stmt = $db->prepare("INSERT INTO payments (user_id, package_id, amount, transaction_id, payment_method, status) VALUES (?, ?, ?, ?, ?, ?)");
            $status = 'pending';
            $payment_method = 'mpesa';
            $stmt->bind_param("iidsss", $user_id, $package_id, $package_price, $transaction_id, $payment_method, $status);
            $stmt->execute();
            $stmt->close();

            echo json_encode(['success' => true, 'message' => 'Payment request sent', 'response' => $response]);
        } else {
            echo json_encode(['success' => false, 'message' => 'M-Pesa request failed', 'response' => $response]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
