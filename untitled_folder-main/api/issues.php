<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../includes/functions.php';

// Check authentication
startSession();
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$pdo = getDBConnection();
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'list':
                    // Get issues with filters
                    $city_id = (int)($_GET['city_id'] ?? $_SESSION['city_id'] ?? 0);
                    $metro_area_id = (int)($_GET['metro_area_id'] ?? $_SESSION['metro_area_id'] ?? 0);
                    $category = $_GET['category'] ?? '';
                    $status = $_GET['status'] ?? '';
                    $limit = (int)($_GET['limit'] ?? 20);
                    $offset = (int)($_GET['offset'] ?? 0);
                    
                    $where_conditions = ['1=1'];
                    $params = [];
                    
                    if ($city_id > 0) {
                        $where_conditions[] = 'i.city_id = ?';
                        $params[] = $city_id;
                    }
                    
                    if ($metro_area_id > 0) {
                        $where_conditions[] = 'i.metro_area_id = ?';
                        $params[] = $metro_area_id;
                    }
                    
                    if (!empty($category)) {
                        $where_conditions[] = 'i.category = ?';
                        $params[] = $category;
                    }
                    
                    if (!empty($status)) {
                        $where_conditions[] = 'i.status = ?';
                        $params[] = $status;
                    }
                    
                    $where_clause = implode(' AND ', $where_conditions);
                    
                    $stmt = $pdo->prepare("
                        SELECT i.*, u.full_name as author_name, u.special_login_id as author_id,
                               c.name as city_name, ma.name as metro_area_name,
                               (SELECT COUNT(*) FROM issue_comments ic WHERE ic.issue_id = i.id) as comments_count,
                               (SELECT COUNT(*) FROM issue_votes iv WHERE iv.issue_id = i.id AND iv.vote_type = 'upvote') as upvotes,
                               (SELECT COUNT(*) FROM issue_votes iv WHERE iv.issue_id = i.id AND iv.vote_type = 'downvote') as downvotes
                        FROM issues i
                        JOIN users u ON i.user_id = u.id
                        LEFT JOIN cities c ON i.city_id = c.id
                        LEFT JOIN metro_areas ma ON i.metro_area_id = ma.id
                        WHERE $where_clause
                        ORDER BY i.created_at DESC
                        LIMIT ? OFFSET ?
                    ");
                    
                    $params[] = $limit;
                    $params[] = $offset;
                    $stmt->execute($params);
                    $issues = $stmt->fetchAll();
                    
                    // Get user's votes for these issues
                    $user_votes = [];
                    if (!empty($issues)) {
                        $issue_ids = array_column($issues, 'id');
                        $placeholders = str_repeat('?,', count($issue_ids) - 1) . '?';
                        $stmt = $pdo->prepare("
                            SELECT issue_id, vote_type 
                            FROM issue_votes 
                            WHERE user_id = ? AND issue_id IN ($placeholders)
                        ");
                        $vote_params = [$_SESSION['user_id']];
                        $vote_params = array_merge($vote_params, $issue_ids);
                        $stmt->execute($vote_params);
                        
                        while ($vote = $stmt->fetch()) {
                            $user_votes[$vote['issue_id']] = $vote['vote_type'];
                        }
                    }
                    
                    // Add user vote info to issues
                    foreach ($issues as &$issue) {
                        $issue['user_vote'] = $user_votes[$issue['id']] ?? null;
                        $issue['created_ago'] = formatDate($issue['created_at']);
                    }
                    
                    $response['success'] = true;
                    $response['data'] = $issues;
                    break;
                    
                case 'single':
                    $issue_id = (int)($_GET['id'] ?? 0);
                    if ($issue_id <= 0) {
                        $response['message'] = 'Invalid issue ID';
                        break;
                    }
                    
                    $stmt = $pdo->prepare("
                        SELECT i.*, u.full_name as author_name, u.special_login_id as author_id,
                               c.name as city_name, ma.name as metro_area_name,
                               (SELECT COUNT(*) FROM issue_comments ic WHERE ic.issue_id = i.id) as comments_count,
                               (SELECT COUNT(*) FROM issue_votes iv WHERE iv.issue_id = i.id AND iv.vote_type = 'upvote') as upvotes,
                               (SELECT COUNT(*) FROM issue_votes iv WHERE iv.issue_id = i.id AND iv.vote_type = 'downvote') as downvotes
                        FROM issues i
                        JOIN users u ON i.user_id = u.id
                        LEFT JOIN cities c ON i.city_id = c.id
                        LEFT JOIN metro_areas ma ON i.metro_area_id = ma.id
                        WHERE i.id = ?
                    ");
                    $stmt->execute([$issue_id]);
                    $issue = $stmt->fetch();
                    
                    if ($issue) {
                        // Get user's vote
                        $stmt = $pdo->prepare("SELECT vote_type FROM issue_votes WHERE user_id = ? AND issue_id = ?");
                        $stmt->execute([$_SESSION['user_id'], $issue_id]);
                        $vote = $stmt->fetch();
                        $issue['user_vote'] = $vote['vote_type'] ?? null;
                        $issue['created_ago'] = formatDate($issue['created_at']);
                        
                        // Get comments
                        $stmt = $pdo->prepare("
                            SELECT ic.*, u.full_name as author_name, u.special_login_id as author_id
                            FROM issue_comments ic
                            JOIN users u ON ic.user_id = u.id
                            WHERE ic.issue_id = ?
                            ORDER BY ic.created_at ASC
                        ");
                        $stmt->execute([$issue_id]);
                        $issue['comments'] = $stmt->fetchAll();
                        
                        $response['success'] = true;
                        $response['data'] = $issue;
                    } else {
                        $response['message'] = 'Issue not found';
                    }
                    break;
                    
                default:
                    $response['message'] = 'Invalid action';
            }
            break;
            
        case 'POST':
            switch ($action) {
                case 'create':
                    $title = sanitizeInput($_POST['title'] ?? '');
                    $description = sanitizeInput($_POST['description'] ?? '');
                    $category = $_POST['category'] ?? '';
                    $priority = $_POST['priority'] ?? 'medium';
                    $city_id = (int)($_POST['city_id'] ?? $_SESSION['city_id'] ?? 0);
                    $metro_area_id = (int)($_POST['metro_area_id'] ?? $_SESSION['metro_area_id'] ?? 0);
                    $image = $_FILES['image'] ?? null;
                    
                    // Validation
                    if (empty($title) || strlen($title) < 5) {
                        $response['message'] = 'Title is required (min 5 characters)';
                        break;
                    }
                    
                    if (empty($description) || strlen($description) < 20) {
                        $response['message'] = 'Description is required (min 20 characters)';
                        break;
                    }
                    
                    if (empty($category)) {
                        $response['message'] = 'Category is required';
                        break;
                    }
                    
                    if ($city_id <= 0) {
                        $response['message'] = 'City is required';
                        break;
                    }
                    
                    // Handle image upload
                    $image_path = null;
                    if ($image && $image['error'] === UPLOAD_ERR_OK) {
                        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
                        $max_size = 5 * 1024 * 1024; // 5MB
                        
                        if (!in_array($image['type'], $allowed_types)) {
                            $response['message'] = 'Only JPEG and PNG images are allowed';
                            break;
                        }
                        
                        if ($image['size'] > $max_size) {
                            $response['message'] = 'Image size must be less than 5MB';
                            break;
                        }
                        
                        $upload_dir = __DIR__ . '/../uploads/issues/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_extension = pathinfo($image['name'], PATHINFO_EXTENSION);
                        $filename = 'issue_' . uniqid() . '.' . $file_extension;
                        $file_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($image['tmp_name'], $file_path)) {
                            $image_path = 'uploads/issues/' . $filename;
                        } else {
                            $response['message'] = 'Failed to upload image';
                            break;
                        }
                    }
                    
                    // Create issue
                    $stmt = $pdo->prepare("
                        INSERT INTO issues (title, description, category, priority, user_id, city_id, metro_area_id, image_path)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([$title, $description, $category, $priority, $_SESSION['user_id'], $city_id, $metro_area_id, $image_path])) {
                        $issue_id = $pdo->lastInsertId();
                        
                        $response['success'] = true;
                        $response['message'] = 'Issue created successfully';
                        $response['data'] = ['issue_id' => $issue_id];
                    } else {
                        $response['message'] = 'Failed to create issue';
                    }
                    break;
                    
                case 'vote':
                    $issue_id = (int)($_POST['issue_id'] ?? 0);
                    $vote_type = $_POST['vote_type'] ?? '';
                    
                    if ($issue_id <= 0) {
                        $response['message'] = 'Invalid issue ID';
                        break;
                    }
                    
                    if (!in_array($vote_type, ['upvote', 'downvote'])) {
                        $response['message'] = 'Invalid vote type';
                        break;
                    }
                    
                    // Check if user already voted
                    $stmt = $pdo->prepare("SELECT id, vote_type FROM issue_votes WHERE user_id = ? AND issue_id = ?");
                    $stmt->execute([$_SESSION['user_id'], $issue_id]);
                    $existing_vote = $stmt->fetch();
                    
                    if ($existing_vote) {
                        if ($existing_vote['vote_type'] === $vote_type) {
                            // Remove vote
                            $stmt = $pdo->prepare("DELETE FROM issue_votes WHERE id = ?");
                            $stmt->execute([$existing_vote['id']]);
                        } else {
                            // Update vote
                            $stmt = $pdo->prepare("UPDATE issue_votes SET vote_type = ? WHERE id = ?");
                            $stmt->execute([$vote_type, $existing_vote['id']]);
                        }
                    } else {
                        // Create new vote
                        $stmt = $pdo->prepare("INSERT INTO issue_votes (issue_id, user_id, vote_type) VALUES (?, ?, ?)");
                        $stmt->execute([$issue_id, $_SESSION['user_id'], $vote_type]);
                    }
                    
                    // Update issue vote count
                    updateIssueVoteCount($issue_id);
                    
                    $response['success'] = true;
                    $response['message'] = 'Vote recorded successfully';
                    break;
                    
                case 'comment':
                    $issue_id = (int)($_POST['issue_id'] ?? 0);
                    $comment = sanitizeInput($_POST['comment'] ?? '');
                    
                    if ($issue_id <= 0) {
                        $response['message'] = 'Invalid issue ID';
                        break;
                    }
                    
                    if (empty($comment) || strlen($comment) < 3) {
                        $response['message'] = 'Comment is required (min 3 characters)';
                        break;
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO issue_comments (issue_id, user_id, comment) VALUES (?, ?, ?)");
                    
                    if ($stmt->execute([$issue_id, $_SESSION['user_id'], $comment])) {
                        // Update issue comment count
                        $stmt = $pdo->prepare("
                            UPDATE issues 
                            SET comments_count = (SELECT COUNT(*) FROM issue_comments WHERE issue_id = ?)
                            WHERE id = ?
                        ");
                        $stmt->execute([$issue_id, $issue_id]);
                        
                        $response['success'] = true;
                        $response['message'] = 'Comment added successfully';
                        $response['data'] = ['comment_id' => $pdo->lastInsertId()];
                    } else {
                        $response['message'] = 'Failed to add comment';
                    }
                    break;
                    
                default:
                    $response['message'] = 'Invalid action';
            }
            break;
            
        default:
            $response['message'] = 'Method not allowed';
    }
    
} catch (Exception $e) {
    $response['message'] = 'An error occurred';
    error_log("Issues API error: " . $e->getMessage());
}

echo json_encode($response);
?>
