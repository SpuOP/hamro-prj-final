<?php
/**
 * Pending Users Management API
 * Handles CRUD operations for pending applications
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Start session for CSRF protection
session_start();

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $pdo = getDatabaseConnection();
    
    // GET request - retrieve applications
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'list';
        
        if ($action === 'list') {
            handleListApplications($pdo);
        } elseif ($action === 'get') {
            handleGetApplication($pdo);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    }
    
    // POST request - approve/reject applications
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        if ($action === 'approve') {
            handleApproveApplication($pdo, $input);
        } elseif ($action === 'reject') {
            handleRejectApplication($pdo, $input);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    }
    
    else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log("Pending Users API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

/**
 * Handle listing applications with filters and pagination
 */
function handleListApplications($pdo) {
    // Get query parameters
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(10, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause based on filters
    $whereConditions = [];
    $params = [];
    
    // Status filter
    if (!empty($_GET['status'])) {
        $whereConditions[] = "pa.status = ?";
        $params[] = $_GET['status'];
    }
    
    // City filter
    if (!empty($_GET['city'])) {
        $whereConditions[] = "pa.city = ?";
        $params[] = $_GET['city'];
    }
    
    // Date range filter
    if (!empty($_GET['dateRange'])) {
        $dateCondition = getDateRangeCondition($_GET['dateRange']);
        if ($dateCondition) {
            $whereConditions[] = $dateCondition;
        }
    }
    
    // Search filter
    if (!empty($_GET['search'])) {
        $searchTerm = '%' . $_GET['search'] . '%';
        $whereConditions[] = "(pa.first_name LIKE ? OR pa.last_name LIKE ? OR pa.email LIKE ?)";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Count total applications
    $countSql = "SELECT COUNT(*) FROM pending_applications pa $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalApplications = $countStmt->fetchColumn();
    
    // Calculate total pages
    $totalPages = ceil($totalApplications / $limit);
    
    // Get applications
    $sql = "
        SELECT 
            pa.id,
            pa.application_id,
            pa.first_name,
            pa.last_name,
            pa.email,
            pa.phone,
            pa.city,
            pa.address,
            pa.document_path,
            pa.terms_accepted,
            pa.community_guidelines,
            pa.status,
            pa.admin_notes,
            pa.created_at,
            pa.reviewed_at,
            pa.reviewed_by,
            pa.special_login_id
        FROM pending_applications pa
        $whereClause
        ORDER BY pa.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $allParams = array_merge($params, [$limit, $offset]);
    $stmt->execute($allParams);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format applications for response
    $formattedApplications = array_map(function($app) {
        return [
            'id' => $app['id'],
            'application_id' => $app['application_id'],
            'first_name' => $app['first_name'],
            'last_name' => $app['last_name'],
            'email' => $app['email'],
            'phone' => $app['phone'],
            'city' => $app['city'],
            'address' => $app['address'],
            'document_path' => $app['document_path'],
            'terms_accepted' => (bool)$app['terms_accepted'],
            'community_guidelines' => (bool)$app['community_guidelines'],
            'status' => $app['status'],
            'admin_notes' => $app['admin_notes'],
            'created_at' => $app['created_at'],
            'reviewed_at' => $app['reviewed_at'],
            'reviewed_by' => $app['reviewed_by'],
            'special_login_id' => $app['special_login_id']
        ];
    }, $applications);
    
    echo json_encode([
        'success' => true,
        'applications' => $formattedApplications,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_applications' => $totalApplications,
            'limit' => $limit
        ]
    ]);
}

/**
 * Handle getting a single application
 */
function handleGetApplication($pdo) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID is required']);
        return;
    }
    
    $sql = "
        SELECT 
            pa.id,
            pa.application_id,
            pa.first_name,
            pa.last_name,
            pa.email,
            pa.phone,
            pa.city,
            pa.address,
            pa.document_path,
            pa.terms_accepted,
            pa.community_guidelines,
            pa.status,
            pa.admin_notes,
            pa.created_at,
            pa.reviewed_at,
            pa.reviewed_by,
            pa.special_login_id
        FROM pending_applications pa
        WHERE pa.id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Application not found']);
        return;
    }
    
    // Format application for response
    $formattedApplication = [
        'id' => $application['id'],
        'application_id' => $application['application_id'],
        'first_name' => $application['first_name'],
        'last_name' => $application['last_name'],
        'email' => $application['email'],
        'phone' => $application['phone'],
        'city' => $application['city'],
        'address' => $application['address'],
        'document_path' => $application['document_path'],
        'terms_accepted' => (bool)$application['terms_accepted'],
        'community_guidelines' => (bool)$application['community_guidelines'],
        'status' => $application['status'],
        'admin_notes' => $application['admin_notes'],
        'created_at' => $application['created_at'],
        'reviewed_at' => $application['reviewed_at'],
        'reviewed_by' => $application['reviewed_by'],
        'special_login_id' => $application['special_login_id']
    ];
    
    echo json_encode([
        'success' => true,
        'application' => $formattedApplication
    ]);
}

/**
 * Handle approving an application
 */
function handleApproveApplication($pdo, $input) {
    $applicationId = $input['application_id'] ?? null;
    
    if (!$applicationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID is required']);
        return;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Get application details
        $stmt = $pdo->prepare("SELECT * FROM pending_applications WHERE id = ? AND status = 'pending'");
        $stmt->execute([$applicationId]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            throw new Exception('Application not found or already processed');
        }
        
        // Generate unique Special Login ID
        $specialLoginId = generateSpecialLoginId($pdo, $application['city']);
        
        // Update application status
        $updateStmt = $pdo->prepare("
            UPDATE pending_applications 
            SET status = 'approved', 
                reviewed_at = NOW(), 
                reviewed_by = 'admin',
                special_login_id = ?,
                admin_notes = 'Application approved by admin'
            WHERE id = ?
        ");
        $updateStmt->execute([$specialLoginId, $applicationId]);
        
        // Create user account
        $userStmt = $pdo->prepare("
            INSERT INTO users (
                email, 
                first_name, 
                last_name, 
                phone, 
                city, 
                address, 
                special_login_id, 
                status, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $userStmt->execute([
            $application['email'],
            $application['first_name'],
            $application['last_name'],
            $application['phone'],
            $application['city'],
            $application['address'],
            $specialLoginId
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Send approval email
        sendApprovalEmail($application, $specialLoginId);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Application approved successfully',
            'special_login_id' => $specialLoginId,
            'user_id' => $userId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Handle rejecting an application
 */
function handleRejectApplication($pdo, $input) {
    $applicationId = $input['application_id'] ?? null;
    $reason = $input['reason'] ?? '';
    
    if (!$applicationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID is required']);
        return;
    }
    
    if (empty($reason)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Rejection reason is required']);
        return;
    }
    
    try {
        // Update application status
        $stmt = $pdo->prepare("
            UPDATE pending_applications 
            SET status = 'rejected', 
                reviewed_at = NOW(), 
                reviewed_by = 'admin',
                admin_notes = ?
            WHERE id = ? AND status = 'pending'
        ");
        $result = $stmt->execute([$reason, $applicationId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Application not found or already processed');
        }
        
        // Get application details for email
        $appStmt = $pdo->prepare("SELECT * FROM pending_applications WHERE id = ?");
        $appStmt->execute([$applicationId]);
        $application = $appStmt->fetch(PDO::FETCH_ASSOC);
        
        // Send rejection email
        sendRejectionEmail($application, $reason);
        
        echo json_encode([
            'success' => true,
            'message' => 'Application rejected successfully'
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Generate unique Special Login ID
 */
function generateSpecialLoginId($pdo, $city) {
    $cityCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $city), 0, 4));
    if (strlen($cityCode) < 4) {
        $cityCode = str_pad($cityCode, 4, 'X');
    }
    
    do {
        $randomPart = strtoupper(substr(md5(uniqid()), 0, 4));
        $specialLoginId = "CIP-{$cityCode}-{$randomPart}";
        
        // Check if ID already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE special_login_id = ?");
        $stmt->execute([$specialLoginId]);
        $exists = $stmt->fetchColumn() > 0;
        
    } while ($exists);
    
    return $specialLoginId;
}

/**
 * Get date range condition for SQL
 */
function getDateRangeCondition($dateRange) {
    $today = date('Y-m-d');
    
    switch ($dateRange) {
        case 'today':
            return "DATE(pa.created_at) = '$today'";
        case 'week':
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            return "DATE(pa.created_at) >= '$weekStart'";
        case 'month':
            $monthStart = date('Y-m-01');
            return "DATE(pa.created_at) >= '$monthStart'";
        default:
            return null;
    }
}

/**
 * Send approval email
 */
function sendApprovalEmail($application, $specialLoginId) {
    $to = $application['email'];
    $subject = "Your CivicPulse Application Has Been Approved!";
    
    $message = "
    <html>
    <head>
        <title>Application Approved</title>
    </head>
    <body>
        <h2>Congratulations! Your application has been approved.</h2>
        <p>Dear {$application['first_name']} {$application['last_name']},</p>
        <p>We're excited to welcome you to the CivicPulse community! Your application has been reviewed and approved.</p>
        
        <h3>Your Special Login ID: <strong>{$specialLoginId}</strong></h3>
        <p>Please use this ID to log in to your account at: <a href='https://civicpulse.com/auth/login.html'>https://civicpulse.com/auth/login.html</a></p>
        
        <h3>Next Steps:</h3>
        <ol>
            <li>Visit the login page</li>
            <li>Enter your Special Login ID: {$specialLoginId}</li>
            <li>Set up your password</li>
            <li>Start engaging with your community!</li>
        </ol>
        
        <p>If you have any questions, please don't hesitate to contact our support team.</p>
        
        <p>Welcome to CivicPulse!</p>
        <p>The CivicPulse Team</p>
    </body>
    </html>
    ";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: CivicPulse <noreply@civicpulse.com>',
        'Reply-To: support@civicpulse.com'
    ];
    
    mail($to, $subject, $message, implode("\r\n", $headers));
}

/**
 * Send rejection email
 */
function sendRejectionEmail($application, $reason) {
    $to = $application['email'];
    $subject = "Update on Your CivicPulse Application";
    
    $message = "
    <html>
    <head>
        <title>Application Update</title>
    </head>
    <body>
        <h2>Application Status Update</h2>
        <p>Dear {$application['first_name']} {$application['last_name']},</p>
        <p>Thank you for your interest in joining CivicPulse. After careful review, we regret to inform you that your application has not been approved at this time.</p>
        
        <h3>Reason for Rejection:</h3>
        <p>{$reason}</p>
        
        <h3>What This Means:</h3>
        <ul>
            <li>Your application will not be processed further</li>
            <li>You may reapply in the future if circumstances change</li>
            <li>Your personal information will be handled according to our privacy policy</li>
        </ul>
        
        <h3>Next Steps:</h3>
        <p>If you believe this decision was made in error, or if you would like to discuss your application further, please contact our support team.</p>
        
        <p>We appreciate your understanding and hope to see you apply again in the future.</p>
        
        <p>Best regards,<br>The CivicPulse Team</p>
    </body>
    </html>
    ";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: CivicPulse <noreply@civicpulse.com>',
        'Reply-To: support@civicpulse.com'
    ];
    
    mail($to, $subject, $message, implode("\r\n", $headers));
}
?>
