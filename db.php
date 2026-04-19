<?php

// ================================
// DATABASE CONFIGURATION
// ================================
define('DB_HOST', 'your-rds-endpoint');
define('DB_USER', 'admin');
define('DB_PASS', 'password123');
define('DB_NAME', 'librarydb');

// ================================
// CREATE CONNECTION
// ================================
function getConnection() {

    $conn = new mysqli(
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME
    );

    // Check connection
    if ($conn->connect_error) {
        error_log("DB Connection Failed: " . $conn->connect_error);
        die(json_encode([
            'status'  => 'error',
            'message' => 'Database connection failed. Please try again.'
        ]));
    }

    // Set charset
    $conn->set_charset("utf8mb4");

    return $conn;
}

?>