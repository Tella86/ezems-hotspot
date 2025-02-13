<?php
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

        // Remove user from FreeRADIUS (radcheck & radusergroup)
        $db->query("DELETE FROM radcheck WHERE username = '$username'");
        $db->query("DELETE FROM radusergroup WHERE username = '$username'");

        // Update subscription status to expired
        $db->query("UPDATE subscriptions SET status = 'expired' WHERE user_id = $user_id");

        // Send SMS & Email notifications
        $message = "Your internet package has expired. Please renew to continue using the service.";
        sendSMS($phone, $message);
        sendEmail($email, $message);
    }
}

// Close connection
$db->close();

/**
 * Function to send SMS notification
 */
function sendSMS($phone, $message) {
    $api_url = "https://sms_provider.com/api/send"; // Replace with actual SMS API URL
    $api_key = "YOUR_SMS_API_KEY"; // Replace with actual API key

    $postData = [
        'api_key' => $api_key,
        'to' => $phone,
        'message' => $message
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_exec($ch);
    curl_close($ch);
}

/**
 * Function to send Email notification
 */
function sendEmail($email, $message) {
    $headers = "From: no-reply@yourisp.com\r\n";
    $headers .= "Reply-To: support@yourisp.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $subject = "Subscription Expired - Renew Now!";
    $body = "<html><body><p>$message</p></body></html>";

    mail($email, $subject, $body, $headers);
}
?>
