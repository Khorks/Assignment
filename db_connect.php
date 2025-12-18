<?php
/**
 * Database Connection File
 * This file establishes connection to the drink_db database
 */

// Database configuration
define('DB_SERVER', 'tarumt-db.cygrix5xf6gu.us-east-1.rds.amazonaws.com');
define('DB_USERNAME', 'admin');
define('DB_PASSWORD', 'Admin123');
define('DB_NAME', 'drink_db');

// Create connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for proper character encoding
$conn->set_charset("utf8mb4");

// Optional: Display success message (comment out in production)
// echo "Connected successfully to database: " . DB_NAME;
?>