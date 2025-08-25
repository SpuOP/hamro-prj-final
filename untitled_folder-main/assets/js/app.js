// CivicPulse Platform - Main Application JavaScript

class CivicPulseApp {
  constructor() {
    this.currentUser = null;
    this.issues = [];
    this.currentSort = 'popular';
    this.init();
  }

  async init() {
    await this.checkAuthStatus();
    this.setupEventListeners();
    this.updateUI();
    
    if (this.currentUser) {
      await this.loadIssues();
    }
  }

  async checkAuthStatus() {
    try {
      const response = await fetch('api/auth/status.php');
      const data = await response.json();
      
      if (data.success && data.user) {
        this.currentUser = data.user;
      }
    } catch (error) {
      console.log('User not authenticated');
    }
  }

  setupEventListeners() {
    // Sort buttons
    document.querySelectorAll('.sort-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        this.handleSortChange(e.target.dataset.sort);
      });
    });

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
      searchInput.addEventListener('input', (e) => {
        this.handleSearch(e.target.value);
      });
    }
  }

  updateUI() {
    const loggedInNav = document.getElementById('loggedInNav');
    const loggedOutNav = document.getElementById('loggedOutNav');
    const loggedInActions = document.getElementById('loggedInActions');
    const loggedOutActions = document.getElementById('loggedOutActions');
    const issuesSection = document.getElementById('issuesSection');
    const welcomeSection = document.getElementById('welcomeSection');
    const loadingState = document.getElementById('loadingState');
    const adminNav = document.getElementById('adminNav');

    if (this.currentUser) {
      // Show logged in UI
      loggedInNav.style.display = 'block';
      loggedOutNav.style.display = 'none';
      loggedInActions.style.display = 'block';
      loggedOutActions.style.display = 'none';
      issuesSection.style.display = 'block';
      welcomeSection.style.display = 'none';
      
      // Show admin nav if user is admin
      if (this.currentUser.is_admin) {
        adminNav.style.display = 'block';
      }
    } else {
      // Show logged out UI
      loggedInNav.style.display = 'none';
      loggedOutNav.style.display = 'block';
      loggedInActions.style.display = 'none';
      loggedOutActions.style.display = 'block';
      issuesSection.style.display = 'none';
      welcomeSection.style.display = 'block';
    }

    // Hide loading state
    loadingState.style.display = 'none';
  }

  async loadIssues() {
    try {
      const response = await fetch(`api/issues.php?sort=${this.currentSort}`);
      const data = await response.json();
      
      if (data.success) {
        this.issues = data.issues;
        this.renderIssues();
        this.updateIssueCount();
      } else {
        this.showError('Failed to load issues');
      }
    } catch (error) {
      console.error('Error loading issues:', error);
      this.showError('Failed to load issues');
    }
  }

  renderIssues() {
    const issuesList = document.getElementById('issuesList');
    
    if (this.issues.length === 0) {
      issuesList.innerHTML = `
        <div class="empty-state">
          <div class="empty-icon">
            <i class="fas fa-inbox"></i>
          </div>
          <h3>No issues found</h3>
          <p>Be the first to post a community issue!</p>
          <a href="issues/create.html" class="btn btn-primary">
            <i class="fas fa-plus"></i> Post First Issue
          </a>
        </div>
      `;
      return;
    }

    issuesList.innerHTML = this.issues.map(issue => this.renderIssueCard(issue)).join('');
    
    // Reinitialize voting system for new issue cards
    if (window.votingSystem) {
      window.votingSystem.bindVoteEvents();
    }
  }

  renderIssueCard(issue) {
    const initial = issue.author_name ? issue.author_name.charAt(0).toUpperCase() : 'U';
    const desc = issue.description || '';
    const tag = this.determineTag(desc, issue.title);
    const latestComment = issue.latest_comment || '';
    const latestAuthor = issue.latest_comment_author || '';
    const truncatedDesc = desc.length > 160 ? desc.substring(0, 160) + '...' : desc;
    const truncatedComment = latestComment.length > 120 ? latestComment.substring(0, 120) + '...' : latestComment;

    return `
      <div class="issue-card">
        <div class="issue-header">
          <!-- Vote rail -->
          <div class="issue-voting">
            <button class="vote-btn upvote-btn" data-issue-id="${issue.id}" data-vote-type="upvote">
              <i class="fas fa-chevron-up"></i>
            </button>
            <div class="vote-count ${issue.vote_count > 0 ? 'text-success' : issue.vote_count < 0 ? 'text-danger' : 'text-muted'}" data-issue-id="${issue.id}">
              ${issue.vote_count}
            </div>
            <button class="vote-btn downvote-btn" data-issue-id="${issue.id}" data-vote-type="downvote">
              <i class="fas fa-chevron-down"></i>
            </button>
          </div>

          <!-- Main -->
          <div class="issue-content">
            <div class="issue-title">
              <a href="issues/view.html?id=${issue.id}">
                ${this.escapeHtml(issue.title)}
              </a>
              ${issue.image_path ? `
                <span class="issue-tag">
                  <i class="fas fa-image"></i> Image
                </span>
              ` : ''}
            </div>
            <p class="issue-description">
              ${this.escapeHtml(truncatedDesc)}
            </p>

            <div class="issue-meta">
              <span class="issue-tag">#${this.escapeHtml(tag)}</span>
              <span class="issue-tag">${issue.comment_count} comments</span>
              <span class="issue-tag"><i class="fas fa-clock"></i> ${this.formatDate(issue.created_at)}</span>
            </div>

            ${latestComment ? `
              <div class="latest-comment">
                <div class="author-avatar">
                  ${this.escapeHtml(latestAuthor ? latestAuthor.charAt(0).toUpperCase() : 'U')}
                </div>
                <div class="comment-content">
                  <span class="comment-author">${this.escapeHtml(latestAuthor || 'User')}:</span>
                  ${this.escapeHtml(truncatedComment)}
                </div>
              </div>
            ` : ''}

            <div class="issue-footer">
              <div class="issue-author">
                <div class="author-avatar">
                  ${this.escapeHtml(initial)}
                </div>
                <span>by ${this.escapeHtml(issue.author_name)}</span>
              </div>
              <a href="issues/view.html?id=${issue.id}" class="btn btn-secondary btn-sm">
                View Details <i class="fas fa-arrow-right"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  determineTag(description, title) {
    const text = (description + ' ' + title).toLowerCase();
    if (text.includes('road') || text.includes('traffic')) return 'infrastructure';
    if (text.includes('trash') || text.includes('pollution') || text.includes('water')) return 'environment';
            return 'community';
  }

  async handleSortChange(sort) {
    this.currentSort = sort;
    
    // Update button states
    document.querySelectorAll('.sort-btn').forEach(btn => {
      btn.classList.remove('btn-primary');
      btn.classList.add('btn-secondary');
    });
    
    const activeBtn = document.querySelector(`[data-sort="${sort}"]`);
    if (activeBtn) {
      activeBtn.classList.remove('btn-secondary');
      activeBtn.classList.add('btn-primary');
    }
    
    // Reload issues with new sort
    await this.loadIssues();
  }

  handleSearch(query) {
    // Implement search functionality
    console.log('Search query:', query);
  }

  updateIssueCount() {
    const countElement = document.getElementById('issueCount');
    if (countElement) {
      countElement.textContent = this.issues.length;
    }
  }

  formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 1) return 'Today';
    if (diffDays === 2) return 'Yesterday';
    if (diffDays < 7) return `${diffDays - 1} days ago`;
    if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
    if (diffDays < 365) return `${Math.floor(diffDays / 30)} months ago`;
    return `${Math.floor(diffDays / 365)} years ago`;
  }

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  showError(message) {
    // Create and show error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'alert alert-danger';
    errorDiv.innerHTML = `
      <i class="fas fa-exclamation-triangle me-2"></i>${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const mainContent = document.getElementById('mainContent');
    mainContent.insertBefore(errorDiv, mainContent.firstChild);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
      if (errorDiv.parentNode) {
        errorDiv.remove();
      }
    }, 5000);
  }

  showSuccess(message) {
    // Create and show success message
    const successDiv = document.createElement('div');
    successDiv.className = 'alert alert-success';
    successDiv.innerHTML = `
      <i class="fas fa-check-circle me-2"></i>${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const mainContent = document.getElementById('mainContent');
    mainContent.insertBefore(successDiv, mainContent.firstChild);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
      if (successDiv.parentNode) {
        successDiv.remove();
      }
    }, 5000);
  }
}

// Add loading state CSS
const loadingStyles = document.createElement('style');
loadingStyles.textContent = `
  .loading-state {
    text-align: center;
    padding: var(--space-12);
  }
  
  .loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--border-color);
    border-top: 4px solid var(--accent-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto var(--space-4);
  }
  
  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
  
  .issues-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
  }
`;
document.head.appendChild(loadingStyles);

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  window.civicPulseApp = new CivicPulseApp();
});
