<?php
require_once '../includes/functions.php';

startSession();

// Clear all session data
session_unset();
session_destroy();

// Redirect to home page with success message
redirect('../index.html', 'You have been logged out successfully.', 'success');
?>
