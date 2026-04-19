<?php
session_start();
require_once 'db.php';

// ================================
// ONLY ACCEPT POST REQUESTS
// ================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.html");
    exit();
}

// ================================
// GET & SANITIZE INPUT
// ================================
$username   = trim($_POST['username'] ?? '');
$password   = trim($_POST['password'] ?? '');
$ip_address = $_SERVER['REMOTE_ADDR'];

// ================================
// VALIDATE INPUT
// ================================
if (empty($username) || empty($password)) {
    header("Location: index.html?error=empty");
    exit();
}

// ================================
// CONNECT TO DATABASE
// ================================
$conn = getConnection();

// ================================
// CHECK USER EXISTS
// Use prepared statement (safe)
// ================================
$stmt = $conn->prepare(
    "SELECT id, username, email, password, role 
     FROM users 
     WHERE username = ? OR email = ?
     LIMIT 1"
);

$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

// ================================
// USER FOUND - VERIFY PASSWORD
// ================================
if ($result->num_rows === 1) {

    $user = $result->fetch_assoc();

    // Verify hashed password
    if (password_verify($password, $user['password'])) {

        // ✅ LOGIN SUCCESS

        // Set session variables
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email']    = $user['email'];
        $_SESSION['role']     = $user['role'];

        // Update last login time
        $update = $conn->prepare(
            "UPDATE users 
             SET last_login = NOW() 
             WHERE id = ?"
        );
        $update->bind_param("i", $user['id']);
        $update->execute();
        $update->close();

        // Log successful login
        $log = $conn->prepare(
            "INSERT INTO login_logs 
                (user_id, username, ip_address, status)
             VALUES (?, ?, ?, 'success')"
        );
        $log->bind_param("iss", $user['id'], $user['username'], $ip_address);
        $log->execute();
        $log->close();

        // Redirect based on role
        if ($user['role'] === 'admin') {
            header("Location: dashboard.php");
        } else {
            header("Location: library.php");
        }
        exit();

    } else {

        // ❌ WRONG PASSWORD

        // Log failed login
        $log = $conn->prepare(
            "INSERT INTO login_logs 
                (user_id, username, ip_address, status)
             VALUES (?, ?, ?, 'failed')"
        );
        $log->bind_param("iss", $user['id'], $username, $ip_address);
        $log->execute();
        $log->close();

        header("Location: index.html?error=invalid");
        exit();
    }

} else {

    // ❌ USER NOT FOUND

    // Log failed attempt
    $log = $conn->prepare(
        "INSERT INTO login_logs 
            (user_id, username, ip_address, status)
         VALUES (NULL, ?, ?, 'failed')"
    );
    $log->bind_param("ss", $username, $ip_address);
    $log->execute();
    $log->close();

    header("Location: index.html?error=notfound");
    exit();
}

$stmt->close();
$conn->close();
?>