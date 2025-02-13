<?php
require_once 'config.php';
require_once 'MpesaPayment.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->connect();
$mpesa = new MpesaPayment($db);

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['phone']) || !isset($data['package_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters.']);
    exit;
}

$phone = $data['phone'];
$package_id = $data['package_id'];

// Get package price (Replace this with database query if necessary)
$package_prices = ['amount'];

$amount = isset($package_prices[$package_id]) ? $package_prices[$package_id] : null;

if (!$amount) {
    echo json_encode(['success' => false, 'message' => 'Invalid package selected.']);
    exit;
}

// Initiate M-Pesa Payment
$response = $mpesa->initiatePayment($phone, $amount, "Internet-Package-" . $package_id);

if ($response && isset($response->ResponseCode) && $response->ResponseCode == "0") {
    echo json_encode(['success' => true, 'message' => 'Payment request sent. Check your phone.']);
} else {
    echo json_encode(['success' => false, 'message' => 'M-Pesa payment failed.']);
}
?>
