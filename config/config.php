<?php
// Load environment variables (if using .env)
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database Credentials
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);

// MPESA API Credentials
define('MPESA_CONSUMER_KEY', $_ENV['MPESA_CONSUMER_KEY']);
define('MPESA_CONSUMER_SECRET', $_ENV['MPESA_CONSUMER_SECRET']);
define('MPESA_SHORTCODE', $_ENV['MPESA_SHORTCODE']);
define('MPESA_PASSKEY', $_ENV['MPESA_PASSKEY']);
define('MPESA_CALLBACK_URL', $_ENV['MPESA_CALLBACK_URL']);

// Check Environment (Sandbox or Live)
define('MPESA_ENV', $_ENV['MPESA_ENV']);

if (MPESA_ENV === 'sandbox') {
    define('MPESA_AUTH_URL', 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
    define('MPESA_STK_URL', 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
} else {
    define('MPESA_AUTH_URL', 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
    define('MPESA_STK_URL', 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
}

// Enable Error Reporting (Only in Development)
if ($_ENV['APP_ENV'] === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>
