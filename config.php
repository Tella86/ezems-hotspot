<?php
// Config file (config.php)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Elon2508/*-');
define('DB_NAME', 'internet_service');

// M-Pesa API configurations
define('MPESA_CONSUMER_KEY', 'lTPKZzbSmeoT0Hx2kJMGOMQwvGUCvI7G');
define('MPESA_CONSUMER_SECRET', 'gp7uF5GfK1EoBIjI');
define('MPESA_SHORTCODE', '7149030');
define('MPESA_PASSKEY', '1059e4e89b1ac704ea6b1b327df0ccaca297e5b31b9ea323c47cc0d87f31bfe1');
define('MPESA_CALLBACK_URL', 'https://wi-fi.ezems.co.ke/backend/callback.php');

// Router API configurations
define('ROUTER_IP', '192.168.24.1');
define('ROUTER_USERNAME', 'admin');
define('ROUTER_PASSWORD', 'Elon2508/*-');
define('ROUTER_API_PORT', '8728');

// Security settings
define('JWT_SECRET', 'your_jwt_secret_key');
session_start();
// Database connection class
class Database {
    private $conn;

    public function connect() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $this->conn;
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
}
?>
