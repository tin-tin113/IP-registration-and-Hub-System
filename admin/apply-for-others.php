<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireRole(['clerk', 'director']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $applicant_name = trim($_POST['applicant_name'] ?? '');
  $applicant_email = trim($_POST['applicant_email'] ?? '');
  $applicant_department = trim($_POST['applicant_department'] ?? '');
  $applicant_contact = trim($_POST['applicant_contact'] ?? '');
  
  $title = trim($_POST['title'] ?? '');
  $ip_type = trim($_POST['ip_type'] ?? '');
  $description = trim($_POST['description'] ?? '');
  
  if (empty($applicant_name) || empty($applicant_email) || empty($title) || empty($ip_type) || empty($description)) {
    $error = 'All required fields must be filled';
  } elseif (!filter_var($applicant_email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Invalid email address';
  } else {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $applicant_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
      $user = $result->fetch_assoc();
      $user_id = $user['id'];
    } else {
      // Create new user account
      $default_password = password_hash('changeMe123', PASSWORD_DEFAULT);
      $insert_user = $conn->prepare("INSERT INTO users (email, password, full_name, role, department, contact_number, is_active) VALUES (?, ?, ?, 'user', ?, ?, TRUE)");
      $insert_user->bind_param("sssss", $applicant_email, $default_password, $applicant_name, $applicant_department, $applicant_contact);
      
      if ($insert_user->execute()) {
        $user_id = $insert_user->insert_id;
      } else {
        $error = 'Failed to create user account';
      }
      $insert_user->close();
    }
    $stmt->close();
    
    if (!isset($error) || empty($error)) {
      $document_files = [];
      if (isset($_FILES['documents']) && !empty($_FILES['documents']['name'][0])) {
        $file_count = count($_FILES['documents']['name']);
        $total_size = 0;
        
        for ($i = 0; $i < $file_count; $i++) {
          if ($_FILES['documents']['error'][$i] === 0) {
            $total_size += $_FILES['documents']['size'][$i];
          }
        }
        
        if ($total_size > MAX_FILE_SIZE) {
          $error = 'Total file size exceeds 50MB limit. Current total: ' . round($total_size / (1024 * 1024), 2) . 'MB';
        } else {
          for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['documents']['error'][$i] === 0) {
              $file_name = $_FILES['documents']['name'][$i];
              $file_tmp = $_FILES['documents']['tmp_name'][$i];
              $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
              
              if (in_array($ext, ALLOWED_EXTENSIONS)) {
                if (!is_dir(UPLOAD_DIR)) {
                  mkdir(UPLOAD_DIR, 0755, true);
                }
                
                $filename = 'doc_' . time() . '_' . uniqid() . '.' . $ext;
                $filepath = UPLOAD_DIR . $filename;
                
                if (move_uploaded_file($file_tmp, $filepath)) {
                  $document_files[] = $filename;
                }
              }
            }
          }
        }
      }
      
      if (empty($error)) {
        $document_file_json = !empty($document_files) ? json_encode($document_files) : '';
        $status = 'submitted';
        
        $stmt = $conn->prepare("INSERT INTO ip_applications (user_id, title, ip_type, description, document_file, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $user_id, $title, $ip_type, $description, $document_file_json, $status);
        
        if ($stmt->execute()) {
          $app_id = $stmt->insert_id;
          auditLog('Admin Apply for Others', 'Application', $app_id, null, json_encode(['applicant' => $applicant_name, 'title' => $title]));
          $stmt->close();
          header("Location: apply-for-others.php?success=Application submitted successfully for " . urlencode($applicant_name) . "!");
          exit;
        } else {
          $error = 'Failed to submit application';
        }
        
        $stmt->close();
      }
    }
  }
}

$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Apply for Others - CHMSU IP System</title>
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
      max-width: 800px;
      margin: 0 auto;
    }
    
    .back-btn {
      background: #f0f0f0;
      color: #333;
      padding: 10px 15px;
      border: 1px solid #ddd;
      border-radius: 5px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      margin-bottom: 20px;
      font-size: 13px;
    }
    
    .card {
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      padding: 40px;
    }
    
    .header {
      margin-bottom: 30px;
      padding-bottom: 20px;
      border-bottom: 3px solid #1B5C3B;
    }
    
    .header h1 {
      color: #1B5C3B;
      font-size: 24px;
      margin-bottom: 10px;
    }
    
    .header p {
      color: #666;
      font-size: 14px;
    }
    
    .alert {
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
    }
    
    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    .alert-danger {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .section {
      margin-bottom: 30px;
      padding: 20px;
      background: #f9f9f9;
      border-radius: 8px;
      border-left: 4px solid #E07D32;
    }
    
    .section h3 {
      color: #1B5C3B;
      font-size: 16px;
      margin-bottom: 15px;
    }
    
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
      margin-bottom: 15px;
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
    }
    
    input:focus,
    select:focus,
    textarea:focus {
      outline: none;
      border-color: #1B5C3B;
      box-shadow: 0 0 5px rgba(27,92,59,0.3);
    }
    
    textarea {
      resize: vertical;
      min-height: 120px;
    }
    
    .file-upload {
      border: 2px dashed #ddd;
      border-radius: 5px;
      padding: 30px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .file-upload:hover {
      border-color: #1B5C3B;
      background-color: #f0f8ff;
    }
    
    .file-upload input[type="file"] {
      display: none;
    }
    
    .file-upload i {
      font-size: 32px;
      color: #1B5C3B;
      margin-bottom: 10px;
      display: block;
    }
    
    button {
      background: linear-gradient(135deg, #1B5C3B 0%, #0F3D2E 100%);
      color: white;
      padding: 15px 40px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 15px;
      font-weight: 600;
      width: 100%;
      transition: transform 0.2s;
    }
    
    button:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(27,92,59,0.3);
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    
    <div class="card">
      <div class="header">
        <h1><i class="fas fa-user-plus"></i> Submit Application for Others</h1>
        <p>Submit IP applications on behalf of faculty, students, or external collaborators</p>
      </div>
      
      <?php if (!empty($success)): ?>
        <div class="alert alert-success">
          <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
      <?php endif; ?>
      
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>
      
      <form method="POST" enctype="multipart/form-data">
        <div class="section">
          <h3><i class="fas fa-user"></i> Applicant Personal Information</h3>
          
          <div class="form-row">
            <div class="form-group">
              <label for="applicant_name">Full Name *</label>
              <input type="text" id="applicant_name" name="applicant_name" placeholder="Juan Dela Cruz" required>
            </div>
            
            <div class="form-group">
              <label for="applicant_email">Email Address *</label>
              <input type="email" id="applicant_email" name="applicant_email" placeholder="juan@chmsu.edu.ph" required>
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label for="applicant_department">Department/College</label>
              <input type="text" id="applicant_department" name="applicant_department" placeholder="College of Engineering">
            </div>
            
            <div class="form-group">
              <label for="applicant_contact">Contact Number</label>
              <input type="text" id="applicant_contact" name="applicant_contact" placeholder="09XX XXX XXXX">
            </div>
          </div>
        </div>
        
        <div class="section">
          <h3><i class="fas fa-file-alt"></i> IP Work Details</h3>
          
          <div class="form-group">
            <label for="title">IP Work Title *</label>
            <input type="text" id="title" name="title" placeholder="e.g., Innovative Learning Management System" required>
          </div>
          
          <div class="form-group">
            <label for="ip_type">IP Type *</label>
            <select id="ip_type" name="ip_type" required>
              <option value="">Select IP Type</option>
              <option value="Copyright">Copyright (literary, artistic, musical works)</option>
              <option value="Patent">Patent (invention, technical innovation)</option>
              <option value="Trademark">Trademark (name, symbol, brand)</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="description">Description *</label>
            <textarea id="description" name="description" placeholder="Provide detailed description of the IP work..." required></textarea>
          </div>
          
          <div class="form-group">
            <label for="documents">Supporting Documents</label>
            <div class="file-upload" onclick="document.getElementById('documents').click()">
              <i class="fas fa-cloud-upload-alt"></i>
              <p>Click to upload or drag & drop</p>
              <small style="color: #999;">Multiple files allowed (Total max 50MB)</small>
              <input type="file" id="documents" name="documents[]" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt" multiple>
              <div class="file-list" id="fileList" style="margin-top: 15px; text-align: left; display: none;"></div>
              <div class="total-size" id="totalSize" style="display: none; margin-top: 10px; padding: 10px; background: #fff3cd; border-radius: 5px; font-size: 13px; text-align: center; font-weight: 600;"></div>
            </div>
          </div>
        </div>
        
        <button type="submit">
          <i class="fas fa-paper-plane"></i> Submit Application
        </button>
      </form>
    </div>
  </div>
  
  <script>
    let selectedFiles = [];
    const maxTotalSize = 50 * 1024 * 1024; // 50MB in bytes
    
    document.getElementById('documents').addEventListener('change', function(e) {
      const newFiles = Array.from(e.target.files);
      selectedFiles = selectedFiles.concat(newFiles);
      updateFileList();
    });
    
    function updateFileList() {
      const fileListDiv = document.getElementById('fileList');
      const totalSizeDiv = document.getElementById('totalSize');
      
      if (selectedFiles.length === 0) {
        fileListDiv.style.display = 'none';
        totalSizeDiv.style.display = 'none';
        return;
      }
      
      fileListDiv.style.display = 'block';
      let totalSize = 0;
      let html = '<h4 style="margin-bottom: 10px; font-size: 13px; color: #333;">Selected Files:</h4>';
      
      selectedFiles.forEach((file, index) => {
        totalSize += file.size;
        const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
        html += `
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f0f4ff; border-radius: 5px; margin-bottom: 8px; font-size: 13px;">
            <div style="flex: 1; color: #333; display: flex; align-items: center; gap: 8px;">
              <i class="fas fa-file"></i>
              ${file.name}
            </div>
            <span style="color: #666; font-size: 12px; margin: 0 10px;">${sizeInMB} MB</span>
            <button type="button" onclick="removeFile(${index})" style="background: #f44336; color: white; border: none; border-radius: 3px; padding: 5px 10px; cursor: pointer; font-size: 11px;">
              <i class="fas fa-times"></i>
            </button>
          </div>
        `;
      });
      
      fileListDiv.innerHTML = html;
      
      const totalSizeInMB = (totalSize / (1024 * 1024)).toFixed(2);
      const isOverLimit = totalSize > maxTotalSize;
      
      totalSizeDiv.style.display = 'block';
      totalSizeDiv.style.background = isOverLimit ? '#f8d7da' : '#fff3cd';
      totalSizeDiv.style.color = isOverLimit ? '#721c24' : '#856404';
      totalSizeDiv.innerHTML = `Total Size: ${totalSizeInMB} MB / 50.00 MB ${isOverLimit ? '⚠️ EXCEEDS LIMIT!' : '✓'}`;
      
      // Update the file input
      const dataTransfer = new DataTransfer();
      selectedFiles.forEach(file => dataTransfer.items.add(file));
      document.getElementById('documents').files = dataTransfer.files;
    }
    
    function removeFile(index) {
      selectedFiles.splice(index, 1);
      updateFileList();
    }
    
    // Drag and drop
    const fileUpload = document.querySelector('.file-upload');
    fileUpload.addEventListener('dragover', (e) => {
      e.preventDefault();
      fileUpload.style.borderColor = '#1B5C3B';
      fileUpload.style.backgroundColor = '#f0f8ff';
    });
    
    fileUpload.addEventListener('dragleave', () => {
      fileUpload.style.borderColor = '#ddd';
      fileUpload.style.backgroundColor = 'white';
    });
    
    fileUpload.addEventListener('drop', (e) => {
      e.preventDefault();
      const newFiles = Array.from(e.dataTransfer.files);
      selectedFiles = selectedFiles.concat(newFiles);
      updateFileList();
      fileUpload.style.borderColor = '#ddd';
      fileUpload.style.backgroundColor = 'white';
    });
  </script>
</body>
</html>
