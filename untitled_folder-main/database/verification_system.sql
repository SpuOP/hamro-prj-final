USE community_voting;

-- CivicPulse Verification System Database Structure
-- Enhanced user verification with proof of residence and community-based authentication

-- Create communities/cities table
CREATE TABLE IF NOT EXISTS communities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    district VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- Insert sample Nepali communities
INSERT INTO communities (name, district, province, postal_code) VALUES
('Kathmandu Metropolitan City', 'Kathmandu', 'Bagmati Province', '44600'),
('Pokhara Metropolitan City', 'Kaski', 'Gandaki Province', '33700'),
('Lalitpur Metropolitan City', 'Lalitpur', 'Bagmati Province', '44700'),
('Bharatpur Metropolitan City', 'Chitwan', 'Bagmati Province', '44200'),
('Biratnagar Metropolitan City', 'Morang', 'Koshi Province', '56613'),
('Birgunj Metropolitan City', 'Parsa', 'Madhesh Province', '44300'),
('Dharan Sub-Metropolitan City', 'Sunsari', 'Koshi Province', '56700'),
('Hetauda Sub-Metropolitan City', 'Makwanpur', 'Bagmati Province', '44107'),
('Nepalgunj Sub-Metropolitan City', 'Banke', 'Lumbini Province', '21900'),
('Butwal Sub-Metropolitan City', 'Rupandehi', 'Lumbini Province', '32907'),
('Dhangadhi Sub-Metropolitan City', 'Kailali', 'Sudurpashchim Province', '10900'),
('Mahendranagar Municipality', 'Kanchanpur', 'Sudurpashchim Province', '10400'),
('Gorkha Municipality', 'Gorkha', 'Gandaki Province', '34000'),
('Palpa Municipality', 'Palpa', 'Lumbini Province', '32500'),
('Baglung Municipality', 'Baglung', 'Gandaki Province', '33100');

-- Create user applications table (replaces direct user registration)
CREATE TABLE IF NOT EXISTS user_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    community_id INT NOT NULL,
    address_detail TEXT NOT NULL,
    proof_document_path VARCHAR(500) NOT NULL,
    document_type ENUM('citizenship', 'utility_bill', 'rental_agreement', 'bank_statement') NOT NULL,
    occupation ENUM('teacher', 'student', 'parent', 'education_officer', 'community_member') NOT NULL,
    motivation TEXT NOT NULL,
    status ENUM('pending', 'under_review', 'approved', 'rejected') DEFAULT 'pending',
    reviewed_by INT NULL,
    review_notes TEXT NULL,
    special_id VARCHAR(20) NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (community_id) REFERENCES communities(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- Update users table to work with special IDs
ALTER TABLE users ADD COLUMN IF NOT EXISTS special_id VARCHAR(20) UNIQUE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS community_id INT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20);
ALTER TABLE users ADD COLUMN IF NOT EXISTS full_name VARCHAR(255);
ALTER TABLE users ADD COLUMN IF NOT EXISTS occupation ENUM('teacher', 'student', 'parent', 'education_officer', 'community_member');
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_verified BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_date TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL;

-- Add foreign key for community
ALTER TABLE users ADD CONSTRAINT fk_user_community FOREIGN KEY (community_id) REFERENCES communities(id);

-- Create admin users table for reviewers
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'reviewer', 'moderator') DEFAULT 'reviewer',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Insert default admin user (password: admin123)
INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES
('admin', 'admin@civicpulse.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'super_admin');

-- Create email verification tokens table
CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES user_applications(id) ON DELETE CASCADE
);

-- Create special ID generation log
CREATE TABLE IF NOT EXISTS special_id_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    special_id VARCHAR(20) NOT NULL,
    application_id INT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    emailed_at TIMESTAMP NULL,
    FOREIGN KEY (application_id) REFERENCES user_applications(id)
);

-- Create indexes for better performance
CREATE INDEX idx_applications_status ON user_applications(status);
CREATE INDEX idx_applications_community ON user_applications(community_id);
CREATE INDEX idx_users_special_id ON users(special_id);
CREATE INDEX idx_users_community ON users(community_id);
