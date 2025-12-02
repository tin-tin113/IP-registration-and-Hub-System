<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireLogin();

$error = '';
$success = '';
$app_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['title'] ?? '');
  $ip_type = trim($_POST['ip_type'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $action = trim($_POST['action'] ?? 'draft');
  
  $user_id = getCurrentUserId();
  
  if (empty($title) || empty($ip_type) || empty($description)) {
    $error = 'Title, type, and description are required';
  } elseif (!in_array($ip_type, ['Copyright', 'Patent', 'Trademark'])) {
    $error = 'Invalid IP type';
  } else {
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
        $three_days_ago = date('Y-m-d H:i:s', strtotime('-3 days'));
        $recent_app_stmt = $conn->prepare("SELECT created_at FROM ip_applications WHERE user_id = ? AND status != 'draft' AND created_at > ? ORDER BY created_at DESC LIMIT 1");
        $recent_app_stmt->bind_param("is", $user_id, $three_days_ago);
        $recent_app_stmt->execute();
        $recent_app_result = $recent_app_stmt->get_result();
        
        if ($recent_app_result->num_rows > 0) {
          $recent_app = $recent_app_result->fetch_assoc();
          $last_app_date = strtotime($recent_app['created_at']);
          $days_since = floor((time() - $last_app_date) / (60 * 60 * 24));
          
          if ($days_since < 3) {
            $days_remaining = 3 - $days_since;
            $error = "You must wait {$days_remaining} more day(s) before submitting another application. Minimum 3 days gap required between applications.";
          }
        }
        $recent_app_stmt->close();
      }
    }
    
    if (empty($error)) {
    $document_files = [];
    $total_size = 0;
    
    if (isset($_FILES['documents']) && !empty($_FILES['documents']['name'][0])) {
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
          if ($_FILES['documents']['error'][$i] === 0) {
            $file_name = $_FILES['documents']['name'][$i];
            $file_tmp = $_FILES['documents']['tmp_name'][$i];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            if (!in_array($ext, ALLOWED_EXTENSIONS)) {
              $error = 'Invalid file type for "' . $file_name . '". Allowed: ' . implode(', ', ALLOWED_EXTENSIONS);
              break;
            } else {
              if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0755, true);
              }
              
              $filename = 'doc_' . time() . '_' . uniqid() . '.' . $ext;
              $filepath = UPLOAD_DIR . $filename;
              
              if (move_uploaded_file($file_tmp, $filepath)) {
                $document_files[] = $filename;
              } else {
                $error = 'Failed to upload file: ' . $file_name;
                break;
              }
            }
          }
        }
      }
    }
    
    if (empty($error)) {
      if ($action === 'draft') {
        $status = 'draft';
      } else {
        $status = 'submitted';
      }
      
      // Store multiple files as JSON array
      $document_file_json = !empty($document_files) ? json_encode($document_files) : '';
      
      $stmt = $conn->prepare("INSERT INTO ip_applications (user_id, title, ip_type, description, document_file, status) VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("isssss", $user_id, $title, $ip_type, $description, $document_file_json, $status);
      
      if ($stmt->execute()) {
        $app_id = $stmt->insert_id;
        auditLog('Submit Application', 'Application', $app_id, null, json_encode(['title' => $title, 'type' => $ip_type]));
        
        if ($status === 'submitted') {
          $stmt->close();
          header("Location: my-applications.php?success=Application submitted successfully! Clerk will review it soon.");
          exit;
        } else {
          $success = 'Application saved as draft.';
        }
      } else {
        $error = 'Failed to save application';
      }
      
      $stmt->close();
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
  <title>Submit IP Application - CHMSU</title>
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
  </style>
</head>
<body>
  <div class="container">
    <a href="../dashboard.php" class="btn-back" style="display: inline-flex; align-items: center; gap: 8px; color: #667eea; text-decoration: none; margin-bottom: 20px; font-size: 14px; font-weight: 600;"><i class="fas fa-arrow-left"></i> Back</a>
    
    <div class="header">
      <i class="fas fa-lightbulb"></i>
      <div>
        <h1>Submit IP Application</h1>
        <div class="breadcrumb">Dashboard / Submit Application</div>
      </div>
    </div>
    
    <!-- Requirements & Instructions -->
    <div style="background: linear-gradient(135deg, #1B5C3B 0%, #0F3D2E 100%); color: white; padding: 25px; border-radius: 8px; margin-bottom: 30px;">
      <h2 style="margin-bottom: 15px; font-size: 16px;"><i class="fas fa-clipboard-list"></i> Requirements & Instructions</h2>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div>
          <h4 style="margin-bottom: 8px; font-size: 13px;">What to Include:</h4>
          <ul style="margin-left: 20px; font-size: 13px; line-height: 1.8;">
            <li>Clear, descriptive title of your IP work</li>
            <li>Detailed description (minimum 50 characters)</li>
            <li>Select appropriate IP type (Copyright, Patent, or Trademark)</li>
            <li>Supporting documentation (PDF, DOC, or image)</li>
          </ul>
        </div>
        <div>
          <h4 style="margin-bottom: 8px; font-size: 13px;">Document Requirements:</h4>
          <ul style="margin-left: 20px; font-size: 13px; line-height: 1.8;">
            <li><strong>Accepted formats:</strong> PDF, DOC, DOCX, JPG, PNG, TXT</li>
            <li><strong>Maximum total size:</strong> 50MB for all files</li>
            <li><strong>Multiple files:</strong> You can upload multiple documents</li>
            <li><strong>Language:</strong> English or Filipino</li>
          </ul>
        </div>
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
      <div class="form-group">
        <label for="title">IP Work Title *</label>
        <input type="text" id="title" name="title" placeholder="e.g., Advanced Machine Learning Framework" required>
      </div>
      
      <div class="form-group">
        <label for="ip_type">IP Type *</label>
        <select id="ip_type" name="ip_type" required>
          <option value="">Select IP Type</option>
          <option value="Copyright">Copyright (literary, artistic, musical works)</option>
          <option value="Patent">Patent (invention, technical innovation)</option>
          <option value="Trademark">Trademark (name, symbol, brand)</option>
        </select>
        <div class="ip-type-info" id="typeInfo"></div>
      </div>
      
      <div class="form-group">
        <label for="description">Description *</label>
        <textarea id="description" name="description" placeholder="Provide detailed description of your IP work..." required></textarea>
        <div class="label-info">Minimum 50 characters describing your work</div>
      </div>
      
      <div class="form-group">
        <label for="documents">Supporting Documents (Multiple files allowed)</label>
        <div class="file-upload" onclick="document.getElementById('documents').click()">
          <i class="fas fa-cloud-upload-alt"></i>
          <p>Click to upload or drag & drop</p>
          <small style="color: #999;">PDF, DOC, DOCX, JPG, PNG (Total max 50MB)</small>
          <!-- Changed to accept multiple files -->
          <input type="file" id="documents" name="documents[]" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt" multiple>
          <!-- Added file list display -->
          <div class="file-list" id="fileList"></div>
          <div class="total-size" id="totalSize" style="display: none;"></div>
        </div>
      </div>
      
      <div class="buttons">
        <button type="submit" name="action" value="draft" class="btn-draft">Save as Draft</button>
        <button type="submit" name="action" value="submit" class="btn-submit">Submit Application</button>
      </div>
    </form>
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
        fileListDiv.innerHTML = '';
        totalSizeDiv.style.display = 'none';
        return;
      }
      
      let totalSize = 0;
      let html = '<h4 style="margin-bottom: 10px; font-size: 13px; color: #333;">Selected Files:</h4>';
      
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
            <button type="button" class="file-item-remove" onclick="removeFile(${index})">
              <i class="fas fa-times"></i>
            </button>
          </div>
        `;
      });
      
      fileListDiv.innerHTML = html;
      
      const totalSizeInMB = (totalSize / (1024 * 1024)).toFixed(2);
      const isOverLimit = totalSize > maxTotalSize;
      
      totalSizeDiv.style.display = 'block';
      totalSizeDiv.className = 'total-size' + (isOverLimit ? ' warning' : '');
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
    
    // IP Type info
    document.getElementById('ip_type').addEventListener('change', function() {
      const info = {
        'Copyright': 'Protects original literary, artistic, musical, or dramatic works. Provides automatic protection upon creation.',
        'Patent': 'Protects technical innovations and inventions. Requires detailed technical specifications and novelty evidence.',
        'Trademark': 'Protects brand names, logos, and distinctive symbols. Used for brand identification and market distinction.'
      };
      document.getElementById('typeInfo').textContent = info[this.value] || '';
    });
    
    // Drag and drop
    const fileUpload = document.querySelector('.file-upload');
    fileUpload.addEventListener('dragover', (e) => {
      e.preventDefault();
      fileUpload.style.borderColor = '#667eea';
      fileUpload.style.backgroundColor = '#f0f4ff';
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
