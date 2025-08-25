<?php
session_start();

// Clear admin session
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);

// Destroy session
session_destroy();

// Redirect to main site
header("Location: ../index.html");
exit;
?>
