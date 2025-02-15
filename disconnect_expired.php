<?php

require 'africastalking/src/AfricasTalking.php';
require 'africastalking/vendor/autoload.php';
require_once 'config.php';

// Connect to the database
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    die("Database connection failed: " . $db->connect_error);
}

// Get expired subscriptions
$query = "SELECT s.user_id, u.username, u.phone, u.email FROM subscriptions s
          JOIN users u ON s.user_id = u.id
          WHERE s.end_time <= NOW() AND s.status = 'active'";

$result = $db->query($query);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $username = $row['username'];
        $user_id = $row['user_id'];
        $phone = $row['phone'];
        $email = $row['email'];

        // Securely delete user from FreeRADIUS
        $stmt1 = $db->prepare("DELETE FROM radcheck WHERE username = ?");
        $stmt1->bind_param("s", $username);
        $stmt1->execute();

        $stmt2 = $db->prepare("DELETE FROM radusergroup WHERE username = ?");
        $stmt2->bind_param("s", $username);
        $stmt2->execute();

        // Update subscription status to expired
        $stmt3 = $db->prepare("UPDATE subscriptions SET status = 'expired' WHERE user_id = ?");
        $stmt3->bind_param("i", $user_id);
        $stmt3->execute();

        // Send SMS & Email notifications
        $message = "Your internet package has expired. Please renew to continue using the service.";
        sendSMS($phone, $message);
        sendEmail($email, $message);
    }
}

// Close connection
$db->close();

/**
 * Function to send SMS using AfricasTalking API
 */
function sendSMS($phone, $message) {
    $username = "ezems";  // Your AfricasTalking username
    $apiKey = "39fafb4f99370b33f2ce8a89fb49de56c6db75d19219d49db45c0522931be77e"; // Your API Key

    $AT = new AfricasTalking($username, $apiKey);
    $sms = $AT->sms();

    try {
        $response = $sms->send([
            'to'      => $phone,
            'message' => $message
        ]);
        return $response;
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

/**
 * Function to send Email notification
 */
function sendEmail($email, $message) {
    $headers = "From: no-reply@ezems.co.ke\r\n";
    $headers .= "Reply-To: support@ezems.co.ke\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $subject = "Subscription Expired - Renew Now!";
    $body = "<html><body><p>$message</p></body></html>";

    mail($email, $subject, $body, $headers);
}
?>
