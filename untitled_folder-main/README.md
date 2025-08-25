# CivicPulse - Community Voting Platform

A comprehensive civic engagement platform that enables citizens to report local issues, vote on community priorities, and engage in democratic decision-making for neighborhood improvement.

## 🎯 Platform Overview

CivicPulse is a community voting platform that connects citizens with their local government through transparent democratic participation. The platform enables citizens to report community issues, vote on priorities, and engage directly with local representatives to create stronger, more responsive neighborhoods.

## ✨ Key Features

### 🔐 Authentication & User Management
- **Special Login ID System**: Unique IDs (CIP-CITY-XXXX format) generated upon admin approval
- **Document Verification**: Multi-step registration with identity document upload
- **Admin Approval Workflow**: Comprehensive review system for user applications
- **Role-Based Access**: Different permissions for citizens, admins, and moderators

### 🗳️ Community Voting System
- **Issue Reporting**: Citizens can post local problems with images and detailed descriptions
- **Democratic Voting**: Upvote/downvote system to prioritize community concerns
- **Location-Based Filtering**: Issues filtered by city and metro area
- **Real-time Updates**: Live vote counts and comment systems

### 📊 Admin Dashboard
- **User Management**: Review and approve/reject citizen applications
- **Issue Moderation**: Manage and moderate community issues
- **Analytics**: Comprehensive statistics and reporting
- **Bulk Operations**: Efficient batch processing for user applications

### 📧 Communication System
- **Email Notifications**: Automated emails for registration, approval, and updates
- **Contact Form**: Integrated contact system with backend processing
- **Special ID Delivery**: Email-based Special Login ID distribution

## 🏗️ Technical Architecture

### Frontend Technologies
- **HTML5**: Semantic markup with accessibility features
- **CSS3**: Modern styling with CSS Grid, Flexbox, and CSS Variables
- **JavaScript (ES6+)**: Vanilla JS with Fetch API and async/await
- **Responsive Design**: Mobile-first approach with Bootstrap 5
- **Dark Mode**: Complete theme system with persistent preferences

### Backend Technologies
- **PHP 7.4+**: Object-oriented approach with modern PHP features
- **MySQL 8.0**: Relational database with proper indexing and constraints
- **RESTful APIs**: JSON-based API endpoints for frontend integration
- **Security**: Password hashing, SQL injection prevention, XSS protection

### Database Schema
- **Users & Applications**: Complete user lifecycle management
- **Issues & Voting**: Community problem reporting and democratic voting
- **Comments & Updates**: Discussion and progress tracking
- **Email Logs**: Communication tracking and delivery status

## 📁 File Structure

```
untitled_folder-main/
├── api/                          # Backend API endpoints
│   ├── auth/                     # Authentication APIs
│   │   ├── login.php            # Special ID login
│   │   └── register.php         # User registration
│   ├── admin/                    # Admin APIs
│   │   └── stats.php            # Dashboard statistics
│   ├── issues.php               # Issue management
│   └── contact.php              # Contact form processing
├── assets/                       # Frontend assets
│   ├── css/
│   │   └── style.css            # Complete theme system
│   └── js/
│       ├── auth.js              # Authentication management
│       ├── issues.js            # Issue management
│       ├── app.js               # Main application logic
│       └── theme.js             # Dark mode functionality
├── auth/                         # Authentication pages
│   ├── login.html               # Special ID login form
│   ├── register.html            # Multi-step registration
│   └── logout.php               # Session cleanup
├── pages/                        # Static pages (NEW)
│   ├── about.html               # Platform information
│   └── contact.html             # Contact form with backend
├── admin/                        # Admin interface
│   ├── dashboard.php            # Main admin dashboard
│   ├── pending-users.php        # User application review
│   └── dashboard.html           # Admin HTML template
├── includes/                     # PHP includes
│   ├── functions.php            # Core utility functions
│   └── email_functions.php      # Email system
├── config/                       # Configuration
│   └── database.php             # Database connection
├── database/                     # Database files
│   └── complete_schema.sql      # Complete database schema
├── uploads/                      # File uploads
│   └── proof_documents/         # User verification documents
└── index.html                    # Landing page
```

## 🚀 Installation & Setup

### Prerequisites
- PHP 7.4 or higher
- MySQL 8.0 or higher
- Web server (Apache/Nginx)
- Composer (for dependencies)

### Installation Steps

1. **Clone the Repository**
   ```bash
   git clone <repository-url>
   cd untitled_folder-main
   ```

2. **Database Setup**
   ```bash
   # Import the complete schema
   mysql -u root -p < database/complete_schema.sql
   ```

3. **Configuration**
   ```bash
   # Update database connection in config/database.php
   # Set your database credentials
   ```

4. **File Permissions**
   ```bash
   # Set proper permissions for uploads
   chmod 755 uploads/
   chmod 755 uploads/proof_documents/
   ```

5. **Web Server Configuration**
   - Point your web server to the project root
   - Ensure PHP has write permissions to uploads directory
   - Configure URL rewriting if needed

## 🔧 Configuration

### Database Configuration
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'community_voting');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### Email Configuration
Configure SMTP settings in `includes/email_functions.php`:
```php
// Update SMTP settings for your email provider
$smtp_host = 'your-smtp-host';
$smtp_port = 587;
$smtp_username = 'your-email@domain.com';
$smtp_password = 'your-password';
```

## 🎨 Customization

### Theme Customization
The platform uses CSS variables for easy theming. Edit `assets/css/style.css`:
```css
:root {
  --accent-color: #7c3aed;        /* Primary brand color */
  --bg-primary: #ffffff;          /* Background color */
  --text-primary: #212529;        /* Text color */
  /* ... more variables */
}
```

### Special ID Format
Customize the Special Login ID format in `includes/functions.php`:
```php
function generateSpecialLoginID($cityName) {
    // Current format: CIP-CITY-XXXX
    // Modify as needed for your requirements
}
```

## 🔒 Security Features

### Authentication Security
- **Password Hashing**: Bcrypt with cost factor 12
- **Session Management**: Secure session handling with CSRF protection
- **Input Validation**: Comprehensive sanitization and validation
- **SQL Injection Prevention**: Prepared statements throughout

### File Upload Security
- **Type Validation**: Only allowed file types (PDF, JPG, PNG)
- **Size Limits**: Maximum 5MB per file
- **Secure Storage**: Files stored outside web root when possible
- **Virus Scanning**: Integration points for malware detection

### API Security
- **CORS Configuration**: Proper cross-origin resource sharing
- **Rate Limiting**: Protection against abuse
- **Error Handling**: Secure error messages without information leakage

## 📊 Admin Features

### User Management
- **Application Review**: View and approve/reject user applications
- **Document Verification**: Preview uploaded identity documents
- **Bulk Operations**: Process multiple applications simultaneously
- **User Statistics**: Comprehensive user analytics

### Issue Moderation
- **Content Moderation**: Review and manage community issues
- **Flag Management**: Handle flagged content and comments
- **Status Updates**: Track issue resolution progress
- **Reporting**: Generate detailed reports

### Analytics Dashboard
- **User Growth**: Registration and activity trends
- **Issue Analytics**: Category breakdown and resolution rates
- **Community Engagement**: Voting and participation metrics
- **Performance Metrics**: Platform usage statistics

## 🔄 API Endpoints

### Authentication
- `POST /api/auth/register.php` - User registration
- `POST /api/auth/login.php` - Special ID login
- `POST /api/auth/logout.php` - Session cleanup

### Issues
- `GET /api/issues.php?action=list` - List community issues
- `POST /api/issues.php?action=create` - Create new issue
- `POST /api/issues.php?action=vote` - Vote on issues
- `POST /api/issues.php?action=comment` - Add comments

### Admin
- `GET /api/admin/stats.php` - Dashboard statistics
- `POST /api/admin/approve-user.php` - Approve user application
- `POST /api/admin/reject-user.php` - Reject user application

### Contact
- `POST /api/contact.php` - Contact form submission

## 🧪 Testing

### Manual Testing Checklist
- [ ] User registration with document upload
- [ ] Admin approval workflow
- [ ] Special ID generation and email delivery
- [ ] Login with Special ID
- [ ] Issue creation and voting
- [ ] Comment system functionality
- [ ] Contact form submission
- [ ] Dark mode toggle functionality
- [ ] Responsive design on mobile devices
- [ ] Admin dashboard features

### Automated Testing
```bash
# Run PHP unit tests (if implemented)
composer test

# Run frontend tests (if implemented)
npm test
```

## 🚀 Deployment

### Production Checklist
- [ ] Database optimization and indexing
- [ ] SSL certificate installation
- [ ] Email service configuration
- [ ] File upload directory security
- [ ] Error logging and monitoring
- [ ] Backup system implementation
- [ ] Performance optimization
- [ ] Security audit completion

### Deployment Commands
```bash
# Set production environment
export APP_ENV=production

# Optimize assets
npm run build

# Set proper permissions
chmod 644 assets/css/*
chmod 644 assets/js/*
chmod 755 uploads/

# Restart web server
sudo systemctl restart apache2
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

For support and questions:
- **Email**: support@civicpulse.org
- **Documentation**: [Platform Documentation](docs/)
- **Issues**: [GitHub Issues](https://github.com/your-repo/issues)

## 🔄 Changelog

### Version 1.0.0 (Current)
- ✅ Complete frontend-backend integration
- ✅ Special Login ID system implementation
- ✅ Admin dashboard with user management
- ✅ Community voting and issue reporting
- ✅ Dark mode toggle in header
- ✅ About/Contact pages with backend integration
- ✅ Education content removal and community focus
- ✅ Responsive design and mobile optimization
- ✅ Security hardening and validation
- ✅ Email notification system

## 🎯 Roadmap

### Version 1.1.0 (Planned)
- [ ] Mobile app development
- [ ] Real-time notifications
- [ ] Advanced analytics dashboard
- [ ] Multi-language support
- [ ] API rate limiting
- [ ] Advanced search functionality

### Version 1.2.0 (Future)
- [ ] Integration with government APIs
- [ ] Blockchain-based voting verification
- [ ] AI-powered issue categorization
- [ ] Community leader verification system
- [ ] Advanced reporting and analytics

---

**CivicPulse** - Empowering communities through democratic participation and local issue resolution.

