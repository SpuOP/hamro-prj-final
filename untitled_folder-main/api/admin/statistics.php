<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Connect to database
    $pdo = getDatabaseConnection();
    
    // Get overall statistics
    $stats = [];
    
    // User counts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1 AND last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $stats['active_users_30_days'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1 AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $stats['active_users_7_days'] = $stmt->fetchColumn();
    
    // Application counts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_applications WHERE status = 'pending'");
    $stmt->execute();
    $stats['pending_applications'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_applications WHERE status = 'approved'");
    $stmt->execute();
    $stats['approved_applications'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_applications WHERE status = 'rejected'");
    $stmt->execute();
    $stats['rejected_applications'] = $stmt->fetchColumn();
    
    // Issue counts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM issues");
    $stmt->execute();
    $stats['total_issues'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM issues WHERE status = 'open'");
    $stmt->execute();
    $stats['open_issues'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM issues WHERE status = 'in_progress'");
    $stmt->execute();
    $stats['in_progress_issues'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM issues WHERE status = 'resolved'");
    $stmt->execute();
    $stats['resolved_issues'] = $stmt->fetchColumn();
    
    // Voting statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM issue_votes");
    $stmt->execute();
    $stats['total_votes'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM issue_comments");
    $stmt->execute();
    $stats['total_comments'] = $stmt->fetchColumn();
    
    // Recent activity (last 7 days)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM user_applications 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $stats['new_applications_7_days'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM issues 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $stats['new_issues_7_days'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM issue_votes 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $stats['new_votes_7_days'] = $stmt->fetchColumn();
    
    // City-wise user distribution
    $stmt = $pdo->prepare("
        SELECT c.name as city_name, COUNT(u.id) as user_count
        FROM users u
        JOIN cities c ON u.city_id = c.id
        WHERE u.is_active = 1
        GROUP BY u.city_id, c.name
        ORDER BY user_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $stats['city_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Issue category distribution
    $stmt = $pdo->prepare("
        SELECT category, COUNT(*) as count
        FROM issues
        GROUP BY category
        ORDER BY count DESC
    ");
    $stmt->execute();
    $stats['issue_categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly registration trend (last 6 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM user_applications
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $stats['registration_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly issue creation trend (last 6 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM issues
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $stats['issue_creation_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top issues by votes
    $stmt = $pdo->prepare("
        SELECT 
            i.title,
            i.category,
            i.votes_count,
            i.comments_count,
            i.created_at,
            CONCAT(u.first_name, ' ', u.last_name) as author_name,
            c.name as city_name
        FROM issues i
        JOIN users u ON i.user_id = u.id
        JOIN cities c ON i.city_id = c.id
        ORDER BY i.votes_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $stats['top_issues'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent applications
    $stmt = $pdo->prepare("
        SELECT 
            ua.id,
            ua.first_name,
            ua.last_name,
            ua.email,
            ua.phone,
            ua.document_type,
            ua.created_at,
            c.name as city_name,
            ua.profile_completion_percentage
        FROM user_applications ua
        LEFT JOIN cities c ON ua.city_id = c.id
        WHERE ua.status = 'pending'
        ORDER BY ua.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $stats['recent_applications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent issues
    $stmt = $pdo->prepare("
        SELECT 
            i.id,
            i.title,
            i.category,
            i.priority,
            i.votes_count,
            i.created_at,
            CONCAT(u.first_name, ' ', u.last_name) as author_name,
            c.name as city_name
        FROM issues i
        JOIN users u ON i.user_id = u.id
        JOIN cities c ON i.city_id = c.id
        ORDER BY i.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $stats['recent_issues'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate growth percentages
    $stats['user_growth_30_days'] = calculateGrowthPercentage($pdo, 'users', 30);
    $stats['issue_growth_30_days'] = calculateGrowthPercentage($pdo, 'issues', 30);
    $stats['vote_growth_30_days'] = calculateGrowthPercentage($pdo, 'issue_votes', 30);
    
    // Calculate engagement metrics
    $stats['avg_votes_per_issue'] = $stats['total_issues'] > 0 ? round($stats['total_votes'] / $stats['total_issues'], 2) : 0;
    $stats['avg_comments_per_issue'] = $stats['total_issues'] > 0 ? round($stats['total_comments'] / $stats['total_issues'], 2) : 0;
    $stats['user_engagement_rate'] = $stats['total_users'] > 0 ? round(($stats['active_users_30_days'] / $stats['total_users']) * 100, 2) : 0;
    
    // Return statistics
    echo json_encode([
        'success' => true,
        'data' => $stats,
        'generated_at' => date('Y-m-d H:i:s')
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
    
    error_log("Admin statistics database error: " . $e->getMessage());
}

// Helper function to calculate growth percentage
function calculateGrowthPercentage($pdo, $table, $days) {
    try {
        // Current period count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM $table 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        $current_count = $stmt->fetchColumn();
        
        // Previous period count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM $table 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
            AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days * 2, $days]);
        $previous_count = $stmt->fetchColumn();
        
        if ($previous_count == 0) {
            return $current_count > 0 ? 100 : 0;
        }
        
        $growth = (($current_count - $previous_count) / $previous_count) * 100;
        return round($growth, 2);
        
    } catch (Exception $e) {
        return 0;
    }
}
?>
