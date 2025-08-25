<?php
header('Content-Type: application/json');
require_once '../includes/functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to vote']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['issue_id']) || !isset($input['vote_type'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$issue_id = (int)$input['issue_id'];
$vote_type = $input['vote_type'];
$user_id = $_SESSION['user_id'];

// Validate vote type
if (!in_array($vote_type, ['upvote', 'downvote'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid vote type']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Check if issue exists
    $stmt = $pdo->prepare("SELECT id FROM issues WHERE id = ?");
    $stmt->execute([$issue_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Issue not found']);
        exit;
    }
    
    // Check if user already voted on this issue
    $stmt = $pdo->prepare("SELECT id, vote_type FROM votes WHERE issue_id = ? AND user_id = ?");
    $stmt->execute([$issue_id, $user_id]);
    $existing_vote = $stmt->fetch();
    
    if ($existing_vote) {
        if ($existing_vote['vote_type'] === $vote_type) {
            // Remove vote if clicking the same button
            $stmt = $pdo->prepare("DELETE FROM votes WHERE id = ?");
            $stmt->execute([$existing_vote['id']]);
        } else {
            // Change vote type
            $stmt = $pdo->prepare("UPDATE votes SET vote_type = ? WHERE id = ?");
            $stmt->execute([$vote_type, $existing_vote['id']]);
        }
    } else {
        // Create new vote
        $stmt = $pdo->prepare("INSERT INTO votes (issue_id, user_id, vote_type) VALUES (?, ?, ?)");
        $stmt->execute([$issue_id, $user_id, $vote_type]);
    }
    
    // Update issue vote count
    updateIssueVoteCount($issue_id);
    
    // Get new vote count
    $stmt = $pdo->prepare("SELECT vote_count FROM issues WHERE id = ?");
    $stmt->execute([$issue_id]);
    $issue = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'new_vote_count' => $issue['vote_count'],
        'message' => 'Vote recorded successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Voting error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while recording your vote']);
}
?>
