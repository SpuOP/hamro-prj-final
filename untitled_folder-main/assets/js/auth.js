// Authentication JavaScript for Community Issues Platform
class AuthManager {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.initSpecialIDFormatting();
    }

    bindEvents() {
        // Login form
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }

        // Registration form
        const registerForm = document.getElementById('registerForm');
        if (registerForm) {
            registerForm.addEventListener('submit', (e) => this.handleRegistration(e));
        }

        // Special ID input formatting
        const specialIDInput = document.getElementById('special_id');
        if (specialIDInput) {
            specialIDInput.addEventListener('input', (e) => this.formatSpecialID(e.target));
        }

        // Password visibility toggle
        const passwordToggles = document.querySelectorAll('.password-toggle');
        passwordToggles.forEach(toggle => {
            toggle.addEventListener('click', (e) => this.togglePasswordVisibility(e));
        });

        // File upload preview
        const fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            input.addEventListener('change', (e) => this.handleFileUpload(e));
        });
    }

    initSpecialIDFormatting() {
        const specialIDInput = document.getElementById('special_id');
        if (specialIDInput) {
            specialIDInput.addEventListener('keyup', (e) => {
                if (e.key === 'Backspace' || e.key === 'Delete') {
                    return;
                }
                this.formatSpecialID(e.target);
            });
        }
    }

    formatSpecialID(input) {
        let value = input.value.replace(/[^A-Z0-9]/g, '').toUpperCase();
        
        if (value.length > 0 && !value.startsWith('CIP')) {
            value = 'CIP' + value.substring(3);
        }
        
        if (value.length > 7) {
            value = value.substring(0, 7) + '-' + value.substring(7, 11);
        }
        
        input.value = value;
    }

    async handleLogin(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
        
        // Clear previous errors
        this.clearErrors(form);
        
        try {
            const formData = new FormData(form);
            const response = await fetch('/api/auth/login.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success message
                this.showMessage('Login successful! Redirecting...', 'success');
                
                // Redirect after a short delay
                setTimeout(() => {
                    window.location.href = result.data.redirect_url || '/dashboard.php';
                }, 1500);
            } else {
                this.showError(form, result.message);
            }
        } catch (error) {
            console.error('Login error:', error);
            this.showError(form, 'An error occurred during login. Please try again.');
        } finally {
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    async handleRegistration(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting Application...';
        
        // Clear previous errors
        this.clearErrors(form);
        
        // Validate form
        if (!this.validateRegistrationForm(form)) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            return;
        }
        
        try {
            const formData = new FormData(form);
            const response = await fetch('/api/auth/register.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success message
                this.showMessage(result.message, 'success');
                
                // Disable form and show success state
                form.style.display = 'none';
                const successDiv = document.createElement('div');
                successDiv.className = 'alert alert-success text-center';
                successDiv.innerHTML = `
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <h4>Application Submitted Successfully!</h4>
                    <p>${result.message}</p>
                    <div class="mt-4">
                        <a href="/index.html" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i>Return to Home
                        </a>
                    </div>
                `;
                form.parentNode.insertBefore(successDiv, form);
            } else {
                if (result.errors) {
                    // Show field-specific errors
                    result.errors.forEach(error => {
                        this.showFieldError(form, error);
                    });
                } else {
                    this.showError(form, result.message);
                }
            }
        } catch (error) {
            console.error('Registration error:', error);
            this.showError(form, 'An error occurred during registration. Please try again.');
        } finally {
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    validateRegistrationForm(form) {
        let isValid = true;
        
        // Required fields validation
        const requiredFields = ['full_name', 'email', 'phone', 'password', 'confirm_password', 'city_id', 'address_detail', 'occupation', 'motivation', 'document_type'];
        
        requiredFields.forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (field && !field.value.trim()) {
                this.showFieldError(form, `${this.getFieldLabel(fieldName)} is required`);
                isValid = false;
            }
        });
        
        // Email validation
        const emailField = form.querySelector('[name="email"]');
        if (emailField && emailField.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailField.value)) {
                this.showFieldError(form, 'Please enter a valid email address');
                isValid = false;
            }
        }
        
        // Password validation
        const passwordField = form.querySelector('[name="password"]');
        const confirmPasswordField = form.querySelector('[name="confirm_password"]');
        
        if (passwordField && passwordField.value.length < 8) {
            this.showFieldError(form, 'Password must be at least 8 characters long');
            isValid = false;
        }
        
        if (passwordField && confirmPasswordField && passwordField.value !== confirmPasswordField.value) {
            this.showFieldError(form, 'Passwords do not match');
            isValid = false;
        }
        
        // Phone validation
        const phoneField = form.querySelector('[name="phone"]');
        if (phoneField && phoneField.value) {
            const phoneRegex = /^[0-9+\-\s()]{10,15}$/;
            if (!phoneRegex.test(phoneField.value)) {
                this.showFieldError(form, 'Please enter a valid phone number');
                isValid = false;
            }
        }
        
        // File upload validation
        const fileField = form.querySelector('[name="proof_document"]');
        if (fileField && !fileField.files[0]) {
            this.showFieldError(form, 'Please upload a proof document');
            isValid = false;
        }
        
        return isValid;
    }

    getFieldLabel(fieldName) {
        const labels = {
            'full_name': 'Full Name',
            'email': 'Email Address',
            'phone': 'Phone Number',
            'password': 'Password',
            'confirm_password': 'Confirm Password',
            'city_id': 'City',
            'address_detail': 'Address',
            'occupation': 'Occupation',
            'motivation': 'Motivation',
            'document_type': 'Document Type'
        };
        return labels[fieldName] || fieldName;
    }

    showError(form, message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger alert-dismissible fade show';
        errorDiv.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        form.insertBefore(errorDiv, form.firstChild);
    }

    showFieldError(form, message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger alert-dismissible fade show';
        errorDiv.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        form.insertBefore(errorDiv, form.firstChild);
    }

    showMessage(message, type = 'info') {
        const messageDiv = document.createElement('div');
        messageDiv.className = `alert alert-${type} alert-dismissible fade show`;
        messageDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insert at the top of the page
        document.body.insertBefore(messageDiv, document.body.firstChild);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }

    clearErrors(form) {
        const alerts = form.querySelectorAll('.alert');
        alerts.forEach(alert => alert.remove());
    }

    togglePasswordVisibility(e) {
        const button = e.currentTarget;
        const input = button.parentNode.querySelector('input[type="password"], input[type="text"]');
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }

    handleFileUpload(e) {
        const file = e.target.files[0];
        const previewContainer = e.target.parentNode.querySelector('.file-preview');
        
        if (!file) return;
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        if (!allowedTypes.includes(file.type)) {
            this.showFieldError(e.target.form, 'Only JPEG, PNG, or PDF files are allowed');
            e.target.value = '';
            return;
        }
        
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            this.showFieldError(e.target.form, 'File size must be less than 5MB');
            e.target.value = '';
            return;
        }
        
        // Show preview
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                if (previewContainer) {
                    previewContainer.innerHTML = `
                        <img src="${e.target.result}" class="img-thumbnail mt-2" style="max-width: 200px; max-height: 150px;">
                        <p class="text-muted mt-1">${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)</p>
                    `;
                }
            };
            reader.readAsDataURL(file);
        } else {
            if (previewContainer) {
                previewContainer.innerHTML = `
                    <div class="alert alert-info mt-2">
                        <i class="fas fa-file-pdf me-2"></i>
                        ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)
                    </div>
                `;
            }
        }
    }

    // Utility method to check if user is logged in
    static isLoggedIn() {
        return document.cookie.includes('PHPSESSID') || localStorage.getItem('user_token');
    }

    // Utility method to logout
    static async logout() {
        try {
            await fetch('/auth/logout.php', { method: 'POST' });
            window.location.href = '/index.html';
        } catch (error) {
            console.error('Logout error:', error);
            window.location.href = '/index.html';
        }
    }
}

// Initialize authentication manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new AuthManager();
});

// Export for use in other modules
window.AuthManager = AuthManager;
