<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.html");
    exit();
}

// ================================
// GET & SANITIZE INPUT
// ================================
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');
$confirm  = trim($_POST['confirm']  ?? '');

// ================================
// VALIDATE
// ================================
$errors = [];

if (empty($username)) $errors[] = "Username is required";
if (empty($email))    $errors[] = "Email is required";
if (empty($password)) $errors[] = "Password is required";
if (strlen($password) < 6) $errors[] = "Password must be 6+ characters";
if ($password !== $confirm)  $errors[] = "Passwords do not match";
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email";

if (!empty($errors)) {
    $msg = urlencode(implode(', ', $errors));
    header("Location: index.html?error=$msg");
    exit();
}

// ================================
// CONNECT DB
// ================================
$conn = getConnection();

// ================================
// CHECK IF USER EXISTS
// ================================
$check = $conn->prepare(
    "SELECT id FROM users 
     WHERE username = ? OR email = ?"
);
$check->bind_param("ss", $username, $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    header("Location: index.html?error=exists");
    $check->close();
    $conn->close();
    exit();
}
$check->close();

// ================================
// HASH PASSWORD & INSERT USER
// ================================
$hashed = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare(
    "INSERT INTO users 
        (username, email, password, role)
     VALUES (?, ?, ?, 'user')"
);
$stmt->bind_param("sss", $username, $email, $hashed);

if ($stmt->execute()) {
    $new_user_id = $conn->insert_id;

    // Auto login after register
    $_SESSION['user_id']  = $new_user_id;
    $_SESSION['username'] = $username;
    $_SESSION['email']    = $email;
    $_SESSION['role']     = 'user';

    header("Location: library.php");
    exit();

} else {
    header("Location: index.html?error=failed");
    exit();
}

$stmt->close();
$conn->close();
?>