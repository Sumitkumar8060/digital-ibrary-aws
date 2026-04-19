<?php

// ================================
// PROTECT PAGES
// Call this at top of any
// page that needs login
// ================================

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

?>