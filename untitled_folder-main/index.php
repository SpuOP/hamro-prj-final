<?php
require_once 'includes/functions.php';


// Get sorting parameter
$sort = $_GET['sort'] ?? 'popular';
$search = $_GET['search'] ?? '';

// Build query based on sorting
$orderBy = match($sort) {
    'recent' => 'i.created_at DESC',
    'trending' => 'i.vote_count DESC, i.created_at DESC',
    default => 'i.vote_count DESC, i.created_at DESC'
};

$pdo = getDBConnection();

// Build search query
$whereClause = '';
$params = [];
if (!empty($search)) {
    $whereClause = 'WHERE i.title LIKE ? OR i.description LIKE ?';
    $params = ["%$search%", "%$search%"];
}

// Only load issues if user is logged in
$issues = [];
if (isLoggedIn()) {
    // Get issues with user info, vote counts, and latest comment preview
    $query = "
        SELECT i.id, i.title, i.description, i.image_path, i.created_by, i.vote_count, i.created_at,
               u.username AS author_name,
               COUNT(DISTINCT c.id) AS comment_count,
               COALESCE(SUM(CASE WHEN v.vote_type = 'upvote' THEN 1 ELSE 0 END),0) AS upvotes,
               COALESCE(SUM(CASE WHEN v.vote_type = 'downvote' THEN 1 ELSE 0 END),0) AS downvotes,
               (SELECT c2.content FROM comments c2 WHERE c2.issue_id = i.id ORDER BY c2.created_at DESC LIMIT 1) AS latest_comment,
               (SELECT u2.username FROM comments c3 JOIN users u2 ON u2.id = c3.user_id WHERE c3.issue_id = i.id ORDER BY c3.created_at DESC LIMIT 1) AS latest_comment_author
        FROM issues i
        LEFT JOIN users u ON i.created_by = u.id
        LEFT JOIN comments c ON i.id = c.issue_id
        LEFT JOIN votes v ON i.id = v.issue_id
        $whereClause
        GROUP BY i.id, i.title, i.description, i.image_path, i.created_by, i.vote_count, i.created_at, u.username
        ORDER BY $orderBy
        LIMIT 50
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $issues = $stmt->fetchAll();
}
?>

<?php include __DIR__ . '/partials/header.php'; ?>

    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="main-content" id="main-content">
            <section class="hero">
                <div class="hero-content">
                            <h1>CivicPulse — Community Voting Platform</h1>
        <p>Share community issues, vote on priorities, and work together for better neighborhoods.</p>
                    <div class="hero-actions">
                        <?php if (isLoggedIn()): ?>
                            <a href="issues/create.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-plus"></i> Post New Issue
                            </a>
                        <?php else: ?>
                            <a href="auth/register.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus"></i> Join Community
                            </a>
                            <a href="auth/login.php" class="btn btn-secondary btn-lg">
                                <i class="fas fa-sign-in-alt"></i> Sign In
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            
    <!-- Main Content -->
    <div class="container my-5">
        <!-- Messages -->
        <?php echo displayMessage(); ?>
        
        <?php if (isLoggedIn()): ?>
            <!-- Sorting and Stats -->
            <div class="sorting-section">
                <div class="sorting-controls">
                    <div class="sort-buttons">
                        <a href="?sort=popular<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="btn <?php echo $sort === 'popular' ? 'btn-primary' : 'btn-secondary'; ?>">
                            <i class="fas fa-fire"></i> Most Popular
                        </a>
                        <a href="?sort=recent<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="btn <?php echo $sort === 'recent' ? 'btn-primary' : 'btn-secondary'; ?>">
                            <i class="fas fa-clock"></i> Recent
                        </a>
                        <a href="?sort=trending<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="btn <?php echo $sort === 'trending' ? 'btn-primary' : 'btn-secondary'; ?>">
                            <i class="fas fa-trending-up"></i> Trending
                        </a>
                    </div>
                    <div class="issue-count">
                        <span class="text-muted">
                            <i class="fas fa-list"></i>
                            <?php echo count($issues); ?> issues found
                        </span>
                    </div>
                </div>
            </div>

            <!-- Issues List -->
            <?php if (empty($issues)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h3>No issues found</h3>
                    <p>
                        <?php if (!empty($search)): ?>
                            Try adjusting your search terms
                        <?php else: ?>
                            Be the first to post a community issue!
                        <?php endif; ?>
                    </p>
                    <?php if (empty($search) && isLoggedIn()): ?>
                        <a href="issues/create.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Post First Issue
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-4">
                    <?php foreach ($issues as $issue): ?>
                        <?php 
                            $initial = strtoupper(substr((string)$issue['author_name'], 0, 1));
                            $desc = (string)$issue['description'];
                            $tag = 'community';
                            $lower = strtolower($desc . ' ' . (string)$issue['title']);
                            if (str_contains($lower, 'road') || str_contains($lower, 'traffic')) { $tag = 'infrastructure'; }
                            if (str_contains($lower, 'trash') || str_contains($lower, 'pollution') || str_contains($lower, 'water')) { $tag = 'environment'; }
                            $latestComment = isset($issue['latest_comment']) ? trim((string)$issue['latest_comment']) : '';
                            $latestAuthor = isset($issue['latest_comment_author']) ? (string)$issue['latest_comment_author'] : '';
                        ?>
                        <div class="issue-card">
                            <div class="issue-header">
                                <!-- Vote rail -->
                                <div class="issue-voting">
                                    <button class="vote-btn upvote-btn" data-issue-id="<?php echo $issue['id']; ?>" data-vote-type="upvote">
                                        <i class="fas fa-chevron-up"></i>
                                    </button>
                                    <div class="vote-count <?php echo ($issue['vote_count']>0?'text-success':($issue['vote_count']<0?'text-danger':'text-muted')); ?>" data-issue-id="<?php echo $issue['id']; ?>">
                                        <?php echo (int)$issue['vote_count']; ?>
                                    </div>
                                    <button class="vote-btn downvote-btn" data-issue-id="<?php echo $issue['id']; ?>" data-vote-type="downvote">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </div>

                                <!-- Main -->
                                <div class="issue-content">
                                    <div class="issue-title">
                                        <a href="issues/view.php?id=<?php echo $issue['id']; ?>">
                                            <?php echo htmlspecialchars($issue['title']); ?>
                                        </a>
                                        <?php if ($issue['image_path']): ?>
                                            <span class="issue-tag">
                                                <i class="fas fa-image"></i> Image
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="issue-description">
                                        <?php echo htmlspecialchars(substr($issue['description'], 0, 160)); ?><?php if (strlen($issue['description'])>160) { echo '...'; } ?>
                                    </p>

                                    <div class="issue-meta">
                                        <span class="issue-tag">#<?php echo htmlspecialchars($tag); ?></span>
                                        <span class="issue-tag"><?php echo (int)$issue['comment_count']; ?> comments</span>
                                        <span class="issue-tag"><i class="fas fa-clock"></i> <?php echo formatDate($issue['created_at']); ?></span>
                                    </div>

                                    <?php if (!empty($latestComment)): ?>
                                        <div class="latest-comment">
                                            <div class="author-avatar">
                                                <?php echo htmlspecialchars(substr($latestAuthor !== '' ? $latestAuthor : 'U', 0, 1)); ?>
                                            </div>
                                            <div class="comment-content">
                                                <span class="comment-author"><?php echo htmlspecialchars($latestAuthor !== '' ? $latestAuthor : 'User'); ?>:</span>
                                                <?php echo htmlspecialchars(substr($latestComment, 0, 120)); ?><?php if (strlen($latestComment)>120) { echo '...'; } ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="issue-footer">
                                        <div class="issue-author">
                                            <div class="author-avatar">
                                                <?php echo htmlspecialchars($initial); ?>
                                            </div>
                                            <span>by <?php echo htmlspecialchars($issue['author_name']); ?></span>
                                        </div>
                                        <a href="issues/view.php?id=<?php echo $issue['id']; ?>" class="btn btn-secondary btn-sm">
                                            View Details <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- About Section for Non-logged Users -->
            <div class="welcome-section">
                <div class="welcome-card">
                    <div class="welcome-header">
                        <div class="welcome-icon">
                            <i class="fas fa-vote-yea"></i>
                        </div>
                        <h2>Welcome to CivicPulse</h2>
                        <p class="lead">Your Community Voting Partner</p>
                    </div>
                    
                    <div class="features-grid">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-comments"></i>
                            </div>
                                                    <h5>Share Community Issues</h5>
                        <p>Post problems you see in your neighborhood and community.</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-vote-yea"></i>
                            </div>
                            <h5>Vote on Priorities</h5>
                            <p>Help the community prioritize what matters most.</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h5>Community Collaboration</h5>
                            <p>Work with neighbors, community leaders, and local officials.</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-lightbulb"></i>
                            </div>
                            <h5>Find Solutions</h5>
                            <p>Co-create practical solutions together.</p>
                        </div>
                    </div>
                    
                    <div class="welcome-actions">
                        <h4>Join Our Community Voting Platform Today!</h4>
                        <p>
                            To access community issues and participate in democratic voting, please create an account.
                        </p>
                        <div class="action-buttons">
                            <a href="auth/register.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus"></i> Create Account
                            </a>
                            <a href="auth/login.php" class="btn btn-secondary btn-lg">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
  </div>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="text-center py-4">
        <p class="text-muted mb-0">
          <i class="fas fa-heart text-danger me-1"></i>
          Built for civic community engagement and democratic participation<br>
          <small>Empowering communities through democratic voting and local issue resolution</small>
        </p>
      </div>
    </div>
  </footer>

  <script src="assets/js/theme.js"></script>
  <script src="assets/js/voting.js"></script>
</body>
</html>
