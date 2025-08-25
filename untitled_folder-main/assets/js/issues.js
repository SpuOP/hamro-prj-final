// Issues Management JavaScript for Community Issues Platform
class IssuesManager {
    constructor() {
        this.currentPage = 1;
        this.itemsPerPage = 20;
        this.currentFilters = {};
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadIssues();
        this.initInfiniteScroll();
    }

    bindEvents() {
        // Issue creation form
        const createIssueForm = document.getElementById('createIssueForm');
        if (createIssueForm) {
            createIssueForm.addEventListener('submit', (e) => this.handleCreateIssue(e));
        }

        // Filter controls
        const filterForm = document.getElementById('issueFilters');
        if (filterForm) {
            filterForm.addEventListener('change', (e) => this.handleFilterChange(e));
        }

        // Search functionality
        const searchInput = document.getElementById('issueSearch');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.currentFilters.search = e.target.value;
                    this.loadIssues(true);
                }, 500);
            });
        }

        // Category filter
        const categoryFilter = document.getElementById('categoryFilter');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', (e) => {
                this.currentFilters.category = e.target.value;
                this.loadIssues(true);
            });
        }

        // Status filter
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => {
                this.currentFilters.status = e.target.value;
                this.loadIssues(true);
            });
        }

        // Sort options
        const sortSelect = document.getElementById('sortIssues');
        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                this.currentFilters.sort = e.target.value;
                this.loadIssues(true);
            });
        }
    }

    initInfiniteScroll() {
        const issuesContainer = document.getElementById('issuesContainer');
        if (!issuesContainer) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadMoreIssues();
                }
            });
        }, { threshold: 0.1 });

        // Observe the last issue card
        const observeLastCard = () => {
            const cards = issuesContainer.querySelectorAll('.issue-card');
            if (cards.length > 0) {
                observer.observe(cards[cards.length - 1]);
            }
        };

        // Initial observation
        observeLastCard();
    }

    async loadIssues(reset = false) {
        const issuesContainer = document.getElementById('issuesContainer');
        if (!issuesContainer) return;

        if (reset) {
            this.currentPage = 1;
            issuesContainer.innerHTML = '';
        }

        // Show loading state
        if (this.currentPage === 1) {
            issuesContainer.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
        }

        try {
            const params = new URLSearchParams({
                action: 'list',
                limit: this.itemsPerPage,
                offset: (this.currentPage - 1) * this.itemsPerPage,
                ...this.currentFilters
            });

            const response = await fetch(`/api/issues.php?${params}`);
            const result = await response.json();

            if (result.success) {
                if (this.currentPage === 1) {
                    issuesContainer.innerHTML = '';
                }

                if (result.data.length === 0 && this.currentPage === 1) {
                    issuesContainer.innerHTML = `
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No issues found</h5>
                            <p class="text-muted">Try adjusting your filters or be the first to post an issue!</p>
                        </div>
                    `;
                    return;
                }

                result.data.forEach(issue => {
                    issuesContainer.appendChild(this.createIssueCard(issue));
                });

                this.currentPage++;
            } else {
                this.showError(result.message);
            }
        } catch (error) {
            console.error('Error loading issues:', error);
            this.showError('Failed to load issues. Please try again.');
        }
    }

    async loadMoreIssues() {
        await this.loadIssues(false);
    }

    createIssueCard(issue) {
        const card = document.createElement('div');
        card.className = 'issue-card card mb-3';
        card.innerHTML = `
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="card-title mb-1">
                            <a href="#" class="text-decoration-none" onclick="issuesManager.viewIssue(${issue.id})">
                                ${this.escapeHtml(issue.title)}
                            </a>
                        </h5>
                        <div class="text-muted small">
                            <i class="fas fa-user me-1"></i>${this.escapeHtml(issue.author_name)}
                            <span class="mx-2">•</span>
                            <i class="fas fa-clock me-1"></i>${issue.created_ago}
                            <span class="mx-2">•</span>
                            <i class="fas fa-map-marker-alt me-1"></i>${this.escapeHtml(issue.city_name)}
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-${this.getCategoryColor(issue.category)}">${issue.category}</span>
                        <span class="badge bg-${this.getPriorityColor(issue.priority)} ms-1">${issue.priority}</span>
                    </div>
                </div>
                
                <p class="card-text text-muted">${this.escapeHtml(issue.description.substring(0, 150))}${issue.description.length > 150 ? '...' : ''}</p>
                
                ${issue.image_path ? `
                    <div class="mb-3">
                        <img src="/${issue.image_path}" class="img-fluid rounded" style="max-height: 200px; object-fit: cover;" alt="Issue Image">
                    </div>
                ` : ''}
                
                <div class="d-flex justify-content-between align-items-center">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-primary ${issue.user_vote === 'upvote' ? 'active' : ''}" 
                                onclick="issuesManager.voteIssue(${issue.id}, 'upvote')">
                            <i class="fas fa-thumbs-up"></i>
                            <span class="ms-1">${issue.upvotes || 0}</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger ${issue.user_vote === 'downvote' ? 'active' : ''}" 
                                onclick="issuesManager.voteIssue(${issue.id}, 'downvote')">
                            <i class="fas fa-thumbs-down"></i>
                            <span class="ms-1">${issue.downvotes || 0}</span>
                        </button>
                    </div>
                    
                    <div class="text-muted small">
                        <i class="fas fa-comments me-1"></i>${issue.comments_count || 0} comments
                    </div>
                </div>
            </div>
        `;
        return card;
    }

    async handleCreateIssue(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Issue...';
        
        try {
            const formData = new FormData(form);
            formData.append('action', 'create');
            
            const response = await fetch('/api/issues.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Issue created successfully!');
                form.reset();
                
                // Close modal if exists
                const modal = bootstrap.Modal.getInstance(document.getElementById('createIssueModal'));
                if (modal) {
                    modal.hide();
                }
                
                // Reload issues
                this.loadIssues(true);
            } else {
                this.showError(result.message);
            }
        } catch (error) {
            console.error('Error creating issue:', error);
            this.showError('Failed to create issue. Please try again.');
        } finally {
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    async voteIssue(issueId, voteType) {
        try {
            const formData = new FormData();
            formData.append('action', 'vote');
            formData.append('issue_id', issueId);
            formData.append('vote_type', voteType);
            
            const response = await fetch('/api/issues.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Update the vote buttons visually
                this.updateVoteButtons(issueId, voteType);
            } else {
                this.showError(result.message);
            }
        } catch (error) {
            console.error('Error voting:', error);
            this.showError('Failed to record vote. Please try again.');
        }
    }

    updateVoteButtons(issueId, voteType) {
        const issueCard = document.querySelector(`[data-issue-id="${issueId}"]`) || 
                         document.querySelector(`.issue-card:has(a[onclick*="${issueId}"])`);
        
        if (!issueCard) return;
        
        const upvoteBtn = issueCard.querySelector('button[onclick*="upvote"]');
        const downvoteBtn = issueCard.querySelector('button[onclick*="downvote"]');
        
        // Reset both buttons
        upvoteBtn.classList.remove('active');
        downvoteBtn.classList.remove('active');
        
        // Activate the voted button
        if (voteType === 'upvote') {
            upvoteBtn.classList.add('active');
        } else if (voteType === 'downvote') {
            downvoteBtn.classList.add('active');
        }
    }

    async viewIssue(issueId) {
        try {
            const response = await fetch(`/api/issues.php?action=single&id=${issueId}`);
            const result = await response.json();
            
            if (result.success) {
                this.showIssueModal(result.data);
            } else {
                this.showError(result.message);
            }
        } catch (error) {
            console.error('Error loading issue:', error);
            this.showError('Failed to load issue details.');
        }
    }

    showIssueModal(issue) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'issueModal';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${this.escapeHtml(issue.title)}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>${this.escapeHtml(issue.author_name)}</strong>
                                    <span class="text-muted ms-2">${issue.created_ago}</span>
                                </div>
                                <div>
                                    <span class="badge bg-${this.getCategoryColor(issue.category)}">${issue.category}</span>
                                    <span class="badge bg-${this.getPriorityColor(issue.priority)} ms-1">${issue.priority}</span>
                                </div>
                            </div>
                        </div>
                        
                        <p class="mb-3">${this.escapeHtml(issue.description)}</p>
                        
                        ${issue.image_path ? `
                            <div class="mb-3">
                                <img src="/${issue.image_path}" class="img-fluid rounded" alt="Issue Image">
                            </div>
                        ` : ''}
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-primary ${issue.user_vote === 'upvote' ? 'active' : ''}" 
                                        onclick="issuesManager.voteIssue(${issue.id}, 'upvote')">
                                    <i class="fas fa-thumbs-up"></i>
                                    <span class="ms-1">${issue.upvotes || 0}</span>
                                </button>
                                <button type="button" class="btn btn-outline-danger ${issue.user_vote === 'downvote' ? 'active' : ''}" 
                                        onclick="issuesManager.voteIssue(${issue.id}, 'downvote')">
                                    <i class="fas fa-thumbs-down"></i>
                                    <span class="ms-1">${issue.downvotes || 0}</span>
                                </button>
                            </div>
                            
                            <div class="text-muted">
                                <i class="fas fa-comments me-1"></i>${issue.comments_count || 0} comments
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h6>Comments</h6>
                        <div id="commentsContainer">
                            ${this.renderComments(issue.comments || [])}
                        </div>
                        
                        <form id="commentForm" class="mt-3">
                            <div class="input-group">
                                <input type="text" class="form-control" id="commentText" placeholder="Add a comment..." required>
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
        
        // Handle comment submission
        const commentForm = modal.querySelector('#commentForm');
        commentForm.addEventListener('submit', (e) => this.handleAddComment(e, issue.id));
        
        // Clean up modal when hidden
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }

    renderComments(comments) {
        if (comments.length === 0) {
            return '<p class="text-muted">No comments yet. Be the first to comment!</p>';
        }
        
        return comments.map(comment => `
            <div class="comment mb-2">
                <div class="d-flex">
                    <div class="flex-grow-1">
                        <strong>${this.escapeHtml(comment.author_name)}</strong>
                        <span class="text-muted ms-2">${this.formatDate(comment.created_at)}</span>
                        <p class="mb-1">${this.escapeHtml(comment.comment)}</p>
                    </div>
                </div>
            </div>
        `).join('');
    }

    async handleAddComment(e, issueId) {
        e.preventDefault();
        
        const form = e.target;
        const input = form.querySelector('#commentText');
        const comment = input.value.trim();
        
        if (!comment) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'comment');
            formData.append('issue_id', issueId);
            formData.append('comment', comment);
            
            const response = await fetch('/api/issues.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Clear input
                input.value = '';
                
                // Reload comments
                this.loadComments(issueId);
            } else {
                this.showError(result.message);
            }
        } catch (error) {
            console.error('Error adding comment:', error);
            this.showError('Failed to add comment. Please try again.');
        }
    }

    async loadComments(issueId) {
        try {
            const response = await fetch(`/api/issues.php?action=single&id=${issueId}`);
            const result = await response.json();
            
            if (result.success) {
                const commentsContainer = document.querySelector('#commentsContainer');
                if (commentsContainer) {
                    commentsContainer.innerHTML = this.renderComments(result.data.comments || []);
                }
            }
        } catch (error) {
            console.error('Error loading comments:', error);
        }
    }

    handleFilterChange(e) {
        const filterName = e.target.name;
        const filterValue = e.target.value;
        
        if (filterValue) {
            this.currentFilters[filterName] = filterValue;
        } else {
            delete this.currentFilters[filterName];
        }
        
        this.loadIssues(true);
    }

    getCategoryColor(category) {
        const colors = {
            'infrastructure': 'primary',
            'sanitation': 'warning',
            'transportation': 'info',
            'safety': 'danger',
            'environment': 'success',
            'community': 'secondary',
            'health': 'danger',
            'other': 'dark'
        };
        return colors[category] || 'secondary';
    }

    getPriorityColor(priority) {
        const colors = {
            'low': 'success',
            'medium': 'warning',
            'high': 'danger',
            'critical': 'dark'
        };
        return colors[priority] || 'secondary';
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
        if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
        
        return date.toLocaleDateString();
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showSuccess(message) {
        this.showMessage(message, 'success');
    }

    showError(message) {
        this.showMessage(message, 'danger');
    }

    showMessage(message, type = 'info') {
        const messageDiv = document.createElement('div');
        messageDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        messageDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        messageDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(messageDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }
}

// Initialize issues manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.issuesManager = new IssuesManager();
});

// Export for use in other modules
window.IssuesManager = IssuesManager;
