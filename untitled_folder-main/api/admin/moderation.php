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
    
    // Get recent issues (last 10)
    $stmt = $pdo->query("
        SELECT i.id, i.title, i.created_at, u.username as author_name
        FROM issues i
        LEFT JOIN users u ON i.created_by = u.id
        ORDER BY i.created_at DESC
        LIMIT 10
    ");
    $recentIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent comments (last 10)
    $stmt = $pdo->query("
        SELECT c.id, c.content, c.created_at, c.issue_id, u.username as author_name
        FROM comments c
        LEFT JOIN users u ON c.user_id = u.id
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
    $recentComments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $formattedIssues = array_map(function($issue) {
        return [
            'id' => (int)$issue['id'],
            'title' => $issue['title'],
            'created_at' => $issue['created_at'],
            'author_name' => $issue['author_name']
        ];
    }, $recentIssues);
    
    $formattedComments = array_map(function($comment) {
        return [
            'id' => (int)$comment['id'],
            'content' => $comment['content'],
            'created_at' => $comment['created_at'],
            'issue_id' => (int)$comment['issue_id'],
            'author_name' => $comment['author_name']
        ];
    }, $recentComments);
    
    echo json_encode([
        'success' => true,
        'recentIssues' => $formattedIssues,
        'recentComments' => $formattedComments
    ]);
    
} catch (Exception $e) {
    error_log("Admin Moderation Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching moderation data'
    ]);
}
?>
