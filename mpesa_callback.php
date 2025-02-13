<?php
require_once 'config.php';

header("Content-Type: application/json");

$callbackData = json_decode(file_get_contents('php://input'), true);

if (!$callbackData) {
    error_log("Invalid M-Pesa callback data");
    exit;
}

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    error_log("Database connection failed: " . $db->connect_error);
    exit;
}

$transaction_id = $callbackData['Body']['stkCallback']['CheckoutRequestID'] ?? null;
$result_code = $callbackData['Body']['stkCallback']['ResultCode'] ?? null;
$result_desc = $callbackData['Body']['stkCallback']['ResultDesc'] ?? null;
$amount = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'] ?? null;
$mpesa_receipt_number = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'] ?? null;

if ($result_code == 0 && $transaction_id && $mpesa_receipt_number) {
    // Update the payments table
    $updatePayment = $db->prepare("UPDATE payments SET transaction_id = ?, status = 'completed' WHERE transaction_id = ?");
    $updatePayment->bind_param("ss", $mpesa_receipt_number, $transaction_id);
    $updatePayment->execute();
    $updatePayment->close();

    // Get user_id and package_id for subscription
    $paymentQuery = $db->prepare("SELECT user_id, package_id FROM payments WHERE transaction_id = ?");
    $paymentQuery->bind_param("s", $mpesa_receipt_number);
    $paymentQuery->execute();
    $paymentQuery->bind_result($user_id, $package_id);
    $paymentQuery->fetch();
    $paymentQuery->close();

    if ($user_id && $package_id) {
        $start_time = date('Y-m-d H:i:s');
        $end_time = date('Y-m-d H:i:s', strtotime('+30 days')); // Assuming 30-day validity

        // Insert subscription
        $stmt = $db->prepare("INSERT INTO subscriptions (user_id, package_id, start_time, end_time, status) VALUES (?, ?, ?, ?, ?)");
        $status = 'active';
        $stmt->bind_param("iisss", $user_id, $package_id, $start_time, $end_time, $status);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);
} else {
    // Update failed payment
    $updateFailed = $db->prepare("UPDATE payments SET status = 'failed' WHERE transaction_id = ?");
    $updateFailed->bind_param("s", $transaction_id);
    $updateFailed->execute();
    $updateFailed->close();

    echo json_encode(['success' => false, 'message' => 'Payment failed', 'reason' => $result_desc]);
}
?>
