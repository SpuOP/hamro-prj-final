<?php
if (!defined('CV_FUNCTIONS_LOADED')) {
define('CV_FUNCTIONS_LOADED', true);
/**
 * Utility Functions
 */

require_once __DIR__ . '/../config/database.php';

// Start session if not already started
if (!function_exists('startSession')) {
    function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

// Check if user is logged in
function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']);
}

// Get current user data
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Generate CSRF token
function generateCSRFToken() {
    startSession();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    startSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Format date for display
function formatDate($date) {
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return "Just now";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date('M j, Y', $timestamp);
    }
}

// Get user's vote on an issue
function getUserVote($issueId, $userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT vote_type FROM votes WHERE issue_id = ? AND user_id = ?");
    $stmt->execute([$issueId, $userId]);
    $result = $stmt->fetch();
    return $result ? $result['vote_type'] : null;
}

// Update issue vote count
function updateIssueVoteCount($issueId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        UPDATE issues 
        SET votes_count = (
            SELECT COALESCE(SUM(CASE WHEN vote_type = 'upvote' THEN 1 ELSE -1 END), 0)
            FROM issue_votes 
            WHERE issue_id = ?
        )
        WHERE id = ?
    ");
    $stmt->execute([$issueId, $issueId]);
}

// Generate special login ID
function generateSpecialLoginID($cityName) {
    $pdo = getDBConnection();
    
    // Get city prefix (first 3 letters, uppercase)
    $cityPrefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $cityName), 0, 3));
    
    // Get next sequence number for this city
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 as next_number 
        FROM users 
        WHERE special_login_id LIKE ?
    ");
    $stmt->execute([$cityPrefix . '-%']);
    $result = $stmt->fetch();
    $nextNumber = $result['next_number'];
    
    // Format: CIP-CITY-XXXX (e.g., CIP-KTM-1001)
    return 'CIP-' . $cityPrefix . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
}

// Check if special ID is unique
function isSpecialIDUnique($specialId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE special_login_id = ?");
    $stmt->execute([$specialId]);
    return $stmt->fetchColumn() == 0;
}

// Get user by special ID
function getUserBySpecialID($specialId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT u.*, c.name as city_name, ma.name as metro_area_name 
        FROM users u 
        LEFT JOIN cities c ON u.city_id = c.id 
        LEFT JOIN metro_areas ma ON u.metro_area_id = ma.id 
        WHERE u.special_login_id = ? AND u.is_verified = 1 AND u.is_active = 1
    ");
    $stmt->execute([$specialId]);
    return $stmt->fetch();
}

// Get pending applications
function getPendingApplications($limit = 50) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT ua.*, c.name as city_name, ma.name as metro_area_name 
        FROM user_applications ua 
        LEFT JOIN cities c ON ua.city_id = c.id 
        LEFT JOIN metro_areas ma ON ua.metro_area_id = ma.id 
        WHERE ua.status = 'pending' 
        ORDER BY ua.created_at ASC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// Approve user application
function approveUserApplication($applicationId, $adminId) {
    $pdo = getDBConnection();
    
    try {
        $pdo->beginTransaction();
        
        // Get application details
        $stmt = $pdo->prepare("
            SELECT ua.*, c.name as city_name 
            FROM user_applications ua 
            LEFT JOIN cities c ON ua.city_id = c.id 
            WHERE ua.id = ?
        ");
        $stmt->execute([$applicationId]);
        $application = $stmt->fetch();
        
        if (!$application) {
            throw new Exception("Application not found");
        }
        
        // Generate special login ID
        $specialId = generateSpecialLoginID($application['city_name']);
        
        // Ensure uniqueness
        while (!isSpecialIDUnique($specialId)) {
            $specialId = generateSpecialLoginID($application['city_name']);
        }
        
        // Create user account
        $stmt = $pdo->prepare("
            INSERT INTO users (
                special_login_id, email, password_hash, full_name, phone, 
                city_id, metro_area_id, address_detail, occupation, 
                document_type, proof_document_path, is_verified, 
                approved_at, approved_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?)
        ");
        $stmt->execute([
            $specialId, $application['email'], $application['password_hash'],
            $application['full_name'], $application['phone'], $application['city_id'],
            $application['metro_area_id'], $application['address_detail'],
            $application['occupation'], $application['document_type'],
            $application['proof_document_path'], $adminId
        ]);
        
        // Update application status
        $stmt = $pdo->prepare("
            UPDATE user_applications 
            SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? 
            WHERE id = ?
        ");
        $stmt->execute([$adminId, $applicationId]);
        
        $pdo->commit();
        
        // Send approval email with special ID
        require_once __DIR__ . '/email_functions.php';
        sendSpecialIDEmail(
            $application['email'], 
            $application['full_name'], 
            $specialId, 
            $application['city_name']
        );
        
        return $specialId;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Reject user application
function rejectUserApplication($applicationId, $adminId, $reason = '') {
    $pdo = getDBConnection();
    
    try {
        $pdo->beginTransaction();
        
        // Get application details
        $stmt = $pdo->prepare("SELECT * FROM user_applications WHERE id = ?");
        $stmt->execute([$applicationId]);
        $application = $stmt->fetch();
        
        if (!$application) {
            throw new Exception("Application not found");
        }
        
        // Update application status
        $stmt = $pdo->prepare("
            UPDATE user_applications 
            SET status = 'rejected', admin_notes = ?, reviewed_at = NOW(), reviewed_by = ? 
            WHERE id = ?
        ");
        $stmt->execute([$reason, $adminId, $applicationId]);
        
        $pdo->commit();
        
        // Send rejection email
        require_once __DIR__ . '/email_functions.php';
        sendRejectionEmail($application['email'], $application['full_name'], $reason);
        
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Redirect with message
function redirect($url, $message = '', $type = 'info') {
    if ($message) {
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $type;
    }
    header("Location: $url");
    exit();
}

// Display message
function displayMessage() {
    startSession();
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message_type'] ?? 'info';
        $message = $_SESSION['message'];
        unset($_SESSION['message'], $_SESSION['message_type']);
        
        $alertClass = match($type) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info'
        };
        
        return "<div class='alert $alertClass alert-dismissible fade show' role='alert'>
                    $message
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
    }
    return '';
}
}
?>
