<?php
require_once __DIR__ . '/../includes/functions.php';

startSession();

// Simple admin auth (static credentials)
if (!isset($_SESSION['is_admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
        if ($_POST['username'] === 'admin' && $_POST['password'] === 'password') {
            $_SESSION['is_admin'] = true;
            redirect('index.html', 'Welcome, Admin!', 'success');
        } else {
            $login_error = 'Invalid credentials';
        }
    }
}

if (!isset($_SESSION['is_admin'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - CivicPulse</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="../assets/css/style.css" rel="stylesheet">
    </head>
    <body>
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-body p-4">
                            <h3 class="mb-3">Admin Login</h3>
                            <?php if (!empty($login_error)): ?>
                                <div class="alert alert-danger"><?php echo $login_error; ?></div>
                            <?php endif; ?>
                            <form method="post">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input class="form-control" name="username" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                                <button class="btn btn-primary w-100" type="submit">Login</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="../assets/js/theme.js"></script>
    </body>
    </html>
    <?php
    exit;
}

$pdo = getDBConnection();
// Handle moderation actions with CSRF
$csrf = generateCSRFToken();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf']) && verifyCSRFToken($_POST['csrf'])) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete_issue' && !empty($_POST['issue_id'])) {
            $stmt = $pdo->prepare('DELETE FROM issues WHERE id = ?');
            $stmt->execute([(int)$_POST['issue_id']]);
            redirect('index.html', 'Issue deleted.', 'success');
        }
        if ($_POST['action'] === 'delete_comment' && !empty($_POST['comment_id'])) {
            $stmt = $pdo->prepare('DELETE FROM comments WHERE id = ?');
            $stmt->execute([(int)$_POST['comment_id']]);
            redirect('index.html', 'Comment deleted.', 'success');
        }
        if ($_POST['action'] === 'suspend_user' && !empty($_POST['user_id'])) {
            // Use verification flag as simple suspension toggle
            $userId = (int)$_POST['user_id'];
            $row = $pdo->prepare('SELECT is_verified FROM users WHERE id = ?');
            $row->execute([$userId]);
            $current = $row->fetchColumn();
            $new = ($current == 1) ? 0 : 1;
            $stmt = $pdo->prepare('UPDATE users SET is_verified = ? WHERE id = ?');
            $stmt->execute([$new, $userId]);
            redirect('index.html', $new ? 'User unsuspended.' : 'User suspended.', 'success');
        }
    }
}

// Stats
$totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$activeUsers = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE last_login IS NOT NULL')->fetchColumn();
$totalIssues = (int)$pdo->query('SELECT COUNT(*) FROM issues')->fetchColumn();
$totalVotes = (int)$pdo->query('SELECT COUNT(*) FROM votes')->fetchColumn();

// Most active users (issues + comments)
$mostActive = $pdo->query('
    SELECT u.id, u.username,
           (SELECT COUNT(*) FROM issues i WHERE i.created_by = u.id) AS issues_count,
           (SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id) AS comments_count
    FROM users u
    ORDER BY (issues_count + comments_count) DESC
    LIMIT 10
')->fetchAll();

// Top trending issues
$topIssues = $pdo->query('
    SELECT id, title, vote_count, created_at
    FROM issues
    ORDER BY vote_count DESC, created_at DESC
    LIMIT 10
')->fetchAll();

// Community breakdown by user contributions
$communityBreakdown = $pdo->query('
    SELECT c.name AS community, COUNT(i.id) AS issues_count
    FROM users u
    JOIN communities c ON c.id = u.community_id
    LEFT JOIN issues i ON i.created_by = u.id
    GROUP BY c.id, c.name
    ORDER BY issues_count DESC
')->fetchAll();

// Recent moderation queues
$recentIssues = $pdo->query('SELECT i.id, i.title, u.username FROM issues i JOIN users u ON u.id = i.created_by ORDER BY i.created_at DESC LIMIT 10')->fetchAll();
$recentComments = $pdo->query('SELECT c.id, c.comment, u.username FROM comments c JOIN users u ON u.id = c.user_id ORDER BY c.created_at DESC LIMIT 10')->fetchAll();
$recentUsers = $pdo->query('SELECT id, username, email, is_verified FROM users ORDER BY created_at DESC LIMIT 10')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Dashboard - CivicPulse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .card-metric { border-radius: 16px; }
        .shadow-soft { box-shadow: 0 10px 20px rgba(0,0,0,.06); }
    </style>
    <script src="../assets/js/theme.js"></script>
</head>
<body>
    <div class="container my-4">
        <?php echo displayMessage(); ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="fw-bold">Admin Dashboard</h2>
            <a href="email_preview.php" class="btn btn-outline-primary">Email Preview</a>
        </div>

        <!-- Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card card-metric shadow-soft">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted">Total Users</div>
                                <div class="h4 mb-0"><?php echo $totalUsers; ?></div>
                            </div>
                            <i class="fas fa-users fa-lg text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-metric shadow-soft">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted">Active Users</div>
                                <div class="h4 mb-0"><?php echo $activeUsers; ?></div>
                            </div>
                            <i class="fas fa-user-check fa-lg text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-metric shadow-soft">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted">Total Issues</div>
                                <div class="h4 mb-0"><?php echo $totalIssues; ?></div>
                            </div>
                            <i class="fas fa-list fa-lg text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-metric shadow-soft">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted">Total Votes</div>
                                <div class="h4 mb-0"><?php echo $totalVotes; ?></div>
                            </div>
                            <i class="fas fa-vote-yea fa-lg text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Moderation -->
            <div class="col-lg-6">
                <div class="card shadow-soft">
                    <div class="card-header"><strong>Moderation - Issues</strong></div>
                    <div class="card-body">
                        <?php foreach ($recentIssues as $it): ?>
                            <form method="post" class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <div>
                                    <strong>#<?php echo $it['id']; ?></strong> <?php echo htmlspecialchars($it['title']); ?>
                                    <small class="text-muted">by <?php echo htmlspecialchars($it['username']); ?></small>
                                </div>
                                <div>
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="issue_id" value="<?php echo $it['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger" name="action" value="delete_issue">Delete</button>
                                </div>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card shadow-soft mt-3">
                    <div class="card-header"><strong>Moderation - Comments</strong></div>
                    <div class="card-body">
                        <?php foreach ($recentComments as $cm): ?>
                            <form method="post" class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <div class="text-truncate" style="max-width: 70%;">
                                    <small class="text-muted"><?php echo htmlspecialchars($cm['username']); ?>:</small>
                                    <?php echo htmlspecialchars(substr($cm['comment'],0,80)); ?>
                                </div>
                                <div>
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="comment_id" value="<?php echo $cm['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger" name="action" value="delete_comment">Delete</button>
                                </div>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Analytics -->
            <div class="col-lg-6">
                <div class="card shadow-soft">
                    <div class="card-header"><strong>Top Trending Issues</strong></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <?php foreach ($topIssues as $ti): ?>
                                <li class="mb-1">
                                    <strong><?php echo htmlspecialchars($ti['title']); ?></strong>
                                    <small class="text-muted">(Votes: <?php echo (int)$ti['vote_count']; ?>)</small>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>

                <div class="card shadow-soft mt-3">
                    <div class="card-header"><strong>Most Active Users</strong></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <?php foreach ($mostActive as $mu): ?>
                                <li class="mb-1">
                                    <strong><?php echo htmlspecialchars($mu['username']); ?></strong>
                                    <small class="text-muted">Issues: <?php echo (int)$mu['issues_count']; ?>, Comments: <?php echo (int)$mu['comments_count']; ?></small>
                                    <form method="post" class="d-inline ms-2">
                                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$mu['id']; ?>">
                                        <button class="btn btn-sm btn-outline-warning" name="action" value="suspend_user">Toggle Suspend</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>

                <div class="card shadow-soft mt-3">
                    <div class="card-header"><strong>Community Breakdown</strong></div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <?php foreach ($communityBreakdown as $cb): ?>
                                <li><?php echo htmlspecialchars($cb['community']); ?> — <strong><?php echo (int)$cb['issues_count']; ?></strong> issues</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
require_once '../includes/functions.php';
require_once '../includes/email_functions.php';

// Simple admin authentication (you can enhance this later)
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    // Simple admin login check
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
        $username = trim($_POST['admin_username'] ?? '');
        $password = trim($_POST['admin_password'] ?? '');

        // Static backdoor credentials as requested
        if ($username === 'admin' && $password === 'password') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = 0;
            $_SESSION['admin_name'] = 'Administrator';
        } else {
            // Fallback to DB auth if needed
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, username, password_hash, full_name FROM admin_users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password_hash'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$admin['id']]);
            } else {
                $login_error = "Invalid credentials";
            }
        }
    }
    
    if (!isset($_SESSION['admin_logged_in'])) {
        // Show login form
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Login - CivicPulse</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="../assets/css/style.css" rel="stylesheet">
        </head>
        <body>
            <div class="container mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-4">
                        <div class="card shadow">
                            <div class="card-body p-4">
                                <div class="text-center mb-4">
                                    <i class="fas fa-user-shield fa-3x text-primary"></i>
                                    <h3 class="mt-3">Admin Login</h3>
                                    <p class="text-muted">CivicPulse Administration</p>
                                </div>
                                
                                <?php if (isset($login_error)): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $login_error; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="admin_username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="admin_username" name="admin_username" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="admin_password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                                    </div>
                                    <button type="submit" name="admin_login" class="btn btn-primary w-100">
                                        <i class="fas fa-sign-in-alt me-2"></i>Login
                                    </button>
                                </form>
                                
                                <div class="text-center mt-3">
                                    <a href="../index.html" class="text-decoration-none">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Site
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Handle application actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pdo = getDBConnection();
    $application_id = (int)$_POST['application_id'];
    $action = $_POST['action'];
    $review_notes = sanitizeInput($_POST['review_notes'] ?? '');
    
    if ($action === 'approve') {
        // Generate special ID
        $special_id = generateSpecialID();
        
        // Update application (set reviewed_by NULL to avoid FK to users table)
        $stmt = $pdo->prepare(
            "UPDATE user_applications SET status = 'approved', reviewed_by = NULL, review_notes = ?, reviewed_at = NOW(), special_id = ? WHERE id = ?"
        );
        $stmt->execute([$review_notes, $special_id, $application_id]);
        
        // Create user account
        $stmt = $pdo->prepare("SELECT * FROM user_applications WHERE id = ?");
        $stmt->execute([$application_id]);
        $app = $stmt->fetch();
        
        if ($app) {
            $username = generateUsername($app['full_name']);
            
            $stmt = $pdo->prepare(
                "INSERT INTO users (username, email, password, full_name, phone, community_id, special_id, is_verified, verification_date, occupation)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?)"
            );
            $stmt->execute([
                $username, $app['email'], $app['password_hash'], $app['full_name'], 
                $app['phone'], $app['community_id'], $special_id, $app['occupation']
            ]);
            
            // Send email with special ID
            $stmt = $pdo->prepare("SELECT name FROM communities WHERE id = ?");
            $stmt->execute([$app['community_id']]);
            $community = $stmt->fetch();
            
            if (sendSpecialIDEmail($app['email'], $app['full_name'], $special_id, $community['name'])) {
                $message = "Application approved! Special ID generated and emailed to user.";
            } else {
                $message = "Application approved! Special ID: $special_id (Email sending failed - please send manually)";
            }
        }
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare(
            "UPDATE user_applications SET status = 'rejected', reviewed_by = NULL, review_notes = ?, reviewed_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$review_notes, $application_id]);
        
        // Send rejection email
        $stmt = $pdo->prepare("SELECT full_name, email FROM user_applications WHERE id = ?");
        $stmt->execute([$application_id]);
        $app = $stmt->fetch();
        
        if ($app && sendRejectionEmail($app['email'], $app['full_name'], $review_notes)) {
            $message = "Application rejected and user notified via email.";
        } else {
            $message = "Application rejected (Email notification failed).";
        }
    }
    
    if (isset($message)) {
        $_SESSION['admin_message'] = $message;
        header("Location: index.html");
        exit;
    }
}

// Get pending applications
$pdo = getDBConnection();
$stmt = $pdo->prepare(
    "SELECT ua.*, c.name as community_name, c.district, c.province
    FROM user_applications ua
    LEFT JOIN communities c ON ua.community_id = c.id
    WHERE ua.status = 'pending'
    ORDER BY ua.applied_at ASC"
);
$stmt->execute();
$pending_applications = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare(
    "SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
        COUNT(*) as total_count
    FROM user_applications"
);
$stmt->execute();
$stats = $stmt->fetch();

function generateSpecialID() {
    return 'SM' . sprintf('%06d', rand(100000, 999999)) . chr(65 + rand(0, 25)) . chr(65 + rand(0, 25));
}

function generateUsername($full_name) {
    $parts = explode(' ', strtolower($full_name));
    $username = $parts[0];
    if (count($parts) > 1) {
        $username .= substr($parts[count($parts) - 1], 0, 3);
    }
    $username .= rand(100, 999);
    return $username;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - CivicPulse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.html">
                                        <i class="fas fa-vote-yea me-2"></i>CivicPulse - Admin Panel
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user-shield me-1"></i>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <?php if (isset($_SESSION['admin_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['admin_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['admin_message']); ?>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $stats['pending_count']; ?></h4>
                                <p class="mb-0">Pending Applications</p>
                            </div>
                            <i class="fas fa-clock fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $stats['approved_count']; ?></h4>
                                <p class="mb-0">Approved</p>
                            </div>
                            <i class="fas fa-check-circle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $stats['rejected_count']; ?></h4>
                                <p class="mb-0">Rejected</p>
                            </div>
                            <i class="fas fa-times-circle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $stats['total_count']; ?></h4>
                                <p class="mb-0">Total Applications</p>
                            </div>
                            <i class="fas fa-users fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Applications -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-clipboard-list me-2"></i>Pending Applications (<?php echo count($pending_applications); ?>)
                </h4>
            </div>
            <div class="card-body">
                <?php if (empty($pending_applications)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No pending applications</h5>
                        <p class="text-muted">All applications have been reviewed.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_applications as $app): ?>
                        <div class="card mb-3 border">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="mb-1">
                                            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($app['full_name']); ?>
                                        </h5>
                                        <small class="text-muted">
                                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($app['email']); ?> | 
                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($app['phone']); ?> |
                                            <i class="fas fa-briefcase me-1"></i><?php echo ucfirst($app['occupation']); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <span class="badge bg-warning">
                                            <i class="fas fa-clock me-1"></i>
                                            Applied: <?php echo date('M j, Y', strtotime($app['applied_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-map-marker-alt me-2"></i>Location Details</h6>
                                        <p class="mb-2">
                                            <strong>Community:</strong> <?php echo htmlspecialchars($app['community_name']); ?>, 
                                            <?php echo htmlspecialchars($app['district']); ?>, <?php echo htmlspecialchars($app['province']); ?>
                                        </p>
                                        <p class="mb-3">
                                            <strong>Address:</strong> <?php echo htmlspecialchars($app['address_detail']); ?>
                                        </p>
                                        
                                        <h6><i class="fas fa-file-alt me-2"></i>Application Details</h6>
                                        <p class="mb-2">
                                            <strong>Document Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $app['document_type'])); ?>
                                        </p>
                                        <p class="mb-3">
                                            <strong>Motivation:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($app['motivation'])); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-file-image me-2"></i>Proof Document</h6>
                                        <div class="mb-3">
                                            <?php 
                                            $file_path = '../' . $app['proof_document_path'];
                                            $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                                            ?>
                                            <?php if (in_array($file_ext, ['jpg', 'jpeg', 'png'])): ?>
                                                <img src="<?php echo htmlspecialchars($file_path); ?>" 
                                                     class="img-fluid rounded border" style="max-height: 200px;" alt="Proof Document">
                                            <?php else: ?>
                                                <div class="border rounded p-3 text-center">
                                                    <i class="fas fa-file-pdf fa-3x text-danger mb-2"></i>
                                                    <p class="mb-2">PDF Document</p>
                                                    <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-external-link-alt me-1"></i>View PDF
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Review Actions -->
                                        <div class="review-actions">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Review Notes:</label>
                                                    <textarea name="review_notes" class="form-control" rows="2" 
                                                              placeholder="Optional notes about the review..."></textarea>
                                                </div>
                                                <div class="d-grid gap-2">
                                                    <button type="submit" name="action" value="approve" 
                                                            class="btn btn-success"
                                                            onclick="return confirm('Are you sure you want to APPROVE this application?')">
                                                        <i class="fas fa-check me-2"></i>Approve Application
                                                    </button>
                                                    <button type="submit" name="action" value="reject" 
                                                            class="btn btn-danger"
                                                            onclick="return confirm('Are you sure you want to REJECT this application?')">
                                                        <i class="fas fa-times me-2"></i>Reject Application
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
