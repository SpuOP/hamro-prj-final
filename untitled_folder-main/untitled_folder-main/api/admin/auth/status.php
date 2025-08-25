<?php
// Admin auth status endpoint
// Returns JSON with authentication status and admin info if logged in

declare(strict_types=1);

header('Content-Type: application/json');

// Harden session settings (must be called before session_start)
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

require_once __DIR__ . '/../../../config/database.php';

// Session helpers
function admin_start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function admin_is_authenticated(): bool {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_username']);
}

function admin_session_expired(int $timeoutSeconds): bool {
    if (!isset($_SESSION['admin_last_activity'])) {
        return true;
    }
    return (time() - (int)$_SESSION['admin_last_activity']) > $timeoutSeconds;
}

// Config
const ADMIN_SESSION_TIMEOUT = 1800; // 30 minutes

admin_start_session();

// Handle session timeout
if (admin_is_authenticated() && admin_session_expired(ADMIN_SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
}

if (admin_is_authenticated()) {
    // Refresh activity timestamp
    $_SESSION['admin_last_activity'] = time();

    $response = [
        'authenticated' => true,
        'admin' => [
            'id' => (int)$_SESSION['admin_id'],
            'username' => (string)($_SESSION['admin_username'] ?? ''),
            'email' => (string)($_SESSION['admin_email'] ?? ''),
            'role' => (string)($_SESSION['admin_role'] ?? 'admin')
        ],
        'expiresIn' => ADMIN_SESSION_TIMEOUT,
        'redirect' => null
    ];

    echo json_encode($response);
    exit;
}

http_response_code(401);
echo json_encode([
    'authenticated' => false,
    'reason' => 'unauthorized',
    'redirect' => '/untitled_folder-main/auth/login.html'
]);
exit;
?>
