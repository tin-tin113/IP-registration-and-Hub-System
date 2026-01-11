<?php
// CHMSU IP System Configuration

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'chmsu-IP-system');

define('BASE_URL', 'http://localhost/chmsu-IP-system/');
define('PROJECT_NAME', 'CHMSU Intellectual Property Registration and Hub');

define('PRIMARY_COLOR', '#1B5C3B');
define('SECONDARY_COLOR', '#0F3D2E');
define('ACCENT_COLOR', '#E07D32');

// Session timeout (30 minutes)
define('SESSION_TIMEOUT', 1800);

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt']);
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB for IP documents

// CHMSU Details
define('UNIVERSITY_NAME', 'Carlos Hilado Memorial State University');
define('UNIVERSITY_SHORT', 'CHMSU');
define('IP_OFFICE_LOCATION', 'Reaserch Innovation and Extension House - Intellectual Property Management Office 1st floor');
define('IP_OFFICE_HOURS', 'Monday-Friday, 8:00 AM - 5:00 PM');
define('IP_OFFICE_EMAIL', 'ipmoffice@chmsu.edu.ph');
define('IP_OFFICE_PHONE', '(034) 495-4996');
define('IP_OFFICE_FACEBOOK', 'https://www.facebook.com/chmsuofficial');
define('CASHIER_LOCATION', 'CHMSU Cashier Office');
define('IP_REGISTRATION_FEE', 500.00); // Default registration fee in PHP

// Certificate details
define('CERTIFICATE_VALIDITY', 'For internal university recognition only, NOT legal IP protection');

// Sample security questions
$security_questions = [
  "What is your mother's maiden name?",
  "What city were you born?",
  "What is your pet's name?",
  "What is your favorite color?",
  "What was the name of your first school?",
  "What is your favorite book?",
  "In what city or town did your mother and father meet?"
];
