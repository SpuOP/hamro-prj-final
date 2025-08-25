// CivicPulse Platform - Enhanced AJAX Voting System

class VotingSystem {
  constructor() {
    this.voteCache = new Map(); // Cache for optimistic updates
    this.pendingVotes = new Set(); // Track pending votes
    this.initializeVoting();
  }

  initializeVoting() {
    document.addEventListener('DOMContentLoaded', () => {
      this.bindVoteEvents();
      this.initializeVoteStates();
    });
  }

  bindVoteEvents() {
    document.querySelectorAll('.vote-btn').forEach(button => {
      button.addEventListener('click', (e) => this.handleVote(e));
      
      // Keyboard support
      button.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          this.handleVote(e);
        }
      });
    });
  }

  initializeVoteStates() {
    // Initialize vote buttons with current user's votes
    document.querySelectorAll('.vote-btn').forEach(button => {
      const issueId = button.dataset.issueId;
      const voteType = button.dataset.voteType;
      
      // Check if user has already voted on this issue
      const userVote = this.getUserVote(issueId);
      if (userVote === voteType) {
        button.classList.add('active');
      }
    });
  }

  async handleVote(event) {
    event.preventDefault();
    
    const button = event.currentTarget;
    const issueId = button.dataset.issueId;
    const voteType = button.dataset.voteType;
    
    // Prevent multiple clicks
    if (this.pendingVotes.has(`${issueId}-${voteType}`)) {
      return;
    }
    
    // Add to pending votes
    this.pendingVotes.add(`${issueId}-${voteType}`);
    
    // Store current state for rollback
    const currentVoteCount = this.getVoteCount(issueId);
    const currentUserVote = this.getUserVote(issueId);
    
    // Optimistic update
    this.updateVoteOptimistically(issueId, voteType, button);
    
    try {
      // Show loading state
      this.showLoadingState(button);
      
      // Send AJAX request
      const response = await this.sendVoteRequest(issueId, voteType);
      
      if (response.success) {
        // Update with server response
        this.updateVoteCount(issueId, response.new_vote_count);
        this.updateVoteButtons(issueId, voteType);
        
        // Show success feedback
        this.showSuccessFeedback(button);
        this.showMessage('Vote recorded successfully!', 'success');
        
        // Update cache
        this.voteCache.set(issueId, {
          voteType: voteType,
          count: response.new_vote_count
        });
      } else {
        // Rollback on error
        this.rollbackVote(issueId, currentVoteCount, currentUserVote);
        this.showMessage(response.message || 'Failed to record vote', 'error');
      }
    } catch (error) {
      console.error('Voting error:', error);
      
      // Rollback on error
      this.rollbackVote(issueId, currentVoteCount, currentUserVote);
      this.showMessage('An error occurred while voting', 'error');
    } finally {
      // Remove from pending votes
      this.pendingVotes.delete(`${issueId}-${voteType}`);
      
      // Remove loading state
      this.hideLoadingState(button);
    }
  }

  updateVoteOptimistically(issueId, voteType, button) {
    const currentCount = this.getVoteCount(issueId);
    const currentUserVote = this.getUserVote(issueId);
    let newCount = currentCount;
    
    // Calculate new count based on vote logic
    if (currentUserVote === voteType) {
      // Remove vote
      newCount = voteType === 'upvote' ? currentCount - 1 : currentCount + 1;
      this.setUserVote(issueId, null);
    } else if (currentUserVote) {
      // Change vote
      if (voteType === 'upvote') {
        newCount = currentCount + 2; // Remove downvote (+1) and add upvote (+1)
      } else {
        newCount = currentCount - 2; // Remove upvote (-1) and add downvote (-1)
      }
      this.setUserVote(issueId, voteType);
    } else {
      // New vote
      newCount = voteType === 'upvote' ? currentCount + 1 : currentCount - 1;
      this.setUserVote(issueId, voteType);
    }
    
    // Update UI immediately
    this.updateVoteCount(issueId, newCount);
    this.updateVoteButtons(issueId, voteType);
  }

  rollbackVote(issueId, originalCount, originalUserVote) {
    this.updateVoteCount(issueId, originalCount);
    this.setUserVote(issueId, originalUserVote);
    this.updateVoteButtons(issueId, originalUserVote);
  }

  async sendVoteRequest(issueId, voteType) {
    const response = await fetch('/untitled_folder/api/vote.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        issue_id: issueId,
        vote_type: voteType
      })
    });
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    return await response.json();
  }

  updateVoteCount(issueId, newCount) {
    const voteCounts = document.querySelectorAll(`.vote-count[data-issue-id="${issueId}"]`);
    
    voteCounts.forEach(countElement => {
      // Animate count change
      const oldCount = parseInt(countElement.textContent) || 0;
      this.animateCountChange(countElement, oldCount, newCount);
      
      // Update styling based on vote count
      countElement.className = 'vote-count';
      if (newCount > 0) {
        countElement.classList.add('text-success');
      } else if (newCount < 0) {
        countElement.classList.add('text-danger');
      } else {
        countElement.classList.add('text-muted');
      }
    });
  }

  animateCountChange(element, oldCount, newCount) {
    if (oldCount === newCount) return;
    
    // Add animation class
    element.classList.add('count-changing');
    
    // Update the number
    element.textContent = newCount;
    
    // Remove animation class after transition
    setTimeout(() => {
      element.classList.remove('count-changing');
    }, 300);
  }

  updateVoteButtons(issueId, voteType) {
    const upvoteBtn = document.querySelector(`[data-issue-id="${issueId}"].upvote-btn`);
    const downvoteBtn = document.querySelector(`[data-issue-id="${issueId}"].downvote-btn`);
    
    if (upvoteBtn && downvoteBtn) {
      // Remove active state from both buttons
      upvoteBtn.classList.remove('active');
      downvoteBtn.classList.remove('active');
      
      // Add active state to the current vote
      const currentVote = this.getUserVote(issueId);
      if (currentVote === 'upvote') {
        upvoteBtn.classList.add('active');
      } else if (currentVote === 'downvote') {
        downvoteBtn.classList.add('active');
      }
    }
  }

  showLoadingState(button) {
    button.classList.add('loading');
    button.disabled = true;
    
    // Add loading spinner
    const spinner = document.createElement('div');
    spinner.className = 'vote-spinner';
    spinner.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.appendChild(spinner);
  }

  hideLoadingState(button) {
    button.classList.remove('loading');
    button.disabled = false;
    
    // Remove loading spinner
    const spinner = button.querySelector('.vote-spinner');
    if (spinner) {
      spinner.remove();
    }
  }

  showSuccessFeedback(button) {
    button.classList.add('success-feedback');
    setTimeout(() => {
      button.classList.remove('success-feedback');
    }, 600);
  }

  getVoteCount(issueId) {
    const countElement = document.querySelector(`.vote-count[data-issue-id="${issueId}"]`);
    return countElement ? parseInt(countElement.textContent) || 0 : 0;
  }

  getUserVote(issueId) {
    return this.voteCache.get(issueId)?.voteType || null;
  }

  setUserVote(issueId, voteType) {
    if (voteType) {
      this.voteCache.set(issueId, { voteType });
    } else {
      this.voteCache.delete(issueId);
    }
  }

  showMessage(message, type) {
    // Remove existing messages
    const existingMessages = document.querySelectorAll('.vote-message');
    existingMessages.forEach(msg => msg.remove());
    
    // Create message element
    const messageDiv = document.createElement('div');
    messageDiv.className = `vote-message alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
    messageDiv.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      min-width: 300px;
      max-width: 400px;
      animation: slideInFromRight 0.4s ease-out;
    `;
    
    // Add appropriate icon
    const icon = this.getMessageIcon(type);
    
    messageDiv.innerHTML = `
      <i class="${icon} me-2"></i>${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Add to page
    document.body.appendChild(messageDiv);
    
    // Auto-remove after 4 seconds
    setTimeout(() => {
      if (messageDiv.parentNode) {
        messageDiv.style.animation = 'slideOutToRight 0.3s ease-in';
        setTimeout(() => {
          if (messageDiv.parentNode) {
            messageDiv.remove();
          }
        }, 300);
      }
    }, 4000);
  }

  getMessageIcon(type) {
    switch(type) {
      case 'success': return 'fas fa-check-circle';
      case 'error': 
      case 'danger': return 'fas fa-exclamation-triangle';
      case 'warning': return 'fas fa-exclamation-circle';
      case 'info': return 'fas fa-info-circle';
      default: return 'fas fa-info-circle';
    }
  }
}

// Initialize voting system
const votingSystem = new VotingSystem();

// Enhanced UI interactions
document.addEventListener('DOMContentLoaded', () => {
  // Add hover effects for vote buttons
  document.querySelectorAll('.vote-btn').forEach(button => {
    button.addEventListener('mouseenter', function() {
      if (!this.classList.contains('loading')) {
        this.style.transform = 'scale(1.1)';
        this.style.transition = 'all 0.2s ease';
      }
    });
    
    button.addEventListener('mouseleave', function() {
      this.style.transform = 'scale(1)';
    });
    
    // Add click animation
    button.addEventListener('click', function() {
      if (!this.classList.contains('loading')) {
        this.style.transform = 'scale(0.95)';
        setTimeout(() => {
          this.style.transform = 'scale(1.1)';
        }, 100);
      }
    });
  });
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
  .vote-spinner {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: var(--accent-color);
  }
  
  .count-changing {
    animation: countPulse 0.3s ease-out;
  }
  
  @keyframes countPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
  }
  
  .success-feedback {
    animation: successPulse 0.6s ease-out;
  }
  
  @keyframes successPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); background-color: var(--success); }
    100% { transform: scale(1); }
  }
  
  @keyframes slideInFromRight {
    from {
      transform: translateX(100%);
      opacity: 0;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }
  
  @keyframes slideOutToRight {
    from {
      transform: translateX(0);
      opacity: 1;
    }
    to {
      transform: translateX(100%);
      opacity: 0;
    }
  }
  
  .vote-btn.loading {
    pointer-events: none;
    opacity: 0.7;
  }
  
  .vote-btn.loading .vote-spinner {
    display: block;
  }
  
  .vote-btn:not(.loading) .vote-spinner {
    display: none;
  }
`;
document.head.appendChild(style);
