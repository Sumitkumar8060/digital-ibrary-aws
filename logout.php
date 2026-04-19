<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Redirect to login
header("Location: index.html");
exit();
?>