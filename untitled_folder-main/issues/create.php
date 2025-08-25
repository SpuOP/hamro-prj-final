<?php
require_once '../includes/functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php', 'Please log in to post issues.', 'warning');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $image = $_FILES['image'] ?? null;
    
    // Validation
    if (empty($title) || strlen($title) < 5) {
        $errors[] = "Title must be at least 5 characters long";
    }
    
    if (empty($description) || strlen($description) < 20) {
        $errors[] = "Description must be at least 20 characters long";
    }
    
// Image validation
$image_path = null;
if ($image && $image['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($image['type'], $allowed_types)) {
        $errors[] = "Only JPEG, PNG, and GIF images are allowed";
    } elseif ($image['size'] > $max_size) {
        $errors[] = "Image size must be less than 5MB";
    } else {
        // Absolute path to uploads directory
        $upload_dir = __DIR__ . '/../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Generate unique filename
        $extension = pathinfo($image['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $image_path = 'uploads/' . $filename; // Relative path for DB

        if (!move_uploaded_file($image['tmp_name'], $upload_dir . $filename)) {
            $errors[] = "Failed to upload image";
            $image_path = null;
        }
    }
}


    
    // Create issue if no errors
    if (empty($errors)) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO issues (title, description, image_path, created_by) VALUES (?, ?, ?, ?)");
        
        if ($stmt->execute([$title, $description, $image_path, $_SESSION['user_id']])) {
            $issue_id = $pdo->lastInsertId();
            redirect("../issues/view.php?id=$issue_id", 'Issue posted successfully!', 'success');
        } else {
            $errors[] = "Failed to create issue";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Post New Issue - CivicPulse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.html">
                <i class="fas fa-graduation-cap me-2"></i>
                                        CivicPulse
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.html">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="create.php">Post Issue</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" 
                           data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center py-3">
                        <h3 class="mb-0">
                            <i class="fas fa-plus-circle me-2"></i>
                            Post New Educational Issue
                        </h3>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">Issue Title</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       placeholder="e.g., School lacks proper library facilities"
                                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                                <small class="form-text text-muted">Describe the educational issue briefly</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Detailed Description</label>
                                <textarea class="form-control" id="description" name="description" rows="6" 
                                          placeholder="Provide detailed information about the educational issue, its impact, and possible solutions..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                <small class="form-text text-muted">Explain the problem and its impact on education</small>
                            </div>
                            
                            <div class="mb-4">
                                <label for="image" class="form-label">Image (Optional)</label>
                                <input type="file" class="form-control" id="image" name="image" 
                                       accept="image/jpeg,image/png,image/gif">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Post Issue
                                </button>
                                <a href="../index.html" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tips Section -->
                <div class="card mt-4 border-info">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-lightbulb me-2"></i>Tips for Better Issues
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>Be specific about the location and nature of the problem</li>
                            <li>Include relevant details that help others understand the issue</li>
                            <li>Add photos when possible to provide visual context</li>
                            <li>Use clear, concise language</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
