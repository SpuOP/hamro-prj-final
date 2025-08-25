/**
 * CivicPulse Admin JavaScript
 * Handles admin authentication, session management, and admin-specific functionality
 */

class AdminManager {
  constructor() {
    this.isAuthenticated = false;
    this.adminData = null;
    this.sessionTimeout = null;
    this.init();
  }

  init() {
    this.checkAuthStatus();
    this.setupEventListeners();
    this.setupSessionTimeout();
  }

  // Check if admin is authenticated
  async checkAuthStatus() {
    try {
      const response = await fetch('../api/admin/auth/status.php');
      const data = await response.json();
      
      if (data.success && data.authenticated) {
        this.isAuthenticated = true;
        this.adminData = data.admin;
        this.showAuthenticatedUI();
        this.resetSessionTimeout();
      } else {
        this.isAuthenticated = false;
        this.showLoginUI();
      }
    } catch (error) {
      console.error('Error checking auth status:', error);
      this.showLoginUI();
    }
  }

  // Setup event listeners
  setupEventListeners() {
    // Logout button
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', (e) => {
        e.preventDefault();
        this.logout();
      });
    }

    // Theme toggle
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
      themeToggle.addEventListener('click', () => {
        this.toggleTheme();
      });
    }

    // Mobile sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
      sidebarToggle.addEventListener('click', () => {
        this.toggleSidebar();
      });
    }
  }

  // Setup session timeout
  setupSessionTimeout() {
    // Session expires after 2 hours of inactivity
    const SESSION_TIMEOUT = 2 * 60 * 60 * 1000;
    
    const resetTimeout = () => {
      if (this.sessionTimeout) {
        clearTimeout(this.sessionTimeout);
      }
      this.sessionTimeout = setTimeout(() => {
        this.logout('Session expired due to inactivity');
      }, SESSION_TIMEOUT);
    };

    // Reset timeout on user activity
    ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
      document.addEventListener(event, resetTimeout, true);
    });

    resetTimeout();
  }

  // Reset session timeout
  resetSessionTimeout() {
    if (this.sessionTimeout) {
      clearTimeout(this.sessionTimeout);
    }
    this.setupSessionTimeout();
  }

  // Show authenticated UI
  showAuthenticatedUI() {
    const authElements = document.querySelectorAll('.auth-required');
    const loginElements = document.querySelectorAll('.login-required');
    
    authElements.forEach(el => el.style.display = 'block');
    loginElements.forEach(el => el.style.display = 'none');
    
    // Update admin info
    this.updateAdminInfo();
  }

  // Show login UI
  showLoginUI() {
    const authElements = document.querySelectorAll('.auth-required');
    const loginElements = document.querySelectorAll('.login-required');
    
    authElements.forEach(el => el.style.display = 'none');
    loginElements.forEach(el => el.style.display = 'block');
    
    // Redirect to login if not on login page
    if (!window.location.pathname.includes('login')) {
      window.location.href = 'login.html';
    }
  }

  // Update admin information display
  updateAdminInfo() {
    if (!this.adminData) return;
    
    const adminNameElements = document.querySelectorAll('.admin-name');
    const adminEmailElements = document.querySelectorAll('.admin-email');
    
    adminNameElements.forEach(el => {
      el.textContent = this.adminData.name || 'Admin';
    });
    
    adminEmailElements.forEach(el => {
      el.textContent = this.adminData.email || 'admin@civicpulse.com';
    });
  }

  // Toggle theme
  toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    // Update theme toggle button
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
      const icon = themeToggle.querySelector('i');
      if (icon) {
        icon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
      }
    }
  }

  // Toggle mobile sidebar
  toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) {
      sidebar.classList.toggle('active');
    }
    
    if (overlay) {
      overlay.classList.toggle('active');
    }
  }

  // Logout function
  async logout(reason = 'Logged out successfully') {
    try {
      const response = await fetch('../api/admin/auth/logout.php', {
        method: 'POST',
        credentials: 'include'
      });
      
      if (response.ok) {
        this.isAuthenticated = false;
        this.adminData = null;
        
        // Clear session timeout
        if (this.sessionTimeout) {
          clearTimeout(this.sessionTimeout);
        }
        
        // Show logout message
        this.showNotification(reason, 'success');
        
        // Redirect to login page
        setTimeout(() => {
          window.location.href = 'login.html';
        }, 1500);
      }
    } catch (error) {
      console.error('Error during logout:', error);
      // Force logout even if API call fails
      this.isAuthenticated = false;
      this.adminData = null;
      window.location.href = 'login.html';
    }
  }

  // Show notification
  showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
      <div class="notification-content">
        <i class="fas fa-${this.getNotificationIcon(type)}"></i>
        <span>${message}</span>
      </div>
      <button class="notification-close" onclick="this.parentElement.remove()">
        <i class="fas fa-times"></i>
      </button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
      if (notification.parentElement) {
        notification.remove();
      }
    }, 5000);
  }

  // Get notification icon based on type
  getNotificationIcon(type) {
    const icons = {
      success: 'check-circle',
      error: 'exclamation-circle',
      warning: 'exclamation-triangle',
      info: 'info-circle'
    };
    return icons[type] || 'info-circle';
  }

  // Check permissions
  hasPermission(permission) {
    if (!this.adminData || !this.adminData.permissions) {
      return false;
    }
    
    return this.adminData.permissions.includes(permission) || 
           this.adminData.permissions.includes('super_admin');
  }

  // Require permission
  requirePermission(permission, callback) {
    if (this.hasPermission(permission)) {
      callback();
    } else {
      this.showNotification('You do not have permission to perform this action', 'error');
    }
  }

  // Format date
  formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  // Format file size
  formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  // Validate email
  validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
  }

  // Validate phone
  validatePhone(phone) {
    const re = /^[\+]?[1-9][\d]{0,15}$/;
    return re.test(phone.replace(/[\s\-\(\)]/g, ''));
  }

  // Show loading state
  showLoading(element, text = 'Loading...') {
    if (!element) return;
    
    const originalContent = element.innerHTML;
    element.innerHTML = `
      <div class="loading-state">
        <div class="loading-spinner"></div>
        <span>${text}</span>
      </div>
    `;
    
    return originalContent;
  }

  // Hide loading state
  hideLoading(element, originalContent) {
    if (!element || !originalContent) return;
    element.innerHTML = originalContent;
  }

  // Confirm action
  async confirmAction(message, title = 'Confirm Action') {
    return new Promise((resolve) => {
      const modal = document.createElement('div');
      modal.className = 'modal-overlay';
      modal.innerHTML = `
        <div class="modal">
          <div class="modal-header">
            <h3>${title}</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>${message}</p>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove(); resolve(false)">
              Cancel
            </button>
            <button class="btn btn-primary" onclick="this.closest('.modal-overlay').remove(); resolve(true)">
              Confirm
            </button>
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
    });
  }

  // Show error modal
  showErrorModal(message, title = 'Error') {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
      <div class="modal">
        <div class="modal-header">
          <h3>${title}</h3>
          <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <p>${message}</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary" onclick="this.closest('.modal-overlay').remove()">
            OK
          </button>
        </div>
      </div>
    `;
    
    document.body.appendChild(modal);
  }

  // Handle API errors
  handleApiError(error, defaultMessage = 'An error occurred') {
    console.error('API Error:', error);
    
    let message = defaultMessage;
    
    if (error.response) {
      try {
        const errorData = error.response.json();
        message = errorData.message || errorData.error || defaultMessage;
      } catch (e) {
        message = error.response.statusText || defaultMessage;
      }
    } else if (error.message) {
      message = error.message;
    }
    
    this.showNotification(message, 'error');
    return message;
  }

  // Refresh data
  async refreshData() {
    if (typeof loadDashboardData === 'function') {
      await loadDashboardData();
    }
    
    if (typeof loadRecentActivity === 'function') {
      await loadRecentActivity();
    }
    
    this.showNotification('Data refreshed successfully', 'success');
  }

  // Export data
  exportData(data, filename, type = 'json') {
    let content, mimeType;
    
    if (type === 'csv') {
      content = this.convertToCSV(data);
      mimeType = 'text/csv';
    } else {
      content = JSON.stringify(data, null, 2);
      mimeType = 'application/json';
    }
    
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  // Convert data to CSV
  convertToCSV(data) {
    if (!Array.isArray(data) || data.length === 0) return '';
    
    const headers = Object.keys(data[0]);
    const csvContent = [
      headers.join(','),
      ...data.map(row => headers.map(header => `"${row[header]}"`).join(','))
    ].join('\n');
    
    return csvContent;
  }
}

// Initialize admin manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  window.adminManager = new AdminManager();
});

// Global utility functions
window.showNotification = (message, type) => {
  if (window.adminManager) {
    window.adminManager.showNotification(message, type);
  }
};

window.confirmAction = (message, title) => {
  if (window.adminManager) {
    return window.adminManager.confirmAction(message, title);
  }
  return Promise.resolve(false);
};

window.showErrorModal = (message, title) => {
  if (window.adminManager) {
    window.adminManager.showErrorModal(message, title);
  }
};

// Auto-refresh data every 5 minutes
setInterval(() => {
  if (window.adminManager && window.adminManager.isAuthenticated) {
    window.adminManager.refreshData();
  }
}, 5 * 60 * 1000);
