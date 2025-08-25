<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../includes/functions.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    startSession();
    
    // Check if user is admin
    if (!isLoggedIn() || empty($_SESSION['is_admin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }
    
    $pdo = getDBConnection();
    
    // Get trending issues (top 5 by votes)
    $stmt = $pdo->query("
        SELECT i.id, i.title, i.vote_count
        FROM issues i
        ORDER BY i.vote_count DESC
        LIMIT 5
    ");
    $trendingIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get most active user
    $stmt = $pdo->query("
        SELECT u.username, COUNT(i.id) as issue_count
        FROM users u
        LEFT JOIN issues i ON u.id = i.created_by
        GROUP BY u.id, u.username
        ORDER BY issue_count DESC
        LIMIT 1
    ");
    $mostActiveUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get average issues per user
    $stmt = $pdo->query("
        SELECT 
            COUNT(i.id) as total_issues,
            COUNT(DISTINCT i.created_by) as users_with_issues,
            CASE 
                WHEN COUNT(DISTINCT i.created_by) > 0 
                THEN ROUND(COUNT(i.id) / COUNT(DISTINCT i.created_by), 2)
                ELSE 0 
            END as avg_issues_per_user
        FROM issues i
    ");
    $avgIssuesData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get average comments per issue
    $stmt = $pdo->query("
        SELECT 
            COUNT(c.id) as total_comments,
            COUNT(DISTINCT c.issue_id) as issues_with_comments,
            CASE 
                WHEN COUNT(DISTINCT c.issue_id) > 0 
                THEN ROUND(COUNT(c.id) / COUNT(DISTINCT c.issue_id), 2)
                ELSE 0 
            END as avg_comments_per_issue
        FROM comments c
    ");
    $avgCommentsData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format the response
    $formattedTrendingIssues = array_map(function($issue) {
        return [
            'id' => (int)$issue['id'],
            'title' => $issue['title'],
            'vote_count' => (int)$issue['vote_count']
        ];
    }, $trendingIssues);
    
    $userActivity = [
        'mostActiveUser' => $mostActiveUser ? $mostActiveUser['username'] : 'N/A',
        'avgIssuesPerUser' => $avgIssuesData['avg_issues_per_user'],
        'avgCommentsPerIssue' => $avgCommentsData['avg_comments_per_issue']
    ];
    
    echo json_encode([
        'success' => true,
        'trendingIssues' => $formattedTrendingIssues,
        'userActivity' => $userActivity
    ]);
    
} catch (Exception $e) {
    error_log("Admin Analytics Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching analytics data'
    ]);
}
?>
