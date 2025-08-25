<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';
require_once '../../includes/email_functions.php';

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
    
    // Validate required fields
    $required_fields = [
        'first_name', 'last_name', 'date_of_birth', 'gender', 'email', 'password',
        'current_address', 'city_id', 'residence_duration', 'document_type',
        'security_question_1', 'security_answer_1', 'security_question_2', 'security_answer_2'
    ];
    
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    // Validate email format
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Validate age (must be 18+)
    $birthDate = new DateTime($input['date_of_birth']);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    if ($age < 18) {
        throw new Exception('You must be at least 18 years old to register');
    }
    
    // Validate password strength
    if (strlen($input['password']) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }
    
    // Sanitize inputs
    $first_name = htmlspecialchars(trim($input['first_name']), ENT_QUOTES, 'UTF-8');
    $last_name = htmlspecialchars(trim($input['last_name']), ENT_QUOTES, 'UTF-8');
    $middle_name = isset($input['middle_name']) ? htmlspecialchars(trim($input['middle_name']), ENT_QUOTES, 'UTF-8') : '';
    $date_of_birth = $input['date_of_birth'];
    $gender = $input['gender'];
    $phone_country_code = isset($input['phone_country_code']) ? $input['phone_country_code'] : '+977';
    $phone = isset($input['phone']) ? htmlspecialchars(trim($input['phone']), ENT_QUOTES, 'UTF-8') : '';
    $email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
    $alternative_email = isset($input['alternative_email']) ? filter_var(trim($input['alternative_email']), FILTER_SANITIZE_EMAIL) : '';
    $password = $input['password'];
    
    // Address & Location
    $current_address = htmlspecialchars(trim($input['current_address']), ENT_QUOTES, 'UTF-8');
    $ward_area = isset($input['ward_area']) ? htmlspecialchars(trim($input['ward_area']), ENT_QUOTES, 'UTF-8') : '';
    $city_id = (int)$input['city_id'];
    $district = isset($input['district']) ? htmlspecialchars(trim($input['district']), ENT_QUOTES, 'UTF-8') : '';
    $metro_area_id = isset($input['metro_area_id']) ? $input['metro_area_id'] : null;
    $postal_code = isset($input['postal_code']) ? htmlspecialchars(trim($input['postal_code']), ENT_QUOTES, 'UTF-8') : '';
    $residence_duration = $input['residence_duration'];
    
    // Identity Verification
    $document_type = $input['document_type'];
    $document_number = isset($input['document_number']) ? htmlspecialchars(trim($input['document_number']), ENT_QUOTES, 'UTF-8') : '';
    $document_expiry = isset($input['document_expiry']) ? $input['document_expiry'] : null;
    
    // Additional Information
    $occupation = isset($input['occupation']) ? htmlspecialchars(trim($input['occupation']), ENT_QUOTES, 'UTF-8') : '';
    $education_level = isset($input['education_level']) ? $input['education_level'] : '';
    $interested_categories = isset($input['interested_categories']) ? json_encode($input['interested_categories']) : '[]';
    $referral_source = isset($input['referral_source']) ? htmlspecialchars(trim($input['referral_source']), ENT_QUOTES, 'UTF-8') : '';
    
    // Account Security
    $security_question_1 = htmlspecialchars(trim($input['security_question_1']), ENT_QUOTES, 'UTF-8');
    $security_answer_1 = htmlspecialchars(trim($input['security_answer_1']), ENT_QUOTES, 'UTF-8');
    $security_question_2 = htmlspecialchars(trim($input['security_question_2']), ENT_QUOTES, 'UTF-8');
    $security_answer_2 = htmlspecialchars(trim($input['security_answer_2']), ENT_QUOTES, 'UTF-8');
    $newsletter_subscription = isset($input['newsletter_subscription']) ? (bool)$input['newsletter_subscription'] : false;
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Calculate profile completion percentage
    $profile_completion_percentage = calculateProfileCompletion($input);
    
    // Connect to database
    $pdo = getDatabaseConnection();
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM user_applications WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('An application with this email already exists');
    }
    
    // Check if email exists in approved users
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('A user with this email already exists');
    }
    
    // Insert into user_applications table
    $stmt = $pdo->prepare("
        INSERT INTO user_applications (
            first_name, last_name, middle_name, date_of_birth, gender, phone_country_code, phone,
            email, alternative_email, password_hash, current_address, ward_area, city_id, district,
            metro_area_id, postal_code, residence_duration, document_type, document_number,
            document_expiry, occupation, education_level, interested_categories, referral_source,
            security_question_1, security_answer_1, security_question_2, security_answer_2,
            newsletter_subscription, profile_completion_percentage, status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending'
        )
    ");
    
    $stmt->execute([
        $first_name, $last_name, $middle_name, $date_of_birth, $gender, $phone_country_code, $phone,
        $email, $alternative_email, $password_hash, $current_address, $ward_area, $city_id, $district,
        $metro_area_id, $postal_code, $residence_duration, $document_type, $document_number,
        $document_expiry, $occupation, $education_level, $interested_categories, $referral_source,
        $security_question_1, $security_answer_1, $security_question_2, $security_answer_2,
        $newsletter_subscription, $profile_completion_percentage
    ]);
    
    $application_id = $pdo->lastInsertId();
    
    // Send confirmation email to user
    $user_email_subject = "Registration Submitted - CivicPulse Community Platform";
    $user_email_body = "
        Dear $first_name $last_name,
        
        Thank you for registering with CivicPulse! Your application has been successfully submitted and is now under review.
        
        Application Details:
        - Application ID: #$application_id
        - Email: $email
        - Submitted: " . date('Y-m-d H:i:s') . "
        
        What happens next:
        1. Our team will review your application and verify your documents
        2. You'll receive an email notification once your application is reviewed
        3. If approved, you'll receive your special login ID to access the platform
        4. You can then log in and start participating in community voting and issue reporting
        
        Please note that the review process typically takes 2-3 business days.
        
        If you have any questions, please contact us at support@civicpulse.com
        
        Best regards,
        The CivicPulse Team
    ";
    
    $user_email_sent = sendEmail($email, $user_email_subject, $user_email_body);
    
    // Send notification email to admin
    $admin_email = 'admin@civicpulse.com'; // Change this to your admin email
    $admin_email_subject = "New User Registration - Application #$application_id";
    $admin_email_body = "
        A new user registration has been submitted:
        
        Application ID: #$application_id
        Name: $first_name $last_name
        Email: $email
        Phone: $phone_country_code $phone
        City: City ID $city_id
        Document Type: $document_type
        Submitted: " . date('Y-m-d H:i:s') . "
        
        Profile Completion: $profile_completion_percentage%
        
        To review this application, please log in to your admin dashboard.
        
        Best regards,
        CivicPulse System
    ";
    
    $admin_email_sent = sendEmail($admin_email, $admin_email_subject, $admin_email_body);
    
    // Log email attempts
    logEmailAttempt($pdo, $email, $user_email_subject, $user_email_body, 'registration', $user_email_sent);
    logEmailAttempt($pdo, $admin_email, $admin_email_subject, $admin_email_body, 'registration', $admin_email_sent);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Registration submitted successfully! Your application is under review.',
        'application_id' => $application_id,
        'profile_completion' => $profile_completion_percentage,
        'user_email_sent' => $user_email_sent,
        'admin_notification_sent' => $admin_email_sent
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
    error_log("Enhanced registration database error: " . $e->getMessage());
}

// Helper function to calculate profile completion percentage
function calculateProfileCompletion($input) {
    $total_fields = 25; // Total number of fields in the form
    $filled_fields = 0;
    
    $fields_to_check = [
        'first_name', 'last_name', 'middle_name', 'date_of_birth', 'gender',
        'phone_country_code', 'phone', 'email', 'alternative_email',
        'current_address', 'ward_area', 'city_id', 'district', 'metro_area_id',
        'postal_code', 'residence_duration', 'document_type', 'document_number',
        'document_expiry', 'occupation', 'education_level', 'interested_categories',
        'referral_source', 'security_question_1', 'security_answer_1',
        'security_question_2', 'security_answer_2'
    ];
    
    foreach ($fields_to_check as $field) {
        if (isset($input[$field]) && !empty($input[$field])) {
            $filled_fields++;
        }
    }
    
    return round(($filled_fields / $total_fields) * 100);
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
