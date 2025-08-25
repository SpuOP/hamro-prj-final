<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/functions.php';

// CSRF protection
session_start();
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    
    // Validate required fields
    $required_fields = ['firstName', 'lastName', 'email', 'phone', 'city', 'address', 'termsAccepted', 'communityGuidelines'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Validate email format
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    if ($stmt->fetch()) {
        throw new Exception('Email address already registered');
    }
    
    // Check if email already exists in pending applications
    $stmt = $pdo->prepare("SELECT id FROM pending_applications WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    if ($stmt->fetch()) {
        throw new Exception('Application already submitted with this email');
    }
    
    // Handle file upload
    $document_path = null;
    if (isset($_FILES['idDocument']) && $_FILES['idDocument']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['idDocument'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Invalid file type. Only JPG, PNG, and PDF files are allowed.');
        }
        
        if ($file['size'] > $max_size) {
            throw new Exception('File size too large. Maximum size is 10MB.');
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = '../uploads/proof_documents/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'proof_' . uniqid() . '.' . $file_extension;
        $document_path = 'uploads/proof_documents/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            throw new Exception('Failed to save uploaded file');
        }
    } else {
        throw new Exception('Document upload is required');
    }
    
    // Generate unique application ID
    $application_id = 'CIP-' . strtoupper($_POST['city']) . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
    
    // Insert into pending applications table
    $stmt = $pdo->prepare("
        INSERT INTO pending_applications (
            application_id, first_name, last_name, email, phone, city, 
            address, document_path, terms_accepted, community_guidelines, 
            status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->execute([
        $application_id,
        trim($_POST['firstName']),
        trim($_POST['lastName']),
        trim($_POST['email']),
        trim($_POST['phone']),
        trim($_POST['city']),
        trim($_POST['address']),
        $document_path,
        $_POST['termsAccepted'] === 'on' ? 1 : 0,
        $_POST['communityGuidelines'] === 'on' ? 1 : 0
    ]);
    
    $application_id_db = $pdo->lastInsertId();
    
    // Send email notification to admin
    $admin_email = 'admin@civicpulse.com'; // Configure this
    $subject = "New CivicPulse Application: $application_id";
    $message = "
    New community application submitted:
    
    Application ID: $application_id
    Name: {$_POST['firstName']} {$_POST['lastName']}
    Email: {$_POST['email']}
    City: {$_POST['city']}
    Phone: {$_POST['phone']}
    
    Review application at: " . $_SERVER['HTTP_HOST'] . "/admin/pending-users.php?id=$application_id_db
    
    This is an automated notification from CivicPulse.
    ";
    
    $headers = "From: noreply@civicpulse.com\r\n";
    $headers .= "Reply-To: noreply@civicpulse.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    mail($admin_email, $subject, $message, $headers);
    
    // Send confirmation email to user
    $user_subject = "CivicPulse Application Received - $application_id";
    $user_message = "
    Dear {$_POST['firstName']},
    
    Thank you for submitting your application to join CivicPulse!
    
    Your application details:
    Application ID: $application_id
    Name: {$_POST['firstName']} {$_POST['lastName']}
    Email: {$_POST['email']}
    City: {$_POST['city']}
    
    Status: Under Review
    
    Our community administrators will review your application within 24-48 hours. 
    You will receive another email with your Special Login ID (CIP-ID) once approved.
    
    If you have any questions, please contact our support team.
    
    Best regards,
    The CivicPulse Team
    
    ---
    This is an automated message. Please do not reply to this email.
    ";
    
    mail($_POST['email'], $user_subject, $user_message, $headers);
    
    // Log the application
    error_log("New CivicPulse application submitted: $application_id for {$_POST['email']}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully',
        'application_id' => $application_id,
        'redirect_url' => 'success.html'
    ]);
    
} catch (Exception $e) {
    error_log("CivicPulse registration error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("CivicPulse database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
}
?>
