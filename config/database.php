<?php
class Database {
    private $conn;

    public function connect() {
        try {
            // Ensure DB constants are loaded
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            return $this->conn;
        } catch (PDOException $e) {
            // Log error instead of displaying it
            error_log("Database Connection Error: " . $e->getMessage(), 3, __DIR__ . "/../logs/db_errors.log");
            
            // Return a generic message
            die("Database connection failed. Please try again later.");
        }
    }
}
