<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';
require_once '../config/form_fields_helper.php';

requireLogin();

$error = '';
$success = '';
$errors = []; // Array to collect multiple validation errors
$app_id = $_GET['id'] ?? null;
$draft_data = null;
$user_id = getCurrentUserId();

/**
 * Get human-readable file upload error message
 * @param int $error_code PHP file upload error code
 * @return string Human-readable error message
 */
function getFileUploadErrorMessage($error_code) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'The file exceeds the maximum upload size allowed by the server.',
        UPLOAD_ERR_FORM_SIZE => 'The file exceeds the maximum size allowed by the form.',
        UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error: Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Server error: Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by a PHP extension.',
    ];
    return $errors[$error_code] ?? 'Unknown upload error occurred.';
}

/**
 * Log application errors to a file for debugging
 * @param string $message Error message
 * @param array $context Additional context data
 */
function logApplicationError($message, $context = []) {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/apply_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $context_str = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $log_entry = "[{$timestamp}] {$message}{$context_str}\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Ensure user_profiles table exists
try {
    $conn->query("CREATE TABLE IF NOT EXISTS user_profiles (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL UNIQUE,
  first_name VARCHAR(100),
  middle_name VARCHAR(100),
  last_name VARCHAR(100),
  suffix ENUM('', 'Jr.', 'Sr.', 'II', 'III', 'IV', 'V') DEFAULT '',
  email VARCHAR(255),
  birthdate DATE,
  gender ENUM('Male', 'Female') DEFAULT 'Male',
  nationality VARCHAR(100) DEFAULT 'Filipino',
  employment_status ENUM('Student', 'Faculty', 'Staff', 'Researcher', 'Alumni', 'Other') DEFAULT 'Student',
  employee_id VARCHAR(50),
  college VARCHAR(255),
  contact_number VARCHAR(20),
  address_street VARCHAR(255),
  address_barangay VARCHAR(100),
  address_city VARCHAR(100),
  address_province ENUM('Negros Occidental', 'Negros Oriental') DEFAULT 'Negros Occidental',
  address_postal VARCHAR(10),
  is_complete BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_profile_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    logApplicationError('Failed to create user_profiles table', ['error' => $e->getMessage()]);
    // Continue anyway - table likely already exists
}

// Fetch existing profile for auto-fill
try {
    $profile_stmt = $conn->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
    if (!$profile_stmt) {
        throw new Exception("Failed to prepare profile statement: " . $conn->error);
    }
    $profile_stmt->bind_param("i", $user_id);
    $profile_stmt->execute();
    $profile_result = $profile_stmt->get_result();
    $user_profile = $profile_result->fetch_assoc();
    $profile_stmt->close();
} catch (Exception $e) {
    logApplicationError('Failed to fetch user profile', ['user_id' => $user_id, 'error' => $e->getMessage()]);
    $user_profile = null;
}

// Get user email for pre-filling
try {
    $user_stmt = $conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
    if (!$user_stmt) {
        throw new Exception("Failed to prepare user statement: " . $conn->error);
    }
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_data = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();
} catch (Exception $e) {
    logApplicationError('Failed to fetch user data', ['user_id' => $user_id, 'error' => $e->getMessage()]);
    $user_data = ['email' => '', 'full_name' => ''];
}

// If ID provided, check if it's a valid draft for this user
if ($app_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM ip_applications WHERE id = ? AND user_id = ? AND status = 'draft'");
        if (!$stmt) {
            throw new Exception("Failed to prepare draft check statement: " . $conn->error);
        }
        $stmt->bind_param("ii", $app_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $draft_data = $result->fetch_assoc();
        } else {
            $app_id = null; 
        }
        $stmt->close();
    } catch (Exception $e) {
        logApplicationError('Failed to fetch draft data', ['app_id' => $app_id, 'user_id' => $user_id, 'error' => $e->getMessage()]);
        $app_id = null;
        $error = 'Unable to load draft. Please try again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['title'] ?? '');
  $inventor_name = trim($_POST['inventor_name'] ?? '');
  $ip_type = trim($_POST['ip_type'] ?? '');
  $research_type = trim($_POST['research_type'] ?? '');
  $abstract = trim($_POST['abstract'] ?? '');
  $action = trim($_POST['action'] ?? 'draft');
  $existing_app_id = $_POST['app_id'] ?? $app_id ?? null; // Use POST app_id or URL app_id
  
  // Save/Update user profile data
  $profile_first_name = trim($_POST['profile_first_name'] ?? '');
  $profile_middle_name = trim($_POST['profile_middle_name'] ?? '');
  $profile_last_name = trim($_POST['profile_last_name'] ?? '');
  $profile_suffix = trim($_POST['profile_suffix'] ?? '');
  $profile_email = trim($_POST['profile_email'] ?? '');
  $profile_birthdate = trim($_POST['profile_birthdate'] ?? '');
  $profile_gender = trim($_POST['profile_gender'] ?? 'Male');
  $profile_nationality = trim($_POST['profile_nationality'] ?? 'Filipino');
  $profile_employment = trim($_POST['profile_employment_status'] ?? 'Student');
  $profile_employee_id = trim($_POST['profile_employee_id'] ?? '');
  $profile_college = trim($_POST['profile_college'] ?? '');
  $profile_contact = trim($_POST['profile_contact_number'] ?? '');
  $profile_street = trim($_POST['profile_address_street'] ?? '');
  $profile_barangay = trim($_POST['profile_address_barangay'] ?? '');
  $profile_city = trim($_POST['profile_address_city'] ?? '');
  $profile_province = trim($_POST['profile_address_province'] ?? 'Negros Occidental');
  $profile_postal = trim($_POST['profile_address_postal'] ?? '');
  
  // Collect custom fields (admin-added fields)
  $customFields = [];
  $allFormFields = getActiveFormFields($conn);
  $builtinFieldNames = ['first_name', 'middle_name', 'last_name', 'suffix', 'email', 
    'contact_number', 'birthdate', 'gender', 'nationality', 'employment_status', 
    'employee_id', 'college', 'address_street', 'address_barangay', 'address_city', 
    'address_province', 'address_postal'];
  
  foreach ($allFormFields as $field) {
    if (!in_array($field['field_name'], $builtinFieldNames)) {
      $postKey = 'profile_' . $field['field_name'];
      if (isset($_POST[$postKey])) {
        $customFields[$field['field_name']] = trim($_POST[$postKey]);
      }
    }
  }
  $customFieldsJson = !empty($customFields) ? json_encode($customFields) : null;
  
  // Check if profile is complete (required fields filled)
  $profile_is_complete = !empty($profile_first_name) && !empty($profile_last_name) && 
                         !empty($profile_email) && !empty($profile_birthdate) && 
                         !empty($profile_employee_id) && !empty($profile_college) && 
                         !empty($profile_contact);
  
  // Also check if any required custom fields are missing
  foreach ($allFormFields as $field) {
    if ($field['is_required'] && !$field['is_builtin']) {
      $value = $customFields[$field['field_name']] ?? '';
      if (empty($value)) {
        $profile_is_complete = false;
        break;
      }
    }
  }
  
  // Save or update profile
  if (!empty($profile_first_name) || !empty($profile_last_name)) {
    try {
      $check_profile = $conn->prepare("SELECT id, custom_fields FROM user_profiles WHERE user_id = ?");
      if (!$check_profile) {
        throw new Exception("Failed to prepare profile check: " . $conn->error);
      }
      $check_profile->bind_param("i", $user_id);
      $check_profile->execute();
      $profileResult = $check_profile->get_result();
      $profile_exists = $profileResult->num_rows > 0;
      
      // Merge with existing custom fields
      if ($profile_exists) {
        $existingRow = $profileResult->fetch_assoc();
        if (!empty($existingRow['custom_fields'])) {
          $existingCustom = json_decode($existingRow['custom_fields'], true) ?: [];
          $customFields = array_merge($existingCustom, $customFields);
          $customFieldsJson = json_encode($customFields);
        }
      }
      $check_profile->close();
      
      if ($profile_exists) {
        $update_profile = $conn->prepare("UPDATE user_profiles SET 
          first_name=?, middle_name=?, last_name=?, suffix=?, email=?, birthdate=?, 
          gender=?, nationality=?, employment_status=?, employee_id=?, college=?, 
          contact_number=?, address_street=?, address_barangay=?, address_city=?, 
          address_province=?, address_postal=?, custom_fields=?, is_complete=?, updated_at=NOW() 
          WHERE user_id=?");
        if (!$update_profile) {
          throw new Exception("Failed to prepare profile update: " . $conn->error);
        }
        $update_profile->bind_param("ssssssssssssssssssii", 
          $profile_first_name, $profile_middle_name, $profile_last_name, $profile_suffix,
          $profile_email, $profile_birthdate, $profile_gender, $profile_nationality,
          $profile_employment, $profile_employee_id, $profile_college, $profile_contact,
          $profile_street, $profile_barangay, $profile_city, $profile_province, $profile_postal,
          $customFieldsJson, $profile_is_complete, $user_id);
        if (!$update_profile->execute()) {
          throw new Exception("Failed to update profile: " . $update_profile->error);
        }
        $update_profile->close();
      } else {
        $insert_profile = $conn->prepare("INSERT INTO user_profiles 
          (user_id, first_name, middle_name, last_name, suffix, email, birthdate, 
           gender, nationality, employment_status, employee_id, college, contact_number, 
           address_street, address_barangay, address_city, address_province, address_postal, 
           custom_fields, is_complete) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$insert_profile) {
          throw new Exception("Failed to prepare profile insert: " . $conn->error);
        }
        $insert_profile->bind_param("issssssssssssssssssi", 
          $user_id, $profile_first_name, $profile_middle_name, $profile_last_name, $profile_suffix,
          $profile_email, $profile_birthdate, $profile_gender, $profile_nationality,
          $profile_employment, $profile_employee_id, $profile_college, $profile_contact,
          $profile_street, $profile_barangay, $profile_city, $profile_province, $profile_postal,
          $customFieldsJson, $profile_is_complete);
        if (!$insert_profile->execute()) {
          throw new Exception("Failed to insert profile: " . $insert_profile->error);
        }
        $insert_profile->close();
      }
      
      // Refresh profile data
      $profile_stmt = $conn->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
      if ($profile_stmt) {
        $profile_stmt->bind_param("i", $user_id);
        $profile_stmt->execute();
        $user_profile = $profile_stmt->get_result()->fetch_assoc();
        $profile_stmt->close();
      }
    } catch (Exception $e) {
      logApplicationError('Profile save/update failed', [
        'user_id' => $user_id,
        'error' => $e->getMessage()
      ]);
      // Don't block form submission for profile errors, just log it
      // User can still submit the application
    }
  }
  
  // For draft, allow submission even without required fields
  // For submit, all fields including documents are required
  if ($action === 'draft') {
    // Draft can be saved with minimal data - no strict validation
    if (!in_array($ip_type, ['Copyright', 'Patent', 'Trademark']) && !empty($ip_type)) {
      $error = 'Invalid IP type';
    }
  } else {
    // Submit requires all fields
    if (empty($title) || empty($inventor_name) || empty($ip_type) || empty($abstract)) {
      $error = 'Title, inventor name, type, and abstract are required';
    } elseif (strlen($abstract) < 20) {
      $error = 'Abstract must be at least 20 characters long';
    } elseif (!in_array($ip_type, ['Copyright', 'Patent', 'Trademark'])) {
      $error = 'Invalid IP type';
    }
  }
  
  if (empty($error)) {
    // Check application restrictions (only for submitted applications, not drafts)
    if ($action === 'submit') {
      // Check if user has submitted 2 or more applications today
      $today_start = date('Y-m-d 00:00:00');
      $today_end = date('Y-m-d 23:59:59');
      $today_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM ip_applications WHERE user_id = ? AND status != 'draft' AND created_at BETWEEN ? AND ?");
      $today_count_stmt->bind_param("iss", $user_id, $today_start, $today_end);
      $today_count_stmt->execute();
      $today_count_result = $today_count_stmt->get_result();
      $today_count = $today_count_result->fetch_assoc()['count'];
      $today_count_stmt->close();
      
      if ($today_count >= 2) {
        $error = 'You have already submitted 2 applications today. Maximum 2 applications per day allowed.';
      } else {
        // Check if there's a recent application (within 3 days)
        $three_days_ago = date('Y-m-d H:i:s', strtotime('-2 days'));
        $recent_app_stmt = $conn->prepare("SELECT created_at FROM ip_applications WHERE user_id = ? AND status != 'draft' AND created_at > ? ORDER BY created_at DESC LIMIT 1");
        $recent_app_stmt->bind_param("is", $user_id, $three_days_ago);
        $recent_app_stmt->execute();
        $recent_app_result = $recent_app_stmt->get_result();
        
        if ($recent_app_result->num_rows > 0) {
          $recent_app = $recent_app_result->fetch_assoc();
          $last_app_date = strtotime($recent_app['created_at']);
          $days_since = floor((time() - $last_app_date) / (60 * 60 * 24));
          
          if ($days_since < 3) {
            $days_remaining = 2 - $days_since;
            $error = "You must wait {$days_remaining} more day(s) before submitting another application.";
          }
        }
        $recent_app_stmt->close();
      }
    }
    
    if (empty($error)) {
      $document_files = [];
      $total_size = 0;
      $has_new_files = false;
      
      if (isset($_FILES['documents']) && !empty($_FILES['documents']['name'][0])) {
        $has_new_files = true;
        $file_count = count($_FILES['documents']['name']);
        
        // Calculate total size first
        for ($i = 0; $i < $file_count; $i++) {
          if ($_FILES['documents']['error'][$i] === 0) {
            $total_size += $_FILES['documents']['size'][$i];
          }
        }
        
        // Check if total size exceeds 50MB
        if ($total_size > MAX_FILE_SIZE) {
          $error = 'Total file size exceeds 50MB limit. Current total: ' . round($total_size / (1024 * 1024), 2) . 'MB';
        } else {
          // Process each file
          for ($i = 0; $i < $file_count; $i++) {
            $file_error = $_FILES['documents']['error'][$i];
            $file_name = $_FILES['documents']['name'][$i];
            
            // Handle file upload errors with specific messages
            if ($file_error !== UPLOAD_ERR_OK) {
              if ($file_error !== UPLOAD_ERR_NO_FILE) {
                $error = 'Upload error for "' . htmlspecialchars($file_name) . '": ' . getFileUploadErrorMessage($file_error);
                logApplicationError('File upload error', [
                  'file' => $file_name,
                  'error_code' => $file_error,
                  'user_id' => $user_id
                ]);
                break;
              }
              continue; // Skip files with no upload
            }
            
            $file_tmp = $_FILES['documents']['tmp_name'][$i];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            if (!in_array($ext, ALLOWED_EXTENSIONS)) {
              $error = 'Invalid file type for "' . htmlspecialchars($file_name) . '". Allowed: ' . implode(', ', ALLOWED_EXTENSIONS);
              break;
            }
            
            // Create upload directory if needed
            if (!is_dir(UPLOAD_DIR)) {
              if (!@mkdir(UPLOAD_DIR, 0755, true)) {
                $error = 'Server error: Unable to create upload directory.';
                logApplicationError('Failed to create upload directory', ['dir' => UPLOAD_DIR]);
                break;
              }
            }
            
            // Determine subfolder based on IP type
            $target_dir = UPLOAD_DIR;
            $subfolder = '';
            
            if (!empty($ip_type) && in_array($ip_type, ['Copyright', 'Patent', 'Trademark'])) {
              $subfolder = strtolower($ip_type) . '/';
              $target_dir = UPLOAD_DIR . $subfolder;
              
              if (!is_dir($target_dir)) {
                if (!@mkdir($target_dir, 0755, true)) {
                  $error = 'Server error: Unable to create type subfolder.';
                  logApplicationError('Failed to create subfolder', ['dir' => $target_dir]);
                  break;
                }
              }
            }
            
            $filename = 'doc_' . time() . '_' . uniqid() . '.' . $ext;
            $filepath = $target_dir . $filename;
            
            if (move_uploaded_file($file_tmp, $filepath)) {
              // Store relative path (e.g., 'patent/filename.ext')
              $document_files[] = $subfolder . $filename;
            } else {
              $error = 'Failed to upload file: ' . htmlspecialchars($file_name) . '. Please try again.';
              logApplicationError('move_uploaded_file failed', [
                'file' => $file_name,
                'target' => $filepath,
                'user_id' => $user_id
              ]);
              break;
            }
          }
        }
      }
      
      // Check if documents are required (for submit action)
      if ($action === 'submit') {
        // Check if there are new files uploaded OR existing files in draft
        $has_existing_files = false;
        if ($existing_app_id) {
          $stmt = $conn->prepare("SELECT document_file FROM ip_applications WHERE id = ? AND user_id = ?");
          $stmt->bind_param("ii", $existing_app_id, $user_id);
          $stmt->execute();
          $res = $stmt->get_result();
          if ($row = $res->fetch_assoc()) {
            $existing_docs = !empty($row['document_file']) ? json_decode($row['document_file'], true) : [];
            $has_existing_files = is_array($existing_docs) && count($existing_docs) > 0;
          }
          $stmt->close();
        }
        
        if (!$has_new_files && !$has_existing_files) {
          $error = 'Supporting documents are required when submitting an application. Please upload at least one document.';
        }
      }
      
      if (empty($error)) {
        if ($action === 'draft') {
          $status = 'draft';
        } else {
          $status = 'submitted';
        }
        
        // Store multiple files as JSON array
        // Merge new files with existing files if updating a draft
        $final_documents = $document_files;
        
        if ($existing_app_id) {
          // Get existing docs
          $stmt = $conn->prepare("SELECT document_file FROM ip_applications WHERE id = ? AND user_id = ?");
          $stmt->bind_param("ii", $existing_app_id, $user_id);
          $stmt->execute();
          $res = $stmt->get_result();
          if ($row = $res->fetch_assoc()) {
            $existing_docs = !empty($row['document_file']) ? json_decode($row['document_file'], true) : [];
            if (is_array($existing_docs)) {
              // Handle removed files (user removed from form, but we keep them in storage)
              // Only remove from final list if user explicitly removed them
              $removed_files_json = $_POST['removed_files'] ?? '';
              $removed_files = !empty($removed_files_json) ? json_decode($removed_files_json, true) : [];
              
              // Filter out removed files from existing docs
              $existing_docs = array_filter($existing_docs, function($file) use ($removed_files) {
                return !in_array($file, $removed_files);
              });
              
              // Merge existing files (after filtering) with new files
              $final_documents = array_merge(array_values($existing_docs), $document_files);
            }
          }
          $stmt->close();
          
          $document_file_json = !empty($final_documents) ? json_encode($final_documents) : '';
          
          // UPDATE - Store all information in database
          $stmt = $conn->prepare("UPDATE ip_applications SET title=?, inventor_name=?, ip_type=?, research_type=?, abstract=?, document_file=?, status=?, updated_at=NOW() WHERE id=? AND user_id=?");
          $stmt->bind_param("sssssssii", $title, $inventor_name, $ip_type, $research_type, $abstract, $document_file_json, $status, $existing_app_id, $user_id);
          
          if ($stmt->execute()) {
            $app_id = $existing_app_id;
            auditLog('Update Application', 'Application', $app_id, null, json_encode(['title' => $title, 'type' => $ip_type, 'status' => $status]));
            
            if ($status === 'submitted') {
              $stmt->close();
              header("Location: ../dashboard.php?success=Application submitted successfully!");
              exit;
            } else {
              $stmt->close();
              // Redirect to dashboard to prevent resubmission on refresh
              header("Location: ../dashboard.php?success=Draft updated successfully.");
              exit;
            }
          } else {
            $error = 'Failed to update application';
          }
          $stmt->close();
          
        } else {
          // INSERT - Store all information in database
          $document_file_json = !empty($final_documents) ? json_encode($final_documents) : '';
          
          $stmt = $conn->prepare("INSERT INTO ip_applications (user_id, title, inventor_name, ip_type, research_type, abstract, document_file, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
          $stmt->bind_param("isssssss", $user_id, $title, $inventor_name, $ip_type, $research_type, $abstract, $document_file_json, $status);
          
          if ($stmt->execute()) {
            $app_id = $stmt->insert_id;
            auditLog('Submit Application', 'Application', $app_id, null, json_encode(['title' => $title, 'type' => $ip_type]));
            
            if ($status === 'submitted') {
              $stmt->close();
              header("Location: ../dashboard.php?success=Application submitted successfully! Clerk will review it soon.");
              exit;
            } else {
              // Draft saved - redirect to dashboard
              $stmt->close();
              header("Location: ../dashboard.php?success=Application saved as draft successfully.");
              exit;
            }
          } else {
            $error = 'Failed to save application';
          }
          $stmt->close();
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $draft_data ? 'Edit Draft Application' : 'Submit IP Application'; ?> - CHMSU</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f5f7fa;
      min-height: 100vh;
      padding: 20px;
    }
    
    .container {
      max-width: 700px;
      margin: 0 auto;
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      padding: 40px;
    }
    
    .header {
      display: flex;
      align-items: center;
      margin-bottom: 30px;
      padding-bottom: 20px;
      border-bottom: 2px solid #667eea;
    }
    
    .header i {
      font-size: 32px;
      color: #667eea;
      margin-right: 15px;
    }
    
    .header h1 {
      font-size: 24px;
      color: #333;
    }
    
    .breadcrumb {
      font-size: 13px;
      color: #666;
      margin-top: 5px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    label {
      display: block;
      margin-bottom: 8px;
      color: #333;
      font-weight: 600;
      font-size: 14px;
    }
    
    .label-info {
      font-size: 12px;
      color: #999;
      font-weight: normal;
      margin-top: 3px;
    }
    
    input[type="text"],
    input[type="email"],
    select,
    textarea {
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 14px;
      font-family: inherit;
      box-sizing: border-box;
    }
    
    input:focus,
    select:focus,
    textarea:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 5px rgba(102,126,234,0.3);
    }
    
    textarea {
      resize: vertical;
      min-height: 120px;
      word-wrap: break-word;
      overflow-wrap: break-word;
    }
    
    .file-upload {
      position: relative;
      border: 2px dashed #ddd;
      border-radius: 5px;
      padding: 20px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .file-upload:hover {
      border-color: #667eea;
      background-color: #f0f4ff;
    }
    
    .file-upload input[type="file"] {
      display: none;
    }
    
    .file-upload i {
      font-size: 32px;
      color: #667eea;
      margin-bottom: 10px;
      display: block;
    }
    
    /* Added styles for multiple file display */
    .file-list {
      margin-top: 15px;
      text-align: left;
      display: block;
      min-height: 20px;
    }
    
    .file-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px;
      background: #f0f4ff;
      border-radius: 5px;
      margin-bottom: 8px;
      font-size: 13px;
    }
    
    .file-item-name {
      flex: 1;
      color: #333;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .file-item-size {
      color: #666;
      font-size: 12px;
      margin: 0 10px;
    }
    
    .file-item-remove {
      background: #f44336;
      color: white;
      border: none;
      border-radius: 3px;
      padding: 5px 10px;
      cursor: pointer;
      font-size: 11px;
    }
    
    .total-size {
      margin-top: 10px;
      padding: 10px;
      background: #fff3cd;
      border-radius: 5px;
      font-size: 13px;
      text-align: center;
      font-weight: 600;
    }
    
    .total-size.warning {
      background: #f8d7da;
      color: #721c24;
    }
    
    .buttons {
      display: flex;
      gap: 15px;
      margin-top: 30px;
      justify-content: flex-end;
    }
    
    .btn-draft,
    .btn-submit {
      padding: 12px 24px;
      border: none;
      border-radius: 5px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-draft {
      background: #6c757d;
      color: white;
    }
    
    .btn-draft:hover {
      background: #5a6268;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    
    .btn-submit {
      background: linear-gradient(135deg, #1B5C3B);
      color: white;
    }
    
    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102,126,234,0.4);
    }
    
    .alert {
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .alert-danger {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    @media (max-width: 768px) {
      .container {
        padding: 20px;
        max-width: 100%;
      }

      .header {
        flex-direction: column;
        align-items: flex-start;
      }

      .header i {
        font-size: 28px;
      }

      .header h1 {
        font-size: 20px;
      }

      textarea {
        min-height: 100px;
        font-size: 16px; /* Prevents zoom on iOS */
      }

      .buttons {
        flex-direction: column-reverse;
      }

      .btn-draft, .btn-submit {
        width: 100%;
        justify-content: center;
      }
    }

    @media (max-width: 480px) {
      .container {
        padding: 16px;
        border-radius: 0;
      }

      .header h1 {
        font-size: 18px;
      }

      input[type="text"],
      input[type="email"],
      select,
      textarea {
        padding: 12px;
        font-size: 16px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="../dashboard.php" class="btn-back" style="display: inline-flex; align-items: center; gap: 8px; color: #667eea; text-decoration: none; margin-bottom: 20px; font-size: 14px; font-weight: 600;"><i class="fas fa-arrow-left"></i> Back</a>
    
    <div class="header">
      <i class="fas fa-lightbulb"></i>
      <div>
        <h1><?php echo $draft_data ? 'Edit Draft Application' : 'Submit IP Application'; ?></h1>
        <div class="breadcrumb">Dashboard / <?php echo $draft_data ? 'Edit Draft' : 'Submit Application'; ?></div>
      </div>
    </div>
    
    <!-- Requirements & Instructions -->
    <?php
    // Load instructions from JSON file with fallback to defaults
    $instructions_file = __DIR__ . '/../config/form_instructions.json';
    $form_instructions = [];
    if (file_exists($instructions_file)) {
        $form_instructions = json_decode(file_get_contents($instructions_file), true) ?? [];
    }
    
    // Default instructions if JSON file is empty or missing
    if (empty($form_instructions)) {
        $form_instructions = [
            'what_to_include' => [
                'title' => 'What to Include:',
                'items' => [
                    'Clear, descriptive title of your IP work',
                    'Detailed description (minimum 20 characters)',
                    'Select appropriate IP type (Copyright, Patent, or Trademark)',
                    'Supporting documentation (PDF or image)'
                ]
            ],
            'document_requirements' => [
                'title' => 'Document Requirements:',
                'items' => [
                    '<strong>Accepted formats:</strong> PDF,JPG, PNG, TXT',
                    '<strong>Maximum total size:</strong> 50MB for all files',
                    '<strong>Multiple files:</strong> You can upload multiple documents',
                    '<strong>Language:</strong> English or Filipino'
                ]
            ]
        ];
    }
    ?>
    <div style="background: linear-gradient(135deg, #1B5C3B 0%, #0F3D2E 100%); color: white; padding: 25px; border-radius: 8px; margin-bottom: 30px;">
      <h2 style="margin-bottom: 15px; font-size: 16px;"><i class="fas fa-clipboard-list"></i> Requirements & Instructions</h2>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <?php foreach ($form_instructions as $section): ?>
        <div>
          <h4 style="margin-bottom: 8px; font-size: 13px;"><?php echo htmlspecialchars($section['title']); ?></h4>
          <ul style="margin-left: 20px; font-size: 13px; line-height: 1.8;">
            <?php foreach ($section['items'] as $item): ?>
              <li><?php echo $item; ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
      <div class="alert alert-success">
        <?php echo htmlspecialchars($success); ?>
        <a href="my-applications.php" style="color: #155724; font-weight: 600; text-decoration: none;">View my applications →</a>
      </div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data" id="appForm">
      <?php if ($draft_data): ?>
        <input type="hidden" name="app_id" value="<?php echo $draft_data['id']; ?>">
      <?php elseif ($app_id): ?>
        <input type="hidden" name="app_id" value="<?php echo $app_id; ?>">
      <?php endif; ?>
      
      <!-- Personal Information Section (Collapsible) -->
      <div style="background: <?php echo ($user_profile && $user_profile['is_complete']) ? '#D4EDDA' : '#FFF3CD'; ?>; border: 2px solid <?php echo ($user_profile && $user_profile['is_complete']) ? '#28a745' : '#ffc107'; ?>; border-radius: 12px; margin-bottom: 30px; overflow: hidden;">
        <div onclick="toggleProfileSection()" style="padding: 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: <?php echo ($user_profile && $user_profile['is_complete']) ? 'linear-gradient(135deg, #28a745 0%, #20c997 100%)' : 'linear-gradient(135deg, #ffc107 0%, #fd7e14 100%)'; ?>; color: white;">
          <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-<?php echo ($user_profile && $user_profile['is_complete']) ? 'check-circle' : 'user-edit'; ?>" style="font-size: 24px;"></i>
            <div>
              <h3 style="margin: 0; font-size: 16px;"><?php echo ($user_profile && $user_profile['is_complete']) ? 'Profile Complete ✓' : 'Personal Information Required'; ?></h3>
              <p style="margin: 4px 0 0 0; font-size: 12px; opacity: 0.9;"><?php echo ($user_profile && $user_profile['is_complete']) ? 'Click to view or update your information' : 'Please complete your profile for verification purposes'; ?></p>
            </div>
          </div>
          <i class="fas fa-chevron-down" id="profileToggleIcon" style="font-size: 18px; transition: transform 0.3s;"></i>
        </div>
        
        <div id="profileFormSection" style="padding: 25px; display: <?php echo ($user_profile && $user_profile['is_complete']) ? 'none' : 'block'; ?>; background: white;">
          <p style="font-size: 13px; color: #666; margin-bottom: 20px; padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea;">
            <i class="fas fa-shield-alt" style="color: #667eea;"></i> 
            <strong>Privacy Notice:</strong> This information is for verification purposes only and will only be visible to authorized CHMSU IP Office staff.
          </p>
          
          <?php 
          // Get dynamic form fields grouped by section
          $formFieldsBySection = getFieldsBySection($conn);
          $sectionLabels = [
            'name' => 'Name Information',
            'contact' => 'Contact Information', 
            'personal' => 'Personal Details',
            'employment' => 'Employment/Academic',
            'address' => 'Address Information'
          ];
          
          // Check for new unfilled required fields (for notification)
          $newUnfilledFields = [];
          foreach (getActiveFormFields($conn) as $field) {
            if ($field['is_required'] && !$field['is_builtin']) {
              $value = getFormFieldValue($field['field_name'], $user_profile ?? []);
              if (empty($value)) {
                $newUnfilledFields[] = $field['field_label'];
              }
            }
          }
          
          if (!empty($newUnfilledFields) && ($user_profile && $user_profile['is_complete'])): ?>
          <div style="background: #FFF3CD; border: 1px solid #FFC107; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <strong style="color: #856404;"><i class="fas fa-exclamation-triangle"></i> New Fields Required</strong>
            <p style="margin: 8px 0 0 0; font-size: 13px; color: #856404;">
              The administrator has added new required fields that need to be completed: 
              <strong><?php echo htmlspecialchars(implode(', ', $newUnfilledFields)); ?></strong>
            </p>
          </div>
          <?php endif; ?>
          
          <?php foreach ($sectionLabels as $sectionKey => $sectionTitle): 
            $sectionFields = $formFieldsBySection[$sectionKey] ?? [];
            if (empty($sectionFields)) continue;
          ?>
          
          <?php if ($sectionKey === 'address'): ?>
          <h4 style="margin: 20px 0 15px 0; font-size: 14px; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 8px;">
            <i class="fas fa-map-marker-alt"></i> <?php echo $sectionTitle; ?>
          </h4>
          <?php endif; ?>
          
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
            <?php foreach ($sectionFields as $field): 
              $fieldValue = getFormFieldValue($field['field_name'], $user_profile ?? []);
              // Special handling for email - fallback to user_data
              if ($field['field_name'] === 'email' && empty($fieldValue)) {
                $fieldValue = $user_data['email'] ?? '';
              }
              // Default value for nationality
              if ($field['field_name'] === 'nationality' && empty($fieldValue)) {
                $fieldValue = 'Filipino';
              }
              // Default value for province
              if ($field['field_name'] === 'address_province' && empty($fieldValue)) {
                $fieldValue = 'Negros Occidental';
              }
            ?>
            <div class="form-group" style="margin-bottom: 0;">
              <label>
                <?php echo htmlspecialchars($field['field_label']); ?>
                <?php if ($field['is_required']): ?><span style="color: #dc3545;">*</span><?php endif; ?>
              </label>
              <?php 
              // Special handling for province/city selects
              if ($field['field_name'] === 'address_province'): ?>
                <select name="profile_address_province" id="profileProvince" onchange="updateCityOptions()" <?php echo $field['is_required'] ? 'required' : ''; ?>>
                  <option value="Negros Occidental" <?php echo $fieldValue === 'Negros Occidental' ? 'selected' : ''; ?>>Negros Occidental</option>
                  <option value="Negros Oriental" <?php echo $fieldValue === 'Negros Oriental' ? 'selected' : ''; ?>>Negros Oriental</option>
                </select>
              <?php elseif ($field['field_name'] === 'address_city'): ?>
                <select name="profile_address_city" id="profileCity" <?php echo $field['is_required'] ? 'required' : ''; ?>>
                  <!-- Will be populated by JavaScript -->
                </select>
              <?php else:
                echo renderFormField($field, $fieldValue);
              endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-group">
        <label for="title">IP Work Title <span style="color: #dc3545;">*</span> <span style="font-size: 12px; font-weight: normal; color: #666;">(Required for submission)</span></label>
        <input type="text" id="title" name="title" placeholder="e.g., Advanced Machine Learning Framework" value="<?php echo htmlspecialchars($draft_data['title'] ?? ''); ?>">
      </div>
      
      <div class="form-group">
        <label for="num_inventors">Number of Inventors</label>
        <select id="num_inventors" class="form-control" style="margin-bottom: 10px; width: 100px;" onchange="updateInventorFields()">
          <?php for($i=1; $i<=10; $i++): ?>
            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
          <?php endfor; ?>
        </select>

        <label>Name of the Inventor(s) <span style="color: #dc3545;">*</span> <span style="font-size: 12px; font-weight: normal; color: #666;">(Required - Format: First M. Last, Suffix)</span></label>
        <p style="font-size: 12px; color: #666; margin-bottom: 8px; background: #e3f2fd; padding: 10px; border-radius: 4px; border-left: 3px solid #2196F3;">
          <i class="fas fa-info-circle" style="color: #2196F3;"></i> <strong>Important:</strong> Please ensure names are correct. These will appear on your official Certificate of Registration.
        </p>
        
        <div id="inventor_fields_container">
          <!-- Dynamic fields will be inserted here -->
        </div>
        
        <!-- Hidden input to store the final combined string -->
        <input type="hidden" id="inventor_name" name="inventor_name" value="<?php echo htmlspecialchars($draft_data['inventor_name'] ?? ''); ?>">

        <script>
        function updateInventorFields() {
          const container = document.getElementById('inventor_fields_container');
          const count = document.getElementById('num_inventors').value;
          const currentValues = [];
          
          // Save current values
          const existingInputs = container.querySelectorAll('input');
          existingInputs.forEach(input => currentValues.push(input.value));
          
          container.innerHTML = '';
          
          for (let i = 0; i < count; i++) {
            const div = document.createElement('div');
            div.style.marginBottom = '10px';
            
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'inventor-input';
            input.placeholder = `Inventor ${i + 1} Name (e.g., Juan D. Cruz, Jr.)`;
            input.value = currentValues[i] || '';
            input.required = true;
            input.oninput = combineInventorNames;
            
            // Apply auto-capitalization if the function exists
            if (typeof toTitleCase === 'function') {
               input.addEventListener('input', function() {
                 this.value = toTitleCase(this.value);
                 combineInventorNames();
               });
            }
            
            div.appendChild(input);
            container.appendChild(div);
          }
          combineInventorNames();
        }

        function combineInventorNames() {
          const inputs = document.querySelectorAll('.inventor-input');
          const names = [];
          inputs.forEach(input => {
            if (input.value.trim()) {
              names.push(input.value.trim());
            }
          });
          console.log('Combined names:', names.join('\n')); // Debug
          document.getElementById('inventor_name').value = names.join('\n');
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
          const hiddenValue = document.getElementById('inventor_name').value;
          if (hiddenValue) {
            const names = hiddenValue.split('\n');
            // Remove empty lines
            const validNames = names.filter(n => n.trim() !== '');
            if (validNames.length > 0) {
                document.getElementById('num_inventors').value = validNames.length;
            }
            
            // Render fields
            updateInventorFields();
            
            // Fill values
            const inputs = document.querySelectorAll('.inventor-input');
            names.forEach((name, index) => {
              if (inputs[index]) inputs[index].value = name;
            });
          } else {
            updateInventorFields();
          }

          // Force update on form submit to ensure hidden field is populated
          const form = document.getElementById('appForm');
          if (form) {
            form.addEventListener('submit', function() {
                combineInventorNames();
            });
          }
        });
        </script>
      </div>
      
      <div class="form-group">
        <label for="ip_type">IP Type <span style="color: #dc3545;">*</span> <span style="font-size: 12px; font-weight: normal; color: #666;">(Required for submission)</span></label>
        <select id="ip_type" name="ip_type">
          <option value="">Select IP Type</option>
          <option value="Copyright" <?php echo ($draft_data['ip_type'] ?? '') === 'Copyright' ? 'selected' : ''; ?>>Copyright (literary, artistic, musical works)</option>
          <option value="Patent" <?php echo ($draft_data['ip_type'] ?? '') === 'Patent' ? 'selected' : ''; ?>>Patent (invention, technical innovation)</option>
          <option value="Trademark" <?php echo ($draft_data['ip_type'] ?? '') === 'Trademark' ? 'selected' : ''; ?>>Trademark (name, symbol, brand)</option>
        </select>
        <div class="ip-type-info" id="typeInfo" style="margin-top: 8px; font-size: 13px; color: #666;"></div>
      </div>
      
      <div class="form-group" id="researchTypeGroup" style="display: none;">
        <label for="research_type">Research Type/Specialization <span style="color: #dc3545;">*</span> <span style="font-size: 12px; font-weight: normal; color: #666;">(Select based on IP Type)</span></label>
        <select id="research_type" name="research_type">
          <option value="">Select Research Type</option>
        </select>
      </div>
      
      <div class="form-group">
        <label for="abstract">Abstract <span style="color: #dc3545;">*</span> <span style="font-size: 12px; font-weight: normal; color: #666;">(Required for submission, minimum 20 characters)</span></label>
        <textarea id="abstract" name="abstract" placeholder="Provide a detailed abstract of your IP work..."><?php echo htmlspecialchars($draft_data['abstract'] ?? ''); ?></textarea>
        <div class="label-info">Minimum 20 characters describing your work</div>
      </div>
      
      <div class="form-group">
        <label for="documents">Supporting Documents <span id="docRequired" style="color: #dc3545;">*</span> <span style="font-size: 12px; font-weight: normal; color: #666;">(Required for submission, optional for draft)</span></label>
        
        <!-- IP Type Specific Instructions -->
        <div id="documentInstructions" style="background: #E3F2FD; border-left: 4px solid #2196F3; padding: 15px; margin-bottom: 15px; border-radius: 4px; font-size: 13px; display: none;">
          <strong style="color: #1976D2; display: block; margin-bottom: 8px;"><i class="fas fa-info-circle"></i> Required Supporting Documents:</strong>
          <div id="copyrightDocs" style="display: none;">
            <strong>For Copyright:</strong>
            <ul style="margin: 8px 0 0 20px; line-height: 1.8;">
              <li>Copy of the original work (manuscript, artwork, code, etc.)</li>
              <li>Proof of authorship/creation date</li>
              <li>Abstract of the work</li>
              <li><strong>Formats:</strong> PDF, JPG, PNG, TXT</li>
            </ul>
          </div>
          <div id="patentDocs" style="display: none;">
            <strong>For Patent:</strong>
            <ul style="margin: 8px 0 0 20px; line-height: 1.8;">
              <li>Detailed technical specifications and drawings</li>
              <li>Proof of novelty and inventiveness</li>
              <li>Prior art search results (if available)</li>
              <li>Prototype documentation or working model description</li>
              <li><strong>Formats:</strong> PDF, JPG, PNG, TXT</li>
            </ul>
          </div>
          <div id="trademarkDocs" style="display: none;">
            <strong>For Trademark:</strong>
            <ul style="margin: 8px 0 0 20px; line-height: 1.8;">
              <li>Logo, symbol, or brand name design files</li>
              <li>Description of goods/services the trademark will represent</li>
              <li>Proof of use or intent to use</li>
              <li>Color specifications (if applicable)</li>
              <li><strong>Formats:</strong> PDF, JPG, PNG, TXT</li>
            </ul>
          </div>
        </div>
        
        <?php if ($draft_data && !empty($draft_data['document_file'])): ?>
          <div style="margin-bottom: 15px;">
            <p style="font-size: 13px; font-weight: 600; margin-bottom: 5px;">Previously Uploaded Files:</p>
            <div id="existingFilesList">
            <?php 
              $current_files = json_decode($draft_data['document_file'], true);
              if (is_array($current_files)):
                  foreach($current_files as $index => $file): 
            ?>
                <div class="existing-file-item" data-file-index="<?php echo $index; ?>" data-file-name="<?php echo htmlspecialchars($file); ?>" style="font-size: 13px; background: #f0f4ff; padding: 8px 12px; margin-bottom: 5px; border-radius: 4px; display: inline-flex; align-items: center; gap: 8px; margin-right: 8px;">
                  <i class="fas fa-file"></i> 
                  <span><?php echo htmlspecialchars($file); ?></span>
                  <button type="button" class="remove-existing-file" data-file-name="<?php echo htmlspecialchars($file); ?>" style="background: #f44336; color: white; border: none; border-radius: 3px; padding: 3px 8px; cursor: pointer; font-size: 11px; margin-left: 8px;" title="Remove from form (file will remain in draft)">
                    <i class="fas fa-times"></i>
                  </button>
              </div>
            <?php 
                endforeach;
              endif; 
            ?>
            </div>
            <p style="font-size: 11px; color: #666; margin-top: 5px;">Upload new files to add to these. Removing files above only removes them from this form submission.</p>
          </div>
        <?php endif; ?>
        
        <div class="file-upload" id="fileUploadArea" style="position: relative;">
          <i class="fas fa-cloud-upload-alt"></i>
          <p>Click to upload or drag & drop</p>
          <small style="color: #999;">PDF, JPG, PNG, TXT  (Total max 50MB)</small>
          <!-- Changed to accept multiple files -->
          <input type="file" id="documents" name="documents[]" accept=".pdf,  .jpg,.jpeg,.png,.txt" multiple style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;">
        </div>
        <!-- Added file list display outside the upload area -->
        <div class="file-list" id="fileList" style="margin-top: 15px;"></div>
        <div class="total-size" id="totalSize" style="display: none; margin-top: 10px;"></div>
        <input type="hidden" id="removedFiles" name="removed_files" value="">
      </div>
      
      <div class="buttons">
        <button type="submit" name="action" value="draft" class="btn-draft">Save as Draft</button>
        <button type="submit" name="action" value="submit" class="btn-submit">Submit Application</button>
      </div>
    </form>
  </div>
  
  <script>
    // Proper Case / Title Case Function
    function toProperCase(str) {
      return str.replace(/\w\S*/g, function(txt) {
        return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
      });
    }
    
    // Apply Proper Case to input fields
    function applyProperCase(inputId) {
      const input = document.getElementById(inputId);
      if (input) {
        input.addEventListener('blur', function() {
          this.value = toProperCase(this.value);
        });
        // Also apply on input for real-time feedback (optional, can be removed if too aggressive)
        input.addEventListener('input', function() {
          const cursorPos = this.selectionStart;
          const oldValue = this.value;
          const newValue = toProperCase(oldValue);
          if (oldValue !== newValue) {
            this.value = newValue;
            // Restore cursor position
            this.setSelectionRange(cursorPos, cursorPos);
          }
        });
      }
    }
    
    // Apply Proper Case to input fields by name attribute
    function applyProperCaseByName(inputName) {
      const input = document.querySelector(`input[name="${inputName}"]`);
      if (input) {
        input.addEventListener('blur', function() {
          this.value = toProperCase(this.value);
        });
        input.addEventListener('input', function() {
          const cursorPos = this.selectionStart;
          const oldValue = this.value;
          const newValue = toProperCase(oldValue);
          if (oldValue !== newValue) {
            this.value = newValue;
            this.setSelectionRange(cursorPos, cursorPos);
          }
        });
      }
    }
    
    // Profile section toggle
    function toggleProfileSection() {
      const section = document.getElementById('profileFormSection');
      const icon = document.getElementById('profileToggleIcon');
      if (section.style.display === 'none') {
        section.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
      } else {
        section.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
      }
    }
    
    // Initialize Proper Case on page load
    document.addEventListener('DOMContentLoaded', function() {
      // Apply to IP Work Title
      applyProperCase('title');
      
      // Apply to Inventor Name
      applyProperCase('inventor_name');
      
      // Apply to Profile Name fields
      applyProperCaseByName('profile_first_name');
      applyProperCaseByName('profile_middle_name');
      applyProperCaseByName('profile_last_name');
      
      // Apply to Address fields
      applyProperCaseByName('profile_address_street');
      applyProperCaseByName('profile_address_barangay');
      applyProperCaseByName('profile_nationality');
    });
    
    // City options based on province
    const cityOptions = {
      'Negros Occidental': [
        'Bacolod City', 'Bago City', 'Cadiz City', 'Escalante City', 'Himamaylan City',
        'Kabankalan City', 'La Carlota City', 'Sagay City', 'San Carlos City', 'Silay City',
        'Sipalay City', 'Talisay City', 'Victorias City', 'Binalbagan', 'Calatrava',
        'Candoni', 'Cauayan', 'Enrique B. Magalona', 'Hinigaran', 'Hinoba-an', 'Ilog',
        'Isabela', 'La Castellana', 'Manapla', 'Moises Padilla', 'Murcia', 'Pontevedra',
        'Pulupandan', 'Salvador Benedicto', 'San Enrique', 'Toboso', 'Valladolid'
      ],
      'Negros Oriental': [
        'Dumaguete City', 'Bais City', 'Bayawan City', 'Canlaon City', 'Guihulngan City',
        'Tanjay City', 'Amlan', 'Ayungon', 'Bacong', 'Basay', 'Bindoy', 'Dauin',
        'Jimalalud', 'La Libertad', 'Mabinay', 'Manjuyod', 'Pamplona', 'San Jose',
        'Santa Catalina', 'Siaton', 'Sibulan', 'Tayasan', 'Valencia', 'Vallehermoso', 'Zamboanguita'
      ]
    };
    
    function updateCityOptions() {
      const province = document.getElementById('profileProvince').value;
      const citySelect = document.getElementById('profileCity');
      const currentCity = '<?php echo htmlspecialchars($user_profile['address_city'] ?? ''); ?>';
      
      citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
      
      if (cityOptions[province]) {
        cityOptions[province].forEach(city => {
          const option = document.createElement('option');
          option.value = city;
          option.textContent = city;
          if (city === currentCity) {
            option.selected = true;
          }
          citySelect.appendChild(option);
        });
      }
    }
    
    // Initialize city options on page load
    document.addEventListener('DOMContentLoaded', function() {
      updateCityOptions();
    });
    
    let selectedFiles = [];
    const maxTotalSize = 50 * 1024 * 1024; // 50MB in bytes
    
    document.getElementById('documents').addEventListener('change', function(e) {
      if (this.files && this.files.length > 0) {
        const newFiles = Array.from(this.files);
        // Add new files to selectedFiles array (avoid duplicates)
        newFiles.forEach(newFile => {
          // Check if file already exists by name and size
          const exists = selectedFiles.some(existing => 
            existing.name === newFile.name && existing.size === newFile.size
          );
          if (!exists) {
            selectedFiles.push(newFile);
          }
        });
      updateFileList();
      }
      // Reset the input so same file can be selected again if needed
      this.value = '';
    });
    
    // Update file list to display selected files
    function updateFileList() {
      const fileListDiv = document.getElementById('fileList');
      const totalSizeDiv = document.getElementById('totalSize');
      
      if (selectedFiles.length === 0) {
        fileListDiv.innerHTML = '';
        totalSizeDiv.style.display = 'none';
        checkDocumentRequirement();
        return;
      }
      
      let totalSize = 0;
      let html = '<h4 style="margin-bottom: 10px; font-size: 13px; color: #333; font-weight: 600;">New Files Selected:</h4>';
      
      selectedFiles.forEach((file, index) => {
        totalSize += file.size;
        const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
        html += `
          <div class="file-item">
            <div class="file-item-name">
              <i class="fas fa-file"></i>
              ${file.name}
            </div>
            <span class="file-item-size">${sizeInMB} MB</span>
            <button type="button" class="file-item-remove" data-file-index="${index}" style="background: #f44336; color: white; border: none; border-radius: 3px; padding: 5px 10px; cursor: pointer; font-size: 11px;">
              <i class="fas fa-times"></i> Remove
            </button>
          </div>
        `;
      });
      
      fileListDiv.innerHTML = html;
      fileListDiv.style.display = 'block';
      
      const totalSizeInMB = (totalSize / (1024 * 1024)).toFixed(2);
      const isOverLimit = totalSize > maxTotalSize;
      
      totalSizeDiv.style.display = 'block';
      totalSizeDiv.className = 'total-size' + (isOverLimit ? ' warning' : '');
      totalSizeDiv.innerHTML = `Total Size: ${totalSizeInMB} MB / 50.00 MB ${isOverLimit ? '⚠️ EXCEEDS LIMIT!' : '✓'}`;
      
      // Update the file input to include all selected files
      // This ensures files are included in form submission
      const dataTransfer = new DataTransfer();
      selectedFiles.forEach(file => {
        dataTransfer.items.add(file);
      });
      document.getElementById('documents').files = dataTransfer.files;
      
      checkDocumentRequirement();
    }
    
    // Remove file from selected files (only removes from form, not from storage)
    function removeFile(index, event) {
      if (event) {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
      }
      if (index >= 0 && index < selectedFiles.length) {
      selectedFiles.splice(index, 1);
      updateFileList();
        checkDocumentRequirement();
      }
      return false;
    }
    
    // Track removed existing files (only from form, not from storage)
    let removedExistingFiles = [];
    
    // Handle removal of new files using event delegation
    document.addEventListener('click', function(e) {
      if (e.target.closest('.file-item-remove')) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        const button = e.target.closest('.file-item-remove');
        const index = parseInt(button.getAttribute('data-file-index'));
        if (!isNaN(index) && index >= 0 && index < selectedFiles.length) {
          selectedFiles.splice(index, 1);
          updateFileList();
          checkDocumentRequirement();
        }
        return false;
      }
    });
    
    // Handle removal of existing files (only removes from form display, not from storage)
    // Use event delegation since files might be loaded dynamically
    document.addEventListener('click', function(e) {
      if (e.target.closest('.remove-existing-file')) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        const button = e.target.closest('.remove-existing-file');
        const fileName = button.getAttribute('data-file-name');
        const fileItem = button.closest('.existing-file-item');
        
        // Add to removed list (this is just for tracking, files stay in storage)
        if (!removedExistingFiles.includes(fileName)) {
          removedExistingFiles.push(fileName);
          document.getElementById('removedFiles').value = JSON.stringify(removedExistingFiles);
        }
        
        // Remove from display only (file stays in storage)
        if (fileItem) {
          fileItem.style.display = 'none';
        }
        
        // Update validation
        checkDocumentRequirement();
        return false;
      }
    });
    
    // Research type options based on IP Type
    const researchTypeOptions = {
      'Copyright': [
        'Literary Work',
        'Artistic Work',
        'Musical Composition',
        'Dramatic Work',
        'Audiovisual Work',
        'Computer Program/Software',
        'Database',
        'Photographic Work',
        'Architectural Work',
        'Applied Art',
        'Other'
      ],
      'Patent': [
        'Utility Model',
        'Invention',
        'Industrial Design',
        'Plant Variety',
        'Biotechnology',
        'Pharmaceutical',
        'Mechanical Engineering',
        'Electrical/Electronics',
        'Chemical',
        'Agricultural',
        'Information Technology',
        'Other'
      ],
      'Trademark': [
        'Product Brand',
        'Service Mark',
        'Collective Mark',
        'Certification Mark',
        'Trade Name',
        'Logo/Symbol',
        'Slogan/Tagline',
        'Other'
      ]
    };
    
    // Function to update research type options
    function updateResearchTypeOptions(ipType) {
      const researchTypeGroup = document.getElementById('researchTypeGroup');
      const researchTypeSelect = document.getElementById('research_type');
      const currentValue = '<?php echo htmlspecialchars($draft_data['research_type'] ?? ''); ?>';
      
      if (ipType && researchTypeOptions[ipType]) {
        // Show the research type group
        researchTypeGroup.style.display = 'block';
        
        // Clear existing options
        researchTypeSelect.innerHTML = '<option value="">Select Research Type</option>';
        
        // Add new options based on IP type
        researchTypeOptions[ipType].forEach(option => {
          const optionEl = document.createElement('option');
          optionEl.value = option;
          optionEl.textContent = option;
          if (option === currentValue) {
            optionEl.selected = true;
          }
          researchTypeSelect.appendChild(optionEl);
        });
      } else {
        // Hide the research type group if no IP type selected
        researchTypeGroup.style.display = 'none';
        researchTypeSelect.innerHTML = '<option value="">Select Research Type</option>';
      }
    }
    
    // IP Type info and document instructions
    document.getElementById('ip_type').addEventListener('change', function() {
      const info = {
        'Copyright': 'Protects original literary, artistic, musical, or dramatic works. Provides automatic protection upon creation.',
        'Patent': 'Protects technical innovations and inventions. Requires detailed technical specifications and novelty evidence.',
        'Trademark': 'Protects brand names, logos, and distinctive symbols. Used for brand identification and market distinction.'
      };
      document.getElementById('typeInfo').textContent = info[this.value] || '';
      
      // Update research type options based on selected IP type
      updateResearchTypeOptions(this.value);
      
      // Show/hide document instructions based on IP type
      const instructionsDiv = document.getElementById('documentInstructions');
      const copyrightDiv = document.getElementById('copyrightDocs');
      const patentDiv = document.getElementById('patentDocs');
      const trademarkDiv = document.getElementById('trademarkDocs');
      
      if (this.value === 'Copyright') {
        instructionsDiv.style.display = 'block';
        copyrightDiv.style.display = 'block';
        patentDiv.style.display = 'none';
        trademarkDiv.style.display = 'none';
      } else if (this.value === 'Patent') {
        instructionsDiv.style.display = 'block';
        copyrightDiv.style.display = 'none';
        patentDiv.style.display = 'block';
        trademarkDiv.style.display = 'none';
      } else if (this.value === 'Trademark') {
        instructionsDiv.style.display = 'block';
        copyrightDiv.style.display = 'none';
        patentDiv.style.display = 'none';
        trademarkDiv.style.display = 'block';
      } else {
        instructionsDiv.style.display = 'none';
      }
      
      checkDocumentRequirement();
    });
    
    // Check if documents are required based on action
    function checkDocumentRequirement() {
      const submitButton = document.querySelector('button[name="action"][value="submit"]');
      const docRequired = document.getElementById('docRequired');
      const ipType = document.getElementById('ip_type').value;
      
      // Documents are always required for submit, but we'll validate on submit
      if (submitButton) {
        docRequired.style.display = 'inline';
      }
    }
    
    // Initialize instructions if IP type is already selected
    const currentIpType = document.getElementById('ip_type').value;
    if (currentIpType) {
      document.getElementById('ip_type').dispatchEvent(new Event('change'));
    }
    
    // Click to upload - prevent event bubbling from remove buttons
    const fileUploadArea = document.getElementById('fileUploadArea');
    fileUploadArea.addEventListener('click', function(e) {
      // Only trigger file input if clicking on the upload area itself, not on buttons
      if (e.target === fileUploadArea || e.target.closest('i') || e.target.closest('p') || e.target.closest('small')) {
        document.getElementById('documents').click();
      }
    });
    
    // Drag and drop
    fileUploadArea.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.stopPropagation();
      fileUploadArea.style.borderColor = '#667eea';
      fileUploadArea.style.backgroundColor = '#f0f4ff';
    });
    
    fileUploadArea.addEventListener('dragleave', (e) => {
      e.preventDefault();
      e.stopPropagation();
      fileUploadArea.style.borderColor = '#ddd';
      fileUploadArea.style.backgroundColor = 'white';
    });
    
    fileUploadArea.addEventListener('drop', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const newFiles = Array.from(e.dataTransfer.files);
      selectedFiles = selectedFiles.concat(newFiles);
      updateFileList();
      fileUploadArea.style.borderColor = '#ddd';
      fileUploadArea.style.backgroundColor = 'white';
    });
    
    // Form submission handler - validate based on action
    document.getElementById('appForm').addEventListener('submit', function(e) {
      const clickedButton = document.querySelector('button[type="submit"].clicked');
      const action = clickedButton ? clickedButton.value : 'draft';
      
      // Ensure selected files are in the file input before submission
      const dataTransfer = new DataTransfer();
      selectedFiles.forEach(file => {
        dataTransfer.items.add(file);
      });
      document.getElementById('documents').files = dataTransfer.files;
      
      // For submit action, validate required fields
      if (action === 'submit') {
        const title = document.getElementById('title').value.trim();
        const ipType = document.getElementById('ip_type').value;
        const abstract = document.getElementById('abstract').value.trim();
        
        // Check basic fields
        if (!title || !ipType || !abstract) {
          e.preventDefault();
          alert('Please fill in all required fields: Title, IP Type, and Abstract');
          return false;
        }
        
        if (abstract.length < 20) {
          e.preventDefault();
          alert('Abstract must be at least 20 characters long');
          return false;
        }
        
        // Check documents - must have either new files or existing files
        const hasNewFiles = selectedFiles.length > 0;
        const totalExistingFiles = document.querySelectorAll('.existing-file-item').length;
        const hiddenExistingFiles = document.querySelectorAll('.existing-file-item[style*="display: none"]').length;
        const visibleExistingFiles = totalExistingFiles - hiddenExistingFiles;
        const hasExistingFiles = visibleExistingFiles > 0;
        
        if (!hasNewFiles && !hasExistingFiles) {
          e.preventDefault();
          alert('Supporting documents are required when submitting an application. Please upload at least one document.');
          return false;
        }
      }
      // For draft, allow submission even without all fields
    });
    
    // Track which button was clicked
    document.querySelectorAll('button[type="submit"]').forEach(button => {
      button.addEventListener('click', function() {
        document.querySelectorAll('button[type="submit"]').forEach(btn => btn.classList.remove('clicked'));
        this.classList.add('clicked');
      });
    });
  </script>
</body>
</html>
