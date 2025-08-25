<?php
require_once '../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('../index.html');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $special_id = sanitizeInput($_POST['special_id'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($special_id) || empty($password)) {
        $errors[] = "Both Special ID and password are required";
    } elseif (!preg_match('/^SM[0-9]{6}[A-Z]{2}$/', $special_id)) {
        $errors[] = "Invalid Special ID format";
    } else {
        // Authenticate user with special ID
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.password, u.full_name, u.is_verified, u.is_admin, c.name as community_name 
            FROM users u 
            LEFT JOIN communities c ON u.community_id = c.id 
            WHERE u.special_id = ? AND u.is_verified = 1
        ");
        $stmt->execute([$special_id]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Login successful
            startSession();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['special_id'] = $special_id;
            $_SESSION['community_name'] = $user['community_name'];
            if (!empty($user['is_admin'])) { $_SESSION['is_admin'] = true; }
            
            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            if (!empty($user['is_admin'])) {
                redirect('../admin/dashboard.php', 'Welcome back, Admin!', 'success');
            }
            redirect('../index.html', 'Welcome back, ' . $user['full_name'] . '!', 'success');
        } else {
            $errors[] = "Invalid Special ID or password";
            
            // Log failed login attempt
            error_log("Failed login attempt with Special ID: $special_id from IP: " . $_SERVER['REMOTE_ADDR']);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Login - CivicPulse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <i class="fas fa-vote-yea fa-3x text-primary"></i>
                            <h2 class="mt-3">Welcome Back</h2>
                            <p class="text-muted">Sign in to your CivicPulse account</p>
                            <p class="small text-muted">Access civic community discussions</p>
                        </div>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="special_id" class="form-label">Special ID</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="text" class="form-control" id="special_id" name="special_id" 
                                           placeholder="SM123456AB" 
                                           value="<?php echo htmlspecialchars($_POST['special_id'] ?? ''); ?>" 
                                           style="text-transform: uppercase;" required>
                                </div>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Use the Special ID sent to your email after verification
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <p class="mb-0">Haven't applied yet?
                                <a href="register.php" class="text-decoration-none text-primary fw-bold">Apply Now</a>
                            </p>
                            <hr class="my-3">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Important:</strong> To login to CivicPulse, your application must first be approved by our team. You will receive your Special ID via email once approved.
                            </div>
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
