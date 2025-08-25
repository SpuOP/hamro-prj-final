<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../includes/functions.php';

$response = ['success' => false, 'message' => '', 'data' => null];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $special_id = sanitizeInput($input['special_id'] ?? '');
    $password = $input['password'] ?? '';
    
    // Validation
    if (empty($special_id) || empty($password)) {
        $response['message'] = 'Both Special ID and password are required';
        echo json_encode($response);
        exit;
    }
    
    if (!preg_match('/^CIP-[A-Z]{3}-\d{4}$/', $special_id)) {
        $response['message'] = 'Invalid Special ID format (expected: CIP-CITY-XXXX)';
        echo json_encode($response);
        exit;
    }
    
    // Authenticate user with special ID
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT u.id, u.special_login_id, u.password_hash, u.full_name, u.email, 
               u.is_verified, u.is_active, u.city_id, u.metro_area_id,
               c.name as city_name, ma.name as metro_area_name 
        FROM users u 
        LEFT JOIN cities c ON u.city_id = c.id 
        LEFT JOIN metro_areas ma ON u.metro_area_id = ma.id 
        WHERE u.special_login_id = ? AND u.is_verified = 1 AND u.is_active = 1
    ");
    $stmt->execute([$special_id]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        // Login successful
        startSession();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['special_id'] = $user['special_login_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['city_id'] = $user['city_id'];
        $_SESSION['metro_area_id'] = $user['metro_area_id'];
        $_SESSION['city_name'] = $user['city_name'];
        $_SESSION['metro_area_name'] = $user['metro_area_name'];
        
        if (!empty($user['is_admin'])) { 
            $_SESSION['is_admin'] = true; 
        }
        
        // Update last login
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        $response['success'] = true;
        $response['message'] = 'Login successful';
        $response['data'] = [
            'user_id' => $user['id'],
            'special_id' => $user['special_login_id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'city_name' => $user['city_name'],
            'metro_area_name' => $user['metro_area_name'],
            'is_admin' => !empty($user['is_admin']),
            'redirect_url' => !empty($user['is_admin']) ? '/admin/dashboard.php' : '/dashboard.php'
        ];
        
    } else {
        $response['message'] = 'Invalid Special ID or password';
        
        // Log failed login attempt
        error_log("Failed login attempt with Special ID: $special_id from IP: " . $_SERVER['REMOTE_ADDR']);
    }
    
} catch (Exception $e) {
    $response['message'] = 'An error occurred during login';
    error_log("Login error: " . $e->getMessage());
}

echo json_encode($response);
?>
