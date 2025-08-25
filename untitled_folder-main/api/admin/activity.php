<?php
/**
 * Admin Activity API
 * Provides recent activity data for the dashboard
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
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

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $pdo = getDatabaseConnection();
    
    // Get query parameters
    $limit = min(50, max(10, intval($_GET['limit'] ?? 20)));
    $type = $_GET['type'] ?? 'all';
    
    // Get recent activities
    $activities = getRecentActivities($pdo, $limit, $type);
    
    echo json_encode([
        'success' => true,
        'activities' => $activities
    ]);
    
} catch (Exception $e) {
    error_log("Admin Activity API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

/**
 * Get recent activities from various sources
 */
function getRecentActivities($pdo, $limit, $type) {
    $activities = [];
    
    // Get application activities
    if ($type === 'all' || $type === 'applications') {
        $appActivities = getApplicationActivities($pdo, $limit);
        $activities = array_merge($activities, $appActivities);
    }
    
    // Get user activities
    if ($type === 'all' || $type === 'users') {
        $userActivities = getUserActivities($pdo, $limit);
        $activities = array_merge($activities, $userActivities);
    }
    
    // Get issue activities
    if ($type === 'all' || $type === 'issues') {
        $issueActivities = getIssueActivities($pdo, $limit);
        $activities = array_merge($activities, $issueActivities);
    }
    
    // Get voting activities
    if ($type === 'all' || $type === 'votes') {
        $voteActivities = getVotingActivities($pdo, $limit);
        $activities = array_merge($activities, $voteActivities);
    }
    
    // Get comment activities
    if ($type === 'all' || $type === 'comments') {
        $commentActivities = getCommentActivities($pdo, $limit);
        $activities = array_merge($activities, $commentActivities);
    }
    
    // Sort all activities by timestamp
    usort($activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Return only the requested limit
    return array_slice($activities, 0, $limit);
}

/**
 * Get application-related activities
 */
function getApplicationActivities($pdo, $limit) {
    $activities = [];
    
    // New applications
    $stmt = $pdo->prepare("
        SELECT 
            'application_submitted' as type,
            CONCAT(first_name, ' ', last_name) as user_name,
            email,
            city,
            created_at,
            'New application submitted' as title,
            CONCAT(first_name, ' ', last_name, ' submitted a new application from ', city) as description
        FROM pending_applications 
        WHERE status = 'pending'
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $newApps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($newApps as $app) {
        $activities[] = [
            'type' => $app['type'],
            'title' => $app['title'],
            'description' => $app['description'],
            'user_name' => $app['user_name'],
            'email' => $app['email'],
            'city' => $app['city'],
            'created_at' => $app['created_at'],
            'icon' => 'user-plus',
            'color' => 'primary'
        ];
    }
    
    // Approved applications
    $stmt = $pdo->prepare("
        SELECT 
            'application_approved' as type,
            CONCAT(first_name, ' ', last_name) as user_name,
            email,
            city,
            reviewed_at as created_at,
            'Application approved' as title,
            CONCAT(first_name, ' ', last_name, '\'s application was approved') as description,
            special_login_id
        FROM pending_applications 
        WHERE status = 'approved' AND reviewed_at IS NOT NULL
        ORDER BY reviewed_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $approvedApps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($approvedApps as $app) {
        $activities[] = [
            'type' => $app['type'],
            'title' => $app['title'],
            'description' => $app['description'],
            'user_name' => $app['user_name'],
            'email' => $app['email'],
            'city' => $app['city'],
            'created_at' => $app['created_at'],
            'special_login_id' => $app['special_login_id'],
            'icon' => 'check-circle',
            'color' => 'success'
        ];
    }
    
    // Rejected applications
    $stmt = $pdo->prepare("
        SELECT 
            'application_rejected' as type,
            CONCAT(first_name, ' ', last_name) as user_name,
            email,
            city,
            reviewed_at as created_at,
            'Application rejected' as title,
            CONCAT(first_name, ' ', last_name, '\'s application was rejected') as description,
            admin_notes
        FROM pending_applications 
        WHERE status = 'rejected' AND reviewed_at IS NOT NULL
        ORDER BY reviewed_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $rejectedApps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rejectedApps as $app) {
        $activities[] = [
            'type' => $app['type'],
            'title' => $app['title'],
            'description' => $app['description'],
            'user_name' => $app['user_name'],
            'email' => $app['email'],
            'city' => $app['city'],
            'created_at' => $app['created_at'],
            'admin_notes' => $app['admin_notes'],
            'icon' => 'times-circle',
            'color' => 'danger'
        ];
    }
    
    return $activities;
}

/**
 * Get user-related activities
 */
function getUserActivities($pdo, $limit) {
    $activities = [];
    
    // New user registrations
    $stmt = $pdo->prepare("
        SELECT 
            'user_registered' as type,
            CONCAT(first_name, ' ', last_name) as user_name,
            email,
            city,
            created_at,
            'New user registered' as title,
            CONCAT(first_name, ' ', last_name, ' joined CivicPulse from ', city) as description
        FROM users 
        WHERE status = 'active'
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $newUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($newUsers as $user) {
        $activities[] = [
            'type' => $user['type'],
            'title' => $user['title'],
            'description' => $user['description'],
            'user_name' => $user['user_name'],
            'email' => $user['email'],
            'city' => $user['city'],
            'created_at' => $user['created_at'],
            'icon' => 'user-plus',
            'color' => 'info'
        ];
    }
    
    // User logins (if tracking is enabled)
    // This would require a login_logs table or similar
    
    return $activities;
}

/**
 * Get issue-related activities
 */
function getIssueActivities($pdo, $limit) {
    $activities = [];
    
    // New issues created
    $stmt = $pdo->prepare("
        SELECT 
            'issue_created' as type,
            i.title,
            i.category,
            i.priority,
            i.created_at,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            u.city,
            'New issue reported' as title_text,
            CONCAT(u.first_name, ' ', u.last_name, ' reported a new ', i.category, ' issue') as description
        FROM issues i
        JOIN users u ON i.user_id = u.id
        WHERE i.status != 'deleted'
        ORDER BY i.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $newIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($newIssues as $issue) {
        $activities[] = [
            'type' => $issue['type'],
            'title' => $issue['title_text'],
            'description' => $issue['description'],
            'user_name' => $issue['user_name'],
            'city' => $issue['city'],
            'created_at' => $issue['created_at'],
            'issue_title' => $issue['title'],
            'category' => $issue['category'],
            'priority' => $issue['priority'],
            'icon' => 'exclamation-triangle',
            'color' => 'warning'
        ];
    }
    
    // Issues resolved
    $stmt = $pdo->prepare("
            SELECT 
                'issue_resolved' as type,
                i.title,
                i.category,
                i.updated_at as created_at,
                CONCAT(u.first_name, ' ', u.last_name) as user_name,
                u.city,
                'Issue resolved' as title_text,
                CONCAT('Issue \"', i.title, '\" was marked as resolved') as description
            FROM issues i
            JOIN users u ON i.user_id = u.id
            WHERE i.status = 'resolved'
            ORDER BY i.updated_at DESC 
            LIMIT ?
        ");
    $stmt->execute([$limit]);
    $resolvedIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($resolvedIssues as $issue) {
        $activities[] = [
            'type' => $issue['type'],
            'title' => $issue['title_text'],
            'description' => $issue['description'],
            'user_name' => $issue['user_name'],
            'city' => $issue['city'],
            'created_at' => $issue['created_at'],
            'issue_title' => $issue['title'],
            'category' => $issue['category'],
            'icon' => 'check-circle',
            'color' => 'success'
        ];
    }
    
    return $activities;
}

/**
 * Get voting-related activities
 */
function getVotingActivities($pdo, $limit) {
    $activities = [];
    
    // New votes cast
    $stmt = $pdo->prepare("
        SELECT 
            'vote_cast' as type,
            v.created_at,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            u.city,
            i.title as issue_title,
            i.category,
            'Vote cast' as title_text,
            CONCAT(u.first_name, ' ', u.last_name, ' voted on issue \"', i.title, '\"') as description
        FROM votes v
        JOIN users u ON v.user_id = u.id
        JOIN issues i ON v.issue_id = i.id
        WHERE i.status != 'deleted'
        ORDER BY v.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $votes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($votes as $vote) {
        $activities[] = [
            'type' => $vote['type'],
            'title' => $vote['title_text'],
            'description' => $vote['description'],
            'user_name' => $vote['user_name'],
            'city' => $vote['city'],
            'created_at' => $vote['created_at'],
            'issue_title' => $vote['issue_title'],
            'category' => $vote['category'],
            'icon' => 'vote-yea',
            'color' => 'primary'
        ];
    }
    
    return $activities;
}

/**
 * Get comment-related activities
 */
function getCommentActivities($pdo, $limit) {
    $activities = [];
    
    // New comments posted
    $stmt = $pdo->prepare("
        SELECT 
            'comment_posted' as type,
            c.content,
            c.created_at,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            u.city,
            i.title as issue_title,
            i.category,
            'Comment posted' as title_text,
            CONCAT(u.first_name, ' ', u.last_name, ' commented on issue \"', i.title, '\"') as description
        FROM comments c
        JOIN users u ON c.user_id = u.id
        JOIN issues i ON c.issue_id = i.id
        WHERE c.status != 'deleted' AND i.status != 'deleted'
        ORDER BY c.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($comments as $comment) {
        $activities[] = [
            'type' => $comment['type'],
            'title' => $comment['title_text'],
            'description' => $comment['description'],
            'user_name' => $comment['user_name'],
            'city' => $comment['city'],
            'created_at' => $comment['created_at'],
            'issue_title' => $comment['issue_title'],
            'category' => $comment['category'],
            'comment_preview' => substr($comment['content'], 0, 100) . (strlen($comment['content']) > 100 ? '...' : ''),
            'icon' => 'comment',
            'color' => 'info'
        ];
    }
    
    return $activities;
}

/**
 * Format activity data for consistent response
 */
function formatActivity($activity) {
    return [
        'id' => uniqid(),
        'type' => $activity['type'],
        'title' => $activity['title'],
        'description' => $activity['description'],
        'user_name' => $activity['user_name'] ?? 'Unknown User',
        'city' => $activity['city'] ?? 'Unknown City',
        'created_at' => $activity['created_at'],
        'icon' => $activity['icon'] ?? 'info-circle',
        'color' => $activity['color'] ?? 'primary',
        'metadata' => array_diff_key($activity, array_flip(['type', 'title', 'description', 'user_name', 'city', 'created_at', 'icon', 'color']))
    ];
}
?>
