<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Elon2508/*-');
define('DB_NAME', 'internet_service');

// Connect to database
function connectDB() {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Package class to manage internet packages
class Package {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Get all available packages
    public function getAllPackages() {
        $query = "SELECT * FROM packages WHERE active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get package details by ID
    public function getPackageById($id) {
        $query = "SELECT * FROM packages WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Client class to manage client accounts and subscriptions
class Client {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Register new client
    public function register($username, $password, $email, $phone) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO clients (username, password, email, phone) 
                  VALUES (:username, :password, :email, :phone)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":password", $hashed_password);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":phone", $phone);
        
        return $stmt->execute();
    }
    
    // Purchase package
    public function purchasePackage($client_id, $package_id) {
        // Start transaction
        $this->conn->beginTransaction();
        
        try {
            // Get package details
            $package = new Package($this->conn);
            $pkg_details = $package->getPackageById($package_id);
            
            if (!$pkg_details) {
                throw new Exception("Package not found");
            }
            
            // Create subscription record
            $query = "INSERT INTO subscriptions (client_id, package_id, start_date, end_date, status) 
                      VALUES (:client_id, :package_id, NOW(), 
                      DATE_ADD(NOW(), INTERVAL :duration DAY), 'active')";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":client_id", $client_id);
            $stmt->bindParam(":package_id", $package_id);
            $stmt->bindParam(":duration", $pkg_details['duration']);
            $stmt->execute();
            
            // Generate access credentials
            $username = "user_" . $client_id . "_" . time();
            $password = bin2hex(random_bytes(8));
            
            // Store access credentials
            $query = "INSERT INTO access_credentials (client_id, subscription_id, username, password) 
                      VALUES (:client_id, LAST_INSERT_ID(), :username, :password)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":client_id", $client_id);
            $stmt->bindParam(":username", $username);
            $stmt->bindParam(":password", $password);
            $stmt->execute();
            
            $this->conn->commit();
            
            return [
                'username' => $username,
                'password' => $password,
                'package' => $pkg_details
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    // Check subscription status
    public function checkSubscription($client_id) {
        $query = "SELECT s.*, p.name as package_name, p.speed, p.data_limit 
                  FROM subscriptions s 
                  JOIN packages p ON s.package_id = p.id 
                  WHERE s.client_id = :client_id AND s.status = 'active' 
                  AND s.end_date > NOW()";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":client_id", $client_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Usage example for the frontend
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = connectDB();
    $client = new Client($db);
    
    if (isset($_POST['register'])) {
        try {
            $client->register(
                $_POST['username'],
                $_POST['password'],
                $_POST['email'],
                $_POST['phone']
            );
            echo "Registration successful!";
        } catch (Exception $e) {
            echo "Registration failed: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['purchase'])) {
        try {
            $access = $client->purchasePackage(
                $_POST['client_id'],
                $_POST['package_id']
            );
            echo "Package purchased successfully!<br>";
            echo "Username: " . $access['username'] . "<br>";
            echo "Password: " . $access['password'];
        } catch (Exception $e) {
            echo "Purchase failed: " . $e->getMessage();
        }
    }
}
?>