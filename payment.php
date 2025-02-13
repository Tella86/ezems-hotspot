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
define('ROUTER_IP', '192.168.1.1');
define('ROUTER_USERNAME', 'admin');
define('ROUTER_PASSWORD', 'router_password');
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

// M-Pesa Payment Integration
class MpesaPayment {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Generate M-Pesa access token
    private function getAccessToken() {
        $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
        $ch = curl_init('https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $result = json_decode($response);
        curl_close($ch);
        
        return $result->access_token;
    }
    
    // Initiate STK Push
    public function initiatePayment($phone, $amount, $account_ref) {
        $access_token = $this->getAccessToken();
        $timestamp = date('YmdHis');
        $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);
        
        $curl_post_data = array(
            'BusinessShortCode' => MPESA_SHORTCODE,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerBuyGoodsOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => 7136632,
            'PhoneNumber' => $phone,
            'CallBackURL' => MPESA_CALLBACK_URL,
            'AccountReference' => $account_ref,
            'TransactionDesc' => 'Internet Package Purchase'
        );
        
        $ch = curl_init('https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response);
    }
}

// Router Integration using RouterOS API
class RouterManager {
    private $api;
    
    public function __construct() {
        require_once('routeros_api.class.php');
        $this->api = new RouterosAPI();
        $this->api->debug = false;
    }
    
    // Connect to router
    public function connect() {
        if($this->api->connect(ROUTER_IP, ROUTER_USERNAME, ROUTER_PASSWORD)) {
            return true;
        }
        return false;
    }
    
    // Add new user to router
    public function addUser($username, $password, $profile) {
        if($this->connect()) {
            $this->api->comm("/ip/hotspot/user/add", array(
                "name" => $username,
                "password" => $password,
                "profile" => $profile
            ));
            $this->api->disconnect();
            return true;
        }
        return false;
    }
    
    // Disable expired users
    public function disableUser($username) {
        if($this->connect()) {
            $this->api->comm("/ip/hotspot/user/set", array(
                "numbers" => $username,
                "disabled" => "yes"
            ));
            $this->api->disconnect();
            return true;
        }
        return false;
    }
}

// Security class for authentication and authorization
class Security {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Generate JWT token
    public function generateToken($user_id) {
        $payload = array(
            "user_id" => $user_id,
            "exp" => time() + (60 * 60) // 1 hour expiry
        );
        
        return JWT::encode($payload, JWT_SECRET);
    }
    
    // Verify JWT token
    public function verifyToken($token) {
        try {
            $decoded = JWT::decode($token, JWT_SECRET, array('HS256'));
            return $decoded->user_id;
        } catch(Exception $e) {
            return false;
        }
    }
    
    // Sanitize input
    public function sanitizeInput($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
    
    // Validate phone number
    public function validatePhone($phone) {
        return preg_match('/^254[0-9]{9}$/', $phone);
    }
}
?>

<!-- HTML/CSS for the frontend -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internet Package Purchase</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            background-color: #f4f4f4;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .package-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .alert {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Internet Packages</h1>
        
        <div class="package-card">
            <h2>Daily Package</h2>
            <p>Speed: 10 Mbps</p>
            <p>Price: KSH 100</p>
            <button class="btn" onclick="purchasePackage(1)">Purchase</button>
        </div>
        
        <div class="package-card">
            <h2>Weekly Package</h2>
            <p>Speed: 15 Mbps</p>
            <p>Price: KSH 500</p>
            <button class="btn" onclick="purchasePackage(2)">Purchase</button>
        </div>
        
        <div id="payment-form" style="display: none;">
            <h2>Payment Details</h2>
            <form id="mpesa-form">
                <div class="form-group">
                    <label>Phone Number (254XXXXXXXXX)</label>
                    <input type="text" id="phone" required>
                </div>
                <button type="submit" class="btn">Pay with M-Pesa</button>
            </form>
        </div>
    </div>

    <script>
        function purchasePackage(packageId) {
            document.getElementById('payment-form').style.display = 'block';
            // Store package ID in session
            sessionStorage.setItem('selected_package', packageId);
        }
        
        document.getElementById('mpesa-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const phone = document.getElementById('phone').value;
            const packageId = sessionStorage.getItem('selected_package');
            
            // Send payment request
            fetch('process_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    phone: phone,
                    package_id: packageId
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('Please check your phone to complete the payment');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error processing payment');
            });
        });
    </script>
</body>
</html>