<?php
require_once 'config.php';
require_once 'MpesaPayment.php';

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
            // Payment request was successful, now process user creation
            $transaction_id = $response->CheckoutRequestID; // Example transaction ID from M-Pesa response

            // Generate a random username and password
            $username = "user" . rand(1000, 9999); 
            $password = substr(md5(time()), 0, 8); // Generate an 8-character password

            // Check if the user already exists
            $user_check = $db->prepare("SELECT id FROM users WHERE phone = ?");
            $user_check->bind_param("s", $phone);
            $user_check->execute();
            $user_check->store_result();

            if ($user_check->num_rows > 0) {
                // Fetch existing user ID
                $user_check->bind_result($user_id);
                $user_check->fetch();
            } else {
                // Insert new user
                $insert_user = $db->prepare("INSERT INTO users (username, password, phone) VALUES (?, ?, ?)");
                $insert_user->bind_param("sss", $username, $password, $phone);
                $insert_user->execute();
                $user_id = $insert_user->insert_id;
                $insert_user->close();
            }

            $user_check->close();

            // Insert into `radcheck` table for FreeRADIUS authentication
            $insert_radcheck = $db->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
            $insert_radcheck->bind_param("ss", $username, $password);
            $insert_radcheck->execute();
            $insert_radcheck->close();

            // Calculate subscription duration (e.g., 30 days)
            $start_time = date('Y-m-d H:i:s');
            $end_time = date('Y-m-d H:i:s', strtotime('+30 days'));

            // Insert subscription details
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
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
