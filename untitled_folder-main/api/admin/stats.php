<?php
/**
 * Admin Statistics API
 * Provides dashboard statistics and metrics
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
    
    // Get statistics
    $stats = getDashboardStats($pdo);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    error_log("Admin Stats API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

/**
 * Get comprehensive dashboard statistics
 */
function getDashboardStats($pdo) {
    $stats = [];
    
    // Pending applications count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pending_applications WHERE status = 'pending'");
    $stmt->execute();
    $stats['pending'] = $stmt->fetchColumn();
    
    // Approved applications today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM pending_applications 
        WHERE status = 'approved' AND DATE(reviewed_at) = CURDATE()
    ");
    $stmt->execute();
    $stats['approved_today'] = $stmt->fetchColumn();
    
    // Rejected applications today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM pending_applications 
        WHERE status = 'rejected' AND DATE(reviewed_at) = CURDATE()
    ");
    $stmt->execute();
    $stats['rejected_today'] = $stmt->fetchColumn();
    
    // Total users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'active'");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetchColumn();
    
    // New users this week
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM users 
        WHERE status = 'active' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $stats['new_users_week'] = $stmt->fetchColumn();
    
    // New users this month
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM users 
        WHERE status = 'active' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $stats['new_users_month'] = $stmt->fetchColumn();
    
    // Total issues
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM issues WHERE status != 'deleted'");
    $stmt->execute();
    $stats['total_issues'] = $stmt->fetchColumn();
    
    // Open issues
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM issues WHERE status = 'open'");
    $stmt->execute();
    $stats['open_issues'] = $stmt->fetchColumn();
    
    // Resolved issues this week
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM issues 
        WHERE status = 'resolved' AND DATE(updated_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $stats['resolved_week'] = $stmt->fetchColumn();
    
    // Total votes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes");
    $stmt->execute();
    $stats['total_votes'] = $stmt->fetchColumn();
    
    // Votes this week
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM votes 
        WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $stats['votes_week'] = $stmt->fetchColumn();
    
    // Total comments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE status != 'deleted'");
    $stmt->execute();
    $stats['total_comments'] = $stmt->fetchColumn();
    
    // Comments this week
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM comments 
        WHERE status != 'deleted' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $stats['comments_week'] = $stmt->fetchColumn();
    
    // Applications by city (top 5)
    $stmt = $pdo->prepare("
        SELECT city, COUNT(*) as count 
        FROM pending_applications 
        WHERE status = 'pending' 
        GROUP BY city 
        ORDER BY count DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $stats['applications_by_city'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Applications by date (last 7 days)
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM pending_applications 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at) 
        ORDER BY date
    ");
    $stmt->execute();
    $stats['applications_by_date'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // User growth trend (last 6 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM users 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $stats['user_growth_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Issue categories distribution
    $stmt = $pdo->prepare("
        SELECT category, COUNT(*) as count 
        FROM issues 
        WHERE status != 'deleted' 
        GROUP BY category 
        ORDER BY count DESC
    ");
    $stmt->execute();
    $stats['issue_categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Average response time for applications (in hours)
    $stmt = $pdo->prepare("
        SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, reviewed_at)) as avg_response_time
        FROM pending_applications 
        WHERE status IN ('approved', 'rejected') AND reviewed_at IS NOT NULL
    ");
    $stmt->execute();
    $avgResponseTime = $stmt->fetchColumn();
    $stats['avg_response_time_hours'] = round($avgResponseTime, 1);
    
    // Application completion rate
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN document_path IS NOT NULL AND terms_accepted = 1 AND community_guidelines = 1 THEN 1 ELSE 0 END) as complete
        FROM pending_applications 
        WHERE status = 'pending'
    ");
    $stmt->execute();
    $completionData = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['completion_rate'] = $completionData['total'] > 0 ? 
        round(($completionData['complete'] / $completionData['total']) * 100, 1) : 0;
    
    // Top performing cities (by user engagement)
    $stmt = $pdo->prepare("
        SELECT 
            u.city,
            COUNT(DISTINCT u.id) as users,
            COUNT(DISTINCT i.id) as issues,
            COUNT(DISTINCT v.id) as votes,
            ROUND(COUNT(DISTINCT v.id) / COUNT(DISTINCT u.id), 1) as engagement_score
        FROM users u
        LEFT JOIN issues i ON u.id = i.user_id
        LEFT JOIN votes v ON u.id = v.user_id
        WHERE u.status = 'active'
        GROUP BY u.city
        HAVING users >= 5
        ORDER BY engagement_score DESC
        LIMIT 5
    ");
    $stmt->execute();
    $stats['top_cities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // System health metrics
    $stats['system_health'] = [
        'database_connections' => getDatabaseConnectionCount($pdo),
        'last_backup' => getLastBackupTime(),
        'disk_usage' => getDiskUsage(),
        'memory_usage' => getMemoryUsage()
    ];
    
    return $stats;
}

/**
 * Get database connection count (simplified)
 */
function getDatabaseConnectionCount($pdo) {
    try {
        $stmt = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['Value'] ?? 'Unknown';
    } catch (Exception $e) {
        return 'Unknown';
    }
}

/**
 * Get last backup time (placeholder)
 */
function getLastBackupTime() {
    // This would typically check a backup log or file timestamp
    // For now, return a placeholder
    return date('Y-m-d H:i:s', strtotime('-1 day'));
}

/**
 * Get disk usage (simplified)
 */
function getDiskUsage() {
    try {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;
        $percentage = round(($used / $total) * 100, 1);
        
        return [
            'total' => formatBytes($total),
            'used' => formatBytes($used),
            'free' => formatBytes($free),
            'percentage' => $percentage
        ];
    } catch (Exception $e) {
        return [
            'total' => 'Unknown',
            'used' => 'Unknown',
            'free' => 'Unknown',
            'percentage' => 0
        ];
    }
}

/**
 * Get memory usage (simplified)
 */
function getMemoryUsage() {
    try {
        $memoryLimit = ini_get('memory_limit');
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        
        return [
            'limit' => $memoryLimit,
            'current' => formatBytes($memoryUsage),
            'peak' => formatBytes($memoryPeak),
            'percentage' => round(($memoryUsage / getMemoryLimitInBytes($memoryLimit)) * 100, 1)
        ];
    } catch (Exception $e) {
        return [
            'limit' => 'Unknown',
            'current' => 'Unknown',
            'peak' => 'Unknown',
            'percentage' => 0
        ];
    }
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Convert memory limit string to bytes
 */
function getMemoryLimitInBytes($memoryLimit) {
    $unit = strtolower(substr($memoryLimit, -1));
    $value = (int)substr($memoryLimit, 0, -1);
    
    switch ($unit) {
        case 'k':
            return $value * 1024;
        case 'm':
            return $value * 1024 * 1024;
        case 'g':
            return $value * 1024 * 1024 * 1024;
        default:
            return $value;
    }
}
?>
