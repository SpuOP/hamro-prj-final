<?php
require_once '../includes/functions.php';
require_once '../includes/email_functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('../index.html');
}

$errors = [];
$fieldErrors = [];
$success_message = '';

// Get communities for dropdown
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT id, name, district, province FROM communities ORDER BY province, district, name");
$stmt->execute();
$communities = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $community_id = (int)($_POST['community_id'] ?? 0);
    $address_detail = sanitizeInput($_POST['address_detail'] ?? '');
    $occupation = $_POST['occupation'] ?? '';
    $motivation = sanitizeInput($_POST['motivation'] ?? '');
    $document_type = $_POST['document_type'] ?? '';
    $proof_document = $_FILES['proof_document'] ?? null;
    
    // Validation
    if (empty($full_name) || strlen($full_name) < 3) {
        $fieldErrors['full_name'] = "Full name is required (min 3 characters)";
        $errors[] = $fieldErrors['full_name'];
    }
    
    if (empty($email)) {
        $fieldErrors['email'] = "Email is required";
        $errors[] = $fieldErrors['email'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        $fieldErrors['email'] = "Please enter a valid email address";
        $errors[] = $fieldErrors['email'];
    }
    
    if (empty($phone) || !preg_match('/^[0-9+\-\s()]{10,15}$/', $phone)) {
        $fieldErrors['phone'] = "Valid phone number is required";
        $errors[] = $fieldErrors['phone'];
    }
    
    if (empty($password) || strlen($password) < 8) {
        $fieldErrors['password'] = "Password must be at least 8 characters long";
        $errors[] = $fieldErrors['password'];
    }
    
    if ($password !== $confirm_password) {
        $fieldErrors['confirm_password'] = "Passwords do not match";
        $errors[] = $fieldErrors['confirm_password'];
    }
    
    if ($community_id <= 0) {
        $fieldErrors['community_id'] = "Please select your community";
        $errors[] = $fieldErrors['community_id'];
    }
    
    if (empty($address_detail) || strlen($address_detail) < 10) {
        $fieldErrors['address_detail'] = "Complete address is required (min 10 characters)";
        $errors[] = $fieldErrors['address_detail'];
    }
    
    if (empty($occupation)) {
        $fieldErrors['occupation'] = "Please select your occupation";
        $errors[] = $fieldErrors['occupation'];
    }
    
    if (empty($motivation) || strlen($motivation) < 20) {
        $fieldErrors['motivation'] = "Please explain why you want to join (min 20 characters)";
        $errors[] = $fieldErrors['motivation'];
    }
    
    if (empty($document_type)) {
        $fieldErrors['document_type'] = "Please select a document type";
        $errors[] = $fieldErrors['document_type'];
    }
    
    // Validate proof document upload
    if (!$proof_document || $proof_document['error'] !== UPLOAD_ERR_OK) {
        $fieldErrors['proof_document'] = "Please upload a proof of residence document";
        $errors[] = $fieldErrors['proof_document'];
    } else {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($proof_document['type'], $allowed_types)) {
            $fieldErrors['proof_document'] = "Only JPEG, PNG, or PDF files are allowed";
            $errors[] = $fieldErrors['proof_document'];
        } elseif ($proof_document['size'] > $max_size) {
            $fieldErrors['proof_document'] = "File size must be less than 5MB";
            $errors[] = $fieldErrors['proof_document'];
        }
    }
    
    // Check if email already exists in applications or users
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM user_applications WHERE email = ? UNION SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email, $email]);
        
        if ($stmt->fetch()) {
            $fieldErrors['email'] = "This email is already in use";
            $errors[] = $fieldErrors['email'];
        }
    }
    
    // Process application if no errors
    if (empty($errors)) {
        // Upload proof document
        $upload_dir = __DIR__ . '/../uploads/proof_documents/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($proof_document['name'], PATHINFO_EXTENSION);
        $filename = 'proof_' . uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $filename;
        
        if (move_uploaded_file($proof_document['tmp_name'], $file_path)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO user_applications 
                (full_name, email, phone, password_hash, community_id, address_detail, 
                 proof_document_path, document_type, occupation, motivation) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([
                $full_name, $email, $phone, $hashedPassword, $community_id, 
                $address_detail, 'uploads/proof_documents/' . $filename, 
                $document_type, $occupation, $motivation
            ])) {
                // Send confirmation email
                sendApplicationConfirmationEmail($email, $full_name);
                
                $success_message = "Application submitted. We'll email you after review.";
            } else {
                $errors[] = "Failed to submit application. Please try again.";
                unlink($file_path); // Remove uploaded file on database error
            }
        } else {
            $errors[] = "Failed to upload document. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Register - CivicPulse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-12 col-xxl-10">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <i class="fas fa-vote-yea fa-3x text-primary"></i>
                            <h2 class="mt-3">Apply to CivicPulse</h2>
                            <p class="text-muted">Join our verified civic engagement community</p>
                        </div>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-minimal">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Please fix the highlighted fields and try again.
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-minimal">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <!-- Personal Information -->
                            <div class="mb-4">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-user me-2"></i>Personal
                                </h5>
                                
                            <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               placeholder="John Doe"
                                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   placeholder="your.email@example.com"
                                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone *</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   placeholder="98XXXXXXXX"
                                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="occupation" class="form-label">Occupation *</label>
                                    <select class="form-select" id="occupation" name="occupation" required>
                                        <option value="">Select your occupation</option>
                                        <option value="citizen" <?php echo ($_POST['occupation'] ?? '') === 'citizen' ? 'selected' : ''; ?>>Citizen</option>
                                        <option value="community_leader" <?php echo ($_POST['occupation'] ?? '') === 'community_leader' ? 'selected' : ''; ?>>Community Leader</option>
                                        <option value="local_official" <?php echo ($_POST['occupation'] ?? '') === 'local_official' ? 'selected' : ''; ?>>Local Official</option>
                                        <option value="civic_volunteer" <?php echo ($_POST['occupation'] ?? '') === 'civic_volunteer' ? 'selected' : ''; ?>>Civic Volunteer</option>
                                        <option value="community_member" <?php echo ($_POST['occupation'] ?? '') === 'community_member' ? 'selected' : ''; ?>>Community Member</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Location Information -->
                            <div class="mb-4">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-map-marker-alt me-2"></i>Location Information
                                </h5>
                                
                                <div class="mb-3">
                                    <label for="community_id" class="form-label">Your Community/City *</label>
                                    <select class="form-select" id="community_id" name="community_id" required>
                                        <option value="">Select your community</option>
                                        <?php 
                                        $current_province = '';
                                        foreach ($communities as $community): 
                                            if ($current_province !== $community['province']):
                                                if ($current_province !== '') echo '</optgroup>';
                                                $current_province = $community['province'];
                                                echo '<optgroup label="' . htmlspecialchars($current_province) . '">';
                                            endif;
                                        ?>
                                            <option value="<?php echo $community['id']; ?>" 
                                                    <?php echo ($_POST['community_id'] ?? '') == $community['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($community['name'] . ', ' . $community['district']); ?>
                                            </option>
                                        <?php 
                                        endforeach; 
                                        if ($current_province !== '') echo '</optgroup>';
                                        ?>
                                    </select>
                                    <small class="form-text text-muted">Please select only your actual place of residence</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address_detail" class="form-label">Detailed Address *</label>
                                    <textarea class="form-control" id="address_detail" name="address_detail" rows="3" 
                                              placeholder="Ward number, neighborhood, nearby landmarks" required><?php echo htmlspecialchars($_POST['address_detail'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Document Verification -->
                            <div class="mb-4">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-file-upload me-2"></i>Document Verification
                                </h5>
                                
                                <div class="mb-3">
                                    <label for="document_type" class="form-label">Document Type *</label>
                                    <select class="form-select" id="document_type" name="document_type" required>
                                        <option value="">Select document type</option>
                                        <option value="citizenship" <?php echo ($_POST['document_type'] ?? '') === 'citizenship' ? 'selected' : ''; ?>>Citizenship Certificate</option>
                                        <option value="utility_bill" <?php echo ($_POST['document_type'] ?? '') === 'utility_bill' ? 'selected' : ''; ?>>Utility Bill</option>
                                        <option value="rental_agreement" <?php echo ($_POST['document_type'] ?? '') === 'rental_agreement' ? 'selected' : ''; ?>>Rental Agreement</option>
                                        <option value="bank_statement" <?php echo ($_POST['document_type'] ?? '') === 'bank_statement' ? 'selected' : ''; ?>>Bank Statement</option>
                                    </select>
                            </div>
                            
                            <div class="mb-3">
                                    <label for="proof_document" class="form-label">Proof of Residence *</label>
                                    <input type="file" class="form-control" id="proof_document" name="proof_document" 
                                           accept=".jpg,.jpeg,.png,.pdf" required>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        JPEG, PNG, or PDF (under 5MB). Clear photo/scan required
                                    </small>
                                </div>
                            </div>
                            
                            <!-- Application Details -->
                            <div class="mb-4">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-edit me-2"></i>Application Details
                                </h5>
                            
                            <div class="mb-3">
                                    <label for="motivation" class="form-label">Why do you want to join? *</label>
                                    <textarea class="form-control" id="motivation" name="motivation" rows="4" 
                                              placeholder="Briefly explain your motivation" required><?php echo htmlspecialchars($_POST['motivation'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Password Section -->
                            <div class="mb-4">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-lock me-2"></i>Password
                                </h5>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label">Password *</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   placeholder="At least 8 characters" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                                   placeholder="Re-enter password" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Terms and Submit -->
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label" for="terms">I confirm the information is accurate and I accept the terms.</label>
                                </div>
                            </div>
                            
                            <?php if (empty($success_message)): ?>
                                <button type="submit" class="btn btn-primary w-100 btn-lg mb-3">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Application
                            </button>
                            <?php else: ?>
                                <div class="text-center">
                                    <a href="../index.html" class="btn btn-success btn-lg">
                                        <i class="fas fa-home me-2"></i>Go to Home
                                    </a>
                                </div>
                            <?php endif; ?>
                        </form>
                        
                        <div class="text-center">
                            <p class="mb-0">Already have an account?
                                <a href="login.php" class="text-decoration-none text-primary fw-bold">Sign In</a>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="../index.html" class="text-decoration-none text-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
