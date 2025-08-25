-- Complete Community Issues Platform Database Schema
-- CivicPulse - Community Issues Management System

-- Create database
CREATE DATABASE IF NOT EXISTS community_voting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE community_voting;

-- Admin users table
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Cities table
CREATE TABLE cities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    state VARCHAR(100),
    country VARCHAR(100) DEFAULT 'Nepal',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Metro areas table
CREATE TABLE metro_areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    city_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE
);

-- User applications table (pending registrations)
CREATE TABLE user_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    city_id INT,
    metro_area_id INT,
    address_detail TEXT NOT NULL,
    occupation ENUM('teacher', 'student', 'parent', 'education_officer', 'community_member') NOT NULL,
    document_type ENUM('nic', 'citizenship', 'driving_license', 'passport', 'utility_bill', 'rental_agreement', 'bank_statement') NOT NULL,
    proof_document_path VARCHAR(255) NOT NULL,
    motivation TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    FOREIGN KEY (city_id) REFERENCES cities(id),
    FOREIGN KEY (metro_area_id) REFERENCES metro_areas(id),
    FOREIGN KEY (reviewed_by) REFERENCES admins(id)
);

-- Users table with special ID system
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    special_login_id VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    city_id INT,
    metro_area_id INT,
    address_detail TEXT,
    occupation ENUM('teacher', 'student', 'parent', 'education_officer', 'community_member'),
    document_type ENUM('nic', 'citizenship', 'driving_license', 'passport', 'utility_bill', 'rental_agreement', 'bank_statement'),
    proof_document_path VARCHAR(255),
    is_verified BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    approved_by INT NULL,
    last_login TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (city_id) REFERENCES cities(id),
    FOREIGN KEY (metro_area_id) REFERENCES metro_areas(id),
    FOREIGN KEY (approved_by) REFERENCES admins(id)
);

-- Issues/Problems posted by users
CREATE TABLE issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('infrastructure', 'sanitation', 'transportation', 'safety', 'environment', 'education', 'health', 'other') NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    user_id INT NOT NULL,
    city_id INT NOT NULL,
    metro_area_id INT,
    image_path VARCHAR(255),
    votes_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    is_flagged BOOLEAN DEFAULT FALSE,
    flag_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (city_id) REFERENCES cities(id),
    FOREIGN KEY (metro_area_id) REFERENCES metro_areas(id)
);

-- Voting system
CREATE TABLE issue_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT NOT NULL,
    user_id INT NOT NULL,
    vote_type ENUM('upvote', 'downvote') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_issue_vote (issue_id, user_id)
);

-- Comments system
CREATE TABLE issue_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    is_flagged BOOLEAN DEFAULT FALSE,
    flag_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Issue status updates
CREATE TABLE issue_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT NOT NULL,
    status ENUM('open', 'in_progress', 'resolved', 'closed') NOT NULL,
    update_message TEXT,
    updated_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Email notifications log
CREATE TABLE email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message_body TEXT NOT NULL,
    email_type ENUM('registration', 'approval', 'rejection', 'special_id', 'password_reset', 'issue_update') NOT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Contact form messages
CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    issue_category VARCHAR(50),
    message TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    status ENUM('new', 'read', 'replied', 'closed') DEFAULT 'new',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user (password: admin123)
INSERT INTO admins (username, email, password_hash, role) VALUES 
('admin', 'admin@civicpulse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

-- Insert sample cities
INSERT INTO cities (name, state) VALUES 
('Kathmandu', 'Bagmati'),
('Lalitpur', 'Bagmati'),
('Bhaktapur', 'Bagmati'),
('Pokhara', 'Gandaki'),
('Biratnagar', 'Province 1'),
('Birgunj', 'Madhesh'),
('Dharan', 'Province 1'),
('Butwal', 'Lumbini'),
('Hetauda', 'Bagmati'),
('Nepalgunj', 'Lumbini');

-- Insert sample metro areas
INSERT INTO metro_areas (name, city_id) VALUES 
('Thamel', 1),
('Baneshwor', 1),
('Patan Durbar Square', 2),
('Bhaktapur Durbar Square', 3),
('Lakeside', 4),
('Biratnagar Central', 5),
('Birgunj Central', 6),
('Dharan Central', 7),
('Butwal Central', 8),
('Hetauda Central', 9);

-- Pending applications table for new registration system
CREATE TABLE pending_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    city VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    document_path VARCHAR(255) NOT NULL,
    terms_accepted BOOLEAN DEFAULT FALSE,
    community_guidelines BOOLEAN DEFAULT FALSE,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    special_login_id VARCHAR(20) NULL,
    FOREIGN KEY (reviewed_by) REFERENCES admins(id)
);

-- Create indexes for better performance
CREATE INDEX idx_users_special_id ON users(special_login_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_city ON users(city_id);
CREATE INDEX idx_issues_user ON issues(user_id);
CREATE INDEX idx_issues_city ON issues(city_id);
CREATE INDEX idx_issues_status ON issues(status);
CREATE INDEX idx_issues_created ON issues(created_at);
CREATE INDEX idx_votes_issue ON issue_votes(issue_id);
CREATE INDEX idx_votes_user ON issue_votes(user_id);
CREATE INDEX idx_comments_issue ON issue_comments(issue_id);
CREATE INDEX idx_applications_status ON user_applications(status);
CREATE INDEX idx_applications_email ON user_applications(email);
CREATE INDEX idx_pending_applications_email ON pending_applications(email);
CREATE INDEX idx_pending_applications_status ON pending_applications(status);
CREATE INDEX idx_pending_applications_application_id ON pending_applications(application_id);
