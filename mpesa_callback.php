<?php
require_once 'config.php';
require_once 'vendor/autoload.php';
require('routeros_api.class.php');

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
    // Update payment status
    $updatePayment = $db->prepare("UPDATE payments SET transaction_id = ?, status = 'completed' WHERE transaction_id = ?");
    $updatePayment->bind_param("ss", $mpesa_receipt_number, $transaction_id);
    $updatePayment->execute();
    $updatePayment->close();

    // Get user_id and package_id
    $paymentQuery = $db->prepare("SELECT user_id, package_id FROM payments WHERE transaction_id = ?");
    $paymentQuery->bind_param("s", $mpesa_receipt_number);
    $paymentQuery->execute();
    $paymentQuery->bind_result($user_id, $package_id);
    $paymentQuery->fetch();
    $paymentQuery->close();

    if ($user_id && $package_id) {
        $start_time = date('Y-m-d H:i:s');
        $end_time = date('Y-m-d H:i:s', strtotime('+30 days'));

        // Check for existing active subscription
        $checkSub = $db->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active'");
        $checkSub->bind_param("i", $user_id);
        $checkSub->execute();
        $checkSub->store_result();

        if ($checkSub->num_rows > 0) {
            // Update subscription
            $updateSub = $db->prepare("UPDATE subscriptions SET end_time = ?, status = 'active' WHERE user_id = ?");
            $updateSub->bind_param("si", $end_time, $user_id);
            $updateSub->execute();
            $updateSub->close();
        } else {
            // Create new subscription
            $stmt = $db->prepare("INSERT INTO subscriptions (user_id, package_id, start_time, end_time, status) VALUES (?, ?, ?, ?, ?)");
            $status = 'active';
            $stmt->bind_param("iisss", $user_id, $package_id, $start_time, $end_time, $status);
            $stmt->execute();
            $stmt->close();
        }
        $checkSub->close();

        // Enable user in FreeRADIUS
        $enableUser = $db->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Auth-Type', ':=', 'Accept') ON DUPLICATE KEY UPDATE value = 'Accept'");
        $enableUser->bind_param("s", $user_id);
        $enableUser->execute();
        $enableUser->close();

        // Activate user in FreeRADIUS
        $activateUserQuery = $db->prepare("UPDATE radcheck SET value = 'Accept' WHERE username = ?");
        $activateUserQuery->bind_param("s", $user_id);
        $activateUserQuery->execute();
        $activateUserQuery->close();

        // 🚀 **Add user to MikroTik Hotspot**
        $API = new RouterosAPI();
        $API->debug = false;
        if ($API->connect('192.168.24.1', 'admin', 'Elon2508/*-')) {
            $password = substr(md5(uniqid()), 0, 8); // Generate random password
            $profile = '5M-Limit'; // Change based on package

            $API->write('/ip/hotspot/user/add');
            $API->write("=name={$user_id}");
            $API->write("=password={$password}");
            $API->write("=profile={$profile}");
            $API->write("=server=hotspot1");

            $API->read();
            $API->disconnect();

            // Store the password in the database (hashed for security)
            $updateHotspot = $db->prepare("UPDATE users SET hotspot_password = ? WHERE id = ?");
            $updateHotspot->bind_param("si", password_hash($password, PASSWORD_DEFAULT), $user_id);
            $updateHotspot->execute();
            $updateHotspot->close();
        }

        echo json_encode(['success' => true, 'message' => 'Payment processed. User can now access the internet.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: User or Package not found']);
    }
} else {
    // Payment failed
    $updateFailed = $db->prepare("UPDATE payments SET status = 'failed' WHERE transaction_id = ?");
    $updateFailed->bind_param("s", $transaction_id);
    $updateFailed->execute();
    $updateFailed->close();

    echo json_encode(['success' => false, 'message' => 'Payment failed', 'reason' => $result_desc]);
}
?>
