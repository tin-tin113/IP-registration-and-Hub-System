-- Unified complete database setup for CHMSU IP System
-- Run this single file to set up the entire database

-- Drop database if exists and create fresh
DROP DATABASE IF EXISTS `chmsu-IP-system`;
CREATE DATABASE `chmsu-IP-system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `chmsu-IP-system`;

-- =====================================================
-- TABLE: users
-- =====================================================
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(255) NOT NULL,
  role ENUM('user', 'clerk', 'director') DEFAULT 'user',
  security_question VARCHAR(255),
  security_answer VARCHAR(255),
  department VARCHAR(255),
  contact_number VARCHAR(20),
  profile_picture VARCHAR(255),
  innovation_points INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  is_active BOOLEAN DEFAULT TRUE,
  INDEX idx_user_email (email),
  INDEX idx_user_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: ip_applications
-- =====================================================
CREATE TABLE ip_applications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  ip_type ENUM('Copyright', 'Patent', 'Trademark') NOT NULL,
  description LONGTEXT NOT NULL,
  status ENUM('draft', 'submitted', 'office_visit', 'payment_pending', 'payment_verified', 'approved', 'rejected') DEFAULT 'draft',
  document_file TEXT,
  payment_receipt VARCHAR(255),
  supporting_documents TEXT,
  office_visit_date DATETIME,
  payment_date DATETIME,
  payment_amount DECIMAL(10, 2),
  clerk_notes TEXT,
  director_feedback TEXT,
  approved_at DATETIME,
  rejected_at DATETIME,
  reference_number VARCHAR(50) UNIQUE,
  certificate_id VARCHAR(50) UNIQUE,
  rejection_reason TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_app_user (user_id),
  INDEX idx_app_status (status),
  INDEX idx_app_type (ip_type),
  INDEX idx_reference (reference_number),
  INDEX idx_certificate (certificate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: certificates
-- =====================================================
CREATE TABLE certificates (
  id INT PRIMARY KEY AUTO_INCREMENT,
  application_id INT NOT NULL UNIQUE,
  certificate_number VARCHAR(50) UNIQUE NOT NULL,
  reference_number VARCHAR(50) UNIQUE NOT NULL,
  qr_code VARCHAR(255),
  issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES ip_applications(id) ON DELETE CASCADE,
  INDEX idx_cert_app (application_id),
  INDEX idx_cert_number (certificate_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: view_tracking
-- =====================================================
CREATE TABLE view_tracking (
  id INT PRIMARY KEY AUTO_INCREMENT,
  application_id INT NOT NULL,
  viewer_id INT,
  ip_address VARCHAR(45),
  viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES ip_applications(id) ON DELETE CASCADE,
  FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_view_app (application_id),
  INDEX idx_viewer (viewer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: badges
-- =====================================================
CREATE TABLE badges (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  badge_type ENUM('Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond') NOT NULL,
  views_required INT,
  awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_badge_user (user_id),
  INDEX idx_badge_type (badge_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: badge_thresholds
-- =====================================================
CREATE TABLE badge_thresholds (
  id INT PRIMARY KEY AUTO_INCREMENT,
  badge_type ENUM('Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond') NOT NULL UNIQUE,
  views_required INT NOT NULL,
  points_awarded INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_badge_type_threshold (badge_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default thresholds
INSERT INTO badge_thresholds (badge_type, views_required, points_awarded) VALUES
('Bronze', 10, 50),
('Silver', 50, 150),
('Gold', 100, 300),
('Platinum', 250, 500),
('Diamond', 500, 1000);

-- =====================================================
-- TABLE: audit_log
-- =====================================================
CREATE TABLE audit_log (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  action VARCHAR(255) NOT NULL,
  entity_type VARCHAR(50),
  entity_id INT,
  old_value LONGTEXT,
  new_value LONGTEXT,
  ip_address VARCHAR(45),
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_user (user_id),
  INDEX idx_audit_time (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- INSERT DEFAULT ACCOUNTS
-- Password for all accounts: password
-- =====================================================
INSERT INTO users (email, password, full_name, role, security_question, security_answer, is_active) VALUES
('clerk@chmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maria Santos', 'clerk', 'What is your mother\'s maiden name?', 'cruz', TRUE),
('director@chmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Juan Dela Cruz', 'director', 'What city were you born?', 'manila', TRUE),
('student@chmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Student', 'user', 'What is your pet\'s name?', 'buddy', TRUE);

-- =====================================================
-- SAMPLE DATA (Optional - for testing)
-- =====================================================

-- Sample application
INSERT INTO ip_applications (user_id, title, ip_type, description, status) VALUES
(3, 'Mobile Learning App for Mathematics', 'Copyright', 'An innovative mobile application designed to help students learn mathematics through gamification and interactive exercises.', 'submitted');

-- =====================================================
-- GRANT PERMISSIONS (if needed)
-- =====================================================
-- GRANT ALL PRIVILEGES ON `chmsu-IP-system`.* TO 'root'@'localhost';
-- FLUSH PRIVILEGES;

-- =====================================================
-- DATABASE SETUP COMPLETE
-- =====================================================
-- Default Login Credentials:
-- Clerk: clerk@chmsu.edu.ph / password
-- Director: director@chmsu.edu.ph / password
-- Student: student@chmsu.edu.ph / password
-- =====================================================
