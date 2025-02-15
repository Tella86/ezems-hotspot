<?php
require_once 'vendor/autoload.php';  // Ensure Composer's autoloader is included

use \Firebase\JWT\JWT;  // Import the JWT class
// Config file (config.php)

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'ezemscok');
define('DB_PASS', '9Y9lO1ql:Bq7*N');
define('DB_NAME', 'ezemscok_internet_services');

// M-Pesa API configurations
define('MPESA_CONSUMER_KEY', 'lTPKZzbSmeoT0Hx2kJMGOMQwvGUCvI7G');
define('MPESA_CONSUMER_SECRET', 'gp7uF5GfK1EoBIjI');
define('MPESA_SHORTCODE', '7149030');
define('MPESA_PASSKEY', '1059e4e89b1ac704ea6b1b327df0ccaca297e5b31b9ea323c47cc0d87f31bfe1');
define('MPESA_CALLBACK_URL', 'https://wi-fi.ezems.co.ke/ezems-hotspot/mpesa_callback.php');

// Router API configurations
define('ROUTER_IP', '192.168.24.1');
define('ROUTER_USERNAME', 'admin');
define('ROUTER_PASSWORD', 'Elon2508/*-');
define('ROUTER_API_PORT', '8728');

// Security settings
define('JWT_SECRET', 'your_jwt_secret_key');

// Start session
session_start();

// Database connection class
class Database {
    private $conn;

    public function connect() {
        try {
            // Create a new PDO connection to the database
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS
            );
            // Set the PDO error mode to exception for better error handling
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $this->conn;
        } catch(PDOException $e) {
            // If connection fails, display error message and stop execution
            die("Connection failed: " . $e->getMessage());
        }
    }
}

// RouterManager class to manage router interactions
class RouterManager {
    // Function to check router status
    public function checkRouterStatus() {
        // Code to communicate with the router's API using the credentials
        // provided in the config.php file (using HTTP requests or router API)
        $router_url = 'http://' . ROUTER_IP . ':' . ROUTER_API_PORT . '/status';
        $ch = curl_init($router_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, ROUTER_USERNAME . ":" . ROUTER_PASSWORD);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }
    
    // Function to add a user to the router (for internet access control)
    public function addUser($username, $password) {
        // Code to add a user to the router's access control list via its API
        $router_url = 'http://' . ROUTER_IP . ':' . ROUTER_API_PORT . '/add_user';
        $data = array(
            'username' => $username,
            'password' => $password
        );
        
        $ch = curl_init($router_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, ROUTER_USERNAME . ":" . ROUTER_PASSWORD);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    // Function to remove a user from the router
    public function removeUser($username) {
        // Code to remove a user from the router's access control list via its API
        $router_url = 'http://' . ROUTER_IP . ':' . ROUTER_API_PORT . '/remove_user';
        $data = array('username' => $username);
        
        $ch = curl_init($router_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, ROUTER_USERNAME . ":" . ROUTER_PASSWORD);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    // Other router management functions can be added here...
}

// Security class to handle JWT authentication and security features
class Security {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Function to validate JWT token
    public function validateJWT($jwt) {
        // Code to validate JWT token, decode and check for authenticity
        // Use JWT_SECRET from config.php for signature verification
        try {
            $decoded = JWT::decode($jwt, JWT_SECRET);
            return $decoded;
        } catch (Exception $e) {
            return null; // Invalid JWT token
        }
    }

    // Function to create JWT token
    public function createJWT($user_data) {
        // Code to create JWT token using user data and JWT_SECRET
        $issuedAt = time();
        $expirationTime = $issuedAt + 3600;  // jwt valid for 1 hour from the issued time
        $payload = array(
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'data' => $user_data
        );

        $jwt = JWT::encode($payload, JWT_SECRET);
        return $jwt;
    }
}

?>
