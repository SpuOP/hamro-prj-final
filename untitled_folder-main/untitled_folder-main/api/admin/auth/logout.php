<?php
// Admin logout endpoint
// Destroys session and returns JSON

declare(strict_types=1);

header('Content-Type: application/json');

// Secure session cookie flags
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function start_admin_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

start_admin_session();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', (bool)($params['secure'] ?? false), true);
}
session_destroy();

echo json_encode([
    'ok' => true,
    'authenticated' => false,
    'redirect' => '/untitled_folder-main/untitled_folder-main/auth/login.html'
]);
exit;
?>
