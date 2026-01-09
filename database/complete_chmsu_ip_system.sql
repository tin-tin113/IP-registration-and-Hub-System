-- =====================================================
-- CHMSU IP SYSTEM - COMPLETE DATABASE SETUP
-- =====================================================
-- Version: 3.1
-- Date: December 2024
-- 
-- This is the COMPLETE and UNIFIED database schema
-- Run this single file in phpMyAdmin to set up the entire database
-- 
-- Features included:
-- - User accounts with roles (user, clerk, director)
-- - IP Applications (Copyright, Patent, Trademark)
-- - User Profiles for verification
-- - Certificates generation
-- - Achievement badges and points system
-- - View tracking and analytics
-- - Audit logging
-- - Publishing permissions for IP Hub
-- - Reward/Award system
-- =====================================================

-- Drop database if exists and create fresh
-- Using backticks to handle hyphens in the name
DROP DATABASE IF EXISTS `chmsu-IP-system`;
CREATE DATABASE `chmsu-IP-system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `chmsu-IP-system`;

-- =====================================================
-- TABLE: users
-- Purpose: Store all user accounts (students, faculty, clerk, director)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='User accounts and authentication';

-- =====================================================
-- TABLE: user_profiles
-- Purpose: Store verification information for users
-- This data is collected once on the apply page and auto-filled on subsequent visits
-- Only clerks and directors can view this information
-- =====================================================
CREATE TABLE user_profiles (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL UNIQUE,
  
  -- Personal Information (Required)
  first_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100),
  last_name VARCHAR(100) NOT NULL,
  suffix ENUM('', 'Jr.', 'Sr.', 'II', 'III', 'IV', 'V') DEFAULT '',
  email VARCHAR(255) NOT NULL,
  birthdate DATE NOT NULL,
  gender ENUM('Male', 'Female') DEFAULT 'Male',
  nationality VARCHAR(100) DEFAULT 'Filipino',
  
  -- Employment/Academic Information (Required)
  employment_status ENUM('Student', 'Faculty', 'Staff', 'Researcher', 'Alumni', 'Other') NOT NULL DEFAULT 'Student',
  employee_id VARCHAR(50) NOT NULL,
  college ENUM(
    'College of Arts and Sciences',
    'College of Business and Accountancy', 
    'College of Criminal Justice Education',
    'College of Education',
    'College of Engineering and Technology',
    'College of Fisheries',
    'College of Industrial Technology',
    'College of Nursing',
    'Graduate School',
    'Other'
  ) NOT NULL DEFAULT 'College of Arts and Sciences',
  contact_number VARCHAR(20) NOT NULL,
  
  -- Address Information
  address_street VARCHAR(255),
  address_barangay VARCHAR(100),
  address_city VARCHAR(100),
  address_province ENUM('Negros Occidental', 'Negros Oriental') DEFAULT 'Negros Occidental',
  address_postal VARCHAR(10),
  
  -- Profile completion tracking
  is_complete BOOLEAN DEFAULT FALSE,
  
  -- Custom fields added by admin (JSON storage)
  custom_fields JSON DEFAULT NULL COMMENT 'Stores values for admin-created custom fields',
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_profile_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='User verification profiles';

-- =====================================================
-- TABLE: form_fields
-- Purpose: Store dynamic form field configurations for personal info form
-- Allows admins to add/edit/remove/hide form fields
-- =====================================================
CREATE TABLE form_fields (
  id INT PRIMARY KEY AUTO_INCREMENT,
  field_name VARCHAR(100) NOT NULL UNIQUE COMMENT 'Internal field name (snake_case)',
  field_label VARCHAR(255) NOT NULL COMMENT 'Display label for the field',
  field_type ENUM('text', 'email', 'tel', 'date', 'select', 'radio', 'textarea', 'number') NOT NULL DEFAULT 'text',
  field_options TEXT DEFAULT NULL COMMENT 'JSON array for select/radio options',
  placeholder VARCHAR(255) DEFAULT NULL,
  validation_pattern VARCHAR(255) DEFAULT NULL COMMENT 'Regex pattern for validation',
  is_required BOOLEAN DEFAULT FALSE,
  is_active BOOLEAN DEFAULT TRUE,
  is_builtin BOOLEAN DEFAULT FALSE COMMENT 'Built-in fields cannot be deleted',
  field_section ENUM('name', 'contact', 'personal', 'employment', 'address') DEFAULT 'personal',
  field_order INT DEFAULT 0,
  grid_column VARCHAR(50) DEFAULT '1fr' COMMENT 'CSS grid column width',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_active_order (is_active, field_order),
  INDEX idx_section (field_section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Dynamic form field configurations';

-- Seed built-in form fields
INSERT INTO form_fields (field_name, field_label, field_type, field_options, placeholder, is_required, is_active, is_builtin, field_section, field_order) VALUES
('first_name', 'First Name', 'text', NULL, 'Juan', TRUE, TRUE, TRUE, 'name', 1),
('middle_name', 'Middle Name', 'text', NULL, 'Santos', FALSE, TRUE, TRUE, 'name', 2),
('last_name', 'Last Name', 'text', NULL, 'Dela Cruz', TRUE, TRUE, TRUE, 'name', 3),
('suffix', 'Suffix', 'select', '["", "Jr.", "Sr.", "II", "III", "IV", "V"]', NULL, FALSE, TRUE, TRUE, 'name', 4),
('email', 'Email Address', 'email', NULL, 'email@chmsu.edu.ph', TRUE, TRUE, TRUE, 'contact', 5),
('contact_number', 'Contact Number', 'tel', NULL, '09XX-XXX-XXXX', TRUE, TRUE, TRUE, 'contact', 6),
('birthdate', 'Birthdate', 'date', NULL, NULL, TRUE, TRUE, TRUE, 'contact', 7),
('gender', 'Gender', 'radio', '["Male", "Female"]', NULL, TRUE, TRUE, TRUE, 'personal', 8),
('nationality', 'Nationality', 'text', NULL, 'Filipino', FALSE, TRUE, TRUE, 'personal', 9),
('employment_status', 'Employment Status', 'select', '["Student", "Faculty", "Staff", "Researcher", "Alumni", "Other"]', NULL, TRUE, TRUE, TRUE, 'employment', 10),
('employee_id', 'Student/Employee ID', 'text', NULL, 'e.g., 2024-0001', TRUE, TRUE, TRUE, 'employment', 11),
('college', 'College/Department', 'select', '["", "College of Arts and Sciences", "College of Business and Accountancy", "College of Criminal Justice Education", "College of Education", "College of Engineering and Technology", "College of Fisheries", "College of Industrial Technology", "College of Nursing", "Graduate School", "Other"]', NULL, TRUE, TRUE, TRUE, 'employment', 12),
('address_street', 'Street Address', 'text', NULL, 'House No., Street Name', FALSE, TRUE, TRUE, 'address', 13),
('address_barangay', 'Barangay', 'text', NULL, 'Barangay Name', FALSE, TRUE, TRUE, 'address', 14),
('address_province', 'Province', 'select', '["Negros Occidental", "Negros Oriental"]', NULL, FALSE, TRUE, TRUE, 'address', 15),
('address_city', 'City/Municipality', 'select', NULL, NULL, FALSE, TRUE, TRUE, 'address', 16),
('address_postal', 'Postal Code', 'text', NULL, '6100', FALSE, TRUE, TRUE, 'address', 17);

-- =====================================================
-- TABLE: ip_applications
-- Purpose: Store all IP applications with comprehensive information
-- =====================================================
CREATE TABLE ip_applications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  
  -- IP Information Section
  title VARCHAR(255) NOT NULL,
  inventor_name VARCHAR(500) COMMENT 'Name(s) of inventors to display on certificate',
  ip_type ENUM('Copyright', 'Patent', 'Trademark') NOT NULL,
  research_type VARCHAR(100) COMMENT 'Research type/specialization based on IP type',
  abstract LONGTEXT NOT NULL COMMENT 'Detailed abstract of the IP work',
  
  -- Application Status and Workflow
  status ENUM('draft', 'submitted', 'office_visit', 'payment_pending', 'payment_verified', 'approved', 'rejected') DEFAULT 'draft',
  
  -- Publishing Permission for IP Hub
  publish_permission ENUM('pending', 'granted', 'denied') DEFAULT NULL COMMENT 'User permission to display in public IP Hub',
  publish_permission_date DATETIME DEFAULT NULL COMMENT 'Date when user responded to publish permission',
  
  -- File Attachments
  document_file TEXT COMMENT 'JSON array of uploaded document filenames',
  payment_receipt VARCHAR(255),
  payment_rejection_reason TEXT COMMENT 'Reason if payment receipt was rejected',
  supporting_documents TEXT,
  
  -- Important Dates
  office_visit_date DATETIME,
  payment_date DATETIME,
  payment_amount DECIMAL(10, 2),
  approved_at DATETIME,
  rejected_at DATETIME,
  
  -- Review and Feedback
  clerk_notes TEXT,
  director_feedback TEXT,
  rejection_reason TEXT,
  
  -- Award/Incentive Information
  award_amount DECIMAL(10,2) DEFAULT 0,
  award_reason TEXT,
  award_date DATETIME,
  
  -- Reference Numbers
  reference_number VARCHAR(50) UNIQUE,
  certificate_id VARCHAR(50) UNIQUE,
  
  -- Timestamps
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Foreign Keys
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  
  -- Indexes for Performance
  INDEX idx_app_user (user_id),
  INDEX idx_app_status (status),
  INDEX idx_app_type (ip_type),
  INDEX idx_reference (reference_number),
  INDEX idx_certificate (certificate_id),
  INDEX idx_publish_permission (publish_permission)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IP application records';

-- =====================================================
-- TABLE: certificates
-- Purpose: Store certificate information for approved applications
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Certificate records';

-- =====================================================
-- TABLE: achievement_certificates
-- Purpose: Store special certificates awarded when a user earns all badges
-- =====================================================
CREATE TABLE achievement_certificates (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  certificate_number VARCHAR(50) UNIQUE NOT NULL,
  issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_achievement_cert_user (user_id),
  INDEX idx_achievement_cert_number (certificate_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Achievement certificates for users who earn all badges';

-- =====================================================
-- TABLE: view_tracking
-- Purpose: Track views of IP applications for badge calculations
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='View tracking for analytics and badge thresholds';

-- =====================================================
-- TABLE: badges
-- Purpose: Store badges earned by users for their IP works
-- =====================================================
CREATE TABLE badges (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  application_id INT NULL COMMENT 'Specific application this badge was earned for',
  badge_type ENUM('Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond') NOT NULL,
  ip_type ENUM('Copyright', 'Patent', 'Trademark') NULL,
  work_title VARCHAR(255) NULL,
  views_required INT,
  awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (application_id) REFERENCES ip_applications(id) ON DELETE SET NULL,
  INDEX idx_badge_user (user_id),
  INDEX idx_badge_type (badge_type),
  INDEX idx_badge_app (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='User badges and achievements';

-- =====================================================
-- TABLE: badge_thresholds
-- Purpose: Define view thresholds for each badge level
-- =====================================================
CREATE TABLE badge_thresholds (
  id INT PRIMARY KEY AUTO_INCREMENT,
  badge_type ENUM('Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond') NOT NULL UNIQUE,
  views_required INT NOT NULL,
  points_awarded INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_badge_type_threshold (badge_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Badge threshold configuration';

-- Insert default badge thresholds
INSERT INTO badge_thresholds (badge_type, views_required, points_awarded) VALUES
('Bronze', 10, 50),
('Silver', 50, 150),
('Gold', 100, 300),
('Platinum', 250, 500),
('Diamond', 500, 1000);

-- =====================================================
-- TABLE: certificate_template_settings
-- Purpose: Store customizable certificate template settings
-- =====================================================
CREATE TABLE certificate_template_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  setting_key VARCHAR(100) UNIQUE NOT NULL,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Certificate template configuration';

-- Insert default certificate template settings
INSERT INTO certificate_template_settings (setting_key, setting_value) VALUES
('clerk_name', 'Maria Santos'),
('clerk_title', 'IP Office Clerk'),
('director_name', 'Dr. Juan Dela Cruz'),
('director_title', 'IP Office Director'),
('header_text', 'Certificate of Registration'),
('subtitle_text', 'Intellectual Property Work'),
('body_text', 'This is to certify that'),
('acknowledgment_text', 'This certificate acknowledges the registration and documentation of the aforementioned intellectual property work in the CHMSU IP Registry System.');

-- =====================================================
-- TABLE: audit_log
-- Purpose: Track all important system actions for security
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Audit trail for security and monitoring';

-- =====================================================
-- DEFAULT USER ACCOUNTS
-- =====================================================
-- Create default admin accounts (passwords are hashed using PHP password_hash)
-- Default password for all accounts: password

INSERT INTO users (email, password, full_name, role, department) VALUES
('clerk@chmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Clerk', 'clerk', 'IP Office'),
('director@chmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Director', 'director', 'IP Office'),
('student@chmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test Student', 'user', 'College of Engineering');

-- =====================================================
-- DATABASE SETUP COMPLETE
-- =====================================================
-- 
-- Default Login Credentials:
-- -------------------------------------------
-- Clerk Account:
--   Email: clerk@chmsu.edu.ph
--   Password: password
--
-- Director Account:
--   Email: director@chmsu.edu.ph
--   Password: password
--
-- Test User Account:
--   Email: student@chmsu.edu.ph
--   Password: password
-- -------------------------------------------
--
-- Tables Created (10 total):
-- 1. users - Authentication and roles
-- 2. user_profiles - Verification snapshots
-- 3. ip_applications - Main IP records (with abstract, research_type, award)
-- 4. certificates - Generated certificate metadata
-- 5. achievement_certificates - Medal achievements
-- 6. view_tracking - Hub view counters
-- 7. badges - User badges Earned
-- 8. badge_thresholds - Goal settings
-- 9. certificate_template_settings - Custom header/signatures
-- 10. audit_log - System history
--
-- =====================================================
