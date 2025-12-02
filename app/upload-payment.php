<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireLogin();

$user_id = getCurrentUserId();
$app_id = $_GET['id'] ?? null;
$error = '';
$success = '';

if (!$app_id) {
  header("Location: my-applications.php");
  exit;
}

// Verify application belongs to user and is in office_visit status
$stmt = $conn->prepare("SELECT * FROM ip_applications WHERE id = ? AND user_id = ? AND status = 'office_visit'");
$stmt->bind_param("ii", $app_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  header("Location: my-applications.php?error=Application not found or not ready for payment");
  exit;
}

$app = $result->fetch_assoc();
$stmt->close();

// Handle payment upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] === 0) {
    $file = $_FILES['payment_receipt'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
      $error = 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS);
    } elseif ($file['size'] > MAX_FILE_SIZE) {
      $error = 'File too large. Max: 20MB';
    } else {
      if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
      }
      
      $filename = 'payment_' . time() . '_' . uniqid() . '.' . $ext;
      $filepath = UPLOAD_DIR . $filename;
      
      if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Update application with payment receipt
        $stmt = $conn->prepare("UPDATE ip_applications SET payment_receipt=?, status='payment_pending' WHERE id=?");
        $stmt->bind_param("si", $filename, $app_id);
        
        if ($stmt->execute()) {
          auditLog('Upload Payment Receipt', 'Application', $app_id);
          $success = 'Payment receipt uploaded successfully! Awaiting clerk verification.';
          header("Location: my-applications.php?success=payment_uploaded");
          exit;
        } else {
          $error = 'Failed to update application';
        }
        
        $stmt->close();
      } else {
        $error = 'Failed to upload file';
      }
    }
  } else {
    $error = 'Please select a file to upload';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Upload Payment Receipt - CHMSU IP System</title>
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
      padding: 30px;
      margin-bottom: 20px;
    }
    
    .card-header {
      text-align: center;
      padding-bottom: 20px;
      border-bottom: 2px solid #1B5C3B;
      margin-bottom: 25px;
    }
    
    .card-header h1 {
      color: #1B5C3B;
      font-size: 24px;
      margin-bottom: 10px;
    }
    
    .instructions {
      background: linear-gradient(135deg, #1B5C3B 0%, #0F3D2E 100%);
      color: white;
      padding: 25px;
      border-radius: 8px;
      margin-bottom: 25px;
      line-height: 1.6;
    }
    
    .instructions h3 {
      margin-bottom: 15px;
      font-size: 16px;
    }
    
    .instructions ol {
      margin-left: 20px;
    }
    
    .instructions li {
      margin-bottom: 10px;
      font-size: 14px;
    }
    
    .instructions strong {
      color: #E07D32;
    }
    
    .alert {
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
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
    
    .file-upload {
      border: 2px dashed #ddd;
      border-radius: 5px;
      padding: 40px 20px;
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
      font-size: 48px;
      color: #1B5C3B;
      margin-bottom: 15px;
      display: block;
    }
    
    .file-name {
      font-size: 13px;
      color: #666;
      margin-top: 15px;
      font-weight: 600;
    }
    
    button {
      width: 100%;
      background: linear-gradient(135deg, #1B5C3B 0%, #0F3D2E 100%);
      color: white;
      padding: 15px;
      border: none;
      border-radius: 5px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    button:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(27, 92, 59, 0.3);
    }
    
    button:disabled {
      background: #ccc;
      cursor: not-allowed;
      transform: none;
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="my-applications.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Applications</a>
    
    <div class="card">
      <div class="card-header">
        <i class="fas fa-receipt" style="font-size: 48px; color: #1B5C3B; margin-bottom: 10px;"></i>
        <h1>Upload Payment Receipt</h1>
        <p style="color: #666; font-size: 14px;">Application: <?php echo htmlspecialchars($app['title']); ?></p>
      </div>
      
      <div class="instructions">
        <h3><i class="fas fa-info-circle"></i> Payment Instructions</h3>
        <ol>
          <li>Visit the <strong><?php echo CASHIER_LOCATION; ?></strong> during office hours (<?php echo IP_OFFICE_HOURS; ?>)</li>
          <li>Pay the required registration fee (amount will be communicated by the clerk)</li>
          <li>Request an <strong>official receipt</strong> from the cashier</li>
          <li>Take a clear photo or scan of your receipt</li>
          <li>Upload the receipt below and wait for clerk verification</li>
          <li>Once verified by clerk, your documents will be forwarded to the director for final evaluation</li>
        </ol>
        <div style="margin-top: 15px; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 5px;">
          <i class="fas fa-calendar-check"></i> <strong>Office Visit Date:</strong> <?php echo $app['office_visit_date'] ? date('F d, Y', strtotime($app['office_visit_date'])) : 'Not scheduled'; ?>
        </div>
      </div>
      
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      
      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>
      
      <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
          <label for="payment_receipt">Payment Receipt *</label>
          <div class="file-upload" onclick="document.getElementById('payment_receipt').click()">
            <i class="fas fa-cloud-upload-alt"></i>
            <p style="font-size: 16px; color: #333; margin-bottom: 5px;">Click to upload receipt</p>
            <small style="color: #999;">PDF, JPG, PNG (Max 20MB)</small>
            <input type="file" id="payment_receipt" name="payment_receipt" accept=".pdf,.jpg,.jpeg,.png" required>
            <div class="file-name" id="fileName"></div>
          </div>
        </div>
        
        <button type="submit" id="submitBtn" disabled>
          <i class="fas fa-upload"></i> Upload Payment Receipt
        </button>
      </form>
    </div>
  </div>
  
  <script>
    document.getElementById('payment_receipt').addEventListener('change', function(e) {
      const fileName = e.target.files[0]?.name || '';
      const fileNameDiv = document.getElementById('fileName');
      const submitBtn = document.getElementById('submitBtn');
      
      if (fileName) {
        fileNameDiv.textContent = '✓ Selected: ' + fileName;
        fileNameDiv.style.color = '#1B5C3B';
        submitBtn.disabled = false;
      } else {
        fileNameDiv.textContent = '';
        submitBtn.disabled = true;
      }
    });
  </script>
</body>
</html>
