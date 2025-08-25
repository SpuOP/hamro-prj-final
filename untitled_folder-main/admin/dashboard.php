<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
if (empty($_SESSION['is_admin'])) { redirect('index.html','Admin access required','warning'); }
$pdo = getDBConnection();
$csrf = generateCSRFToken();

// Enhanced statistics with growth indicators
$totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$activeUsers = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE last_login IS NOT NULL')->fetchColumn();
$totalIssues = (int)$pdo->query('SELECT COUNT(*) FROM issues')->fetchColumn();
$openIssues = (int)$pdo->query('SELECT COUNT(*) FROM issues')->fetchColumn();
$totalVotes = (int)$pdo->query('SELECT COUNT(*) FROM votes')->fetchColumn();
$flagged = (int)$pdo->query('SELECT COUNT(*) FROM comments WHERE is_flagged = 1')->fetchColumn();

// Growth calculations
$usersLastWeek = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();
$issuesLastWeek = (int)$pdo->query('SELECT COUNT(*) FROM issues WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();
$votesLastWeek = (int)$pdo->query('SELECT COUNT(*) FROM votes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();

$top7 = $pdo->query("SELECT id, title, vote_count FROM issues WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY vote_count DESC LIMIT 10")->fetchAll();
$top30 = $pdo->query("SELECT id, title, vote_count FROM issues WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY vote_count DESC LIMIT 10")->fetchAll();
$mostActive = $pdo->query("SELECT u.username, (SELECT COUNT(*) FROM issues i WHERE i.created_by=u.id)+(SELECT COUNT(*) FROM comments c WHERE c.user_id=u.id) AS score FROM users u ORDER BY score DESC LIMIT 10")->fetchAll();
$byCommunity = $pdo->query("SELECT c.name AS community, COUNT(i.id) AS issues_count FROM users u JOIN communities c ON c.id=u.community_id LEFT JOIN issues i ON i.created_by=u.id GROUP BY c.id ORDER BY issues_count DESC")->fetchAll();

?>
<?php include __DIR__ . '/../partials/header.php'; ?>

<?php include __DIR__ . '/../partials/sidebar.php'; ?>
<main class="main-content" id="main-content">
  <div class="dashboard-header">
    <h1>Admin Dashboard</h1>
    <p>Monitor and manage your community platform</p>
  </div>

  <!-- Statistics Cards -->
  <section class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">
        <i class="fas fa-users"></i>
      </div>
      <div class="stat-content">
        <div class="stat-number"><?php echo number_format($totalUsers); ?></div>
        <div class="stat-label">Total Users</div>
        <div class="stat-growth <?php echo $usersLastWeek > 0 ? 'positive' : 'negative'; ?>">
          <i class="fas fa-<?php echo $usersLastWeek > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
          <?php echo $usersLastWeek; ?> this week
        </div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">
        <i class="fas fa-user-check"></i>
      </div>
      <div class="stat-content">
        <div class="stat-number"><?php echo number_format($activeUsers); ?></div>
        <div class="stat-label">Active Users</div>
        <div class="stat-growth">
          <i class="fas fa-circle"></i>
          <?php echo round(($activeUsers / $totalUsers) * 100, 1); ?>% active
        </div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <div class="stat-content">
        <div class="stat-number"><?php echo number_format($totalIssues); ?></div>
        <div class="stat-label">Total Issues</div>
        <div class="stat-growth <?php echo $issuesLastWeek > 0 ? 'positive' : 'negative'; ?>">
          <i class="fas fa-<?php echo $issuesLastWeek > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
          <?php echo $issuesLastWeek; ?> this week
        </div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">
        <i class="fas fa-vote-yea"></i>
      </div>
      <div class="stat-content">
        <div class="stat-number"><?php echo number_format($totalVotes); ?></div>
        <div class="stat-label">Total Votes</div>
        <div class="stat-growth <?php echo $votesLastWeek > 0 ? 'positive' : 'negative'; ?>">
          <i class="fas fa-<?php echo $votesLastWeek > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
          <?php echo $votesLastWeek; ?> this week
        </div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">
        <i class="fas fa-flag"></i>
      </div>
      <div class="stat-content">
        <div class="stat-number"><?php echo number_format($flagged); ?></div>
        <div class="stat-label">Flagged Content</div>
        <div class="stat-growth">
          <i class="fas fa-exclamation-triangle"></i>
          Needs attention
        </div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">
        <i class="fas fa-chart-line"></i>
      </div>
      <div class="stat-content">
        <div class="stat-number"><?php echo number_format($openIssues); ?></div>
        <div class="stat-label">Open Issues</div>
        <div class="stat-growth">
          <i class="fas fa-clock"></i>
          Pending resolution
        </div>
      </div>
    </div>
  </section>

  <!-- Moderation Section -->
  <section class="moderation-section">
    <div class="moderation-grid">
      <div class="moderation-card">
        <div class="card-header">
          <h3><i class="fas fa-shield-alt"></i> Issue Moderation</h3>
          <a href="#" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="moderation-list">
          <?php foreach ($pdo->query('SELECT i.id, i.title, u.username, i.created_at FROM issues i JOIN users u ON u.id=i.created_by ORDER BY i.created_at DESC LIMIT 8') as $row): ?>
            <div class="moderation-item">
              <div class="item-content">
                <div class="item-title">
                  <strong>#<?php echo (int)$row['id']; ?></strong> 
                  <?php echo htmlspecialchars($row['title']); ?>
                </div>
                <div class="item-meta">
                  by <?php echo htmlspecialchars($row['username']); ?> • 
                  <?php echo date('M j', strtotime($row['created_at'])); ?>
                </div>
              </div>
              <div class="item-actions">
                <form method="post" class="inline-form">
                  <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                  <input type="hidden" name="issue_id" value="<?php echo (int)$row['id']; ?>">
                  <button class="btn btn-danger btn-sm" name="action" value="delete_issue">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="moderation-card">
        <div class="card-header">
          <h3><i class="fas fa-comments"></i> Comment Moderation</h3>
          <a href="#" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="moderation-list">
          <?php foreach ($pdo->query('SELECT c.id, c.comment, u.username, c.created_at FROM comments c JOIN users u ON u.id=c.user_id ORDER BY c.created_at DESC LIMIT 8') as $row): ?>
            <div class="moderation-item">
              <div class="item-content">
                <div class="item-title">
                  <?php echo htmlspecialchars(substr($row['comment'], 0, 60)); ?><?php if (strlen($row['comment']) > 60) echo '...'; ?>
                </div>
                <div class="item-meta">
                  by <?php echo htmlspecialchars($row['username']); ?> • 
                  <?php echo date('M j', strtotime($row['created_at'])); ?>
                </div>
              </div>
              <div class="item-actions">
                <form method="post" class="inline-form">
                  <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                  <input type="hidden" name="comment_id" value="<?php echo (int)$row['id']; ?>">
                  <button class="btn btn-danger btn-sm" name="action" value="delete_comment">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- Analytics Section -->
  <section class="analytics-section">
    <div class="analytics-grid">
      <div class="analytics-card">
        <div class="card-header">
          <h3><i class="fas fa-fire"></i> Trending Issues</h3>
        </div>
        <div class="trending-list">
          <div class="trending-section">
            <h4>Last 7 Days</h4>
            <?php foreach ($top7 as $r): ?>
              <div class="trending-item">
                <div class="trending-title"><?php echo htmlspecialchars($r['title']); ?></div>
                <div class="trending-votes"><?php echo (int)$r['vote_count']; ?> votes</div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="trending-section">
            <h4>Last 30 Days</h4>
            <?php foreach ($top30 as $r): ?>
              <div class="trending-item">
                <div class="trending-title"><?php echo htmlspecialchars($r['title']); ?></div>
                <div class="trending-votes"><?php echo (int)$r['vote_count']; ?> votes</div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="analytics-card">
        <div class="card-header">
          <h3><i class="fas fa-chart-bar"></i> Community Analytics</h3>
        </div>
        <div class="analytics-content">
          <div class="analytics-section">
            <h4>Most Active Users</h4>
            <?php foreach ($mostActive as $u): ?>
              <div class="analytics-item">
                <div class="analytics-label"><?php echo htmlspecialchars($u['username']); ?></div>
                <div class="analytics-value"><?php echo (int)$u['score']; ?> points</div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="analytics-section">
            <h4>Issues by Community</h4>
            <?php foreach ($byCommunity as $c): ?>
              <div class="analytics-item">
                <div class="analytics-label"><?php echo htmlspecialchars($c['community']); ?></div>
                <div class="analytics-value"><?php echo (int)$c['issues_count']; ?> issues</div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- User Management Section -->
  <section class="user-management-section">
    <div class="user-management-card">
      <div class="card-header">
        <h3><i class="fas fa-users-cog"></i> User Management</h3>
        <div class="search-box">
          <form method="get" class="search-form">
            <input type="text" name="q" placeholder="Search users..." class="form-control">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="fas fa-search"></i>
            </button>
          </form>
        </div>
      </div>
      <div class="user-table-container">
        <table class="user-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Email</th>
              <th>Verified</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pdo->query('SELECT id, username, email, is_verified FROM users ORDER BY id DESC LIMIT 20') as $u): ?>
              <tr>
                <td><?php echo (int)$u['id']; ?></td>
                <td><?php echo htmlspecialchars($u['username']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td>
                  <span class="status-badge <?php echo (int)$u['is_verified'] ? 'verified' : 'unverified'; ?>">
                    <?php echo (int)$u['is_verified'] ? 'Verified' : 'Unverified'; ?>
                  </span>
                </td>
                <td>
                  <form method="post" class="inline-form">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                    <button class="btn btn-warning btn-sm" name="action" value="suspend_user">
                      <i class="fas fa-ban"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- Voting Activity Chart -->
  <section class="chart-section">
    <div class="chart-card">
      <div class="card-header">
        <h3><i class="fas fa-chart-line"></i> Voting Activity</h3>
      </div>
      <div class="chart-container">
        <canvas id="votesChart" height="120"></canvas>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  var ctx = document.getElementById('votesChart');
  if (!ctx) return;
  
  var chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
      datasets: [{
        label: 'Votes',
        data: [3, 5, 2, 8, 6, 4, 7],
        borderColor: 'var(--accent-color)',
        backgroundColor: 'rgba(124, 58, 237, 0.1)',
        tension: 0.3,
        fill: true
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: true,
          labels: {
            color: 'var(--text-primary)'
          }
        }
      },
      scales: {
        x: {
          grid: {
            display: false
          },
          ticks: {
            color: 'var(--text-secondary)'
          }
        },
        y: {
          beginAtZero: true,
          grid: {
            color: 'var(--border-color)'
          },
          ticks: {
            color: 'var(--text-secondary)'
          }
        }
      }
    }
  });
})();
</script>


