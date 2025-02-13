<?php
session_start();
require_once 'config.php'; // Ensure this file is error-free
// require_once 'config/database.php';
require_once 'vendor/autoload.php'; // If using JWT
require_once 'mpesa.php';
require_once 'MpesaPayment.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set response header
header('Content-Type: application/json');

// Database connection
$db = new Database();
$conn = $db->connect();

// Initialize classes
$mpesa = new MpesaPayment($conn);
// $router = new RouterManager();
// $security = new Security($conn);

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $data) {
    $phone = $security->sanitizeInput($data['phone']);
    $amount = floatval($data['package_price']); // Ensure the key matches your frontend
    $username = "user_" . bin2hex(random_bytes(4)); // Generate a username
    $password = bin2hex(random_bytes(4)); // Generate a password
    $profile = "default"; // Default router profile

    // Validate phone number
    if (!$security->validatePhone($phone)) {
        echo json_encode(["status" => "error", "message" => "Invalid phone number"]);
        exit;
    }

    // Initiate M-Pesa Payment
    $mpesaResponse = $mpesa->initiatePayment($phone, $amount, $username);

    if (isset($mpesaResponse->ResponseCode) && $mpesaResponse->ResponseCode == "0") {
        // Payment initiated successfully
        echo json_encode(["status" => "pending", "message" => "Payment request sent. Complete payment on your phone."]);
        exit;
    } else {
        echo json_encode(["status" => "error", "message" => "Payment initiation failed"]);
        exit;
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method or empty data"]);
}
?>
