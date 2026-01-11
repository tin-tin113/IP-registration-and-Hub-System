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

// First, verify application belongs to user (without status restriction for POST)
$stmt = $conn->prepare("SELECT * FROM ip_applications WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $app_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  header("Location: my-applications.php?error=Application not found");
  exit;
}

$app = $result->fetch_assoc();
$stmt->close();

// Check if application is in a valid status for payment upload
$valid_statuses = ['office_visit', 'payment_pending'];
if (!in_array($app['status'], $valid_statuses)) {
  // Allow resubmission if payment was rejected (has rejection reason)
  if ($app['status'] !== 'payment_pending' || empty($app['payment_rejection_reason'])) {
    header("Location: my-applications.php?error=Application not ready for payment upload");
    exit;
  }
}

// Handle payment upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] === 0) {
    $file = $_FILES['payment_receipt'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
      $error = 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS);
    } elseif ($file['size'] > MAX_FILE_SIZE) {
      $error = 'File too large. Max: 50MB';
    } else {
      $target_dir = UPLOAD_DIR . 'receipts/';
      if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
      }
      
      $filename = 'payment_' . $app_id . '_' . time() . '_' . uniqid() . '.' . $ext;
      $filepath = $target_dir . $filename;
      
      if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $receipt_db_path = 'receipts/' . $filename;
        
        // Use a transaction to ensure data consistency
        $conn->begin_transaction();
        
        try {
          $stmt = $conn->prepare("UPDATE ip_applications SET payment_receipt=?, status='payment_pending', payment_rejection_reason=NULL, updated_at=NOW() WHERE id=? AND user_id=?");
          $stmt->bind_param("sii", $receipt_db_path, $app_id, $user_id);
          $stmt->execute();
          
          if ($stmt->affected_rows >= 0) { // >= 0 because 0 means no change but still valid
            $conn->commit();
            auditLog('Upload Payment Receipt', 'Application', $app_id);
            header("Location: my-applications.php?success=Payment receipt uploaded successfully. Waiting for clerk verification.", true, 303);
            exit;
          } else {
            throw new Exception('Database update failed');
          }
        } catch (Exception $e) {
          $conn->rollback();
          error_log("Payment upload error: " . $e->getMessage());
          $error = 'Failed to save payment receipt. Please try again.';
          if (file_exists($filepath)) {
            unlink($filepath);
          }
        }
        
        $stmt->close();
      } else {
        $error = 'Failed to upload file. Please try again.';
      }
    }
  } else {
    $error = 'Please select a file to upload';
  }
  
  // Refresh application data after POST
  $stmt = $conn->prepare("SELECT * FROM ip_applications WHERE id = ? AND user_id = ?");
  $stmt->bind_param("ii", $app_id, $user_id);
  $stmt->execute();
  $app = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

// Get payment amount
$payment_amount = !empty($app['payment_amount']) ? $app['payment_amount'] : IP_REGISTRATION_FEE;

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
    
    .file-upload i {
      font-size: 48px;
      color: #1B5C3B;
      margin-bottom: 15px;
      display: block;
    }
    
    .file-list {
      margin-top: 15px;
      text-align: left;
    }
    
    .file-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #f0f4ff;
      padding: 10px 15px;
      border-radius: 5px;
      margin-bottom: 8px;
      border: 1px solid #ddd;
    }
    
    .file-item-name {
      display: flex;
      align-items: center;
      gap: 10px;
      flex: 1;
      font-size: 14px;
      color: #333;
    }
    
    .file-item-size {
      font-size: 12px;
      color: #666;
      margin-right: 10px;
    }
    
    .file-item-remove {
      background: #f44336;
      color: white;
      border: none;
      border-radius: 3px;
      padding: 5px 10px;
      cursor: pointer;
      font-size: 11px;
      transition: background 0.2s;
    }
    
    .file-item-remove:hover {
      background: #d32f2f;
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
          <li>Pay the required registration fee: <strong style="color: #E07D32; font-size: 16px;">₱<?php echo number_format($payment_amount, 2); ?></strong></li>
          <li>Request an <strong>official receipt</strong> from the cashier</li>
          <li>Take a clear photo or scan of your receipt</li>
          <li>Upload the receipt below and wait for clerk verification</li>
          <li>Once verified by clerk, your documents will be forwarded to the director for final evaluation</li>
        </ol>+
      </div>
      
      <?php if (!empty($app['payment_rejection_reason'])): ?>
        <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
          <strong><i class="fas fa-exclamation-triangle"></i> Payment Receipt Rejected</strong>
          <p style="margin: 10px 0 0 0; font-size: 14px;">
            <strong>Reason:</strong><br>
            <?php echo nl2br(htmlspecialchars($app['payment_rejection_reason'])); ?>
          </p>
          <p style="margin: 10px 0 0 0; font-size: 13px; color: #856404;">
            <i class="fas fa-info-circle"></i> Please review the reason above, then upload a new payment receipt.
          </p>
        </div>
      <?php endif; ?>
      
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      
      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>
      
      <form method="POST" enctype="multipart/form-data" id="paymentForm">
        <div class="form-group">
          <label for="payment_receipt">Payment Receipt *</label>
          <div class="file-upload" id="fileUploadArea" style="position: relative;">
            <i class="fas fa-cloud-upload-alt"></i>
            <p style="font-size: 16px; color: #333; margin-bottom: 5px;">Click to upload or drag & drop</p>
            <small style="color: #999;">PDF, JPG, PNG (Max 20MB)</small>
            <input type="file" id="payment_receipt" name="payment_receipt" accept=".pdf,.jpg,.jpeg,.png" required style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;">
          </div>
          <div class="file-list" id="fileList" style="margin-top: 15px;"></div>
        </div>
        
        <button type="submit" id="submitBtn" disabled>
          <i class="fas fa-upload"></i> Upload Payment Receipt
        </button>
      </form>
    </div>
  </div>
  
  <script>
    const maxFileSize = 20 * 1024 * 1024; // 20MB
    
    document.getElementById('payment_receipt').addEventListener('change', function(e) {
      const fileListDiv = document.getElementById('fileList');
      const submitBtn = document.getElementById('submitBtn');
      
      if (this.files && this.files.length > 0) {
        const file = this.files[0];
        
        if (file.size > maxFileSize) {
          alert('File size exceeds 20MB limit. Please select a smaller file.');
          this.value = '';
          fileListDiv.innerHTML = '';
          submitBtn.disabled = true;
          return;
        }
        
        const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
        fileListDiv.innerHTML = `
          <div class="file-item">
            <div class="file-item-name">
              <i class="fas fa-file"></i>
              ${file.name}
            </div>
            <span class="file-item-size">${sizeInMB} MB</span>
            <button type="button" class="file-item-remove" onclick="removeFile()" style="background: #f44336; color: white; border: none; border-radius: 3px; padding: 5px 10px; cursor: pointer; font-size: 11px;">
              <i class="fas fa-times"></i> Remove
            </button>
          </div>
        `;
        fileListDiv.style.display = 'block';
        submitBtn.disabled = false;
      } else {
        fileListDiv.innerHTML = '';
        submitBtn.disabled = true;
      }
    });
    
    function removeFile() {
      document.getElementById('payment_receipt').value = '';
      document.getElementById('fileList').innerHTML = '';
      document.getElementById('submitBtn').disabled = true;
    }
  </script>
</body>
</html>
