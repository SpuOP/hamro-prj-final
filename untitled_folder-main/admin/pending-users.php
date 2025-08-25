<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

// Check admin access
if (empty($_SESSION['is_admin'])) { 
    redirect('../auth/login.php', 'Admin access required', 'warning'); 
}

$pdo = getDBConnection();
$csrf = generateCSRFToken();

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $application_id = (int)($_POST['application_id'] ?? 0);
    
    try {
        switch ($action) {
            case 'approve_user':
                if ($application_id > 0) {
                    $special_id = approveUserApplication($application_id, $_SESSION['admin_id']);
                    $message = "User approved successfully. Special ID: $special_id";
                    $message_type = 'success';
                }
                break;
                
            case 'reject_user':
                if ($application_id > 0) {
                    $reason = sanitizeInput($_POST['rejection_reason'] ?? '');
                    rejectUserApplication($application_id, $_SESSION['admin_id'], $reason);
                    $message = "User application rejected";
                    $message_type = 'warning';
                }
                break;
                
            case 'bulk_approve':
                $selected_ids = $_POST['selected_applications'] ?? [];
                $approved_count = 0;
                foreach ($selected_ids as $id) {
                    if (is_numeric($id)) {
                        approveUserApplication((int)$id, $_SESSION['admin_id']);
                        $approved_count++;
                    }
                }
                $message = "$approved_count applications approved successfully";
                $message_type = 'success';
                break;
                
            case 'bulk_reject':
                $selected_ids = $_POST['selected_applications'] ?? [];
                $reason = sanitizeInput($_POST['bulk_rejection_reason'] ?? '');
                $rejected_count = 0;
                foreach ($selected_ids as $id) {
                    if (is_numeric($id)) {
                        rejectUserApplication((int)$id, $_SESSION['admin_id'], $reason);
                        $rejected_count++;
                    }
                }
                $message = "$rejected_count applications rejected";
                $message_type = 'warning';
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Get pending applications with filters
$status_filter = $_GET['status'] ?? 'pending';
$city_filter = (int)($_GET['city'] ?? 0);
$document_filter = $_GET['document_type'] ?? '';

$where_conditions = ["ua.status = ?"];
$params = [$status_filter];

if ($city_filter > 0) {
    $where_conditions[] = "ua.city_id = ?";
    $params[] = $city_filter;
}

if (!empty($document_filter)) {
    $where_conditions[] = "ua.document_type = ?";
    $params[] = $document_filter;
}

$where_clause = implode(' AND ', $where_conditions);

$stmt = $pdo->prepare("
    SELECT ua.*, c.name as city_name, ma.name as metro_area_name,
           DATEDIFF(NOW(), ua.created_at) as days_pending
    FROM user_applications ua 
    LEFT JOIN cities c ON ua.city_id = c.id 
    LEFT JOIN metro_areas ma ON ua.metro_area_id = ma.id 
    WHERE $where_clause
    ORDER BY ua.created_at ASC
");
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Get cities for filter
$cities = $pdo->query("SELECT id, name FROM cities ORDER BY name")->fetchAll();

// Get document types for filter
$document_types = [
    'nic' => 'NIC',
    'citizenship' => 'Citizenship Certificate',
    'driving_license' => 'Driving License',
    'passport' => 'Passport',
    'utility_bill' => 'Utility Bill',
    'rental_agreement' => 'Rental Agreement',
    'bank_statement' => 'Bank Statement'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pending Applications - CivicPulse Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    /* Pending Users Management Styles */
    .pending-container {
      min-height: 100vh;
      background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
      padding: var(--space-6);
    }

    .page-header {
      background: var(--card-bg-glass);
      backdrop-filter: var(--backdrop-blur);
      -webkit-backdrop-filter: var(--backdrop-blur);
      border: var(--glass-border);
      border-radius: var(--radius-3xl);
      padding: var(--space-8);
      margin-bottom: var(--space-8);
      text-align: center;
      box-shadow: var(--shadow-xl);
    }

    .page-header h1 {
      color: var(--text-primary);
      margin-bottom: var(--space-4);
      font-size: 2.5rem;
    }

    .page-header p {
      color: var(--text-secondary);
      font-size: 1.125rem;
      margin: 0;
    }

    .filters-section {
      background: var(--card-bg-glass);
      backdrop-filter: var(--backdrop-blur);
      -webkit-backdrop-filter: var(--backdrop-blur);
      border: var(--glass-border);
      border-radius: var(--radius-2xl);
      padding: var(--space-6);
      margin-bottom: var(--space-6);
      box-shadow: var(--shadow-lg);
    }

    .filters-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: var(--space-4);
      align-items: end;
    }

    .filter-group {
      display: flex;
      flex-direction: column;
      gap: var(--space-2);
    }

    .filter-label {
      color: var(--text-primary);
      font-weight: 600;
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .filter-control {
      padding: var(--space-3);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-lg);
      background: var(--bg-secondary);
      color: var(--text-primary);
      font-size: 0.875rem;
      transition: all var(--transition-normal);
    }

    .filter-control:focus {
      outline: none;
      border-color: var(--accent-color);
      box-shadow: 0 0 0 3px var(--accent-light);
    }

    .filter-actions {
      display: flex;
      gap: var(--space-3);
      align-items: end;
    }

    .btn-filter {
      padding: var(--space-3) var(--space-5);
      border-radius: var(--radius-lg);
      font-weight: 600;
      border: none;
      cursor: pointer;
      transition: all var(--transition-normal);
      display: inline-flex;
      align-items: center;
      gap: var(--space-2);
    }

    .btn-filter:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg);
    }

    .applications-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
      gap: var(--space-6);
      margin-bottom: var(--space-8);
    }

    .application-card {
      background: var(--card-bg-glass);
      backdrop-filter: var(--backdrop-blur);
      -webkit-backdrop-filter: var(--backdrop-blur);
      border: var(--glass-border);
      border-radius: var(--radius-2xl);
      padding: var(--space-6);
      transition: all var(--transition-normal);
      position: relative;
      overflow: hidden;
    }

    .application-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, var(--warning), var(--accent-color));
      opacity: 0.8;
    }

    .application-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-xl);
      border-color: var(--accent-color);
    }

    .app-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: var(--space-4);
    }

    .app-title {
      color: var(--text-primary);
      font-size: 1.25rem;
      font-weight: 600;
      margin: 0;
    }

    .app-status {
      padding: var(--space-2) var(--space-3);
      border-radius: var(--radius-lg);
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .app-status.pending {
      background: var(--warning-light);
      color: var(--warning-dark);
    }

    .app-status.approved {
      background: var(--success-light);
      color: var(--success-dark);
    }

    .app-status.rejected {
      background: var(--danger-light);
      color: var(--danger-dark);
    }

    .app-info {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: var(--space-4);
      margin-bottom: var(--space-4);
    }

    .info-item {
      display: flex;
      flex-direction: column;
      gap: var(--space-1);
    }

    .info-label {
      color: var(--text-secondary);
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      font-weight: 600;
    }

    .info-value {
      color: var(--text-primary);
      font-weight: 500;
    }

    .document-section {
      margin-bottom: var(--space-4);
    }

    .document-preview {
      width: 100%;
      height: 200px;
      border-radius: var(--radius-lg);
      overflow: hidden;
      background: var(--bg-secondary);
      display: flex;
      align-items: center;
      justify-content: center;
      border: 2px dashed var(--border-color);
      transition: all var(--transition-normal);
      cursor: pointer;
    }

    .document-preview:hover {
      border-color: var(--accent-color);
      background: var(--bg-tertiary);
    }

    .document-preview img {
      max-width: 100%;
      max-height: 100%;
      object-fit: cover;
      border-radius: var(--radius-md);
    }

    .document-preview .placeholder {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: var(--space-2);
      color: var(--text-secondary);
    }

    .document-preview .placeholder i {
      font-size: 2rem;
      opacity: 0.5;
    }

    .app-actions {
      display: flex;
      gap: var(--space-3);
      flex-wrap: wrap;
    }

    .btn-action {
      padding: var(--space-3) var(--space-4);
      border-radius: var(--radius-lg);
      font-weight: 600;
      border: none;
      cursor: pointer;
      transition: all var(--transition-normal);
      display: inline-flex;
      align-items: center;
      gap: var(--space-2);
      font-size: 0.875rem;
    }

    .btn-action:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg);
    }

    .btn-approve {
      background: var(--success);
      color: white;
    }

    .btn-approve:hover {
      background: var(--success-dark);
    }

    .btn-reject {
      background: var(--danger);
      color: white;
    }

    .btn-reject:hover {
      background: var(--danger-dark);
    }

    .btn-view {
      background: var(--accent-color);
      color: white;
    }

    .btn-view:hover {
      background: var(--accent-hover);
    }

    .btn-secondary {
      background: var(--bg-tertiary);
      color: var(--text-primary);
      border: 1px solid var(--border-color);
    }

    .btn-secondary:hover {
      background: var(--bg-secondary);
      border-color: var(--accent-color);
    }

    .pagination {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: var(--space-3);
      margin-top: var(--space-8);
    }

    .pagination-btn {
      padding: var(--space-3) var(--space-4);
      border-radius: var(--radius-lg);
      background: var(--card-bg-glass);
      border: var(--glass-border);
      color: var(--text-primary);
      cursor: pointer;
      transition: all var(--transition-normal);
      display: inline-flex;
      align-items: center;
      gap: var(--space-2);
    }

    .pagination-btn:hover:not(:disabled) {
      background: var(--accent-color);
      color: white;
      border-color: var(--accent-color);
    }

    .pagination-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .pagination-info {
      color: var(--text-secondary);
      font-size: 0.875rem;
    }

    .empty-state {
      text-align: center;
      padding: var(--space-12);
      color: var(--text-secondary);
    }

    .empty-state i {
      font-size: 4rem;
      margin-bottom: var(--space-4);
      opacity: 0.5;
    }

    .empty-state h3 {
      color: var(--text-primary);
      margin-bottom: var(--space-2);
    }

    .loading-state {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: var(--space-4);
      padding: var(--space-12);
      color: var(--text-secondary);
    }

    .loading-spinner {
      width: 40px;
      height: 40px;
      border: 4px solid var(--border-color);
      border-top: 4px solid var(--accent-color);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    /* Modal Styles */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(10px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      padding: var(--space-4);
    }

    .modal {
      background: var(--card-bg-glass);
      backdrop-filter: var(--backdrop-blur);
      -webkit-backdrop-filter: var(--backdrop-blur);
      border: var(--glass-border);
      border-radius: var(--radius-3xl);
      max-width: 500px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: var(--shadow-2xl);
    }

    .modal-header {
      padding: var(--space-6);
      border-bottom: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .modal-header h3 {
      color: var(--text-primary);
      margin: 0;
    }

    .modal-close {
      background: none;
      border: none;
      color: var(--text-secondary);
      cursor: pointer;
      padding: var(--space-2);
      border-radius: var(--radius-md);
      transition: all var(--transition-normal);
    }

    .modal-close:hover {
      background: var(--bg-secondary);
      color: var(--text-primary);
    }

    .modal-body {
      padding: var(--space-6);
    }

    .modal-footer {
      padding: var(--space-6);
      border-top: 1px solid var(--border-color);
      display: flex;
      gap: var(--space-3);
      justify-content: flex-end;
    }

    @media (max-width: 768px) {
      .pending-container {
        padding: var(--space-4);
      }

      .applications-grid {
        grid-template-columns: 1fr;
      }

      .app-info {
        grid-template-columns: 1fr;
      }

      .app-actions {
        flex-direction: column;
      }

      .filters-grid {
        grid-template-columns: 1fr;
      }

      .filter-actions {
        justify-content: center;
      }

      .page-header h1 {
        font-size: 2rem;
      }
    }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="header-left">
      <button id="sidebarToggle" class="burger-menu" aria-label="Toggle Sidebar">
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
      </button>
      <h1 class="site-title">CivicPulse</h1>
    </div>
    <div class="site-actions">
      <div class="user-menu" id="userMenu" style="display:none"></div>
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
        <span class="sun-icon">☀️</span>
        <span class="moon-icon">🌙</span>
      </button>
    </div>
  </header>
  <div class="pending-container">
    <div id="loadingOverlay" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.35);backdrop-filter:blur(4px);z-index:9999;">
      <div style="background:var(--card-bg-glass);border:var(--glass-border);padding:18px 20px;border-radius:16px;display:flex;align-items:center;gap:12px;box-shadow:var(--shadow-xl);">
        <div style="width:18px;height:18px;border:3px solid var(--border-color);border-top-color:var(--accent-color);border-radius:50%;animation:spin 1s linear infinite"></div>
        <span>Checking access…</span>
      </div>
    </div>
    <!-- Page Header -->
    <div class="page-header">
      <h1><i class="fas fa-user-clock"></i> Pending Applications</h1>
      <p>Review and manage community membership applications</p>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
      <div class="filters-grid">
        <div class="filter-group">
          <label class="filter-label">Status</label>
          <select class="filter-control" id="statusFilter">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>

        <div class="filter-group">
          <label class="filter-label">City</label>
          <select class="filter-control" id="cityFilter">
            <option value="">All Cities</option>
            <!-- Cities will be loaded dynamically -->
          </select>
        </div>

        <div class="filter-group">
          <label class="filter-label">Date Range</label>
          <select class="filter-control" id="dateFilter">
            <option value="">All Time</option>
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
          </select>
        </div>

        <div class="filter-group">
          <label class="filter-label">Search</label>
          <input type="text" class="filter-control" id="searchFilter" placeholder="Search by name or email...">
        </div>

        <div class="filter-actions">
          <button class="btn btn-primary btn-filter" onclick="applyFilters()">
            <i class="fas fa-filter"></i> Apply Filters
          </button>
          <button class="btn btn-secondary btn-filter" onclick="clearFilters()">
            <i class="fas fa-times"></i> Clear
          </button>
        </div>
      </div>
    </div>

    <!-- Bulk Actions Bar -->
    <div class="filters-section" style="display:flex;align-items:center;gap:16px;justify-content:space-between;">
      <div style="display:flex;align-items:center;gap:12px;">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
          <input type="checkbox" id="selectAll" />
          <span>Select All</span>
        </label>
        <button class="btn btn-primary" id="bulkApproveBtn"><i class="fas fa-check"></i> Bulk Approve</button>
        <button class="btn btn-secondary" id="bulkRejectBtn"><i class="fas fa-times"></i> Bulk Reject</button>
      </div>
      <button class="btn btn-secondary" onclick="exportApplications()"><i class="fas fa-download"></i> Export</button>
    </div>

    <!-- Applications Grid -->
    <div id="applicationsContainer">
      <!-- Applications will be loaded here -->
    </div>

    <!-- Pagination -->
    <div class="pagination" id="pagination" style="display: none;">
      <button class="pagination-btn" id="prevPage" onclick="changePage(-1)">
        <i class="fas fa-chevron-left"></i> Previous
      </button>
      
      <span class="pagination-info" id="pageInfo">
        Page 1 of 1
      </span>
      
      <button class="pagination-btn" id="nextPage" onclick="changePage(1)">
        Next <i class="fas fa-chevron-right"></i>
      </button>
    </div>
  </div>

  <footer class="footer">
    <div class="container">
      <div class="footer-links" style="display:flex;gap:16px;justify-content:center;padding:16px 0;">
        <a href="../pages/about.html">About</a>
        <a href="../pages/contact.html">Contact</a>
        <a href="#">Privacy</a>
        <a href="#">Terms</a>
        <a href="#">Support</a>
      </div>
    </div>
  </footer>

  <!-- Application Detail Modal -->
  <div class="modal-overlay" id="applicationModal" style="display: none;">
    <div class="modal">
      <div class="modal-header">
        <h3>Application Details</h3>
        <button class="modal-close" onclick="closeModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body" id="modalBody">
        <!-- Modal content will be loaded here -->
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeModal()">Close</button>
        <button class="btn btn-approve" id="modalApproveBtn" onclick="approveApplication()">
          <i class="fas fa-check"></i> Approve
        </button>
        <button class="btn btn-reject" id="modalRejectBtn" onclick="rejectApplication()">
          <i class="fas fa-times"></i> Reject
        </button>
      </div>
    </div>
  </div>

  <script src="../assets/js/admin.js"></script>
  <script>
    const AUTH_STATUS_URL = '/untitled_folder-main/api/admin/auth/status.php';
    const LOGIN_REDIRECT = '/untitled_folder-main/auth/login.html';
    const loadingOverlay = document.getElementById('loadingOverlay');

    function setOverlay(visible) { loadingOverlay.style.display = visible ? 'flex' : 'none'; }

    async function requireAdminAuth() {
      try {
        setOverlay(true);
        const res = await fetch(AUTH_STATUS_URL, { credentials: 'include' });
        if (res.status === 401) { window.location.href = LOGIN_REDIRECT; return false; }
        const data = await res.json();
        if (!data || !data.authenticated) { window.location.href = LOGIN_REDIRECT; return false; }
        return true;
      } catch (e) {
        window.location.href = LOGIN_REDIRECT; return false;
      } finally { setOverlay(false); }
    }

    // Global variables
    let currentApplications = [];
    let currentPage = 1;
    let totalPages = 1;
    let currentFilters = {
      status: '',
      city: '',
      dateRange: '',
      search: ''
    };

    // Initialize page
    document.addEventListener('DOMContentLoaded', async () => {
      const ok = await requireAdminAuth();
      if (!ok) return;
      loadApplications();
      loadCities();
      setupEventListeners();
    });

    // Setup event listeners
    function setupEventListeners() {
      // Filter change events
      document.getElementById('statusFilter').addEventListener('change', (e) => {
        currentFilters.status = e.target.value;
      });

      document.getElementById('cityFilter').addEventListener('change', (e) => {
        currentFilters.city = e.target.value;
      });

      document.getElementById('dateFilter').addEventListener('change', (e) => {
        currentFilters.dateRange = e.target.value;
      });

      document.getElementById('searchFilter').addEventListener('input', (e) => {
        currentFilters.search = e.target.value;
      });

      // Search on Enter key
      document.getElementById('searchFilter').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          applyFilters();
        }
      });

      // Bulk selection
      document.getElementById('selectAll').addEventListener('change', (e) => {
        document.querySelectorAll('.app-select').forEach(cb => { cb.checked = e.target.checked; });
      });
      document.getElementById('bulkApproveBtn').addEventListener('click', () => handleBulk('approve'));
      document.getElementById('bulkRejectBtn').addEventListener('click', () => handleBulk('reject'));
    }

    // Load applications
    async function loadApplications() {
      const container = document.getElementById('applicationsContainer');
      container.innerHTML = `
        <div class="loading-state">
          <div class="loading-spinner"></div>
          <p>Loading applications...</p>
        </div>
      `;

      try {
        const queryParams = new URLSearchParams({
          page: currentPage,
          ...currentFilters
        });

        const response = await fetch(`../api/admin/pending-users.php?${queryParams}`);
        const data = await response.json();

        if (data.success) {
          currentApplications = data.applications;
          totalPages = data.total_pages;
          renderApplications();
          renderPagination();
        } else {
          throw new Error(data.message || 'Failed to load applications');
        }
      } catch (error) {
        console.error('Error loading applications:', error);
        container.innerHTML = `
          <div class="empty-state">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Error Loading Applications</h3>
            <p>${error.message}</p>
            <button class="btn btn-primary" onclick="loadApplications()">
              <i class="fas fa-refresh"></i> Try Again
            </button>
          </div>
        `;
      }
    }

    // Render applications
    function renderApplications() {
      const container = document.getElementById('applicationsContainer');

      if (currentApplications.length === 0) {
        container.innerHTML = `
          <div class="empty-state">
            <i class="fas fa-user-clock"></i>
            <h3>No Applications Found</h3>
            <p>There are no applications matching your current filters.</p>
          </div>
        `;
        return;
      }

      container.innerHTML = currentApplications.map(app => `
        <div class="application-card" data-id="${app.id}">
          <div class="app-header">
            <h3 class="app-title">${app.first_name} ${app.last_name}</h3>
            <span class="app-status ${app.status}">${app.status}</span>
          </div>
          <div style="position:absolute; top:14px; right:14px;">
            <input type="checkbox" class="app-select" data-id="${app.id}" />
          </div>

          <div class="app-info">
            <div class="info-item">
              <span class="info-label">Email</span>
              <span class="info-value">${app.email}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Phone</span>
              <span class="info-value">${app.phone || 'Not provided'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">City</span>
              <span class="info-value">${app.city || 'Not specified'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Submitted</span>
              <span class="info-value">${formatDate(app.created_at)}</span>
            </div>
          </div>

          <div class="document-section">
            <span class="info-label">ID Document</span>
            <div class="document-preview" onclick="viewDocument('${app.document_path}', '${app.first_name} ${app.last_name}')">
              ${app.document_path ? 
                `<img src="../${app.document_path}" alt="ID Document" onerror="this.parentElement.innerHTML='<div class=\\"placeholder\\"><i class=\\"fas fa-file-image\\"></i><span>Click to view</span></div>'">` :
                `<div class="placeholder">
                  <i class="fas fa-file-image"></i>
                  <span>No document uploaded</span>
                </div>`
              }
            </div>
          </div>

          <div class="app-actions">
            <button class="btn-action btn-view" onclick="viewApplication(${app.id})">
              <i class="fas fa-eye"></i> View Details
            </button>
            <button class="btn-action btn-approve" onclick="approveApplication(${app.id})">
              <i class="fas fa-check"></i> Approve
            </button>
            <button class="btn-action btn-reject" onclick="rejectApplication(${app.id})">
              <i class="fas fa-times"></i> Reject
            </button>
          </div>
        </div>
      `).join('');
    }

    // Render pagination
    function renderPagination() {
      const pagination = document.getElementById('pagination');
      
      if (totalPages <= 1) {
        pagination.style.display = 'none';
        return;
      }

      pagination.style.display = 'flex';
      document.getElementById('pageInfo').textContent = `Page ${currentPage} of ${totalPages}`;
      document.getElementById('prevPage').disabled = currentPage <= 1;
      document.getElementById('nextPage').disabled = currentPage >= totalPages;
    }

    // Change page
    function changePage(delta) {
      const newPage = currentPage + delta;
      if (newPage >= 1 && newPage <= totalPages) {
        currentPage = newPage;
        loadApplications();
      }
    }

    // Apply filters
    function applyFilters() {
      currentPage = 1;
      loadApplications();
    }

    // Clear filters
    function clearFilters() {
      currentFilters = {
        status: '',
        city: '',
        dateRange: '',
        search: ''
      };

      // Reset form controls
      document.getElementById('statusFilter').value = '';
      document.getElementById('cityFilter').value = '';
      document.getElementById('dateFilter').value = '';
      document.getElementById('searchFilter').value = '';

      currentPage = 1;
      loadApplications();
    }

    // Load cities for filter
    async function loadCities() {
      try {
        const response = await fetch('../api/cities.php');
        const data = await response.json();

        if (data.success) {
          const cityFilter = document.getElementById('cityFilter');
          data.cities.forEach(city => {
            const option = document.createElement('option');
            option.value = city.id;
            option.textContent = city.name;
            cityFilter.appendChild(option);
          });
        }
      } catch (error) {
        console.error('Error loading cities:', error);
      }
    }

    // View application details
    async function viewApplication(applicationId) {
      try {
        const response = await fetch(`../api/admin/pending-users.php?id=${applicationId}`);
        const data = await response.json();

        if (data.success) {
          const app = data.application;
          showApplicationModal(app);
        } else {
          throw new Error(data.message || 'Failed to load application details');
        }
      } catch (error) {
        console.error('Error loading application details:', error);
        showNotification('Error loading application details', 'error');
      }
    }

    // Show application modal
    function showApplicationModal(application) {
      const modal = document.getElementById('applicationModal');
      const modalBody = document.getElementById('modalBody');

      modalBody.innerHTML = `
        <div class="app-info">
          <div class="info-item">
            <span class="info-label">Full Name</span>
            <span class="info-value">${application.first_name} ${application.last_name}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Email</span>
            <span class="info-value">${application.email}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Phone</span>
            <span class="info-value">${application.phone || 'Not provided'}</span>
          </div>
          <div class="info-item">
            <span class="info-label">City</span>
            <span class="info-value">${application.city || 'Not specified'}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Address</span>
            <span class="info-value">${application.address || 'Not provided'}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Application ID</span>
            <span class="info-value">${application.application_id}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Submitted</span>
            <span class="info-value">${formatDate(application.created_at)}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Terms Accepted</span>
            <span class="info-value">${application.terms_accepted ? 'Yes' : 'No'}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Community Guidelines</span>
            <span class="info-value">${application.community_guidelines ? 'Accepted' : 'Not accepted'}</span>
          </div>
        </div>

        <div class="document-section">
          <span class="info-label">ID Document</span>
          <div class="document-preview" onclick="viewDocument('${application.document_path}', '${application.first_name} ${application.last_name}')">
            ${application.document_path ? 
              `<img src="../${application.document_path}" alt="ID Document" style="max-width: 100%; max-height: 300px; object-fit: contain;">` :
              `<div class="placeholder">
                <i class="fas fa-file-image"></i>
                <span>No document uploaded</span>
              </div>`
            }
          </div>
        </div>
      `;

      // Store application ID for approve/reject actions
      modal.dataset.applicationId = application.id;
      
      // Show/hide action buttons based on status
      const approveBtn = document.getElementById('modalApproveBtn');
      const rejectBtn = document.getElementById('modalRejectBtn');
      
      if (application.status === 'pending') {
        approveBtn.style.display = 'inline-flex';
        rejectBtn.style.display = 'inline-flex';
      } else {
        approveBtn.style.display = 'none';
        rejectBtn.style.display = 'none';
      }

      modal.style.display = 'flex';
    }

    // Close modal
    function closeModal() {
      document.getElementById('applicationModal').style.display = 'none';
    }

    // View document in full size
    function viewDocument(documentPath, applicantName) {
      if (!documentPath) return;
      
      const modal = document.createElement('div');
      modal.className = 'modal-overlay';
      modal.innerHTML = `
        <div class="modal" style="max-width: 90vw; max-height: 90vh;">
          <div class="modal-header">
            <h3>ID Document - ${applicantName}</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body" style="text-align: center; padding: 0;">
            <img src="../${documentPath}" alt="ID Document" style="max-width: 100%; max-height: 70vh; object-fit: contain;">
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
    }

    // Approve application
    async function approveApplication(applicationId = null) {
      const id = applicationId || document.getElementById('applicationModal').dataset.applicationId;
      
      if (!id) return;

      const confirmed = await confirmAction(
        'Are you sure you want to approve this application? The user will receive a Special Login ID.',
        'Approve Application'
      );

      if (!confirmed) return;

      try {
        const response = await fetch('../api/admin/pending-users.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            action: 'approve',
            application_id: id
          })
        });

        const data = await response.json();

        if (data.success) {
          showNotification(`Application approved! Special Login ID: ${data.special_login_id}`, 'success');
          closeModal();
          loadApplications(); // Refresh the list
        } else {
          throw new Error(data.message || 'Failed to approve application');
        }
      } catch (error) {
        console.error('Error approving application:', error);
        showNotification(error.message, 'error');
      }
    }

    // Reject application
    async function rejectApplication(applicationId = null) {
      const id = applicationId || document.getElementById('applicationModal').dataset.applicationId;
      
      if (!id) return;

      const reason = prompt('Please provide a reason for rejection:');
      if (!reason) return;

      const confirmed = await confirmAction(
        `Are you sure you want to reject this application?\n\nReason: ${reason}`,
        'Reject Application'
      );

      if (!confirmed) return;

      try {
        const response = await fetch('../api/admin/pending-users.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            action: 'reject',
            application_id: id,
            reason: reason
          })
        });

        const data = await response.json();

        if (data.success) {
          showNotification('Application rejected successfully', 'success');
          closeModal();
          loadApplications(); // Refresh the list
        } else {
          throw new Error(data.message || 'Failed to reject application');
        }
      } catch (error) {
        console.error('Error rejecting application:', error);
        showNotification(error.message, 'error');
      }
    }

    // Format date
    function formatDate(dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    }

    // Export applications
    function exportApplications() {
      if (currentApplications.length === 0) {
        showNotification('No applications to export', 'warning');
        return;
      }

      const exportData = currentApplications.map(app => ({
        'Application ID': app.application_id,
        'First Name': app.first_name,
        'Last Name': app.last_name,
        'Email': app.email,
        'Phone': app.phone || '',
        'City': app.city || '',
        'Status': app.status,
        'Submitted': app.created_at,
        'Terms Accepted': app.terms_accepted ? 'Yes' : 'No',
        'Community Guidelines': app.community_guidelines ? 'Accepted' : 'No'
      }));

      if (window.adminManager) {
        window.adminManager.exportData(exportData, `applications_${new Date().toISOString().split('T')[0]}.csv`, 'csv');
      }
    }

    async function handleBulk(action) {
      const selected = Array.from(document.querySelectorAll('.app-select:checked')).map(cb => cb.getAttribute('data-id'));
      if (selected.length === 0) { showNotification('Select at least one application', 'warning'); return; }
      const verb = action === 'approve' ? 'approve' : 'reject';
      const reason = action === 'reject' ? prompt('Provide a reason for rejection (optional):', '') : '';
      const confirmed = await confirmAction(`Are you sure you want to ${verb} ${selected.length} applications?`, `Bulk ${verb}`);
      if (!confirmed) return;
      try {
        const response = await fetch('../api/admin/pending-users.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: action === 'approve' ? 'bulk_approve' : 'bulk_reject', ids: selected, reason })
        });
        const data = await response.json();
        if (data.success) {
          showNotification(`Bulk ${verb} completed`, 'success');
          loadApplications();
        } else {
          throw new Error(data.message || 'Bulk operation failed');
        }
      } catch (e) {
        showNotification(e.message, 'error');
      }
    }
  </script>
</body>
</html>
