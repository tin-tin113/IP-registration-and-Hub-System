<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireRole(['clerk', 'director']);

$user_role = getUserRole();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $app_id = $_POST['app_id'] ?? null;
  
  if ($_POST['action'] === 'verify_payment') {
    $payment_amount = floatval($_POST['payment_amount'] ?? 0);
    
    if ($app_id && $payment_amount > 0) {
      $payment_date = date('Y-m-d H:i:s');
      
      $stmt = $conn->prepare("UPDATE ip_applications SET status='payment_verified', payment_date=?, payment_amount=?, payment_rejection_reason=NULL WHERE id=?");
      $stmt->bind_param("sdi", $payment_date, $payment_amount, $app_id);
      
      if ($stmt->execute()) {
        auditLog('Verify Payment', 'Application', $app_id);
        $success = 'Payment verified successfully. Application ready for director approval.';
      }
      
      $stmt->close();
    }
  } elseif ($_POST['action'] === 'reject_payment') {
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    if ($app_id && !empty($rejection_reason)) {
      // Check if payment_rejection_reason column exists, if not add it
      $check_column = $conn->query("SHOW COLUMNS FROM ip_applications LIKE 'payment_rejection_reason'");
      if ($check_column->num_rows === 0) {
        $conn->query("ALTER TABLE ip_applications ADD COLUMN payment_rejection_reason TEXT NULL AFTER payment_receipt");
      }
      
      $stmt = $conn->prepare("UPDATE ip_applications SET status='payment_pending', payment_rejection_reason=? WHERE id=?");
      $stmt->bind_param("si", $rejection_reason, $app_id);
      
      if ($stmt->execute()) {
        auditLog('Reject Payment Receipt', 'Application', $app_id, null, json_encode(['reason' => $rejection_reason]));
        $success = 'Payment receipt rejected. User will be notified and can resubmit.';
      }
      
      $stmt->close();
    } else {
      $error = 'Rejection reason is required.';
    }
  }
}

// Get applications awaiting payment verification
$result = $conn->query("SELECT a.*, u.full_name, u.email FROM ip_applications a JOIN users u ON a.user_id=u.id WHERE a.status='payment_pending' ORDER BY a.created_at ASC");
$applications = $result->fetch_all(MYSQLI_ASSOC);

// Check if payment_rejection_reason column exists
$check_column = $conn->query("SHOW COLUMNS FROM ip_applications LIKE 'payment_rejection_reason'");
if ($check_column->num_rows === 0) {
  $conn->query("ALTER TABLE ip_applications ADD COLUMN payment_rejection_reason TEXT NULL AFTER payment_receipt");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Payments - CHMSU IP System</title>
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
      max-width: 1000px;
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
    
    .header {
      background: white;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
    
    .app-card {
      background: white;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 15px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .app-info {
      margin-bottom: 15px;
      padding-bottom: 15px;
      border-bottom: 1px solid #f0f0f0;
    }
    
    .app-info h3 {
      color: #333;
      margin-bottom: 5px;
    }
    
    .app-meta {
      font-size: 13px;
      color: #666;
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
    }
    
    /* Added receipt preview */
    .receipt-preview {
      background: #f9f9f9;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 15px;
      border: 1px solid #ddd;
    }
    
    .receipt-preview img {
      max-width: 100%;
      max-height: 300px;
      border-radius: 5px;
      margin-top: 10px;
    }
    
    .form-group {
      margin-bottom: 15px;
    }
    
    label {
      display: block;
      margin-bottom: 5px;
      color: #333;
      font-weight: 600;
      font-size: 13px;
    }
    
    input[type="number"],
    input[type="text"],
    textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 13px;
      font-family: inherit;
    }
    
    textarea {
      resize: vertical;
      min-height: 100px;
    }
    
    input:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 5px rgba(102,126,234,0.3);
    }
    
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }
    
    button {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 12px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-weight: 600;
      font-size: 13px;
      transition: all 0.2s;
    }
    
    button:hover {
      transform: translateY(-2px);
    }
    
    .btn-reject {
      background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
      margin-top: 10px;
    }
    
    .btn-reject:hover {
      background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
    }
    
    .button-group {
      display: flex;
      gap: 10px;
      flex-direction: column;
    }
    
    .rejection-form {
      margin-top: 15px;
      padding-top: 15px;
      border-top: 1px solid #f0f0f0;
      display: none;
    }
    
    .rejection-form.active {
      display: block;
    }
    
    .empty {
      text-align: center;
      padding: 50px 20px;
      color: #999;
    }
    
    .empty i {
      font-size: 48px;
      margin-bottom: 15px;
      color: #ddd;
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
    
    <div class="header">
      <h1><i class="fas fa-credit-card"></i> Verify Payment Receipts</h1>
      <p style="color: #666; font-size: 13px; margin-top: 5px;">Review and verify payment receipts from applicants</p>
    </div>
    
    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if (count($applications) === 0): ?>
      <div class="app-card empty">
        <i class="fas fa-inbox"></i>
        <p>No pending payment verifications</p>
      </div>
    <?php else: ?>
      <?php foreach ($applications as $app): ?>
        <div class="app-card">
          <div class="app-info">
            <h3><?php echo htmlspecialchars($app['title']); ?></h3>
            <div class="app-meta">
              <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($app['full_name']); ?></span>
              <span><i class="fas fa-tag"></i> <?php echo $app['ip_type']; ?></span>
              <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($app['created_at'])); ?></span>
            </div>
          </div>
          
          <!-- Display payment receipt if available -->
          <?php if (!empty($app['payment_receipt'])): ?>
            <div class="receipt-preview">
              <strong><i class="fas fa-receipt"></i> Payment Receipt:</strong>
              <div style="margin-top: 10px;">
                <?php 
                  $receipt_ext = strtolower(pathinfo($app['payment_receipt'], PATHINFO_EXTENSION));
                  $receipt_path = '../uploads/' . $app['payment_receipt'];
                ?>
                <?php if (in_array($receipt_ext, ['jpg', 'jpeg', 'png'])): ?>
                  <img src="<?php echo $receipt_path; ?>" alt="Payment Receipt">
                <?php else: ?>
                  <a href="<?php echo $receipt_path; ?>" target="_blank" style="color: #1B5C3B; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-file-pdf"></i> View Receipt Document
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
          
          <!-- Show rejection reason if previously rejected -->
          <?php if (!empty($app['payment_rejection_reason'])): ?>
            <div class="alert alert-danger" style="margin-bottom: 15px;">
              <strong><i class="fas fa-exclamation-triangle"></i> Previous Rejection Reason:</strong><br>
              <?php echo nl2br(htmlspecialchars($app['payment_rejection_reason'])); ?>
            </div>
          <?php endif; ?>
          
          <form method="POST" id="verifyForm_<?php echo $app['id']; ?>">
            <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
            
            <div class="form-group">
              <label for="payment_amount_<?php echo $app['id']; ?>">Payment Amount (₱) *</label>
              <input type="number" id="payment_amount_<?php echo $app['id']; ?>" name="payment_amount" step="0.01" min="0" placeholder="e.g., 500.00" required>
            </div>
            
            <div class="button-group">
              <button type="submit" name="action" value="verify_payment">
                <i class="fas fa-check"></i> Verify Payment & Forward to Director
              </button>
              
              <button type="button" class="btn-reject" onclick="document.getElementById('rejectForm_<?php echo $app['id']; ?>').classList.add('active')">
                <i class="fas fa-times"></i> Reject Receipt
              </button>
            </div>
          </form>
          
          <div class="rejection-form" id="rejectForm_<?php echo $app['id']; ?>">
            <form method="POST">
              <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
              
              <div class="form-group">
                <label for="rejection_reason_<?php echo $app['id']; ?>">Rejection Reason *</label>
                <textarea id="rejection_reason_<?php echo $app['id']; ?>" name="rejection_reason" placeholder="Explain why this payment receipt is being rejected (e.g., unclear image, incorrect amount, missing information)..." required></textarea>
              </div>
              
              <div class="button-group">
                <button type="submit" name="action" value="reject_payment" class="btn-reject">
                  <i class="fas fa-times"></i> Confirm Rejection
                </button>
                <button type="button" onclick="document.getElementById('rejectForm_<?php echo $app['id']; ?>').classList.remove('active')" style="background: #6c757d;">
                  <i class="fas fa-times"></i> Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</body>
</html>
