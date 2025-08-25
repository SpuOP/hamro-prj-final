<?php
declare(strict_types=1);
header('Content-Type: application/json');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
echo json_encode(['ok' => true, 'csrf_token' => $_SESSION['csrf_token']]);
exit;
?>
