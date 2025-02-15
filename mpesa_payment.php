<?php
require_once 'config.php';
require_once 'MpesaPayment.php';
require 'africastalking/src/AfricasTalking.php';
require 'africastalking/vendor/autoload.php';
require_once 'vendor/autoload.php';

use AfricasTalking\SDK\AfricasTalking;

// Load environment variables (ensure .env is properly set up)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$AT_USERNAME = $_ENV['AT_USERNAME'];
$AT_API_KEY = $_ENV['AT_API_KEY'];

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['phone']) || !isset($data['package_id']) || !isset($data['package_price'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
        exit;
    }

    $phone = $data['phone'];
    $package_id = $data['package_id'];
    $package_price = $data['package_price'];

    try {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_error) {
            throw new Exception('Database connection failed: ' . $db->connect_error);
        }

        $mpesa = new MpesaPayment($db);
        $response = $mpesa->initiatePayment($phone, $package_price, "Package ID $package_id");

        if (isset($response->ResponseCode) && $response->ResponseCode == "0") {
            $transaction_id = $response->CheckoutRequestID;
            $username = "user" . rand(1000, 9999);
            $password = bin2hex(random_bytes(4)); // 8-character random password
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            // Check if user exists
            $user_check = $db->prepare("SELECT id FROM users WHERE phone = ?");
            $user_check->bind_param("s", $phone);
            $user_check->execute();
            $user_check->store_result();

            if ($user_check->num_rows > 0) {
                $user_check->bind_result($user_id);
                $user_check->fetch();
            } else {
                $insert_user = $db->prepare("INSERT INTO users (username, password, phone) VALUES (?, ?, ?)");
                $insert_user->bind_param("sss", $username, $hashedPassword, $phone);
                $insert_user->execute();
                $user_id = $insert_user->insert_id;
                $insert_user->close();
            }

            $user_check->close();

            // Insert into `radcheck`
            $insert_radcheck = $db->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
            $insert_radcheck->bind_param("ss", $username, $password);
            $insert_radcheck->execute();
            $insert_radcheck->close();

            // Insert into `radreply` for user attributes
            $reply_attributes = [
                ["Mikrotik-Rate-Limit", ":=", "5M/5M"], // Example bandwidth limit
                ["Acct-Interim-Interval", ":=", "600"] // Example session timeout
            ];

            foreach ($reply_attributes as $attr) {
                $insert_radreply = $db->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, ?, ?, ?)");
                $insert_radreply->bind_param("ssss", $username, $attr[0], $attr[1], $attr[2]);
                $insert_radreply->execute();
                $insert_radreply->close();
            }

            // Subscription handling
            $start_time = date('Y-m-d H:i:s');
            $end_time = date('Y-m-d H:i:s', strtotime('+30 days'));

            $insert_subscription = $db->prepare("INSERT INTO subscriptions (user_id, package_id, start_time, end_time, status) VALUES (?, ?, ?, ?, 'active')");
            $insert_subscription->bind_param("iiss", $user_id, $package_id, $start_time, $end_time);
            $insert_subscription->execute();
            $subscription_id = $insert_subscription->insert_id;
            $insert_subscription->close();

            // Insert payment record
            $insert_payment = $db->prepare("INSERT INTO payments (user_id, package_id, amount, transaction_id, payment_method, status) VALUES (?, ?, ?, ?, 'Mpesa', 'completed')");
            $insert_payment->bind_param("iiss", $user_id, $package_id, $package_price, $transaction_id);
            $insert_payment->execute();
            $insert_payment->close();

            // Send SMS notification
            $message = "Payment of KES $package_price successful. Your internet package is now active. Username: $username, Password: $password.";
            sendSMS($phone, $message, $AT_USERNAME, $AT_API_KEY);

            echo json_encode([
                'success' => true,
                'message' => 'Payment successful. User created.',
                'username' => $username,
                'password' => $password
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'M-Pesa request failed', 'response' => $response]);
        }
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error.']);
    }
}

/**
 * Function to send SMS using AfricasTalking API
 */
function sendSMS($phone, $message, $username, $apiKey) {
    $AT = new AfricasTalking($username, $apiKey);
    $sms = $AT->sms();

    try {
        $response = $sms->send([
            'to'      => $phone,
            'message' => $message
        ]);
        if ($response['status'] !== 'success') {
            error_log("AfricasTalking Error: " . json_encode($response));
        }
    } catch (Exception $e) {
        error_log("AfricasTalking Exception: " . $e->getMessage());
    }
}
?>
