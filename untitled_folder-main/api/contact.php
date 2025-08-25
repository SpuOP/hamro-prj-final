<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../includes/email_functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    // CSRF validation (if provided)
    if (isset($input['csrf_token'])) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['error' => true, 'message' => 'CSRF token validation failed']);
            exit;
        }
    }
    
    // Validate required fields
    $required_fields = ['name', 'email', 'subject', 'message'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    // Validate email format
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Sanitize inputs
    $name = htmlspecialchars(trim($input['name']), ENT_QUOTES, 'UTF-8');
    $email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
    $subject = htmlspecialchars(trim($input['subject']), ENT_QUOTES, 'UTF-8');
    $issue_category = isset($input['issue_category']) ? htmlspecialchars(trim($input['issue_category']), ENT_QUOTES, 'UTF-8') : '';
    $message = htmlspecialchars(trim($input['message']), ENT_QUOTES, 'UTF-8');
    
    // Additional validation
    if (strlen($name) < 2 || strlen($name) > 100) {
        throw new Exception('Name must be between 2 and 100 characters');
    }
    
    if (strlen($message) < 10 || strlen($message) > 2000) {
        throw new Exception('Message must be between 10 and 2000 characters');
    }
    
    // Get client information
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // Connect to database
    $pdo = getDatabaseConnection();
    
    // Insert contact message into database
    $stmt = $pdo->prepare("
        INSERT INTO contact_messages (name, email, subject, issue_category, message, ip_address, user_agent, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'new')
    ");
    
    $stmt->execute([$name, $email, $subject, $issue_category, $message, $ip_address, $user_agent]);
    
    $message_id = $pdo->lastInsertId();
    
    // Send email notification to admin
    $admin_email = 'admin@civicpulse.com'; // Change this to your admin email
    
    $email_subject = "New Contact Form Submission: $subject";
    $email_body = "
        A new contact form submission has been received:
        
        Name: $name
        Email: $email
        Subject: $subject
        Issue Category: " . ($issue_category ?: 'Not specified') . "
        
        Message:
        $message
        
        Submitted from IP: $ip_address
        User Agent: $user_agent
        
        To view this message in the admin panel, please log in to your dashboard.
        
        Best regards,
        CivicPulse System
    ";
    
    // Send email
    $email_sent = sendEmail($admin_email, $email_subject, $email_body);
    
    // Log email attempt
    $email_log_stmt = $pdo->prepare("
        INSERT INTO email_logs (recipient_email, subject, message_body, email_type, status, sent_at)
        VALUES (?, ?, ?, 'contact_form', ?, ?)
    ");
    
    $email_log_stmt->execute([
        $admin_email,
        $email_subject,
        $email_body,
        $email_sent ? 'sent' : 'failed',
        $email_sent ? date('Y-m-d H:i:s') : null
    ]);
    
    // Send confirmation email to user
    $user_email_subject = "Thank you for contacting CivicPulse";
    $user_email_body = "
        Dear $name,
        
        Thank you for contacting CivicPulse. We have received your message and will get back to you within 24 hours.
        
        Your message details:
        Subject: $subject
        Issue Category: " . ($issue_category ?: 'Not specified') . "
        
        We appreciate your engagement with our community platform.
        
        Best regards,
        The CivicPulse Team
    ";
    
    $user_email_sent = sendEmail($email, $user_email_subject, $user_email_body);
    
    // Log user email attempt
    $email_log_stmt->execute([
        $email,
        $user_email_subject,
        $user_email_body,
        'contact_form',
        $user_email_sent ? 'sent' : 'failed',
        $user_email_sent ? date('Y-m-d H:i:s') : null
    ]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Your message has been sent successfully. We will get back to you within 24 hours.',
        'message_id' => $message_id,
        'email_sent' => $email_sent,
        'confirmation_sent' => $user_email_sent
    ]);
    
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
    
    // Log the error for debugging
    error_log("Contact form database error: " . $e->getMessage());
}
?>
