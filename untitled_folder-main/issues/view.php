<?php
require_once '../includes/functions.php';

$issue_id = $_GET['id'] ?? null;
if (!$issue_id) {
    redirect('../index.html', 'Issue not found.', 'error');
}

$pdo = getDBConnection();

// Get issue details
$stmt = $pdo->prepare("
    SELECT i.*, u.username as author_name 
    FROM issues i 
    LEFT JOIN users u ON i.created_by = u.id 
    WHERE i.id = ?
");
$stmt->execute([$issue_id]);
$issue = $stmt->fetch();

if (!$issue) {
    redirect('../index.html', 'Issue not found.', 'error');
}

// Handle comment submission - only for logged in users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    if (!isLoggedIn()) {
        redirect('../auth/login.php', 'Please login to post comments.', 'warning');
    }
    
    $comment = sanitizeInput($_POST['comment']);
    
    if (empty($comment)) {
        $errors[] = "Comment cannot be empty";
    } elseif (strlen($comment) < 5) {
        $errors[] = "Comment must be at least 5 characters long";
    } else {
        $stmt = $pdo->prepare("INSERT INTO comments (issue_id, user_id, comment) VALUES (?, ?, ?)");
        if ($stmt->execute([$issue_id, $_SESSION['user_id'], $comment])) {
            redirect("view.php?id=$issue_id", 'Comment added successfully!', 'success');
        } else {
            $errors[] = "Failed to add comment. Please try again.";
        }
    }
}

// Get comments
$stmt = $pdo->prepare("
    SELECT c.*, u.username 
    FROM comments c 
    LEFT JOIN users u ON c.user_id = u.id 
    WHERE c.issue_id = ? 
    ORDER BY c.created_at ASC
");
$stmt->execute([$issue_id]);
$comments = $stmt->fetchAll();

// Get current user's vote
$current_vote = null;
if (isLoggedIn()) {
    $current_vote = getUserVote($issue_id, $_SESSION['user_id']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo htmlspecialchars($issue['title']); ?> - CivicPulse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.html">
                                        <i class="fas fa-vote-yea me-2"></i>CivicPulse
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../index.html">Home</a>
                <?php if (isLoggedIn()): ?>
                    <a class="nav-link" href="create.php">Post Issue</a>
                    <a class="nav-link" href="../auth/logout.php">Logout</a>
                <?php else: ?>
                    <a class="nav-link" href="../auth/login.php">Login</a>
                    <a class="nav-link" href="../auth/register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <?php echo displayMessage(); ?>
        
        <!-- Issue Details -->
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h1 class="card-title h2"><?php echo htmlspecialchars($issue['title']); ?></h1>
                            <?php if ($issue['image_path']): ?>
                                <span class="badge bg-info">
                                    <i class="fas fa-image me-1"></i>Has Image
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($issue['image_path']): ?>
                            <div class="text-center mb-4">
                                <img src="../<?php echo htmlspecialchars($issue['image_path']); ?>" 
                                     class="img-fluid rounded" alt="Issue Image" style="max-height: 400px;">
                            </div>
                        <?php endif; ?>
                        
                        <div class="issue-description mb-4">
                            <p class="lead"><?php echo nl2br(htmlspecialchars($issue['description'])); ?></p>
                        </div>
                        
                        <div class="issue-meta d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i>
                                    Posted by <?php echo htmlspecialchars($issue['author_name']); ?>
                                </small>
                                <small class="text-muted ms-3">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo formatDate($issue['created_at']); ?>
                                </small>
                            </div>
                            
                            <div class="voting-section d-flex align-items-center">
                                <?php if (isLoggedIn()): ?>
                                    <button class="btn btn-sm vote-btn upvote-btn me-2 <?php echo $current_vote === 'upvote' ? 'active' : ''; ?>" 
                                            data-issue-id="<?php echo $issue['id']; ?>" data-vote-type="upvote">
                                        <i class="fas fa-chevron-up"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <span class="vote-count mx-2 <?php echo $issue['vote_count'] > 0 ? 'text-success' : ($issue['vote_count'] < 0 ? 'text-danger' : 'text-muted'); ?>">
                                    <?php echo $issue['vote_count']; ?>
                                </span>
                                
                                <?php if (isLoggedIn()): ?>
                                    <button class="btn btn-sm vote-btn downvote-btn ms-2 <?php echo $current_vote === 'downvote' ? 'active' : ''; ?>" 
                                            data-issue-id="<?php echo $issue['id']; ?>" data-vote-type="downvote">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Comments Section -->
                <div class="card shadow">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-comments me-2"></i>
                            Comments (<?php echo count($comments); ?>)
                        </h5>
                    </div>
                    
                    <div class="card-body">
                        <!-- Add Comment Form -->
                        <?php if (isLoggedIn()): ?>
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo $error; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="" class="mb-4">
                                <div class="mb-3">
                                    <label for="comment" class="form-label">Add your comment</label>
                                    <textarea class="form-control" id="comment" name="comment" rows="3" 
                                              placeholder="Share your thoughts, suggestions, or updates about this educational issue..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Post Comment
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Please login to post comments</strong><br>
                                <a href="../auth/login.php" class="btn btn-primary btn-sm mt-2">
                                    <i class="fas fa-sign-in-alt me-1"></i>Login
                                </a>
                                <a href="../auth/register.php" class="btn btn-outline-primary btn-sm mt-2 ms-2">
                                    <i class="fas fa-user-plus me-1"></i>Register
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Comments List -->
                        <?php if (empty($comments)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-comment-slash fa-2x mb-3"></i>
                                <p>No comments yet. Be the first to share your thoughts!</p>
                            </div>
                        <?php else: ?>
                            <div class="comments-list">
                                <?php foreach ($comments as $comment): ?>
                                    <div class="comment-item border-bottom pb-3 mb-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="comment-author">
                                                <strong class="text-primary">
                                                    <?php echo $comment['username'] ? htmlspecialchars($comment['username']) : 'Guest'; ?>
                                                </strong>
                                                <small class="text-muted ms-2">
                                                    <?php echo formatDate($comment['created_at']); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="comment-content">
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Issue Info
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Status:</strong>
                            <span class="badge bg-secondary">Active</span>
                        </div>
                        <div class="mb-3">
                            <strong>Total Votes:</strong>
                            <span class="badge bg-primary"><?php echo $issue['vote_count']; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Comments:</strong>
                            <span class="badge bg-info"><?php echo count($comments); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Created:</strong>
                            <br>
                            <small class="text-muted"><?php echo date('F j, Y \a\t g:i A', strtotime($issue['created_at'])); ?></small>
                        </div>
                    </div>
                </div>
                
                <div class="card shadow mt-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-lightbulb me-2"></i>Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="../index.html" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Issues
                            </a>
                            <?php if (isLoggedIn()): ?>
                                <a href="create.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Post New Issue
                                </a>
                            <?php else: ?>
                                <a href="../auth/register.php" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-2"></i>Join Community
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/theme.js"></script>
    <script src="../assets/js/voting.js"></script>
</body>
</html>
