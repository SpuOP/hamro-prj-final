<?php
declare(strict_types=1);
header('Content-Type: application/json');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
require_once __DIR__ . '/../../../config/database.php';
function start_admin_session(): void { if (session_status() === PHP_SESSION_NONE) { session_start(); } }
function json_input(): array { $raw = file_get_contents('php://input'); $data = json_decode($raw ?: '[]', true); return is_array($data) ? $data : []; }
function sanitize_string(?string $v): string { return trim((string)($v ?? '')); }
try {
    $pdo = getDBConnection();
    $body = $_SERVER['REQUEST_METHOD'] === 'POST' ? json_input() : $_POST;
    $usernameOrEmail = sanitize_string($body['usernameOrEmail'] ?? $body['username'] ?? $body['email'] ?? '');
    $password = (string)($body['password'] ?? '');
    $remember = (bool)($body['remember'] ?? false);
    if ($usernameOrEmail === '' || $password === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Missing credentials']); exit; }
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role, is_active FROM admins WHERE (username = ? OR email = ?) LIMIT 1");
    $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
    $admin = $stmt->fetch();
    if (!$admin || !(bool)$admin['is_active']) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Invalid credentials']); exit; }
    if (!password_verify($password, $admin['password_hash'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Invalid credentials']); exit; }
    start_admin_session();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_username'] = (string)$admin['username'];
    $_SESSION['admin_email'] = (string)$admin['email'];
    $_SESSION['admin_role'] = (string)$admin['role'];
    $_SESSION['admin_last_activity'] = time();
    if ($remember && PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(['lifetime' => 7*24*60*60, 'httponly'=>true, 'samesite'=>'Lax']);
    }
    $upd = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
    $upd->execute([(int)$admin['id']]);
    echo json_encode(['ok'=>true,'authenticated'=>true,'admin'=>['id'=>(int)$admin['id'],'username'=>$admin['username'],'email'=>$admin['email'],'role'=>$admin['role']],'redirect'=>'/untitled_folder-main/admin/dashboard.html']);
    exit;
} catch (Throwable $e) {
    http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Server error']); exit;
}
?>
