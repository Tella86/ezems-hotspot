<?php
// config.php
require_once 'vendor/autoload.php';

class Config {
    // Database configuration
    const DB_HOST = 'localhost';
    const DB_USER = 'root';
    const DB_PASS = 'Elon2508/*-';
    const DB_NAME = 'internet_service';
    
    // M-Pesa configuration
    const MPESA_CONSUMER_KEY = 'lTPKZzbSmeoT0Hx2kJMGOMQwvGUCvI7G';
    const MPESA_CONSUMER_SECRET = 'gp7uF5GfK1EoBIjI';
    const MPESA_SHORTCODE = '7149030';
    
    // Router configuration
    const ROUTER_IP = '192.168.24.1';
    const ROUTER_USER = 'admin';
    const ROUTER_PASS = 'Elon2508/*-';
    
    // System settings
    const ADMIN_EMAIL = 'admin@ezems.co.ke';
    const SYSTEM_NAME = 'Internet Service Management';
}

// Database schema
/*
CREATE TABLE packages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    speed_mbps INT,
    data_limit_gb DECIMAL(10,2),
    duration_hours INT,
    price DECIMAL(10,2),
    description TEXT,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'user') DEFAULT 'user',
    is_active BOOLEAN DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    package_id INT,
    start_time DATETIME,
    end_time DATETIME,
    data_used_mb DECIMAL(10,2) DEFAULT 0,
    status ENUM('active', 'expired', 'suspended'),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (package_id) REFERENCES packages(id)
);

CREATE TABLE usage_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscription_id INT,
    bytes_down BIGINT,
    bytes_up BIGINT,
    timestamp DATETIME,
    ip_address VARCHAR(45),
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id)
);

CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    package_id INT,
    amount DECIMAL(10,2),
    transaction_id VARCHAR(100),
    payment_method VARCHAR(50),
    status ENUM('pending', 'completed', 'failed'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (package_id) REFERENCES packages(id)
);
*/

// Package Management Class
class PackageManager {
    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    public function createPackage($data) {
        $query = "INSERT INTO packages (name, speed_mbps, data_limit_gb, duration_hours, 
                  price, description) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            $data['name'],
            $data['speed'],
            $data['data_limit'],
            $data['duration'],
            $data['price'],
            $data['description']
        ]);
    }
    
    public function updatePackage($id, $data) {
        $query = "UPDATE packages SET name = ?, speed_mbps = ?, data_limit_gb = ?, 
                  duration_hours = ?, price = ?, description = ? WHERE id = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            $data['name'],
            $data['speed'],
            $data['data_limit'],
            $data['duration'],
            $data['price'],
            $data['description'],
            $id
        ]);
    }
    
    public function getActivePackages() {
        $query = "SELECT * FROM packages WHERE is_active = 1 ORDER BY price";
        return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Usage Monitoring Class
class UsageMonitor {
    private $db;
    private $router;
    
    public function __construct(PDO $db, RouterManager $router) {
        $this->db = $db;
        $this->router = $router;
    }
    
    public function recordUsage($subscription_id) {
        $usage = $this->router->getUserTraffic($subscription_id);
        $query = "INSERT INTO usage_logs (subscription_id, bytes_down, bytes_up, timestamp, ip_address) 
                  VALUES (?, ?, ?, NOW(), ?)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            $subscription_id,
            $usage['bytes_down'],
            $usage['bytes_up'],
            $usage['ip_address']
        ]);
    }
    
    public function checkDataLimit($subscription_id) {
        $query = "SELECT s.*, p.data_limit_gb 
                  FROM subscriptions s 
                  JOIN packages p ON s.package_id = p.id 
                  WHERE s.id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$subscription_id]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($subscription['data_used_mb'] >= ($subscription['data_limit_gb'] * 1024)) {
            $this->suspendSubscription($subscription_id);
            return false;
        }
        return true;
    }
    
    public function getUsageStats($subscription_id) {
        $query = "SELECT 
                    SUM(bytes_down + bytes_up) as total_bytes,
                    MAX(timestamp) as last_activity,
                    COUNT(*) as sessions
                  FROM usage_logs 
                  WHERE subscription_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$subscription_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Admin Dashboard Class
class AdminDashboard {
    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    public function getSystemStats() {
        return [
            'active_users' => $this->getActiveUsers(),
            'total_revenue' => $this->getTotalRevenue(),
            'package_stats' => $this->getPackageStats(),
            'usage_stats' => $this->getUsageStats()
        ];
    }
    
    public function getActiveUsers() {
        $query = "SELECT COUNT(*) FROM subscriptions WHERE status = 'active'";
        return $this->db->query($query)->fetchColumn();
    }
    
    public function getTotalRevenue() {
        $query = "SELECT SUM(amount) FROM payments WHERE status = 'completed'";
        return $this->db->query($query)->fetchColumn();
    }
    
    public function getPackageStats() {
        $query = "SELECT p.name, COUNT(s.id) as subscribers
                  FROM packages p
                  LEFT JOIN subscriptions s ON p.id = s.package_id
                  GROUP BY p.id";
        return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>

<!-- Admin Dashboard HTML -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Internet Service Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 text-white">
            <div class="p-4">
                <h1 class="text-xl font-bold">Admin Dashboard</h1>
            </div>
            <nav class="mt-4">
                <a href="#" class="block py-2 px-4 hover:bg-gray-700">Dashboard</a>
                <a href="#" class="block py-2 px-4 hover:bg-gray-700">Packages</a>
                <a href="#" class="block py-2 px-4 hover:bg-gray-700">Users</a>
                <a href="#" class="block py-2 px-4 hover:bg-gray-700">Reports</a>
                <a href="#" class="block py-2 px-4 hover:bg-gray-700">Settings</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-8">
            <div class="grid grid-cols-4 gap-4 mb-8">
                <!-- Stats Cards -->
                <div class="bg-white p-4 rounded-lg shadow">
                    <h3 class="text-gray-500">Active Users</h3>
                    <p class="text-2xl font-bold" id="activeUsers">Loading...</p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow">
                    <h3 class="text-gray-500">Total Revenue</h3>
                    <p class="text-2xl font-bold" id="totalRevenue">Loading...</p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow">
                    <h3 class="text-gray-500">Active Packages</h3>
                    <p class="text-2xl font-bold" id="activePackages">Loading...</p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow">
                    <h3 class="text-gray-500">Today's Sales</h3>
                    <p class="text-2xl font-bold" id="todaySales">Loading...</p>
                </div>
            </div>

            <!-- Package Management -->
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <h2 class="text-xl font-bold mb-4">Package Management</h2>
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left py-2">Name</th>
                            <th class="text-left py-2">Speed</th>
                            <th class="text-left py-2">Data Limit</th>
                            <th class="text-left py-2">Price</th>
                            <th class="text-left py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="packagesList">
                        <!-- Packages will be loaded here -->
                    </tbody>
                </table>
            </div>

            <!-- Usage Monitor -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold mb-4">System Usage</h2>
                <div id="usageChart"></div>
            </div>
        </div>
    </div>

    <script>
        // Admin Dashboard JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Load dashboard data
            fetchDashboardStats();
            // Load packages
            loadPackages();
            // Initialize usage chart
            initializeUsageChart();
        });

        function fetchDashboardStats() {
            fetch('api/dashboard-stats.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('activeUsers').textContent = data.active_users;
                    document.getElementById('totalRevenue').textContent = 
                        'KSH ' + data.total_revenue.toLocaleString();
                    document.getElementById('activePackages').textContent = data.active_packages;
                    document.getElementById('todaySales').textContent = 
                        'KSH ' + data.today_sales.toLocaleString();
                });
        }

        function loadPackages() {
            fetch('api/packages.php')
                .then(response => response.json())
                .then(packages => {
                    const tbody = document.getElementById('packagesList');
                    tbody.innerHTML = packages.map(package => `
                        <tr>
                            <td class="py-2">${package.name}</td>
                            <td class="py-2">${package.speed_mbps} Mbps</td>
                            <td class="py-2">${package.data_limit_gb} GB</td>
                            <td class="py-2">KSH ${package.price}</td>
                            <td class="py-2">
                                <button onclick="editPackage(${package.id})" 
                                        class="bg-blue-500 text-white px-2 py-1 rounded">
                                    Edit
                                </button>
                                <button onclick="deletePackage(${package.id})" 
                                        class="bg-red-500 text-white px-2 py-1 rounded">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    `).join('');
                });
        }

        function initializeUsageChart() {
            // Implementation would depend on your chosen charting library
            // Example using Chart.js
            const ctx = document.getElementById('usageChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'System Usage',
                        data: [],
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }]
                }
            });
        }
    </script>
</body>
</html>