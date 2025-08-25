<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';
require_once '../../includes/email_functions.php';

// Only allow GET, POST, and PUT requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST', 'PUT'])) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Connect to database
    $pdo = getDatabaseConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get user applications or users based on status parameter
        $status = $_GET['status'] ?? 'pending';
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 10);
        $offset = ($page - 1) * $limit;
        
        if ($status === 'pending') {
            // Get pending applications
            $stmt = $pdo->prepare("
                SELECT 
                    ua.*,
                    c.name as city_name,
                    ma.name as metro_area_name
                FROM user_applications ua
                LEFT JOIN cities c ON ua.city_id = c.id
                LEFT JOIN metro_areas ma ON ua.metro_area_id = ma.id
                WHERE ua.status = 'pending'
                ORDER BY ua.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_applications WHERE status = 'pending'");
            $countStmt->execute();
            $total = $countStmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'data' => $applications,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            
        } elseif ($status === 'approved') {
            // Get approved users
            $stmt = $pdo->prepare("
                SELECT 
                    u.*,
                    c.name as city_name,
                    ma.name as metro_area_name
                FROM users u
                LEFT JOIN cities c ON u.city_id = c.id
                LEFT JOIN metro_areas ma ON u.metro_area_id = ma.id
                WHERE u.status = 'approved'
                ORDER BY u.approved_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'approved'");
            $countStmt->execute();
            $total = $countStmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'data' => $users,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            
        } elseif ($status === 'rejected') {
            // Get rejected applications
            $stmt = $pdo->prepare("
                SELECT 
                    ua.*,
                    c.name as city_name,
                    ma.name as metro_area_name
                FROM user_applications ua
                LEFT JOIN cities c ON ua.city_id = c.id
                LEFT JOIN metro_areas ma ON ua.metro_area_id = ma.id
                WHERE ua.status = 'rejected'
                ORDER BY ua.reviewed_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_applications WHERE status = 'rejected'");
            $countStmt->execute();
            $total = $countStmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'data' => $applications,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Approve user application
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['action']) || !isset($input['application_id'])) {
            throw new Exception('Invalid request data');
        }
        
        $action = $input['action'];
        $application_id = (int)$input['application_id'];
        $admin_notes = $input['admin_notes'] ?? '';
        $admin_id = $input['admin_id'] ?? 1; // Default admin ID
        
        if ($action === 'approve') {
            // Get application details
            $stmt = $pdo->prepare("SELECT * FROM user_applications WHERE id = ? AND status = 'pending'");
            $stmt->execute([$application_id]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$application) {
                throw new Exception('Application not found or already processed');
            }
            
            // Generate special login ID
            $special_login_id = generateSpecialLoginId($pdo, $application['city_id']);
            
            // Insert into users table
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    special_login_id, first_name, last_name, middle_name, date_of_birth, gender,
                    phone_country_code, phone, email, alternative_email, password_hash,
                    current_address, ward_area, city_id, district, metro_area_id, postal_code,
                    residence_duration, document_type, document_number, document_expiry,
                    occupation, education_level, interested_categories, referral_source,
                    security_question_1, security_answer_1, security_question_2, security_answer_2,
                    newsletter_subscription, profile_completion_percentage, approved_at, approved_by
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            
            $stmt->execute([
                $special_login_id, $application['first_name'], $application['last_name'], $application['middle_name'],
                $application['date_of_birth'], $application['gender'], $application['phone_country_code'],
                $application['phone'], $application['email'], $application['alternative_email'],
                $application['password_hash'], $application['current_address'], $application['ward_area'],
                $application['city_id'], $application['district'], $application['metro_area_id'],
                $application['postal_code'], $application['residence_duration'], $application['document_type'],
                $application['document_number'], $application['document_expiry'], $application['occupation'],
                $application['education_level'], $application['interested_categories'], $application['referral_source'],
                $application['security_question_1'], $application['security_answer_1'],
                $application['security_question_2'], $application['security_answer_2'],
                $application['newsletter_subscription'], $application['profile_completion_percentage'],
                date('Y-m-d H:i:s'), $admin_id
            ]);
            
            // Update application status
            $stmt = $pdo->prepare("
                UPDATE user_applications 
                SET status = 'approved', reviewed_at = ?, reviewed_by = ?, admin_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([date('Y-m-d H:i:s'), $admin_id, $admin_notes, $application_id]);
            
            // Send approval email with special login ID
            $approval_subject = "Application Approved - Welcome to CivicPulse!";
            $approval_body = "
                Dear {$application['first_name']} {$application['last_name']},
                
                Congratulations! Your CivicPulse application has been approved.
                
                Your Special Login ID: $special_login_id
                
                You can now log in to the platform using your email and password at:
                https://civicpulse.org/auth/login.html
                
                Important Notes:
                - Keep your Special Login ID safe and confidential
                - Use your email and password to log in
                - The Special Login ID is for reference purposes
                
                Welcome to the CivicPulse community! You can now:
                - Report community issues
                - Vote on local problems
                - Engage in civic discussions
                - Connect with your local government
                
                If you have any questions, please contact us at support@civicpulse.com
                
                Best regards,
                The CivicPulse Team
            ";
            
            $email_sent = sendEmail($application['email'], $approval_subject, $approval_body);
            
            // Log email
            logEmailAttempt($pdo, $application['email'], $approval_subject, $approval_body, 'approval', $email_sent);
            
            echo json_encode([
                'success' => true,
                'message' => 'Application approved successfully',
                'special_login_id' => $special_login_id,
                'email_sent' => $email_sent
            ]);
            
        } elseif ($action === 'reject') {
            // Reject application
            $stmt = $pdo->prepare("
                UPDATE user_applications 
                SET status = 'rejected', reviewed_at = ?, reviewed_by = ?, admin_notes = ?
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([date('Y-m-d H:i:s'), $admin_id, $admin_notes, $application_id]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Application not found or already processed');
            }
            
            // Get application details for email
            $stmt = $pdo->prepare("SELECT * FROM user_applications WHERE id = ?");
            $stmt->execute([$application_id]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Send rejection email
            $rejection_subject = "Application Status Update - CivicPulse";
            $rejection_body = "
                Dear {$application['first_name']} {$application['last_name']},
                
                Thank you for your interest in joining CivicPulse. After careful review, we regret to inform you that your application could not be approved at this time.
                
                Reason: $admin_notes
                
                You may:
                - Review your application and address any issues mentioned
                - Submit a new application with corrected information
                - Contact us for clarification at support@civicpulse.com
                
                We appreciate your interest in community engagement and hope to see you in the future.
                
                Best regards,
                The CivicPulse Team
            ";
            
            $email_sent = sendEmail($application['email'], $rejection_subject, $rejection_body);
            
            // Log email
            logEmailAttempt($pdo, $application['email'], $rejection_subject, $rejection_body, 'rejection', $email_sent);
            
            echo json_encode([
                'success' => true,
                'message' => 'Application rejected successfully',
                'email_sent' => $email_sent
            ]);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update application notes
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['application_id']) || !isset($input['admin_notes'])) {
            throw new Exception('Invalid request data');
        }
        
        $application_id = (int)$input['application_id'];
        $admin_notes = $input['admin_notes'];
        $admin_id = $input['admin_id'] ?? 1;
        
        $stmt = $pdo->prepare("
            UPDATE user_applications 
            SET admin_notes = ?, reviewed_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$admin_notes, $admin_id, $application_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Notes updated successfully'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    
    error_log("Admin user management database error: " . $e->getMessage());
}

// Helper function to generate special login ID
function generateSpecialLoginId($pdo, $city_id) {
    // Get city code
    $stmt = $pdo->prepare("SELECT UPPER(SUBSTRING(name, 1, 3)) as city_code FROM cities WHERE id = ?");
    $stmt->execute([$city_id]);
    $city = $stmt->fetch(PDO::FETCH_ASSOC);
    $city_code = $city['city_code'] ?? 'CIT';
    
    // Get count of users in this city
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE city_id = ?");
    $stmt->execute([$city_id]);
    $user_count = $stmt->fetchColumn();
    
    // Generate ID: CIP-CITY-XXXX
    $special_id = 'CIP-' . $city_code . '-' . str_pad($user_count + 1, 4, '0', STR_PAD_LEFT);
    
    return $special_id;
}

// Helper function to log email attempts
function logEmailAttempt($pdo, $recipient_email, $subject, $message_body, $email_type, $status) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO email_logs (recipient_email, subject, message_body, email_type, status, sent_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $recipient_email,
            $subject,
            $message_body,
            $email_type,
            $status ? 'sent' : 'failed',
            $status ? date('Y-m-d H:i:s') : null
        ]);
    } catch (Exception $e) {
        error_log("Failed to log email attempt: " . $e->getMessage());
    }
}
?>
