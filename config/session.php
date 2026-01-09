<?php
// Session Management

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Set session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
  session_destroy();
  header("Location: " . BASE_URL . "auth/login.php?error=Session expired");
  exit;
}

$_SESSION['last_activity'] = time();

// Check if user is logged in
function isLoggedIn() {
  return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get current user ID
function getCurrentUserId() {
  return $_SESSION['user_id'] ?? null;
}

// Get current user role
function getUserRole() {
  return $_SESSION['role'] ?? null;
}

// Get current user data
function getCurrentUser() {
  return $_SESSION['user_data'] ?? null;
}

// Require authentication
function requireLogin() {
  if (!isLoggedIn()) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
  }
}

// Require specific role
function requireRole($roles) {
  requireLogin();
  $roles = (array)$roles;
  if (!in_array(getUserRole(), $roles)) {
    header("Location: " . BASE_URL . "dashboard.php?error=Unauthorized");
    exit;
  }
}

// Log audit trail
function auditLog($action, $entity_type, $entity_id, $old_value = null, $new_value = null) {
  global $conn;
  
  $user_id = getCurrentUserId();
  $ip_address = $_SERVER['REMOTE_ADDR'];
  
  $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("ississs", $user_id, $action, $entity_type, $entity_id, $old_value, $new_value, $ip_address);
  $stmt->execute();
  $stmt->close();
}

// Logout
if (isset($_GET['logout'])) {
  session_destroy();
  header("Location: " . BASE_URL . "auth/login.php?message=Logged out successfully");
  exit;
}
