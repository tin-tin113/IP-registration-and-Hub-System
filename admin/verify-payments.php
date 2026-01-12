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
        // Fetch application details for audit log
        $app_query = $conn->query("SELECT a.title, u.full_name FROM ip_applications a JOIN users u ON a.user_id=u.id WHERE a.id='$app_id'");
        $app_data = $app_query->fetch_assoc();
        
        $log_details = json_encode([
          'Action' => 'Payment Verified',
          'Application Title' => $app_data['title'],
          'Applicant' => $app_data['full_name'],
          'Amount' => $payment_amount
        ]);

        auditLog('Verify Payment', 'Application', $app_id, null, $log_details);
        $success = 'Payment verified successfully. Application ready for director approval.';
      }
      
      $stmt->close();
    }
  } elseif ($_POST['action'] === 'reject_payment') {
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    if ($app_id && !empty($rejection_reason)) {
      $check_column = $conn->query("SHOW COLUMNS FROM ip_applications LIKE 'payment_rejection_reason'");
      if ($check_column->num_rows === 0) {
        $conn->query("ALTER TABLE ip_applications ADD COLUMN payment_rejection_reason TEXT NULL AFTER payment_receipt");
      }
      
      $stmt = $conn->prepare("UPDATE ip_applications SET status='office_visit', payment_rejection_reason=?, payment_receipt=NULL WHERE id=?");
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

// Debug: Log how many payment_pending applications exist
error_log("Verify Payments - Checking for payment_pending applications");

$result = $conn->query("SELECT a.*, u.full_name, u.email FROM ip_applications a JOIN users u ON a.user_id=u.id WHERE a.status='payment_pending' AND a.payment_receipt IS NOT NULL AND a.payment_receipt != '' ORDER BY a.updated_at DESC");

if (!$result) {
  error_log("Verify Payments - Query error: " . $conn->error);
  $applications = [];
} else {
  $applications = $result->fetch_all(MYSQLI_ASSOC);
  error_log("Verify Payments - Found " . count($applications) . " applications");
}

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
      font-family: 'Inter', 'Segoe UI', sans-serif;
      background: #f5f7fa;
      min-height: 100vh;
    }
    
    /* Restored original spacing with margin-left instead of flush layout */
    .container {
      margin-left: 30px;
      padding: 30px;
      max-width: 1400px;
    }
    
    .header {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B5C3B 100%);
      color: white;
      padding: 30px;
      border-radius: 10px;
      margin-bottom: 30px;
      box-shadow: 0 4px 15px rgba(10, 77, 46, 0.2);
    }
    
    .header h1 {
      font-size: 28px;
      margin-bottom: 10px;
    }
    
    .header p {
      color: rgba(255, 255, 255, 0.9);
      font-size: 14px;
    }
    
    .alert {
      padding: 15px 20px;
      border-radius: 6px;
      margin-bottom: 20px;
      border-left: 4px solid;
    }
    
    .alert-success {
      background: #d4edda;
      color: #155724;
      border-left-color: #28a745;
    }
    
    .alert-danger {
      background: #f8d7da;
      color: #721c24;
      border-left-color: #f44336;
    }
    
    .app-card {
      background: white;
      border-radius: 10px;
      padding: 25px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      border-top: 4px solid #0A4D2E;
      transition: all 0.3s ease;
    }
    
    .app-card:hover {
      box-shadow: 0 4px 15px rgba(0,0,0,0.12);
    }
    
    .app-info {
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 1px solid #f0f0f0;
    }
    
    .app-info h3 {
      color: #0A4D2E;
      margin-bottom: 12px;
      font-size: 18px;
    }
    
    .app-meta {
      font-size: 13px;
      color: #666;
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
    }
    
    .app-meta span {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .app-meta i {
      color: #DAA520;
      width: 16px;
    }
    
    .receipt-preview {
      background: #f9f9f9;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 15px;
      border-left: 3px solid #DAA520;
    }
    
    .receipt-preview strong {
      color: #0A4D2E;
    }
    
    .receipt-preview img {
      max-width: 100%;
      max-height: 300px;
      border-radius: 6px;
      margin-top: 12px;
    }
    
    .receipt-preview a {
      color: #0A4D2E;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .receipt-preview a:hover {
      color: #DAA520;
    }
    
    .form-group {
      margin-bottom: 15px;
    }
    
    label {
      display: block;
      margin-bottom: 8px;
      color: #0A4D2E;
      font-weight: 700;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    
    input[type="number"],
    input[type="text"],
    textarea {
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 13px;
      font-family: inherit;
      transition: all 0.3s ease;
    }
    
    textarea {
      resize: vertical;
      min-height: 100px;
    }
    
    input:focus,
    textarea:focus {
      outline: none;
      border-color: #0A4D2E;
      box-shadow: 0 0 5px rgba(10, 77, 46, 0.3);
    }
    
    .button-group {
      display: flex;
      gap: 12px;
      flex-direction: column;
    }
    
    button {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B5C3B 100%);
      color: white;
      padding: 12px 20px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      font-size: 13px;
      transition: all 0.3s ease;
    }
    
    button:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(10, 77, 46, 0.3);
    }
    
    .btn-reject {
      background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    }
    
    .btn-reject:hover {
      box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
    }
    
    .btn-cancel {
      background: #6c757d;
    }
    
    .btn-cancel:hover {
      box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
    }
    
    .rejection-form {
      margin-top: 20px;
      padding-top: 15px;
      border-top: 1px solid #f0f0f0;
      display: none;
    }
    
    .rejection-form.active {
      display: block;
    }
    
    .empty {
      text-align: center;
      padding: 60px 20px;
      color: #999;
    }
    
    .empty i {
      font-size: 64px;
      margin-bottom: 20px;
      color: #ddd;
    }
    
    .empty p {
      font-size: 16px;
      margin-bottom: 10px;
    }
    
    @media (max-width: 1024px) {
      .container {
        margin-left: 240px;
      }
    }
    
    @media (max-width: 768px) {
      .container {
        margin-left: 0;
        padding: 20px;
      }
    }
    
    @media (max-width: 600px) {
      .container {
        padding: 15px;
      }
      
      .app-meta {
        flex-direction: column;
        gap: 8px;
      }
    }

    .modal-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }
    
    .modal-content {
      background: white;
      padding: 32px;
      border-radius: 16px;
      width: 90%;
      max-width: 480px;
      text-align: center;
      box-shadow: 0 10px 25px rgba(0,0,0,0.2);
      animation: modalSlide 0.3s ease-out;
    }
    
    @keyframes modalSlide {
      from { transform: translateY(20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    
    .modal-icon {
      font-size: 48px;
      color: #10B981;
      margin-bottom: 20px;
    }
    
    .modal-actions {
      display: flex;
      gap: 12px;
      justify-content: center;
      margin-top: 24px;
    }
  </style>
</head>
<body>
  <?php require_once '../includes/sidebar.php'; ?>
  
  <div class="container">
    <div class="header">
      <h1><i class="fas fa-credit-card"></i> Verify Payment Receipts</h1>
      <p>Review and verify payment receipts from applicants</p>
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
        <small>All payments have been reviewed</small>
      </div>
    <?php else: ?>
      <?php foreach ($applications as $app): ?>
        <div class="app-card">
          <div class="app-info">
            <h3><?php echo htmlspecialchars($app['title']); ?></h3>
            <div class="app-meta">
              <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($app['full_name']); ?></span>
              <span><i class="fas fa-users"></i> <strong>Inventors:</strong><br><?php echo nl2br(htmlspecialchars($app['inventor_name'] ?? '')); ?></span>
              <span><i class="fas fa-tag"></i> <?php echo $app['ip_type']; ?></span>
              <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($app['created_at'])); ?></span>
              <span><i class="fas fa-money-bill-wave"></i> Expected: <strong>₱<?php echo number_format(!empty($app['payment_amount']) ? $app['payment_amount'] : IP_REGISTRATION_FEE, 2); ?></strong></span>
            </div>
          </div>
          
          <?php if (!empty($app['payment_receipt'])): ?>
            <div class="receipt-preview">
              <strong><i class="fas fa-receipt"></i> Payment Receipt:</strong>
              <div style="margin-top: 12px;">
                <?php 
                  $receipt_ext = strtolower(pathinfo($app['payment_receipt'], PATHINFO_EXTENSION));
                  $receipt_path = '../uploads/' . $app['payment_receipt'];
                ?>
                <div style="margin-bottom: 10px;">
                  <?php if (in_array($receipt_ext, ['jpg', 'jpeg', 'png'])): ?>
                    <img src="<?php echo $receipt_path; ?>" alt="Payment Receipt" style="max-width: 100%; border-radius: 6px; border: 1px solid #ddd;">
                  <?php else: ?>
                    <div style="padding: 20px; background: white; border: 1px solid #ddd; border-radius: 6px; text-align: center;">
                      <i class="fas fa-file-pdf" style="font-size: 32px; color: #dc3545;"></i>
                      <p style="margin-top: 10px; color: #666; font-size: 13px;"><?php echo htmlspecialchars(basename($app['payment_receipt'])); ?></p>
                    </div>
                  <?php endif; ?>
                </div>
                
                <div style="display: flex; gap: 10px;">
                  <a href="<?php echo $receipt_path; ?>" target="_blank" class="btn-view" style="flex: 1; justify-content: center; background: #e9ecef; color: #495057; padding: 10px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-eye"></i> View Receipt
                  </a>
                  <a href="<?php echo $receipt_path; ?>" download class="btn-download" style="flex: 1; justify-content: center; background: #0A4D2E; color: white; padding: 10px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-download"></i> Download
                  </a>
                </div>
              </div>
            </div>
          <?php endif; ?>
          
          <?php if (!empty($app['payment_rejection_reason'])): ?>
            <div class="alert alert-danger" style="margin-bottom: 15px;">
              <strong><i class="fas fa-exclamation-triangle"></i> Previous Rejection Reason:</strong><br>
              <?php echo nl2br(htmlspecialchars($app['payment_rejection_reason'])); ?>
            </div>
          <?php endif; ?>
          
          <form method="POST" id="verifyForm_<?php echo $app['id']; ?>">
            <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
            <input type="hidden" name="action" value="verify_payment">
            
            <div class="form-group">
              <label for="payment_amount_<?php echo $app['id']; ?>">Payment Amount (₱) *</label>
              <input type="number" id="payment_amount_<?php echo $app['id']; ?>" name="payment_amount" step="0.01" min="0" value="<?php echo !empty($app['payment_amount']) ? $app['payment_amount'] : IP_REGISTRATION_FEE; ?>" placeholder="e.g., 500.00" required>
              <small style="color: #999; font-size: 12px; margin-top: 6px; display: block;">Default registration fee: ₱<?php echo number_format(IP_REGISTRATION_FEE, 2); ?></small>
            </div>
            
            <div class="button-group">
              <button type="button" onclick="showVerifyModal(<?php echo $app['id']; ?>)">
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
                <textarea id="rejection_reason_<?php echo $app['id']; ?>" name="rejection_reason" placeholder="Explain why this payment receipt is being rejected..." required></textarea>
              </div>
              
              <div class="button-group">
                <button type="submit" name="action" value="reject_payment" class="btn-reject">
                  <i class="fas fa-times"></i> Confirm Rejection
                </button>
                <button type="button" class="btn-cancel" onclick="document.getElementById('rejectForm_<?php echo $app['id']; ?>').classList.remove('active')">
                  <i class="fas fa-times"></i> Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Confirmation Modal -->
  <div class="modal-overlay" id="verifyModal">
    <div class="modal-content">
      <div class="modal-icon">
        <i class="fas fa-credit-card"></i>
      </div>
      <h2 style="margin-bottom: 10px; color: #1E293B;">Confirm Payment Verification</h2>
      <p style="color: #64748B; margin-bottom: 20px;">Are you sure you want to verify this payment and forward the application to the Director?</p>
      
      <div class="modal-actions">
        <button type="button" style="background: #E2E8F0; color: #475569;" onclick="closeModal()">
          Cancel
        </button>
        <button type="button" style="background: linear-gradient(135deg, #0A4D2E 0%, #1B5C3B 100%); color: white;" onclick="confirmVerify()">
          Yes, Verify Payment
        </button>
      </div>
    </div>
  </div>

  <script>
    let currentAppId = null;

    function showVerifyModal(appId) {
      currentAppId = appId;
      document.getElementById('verifyModal').style.display = 'flex';
    }

    function closeModal() {
      document.getElementById('verifyModal').style.display = 'none';
      currentAppId = null;
    }

    function confirmVerify() {
      if (currentAppId) {
        document.getElementById('verifyForm_' + currentAppId).submit();
      }
    }

    // Close modal when clicking outside
    document.getElementById('verifyModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeModal();
      }
    });
  </script>
</body>
</html>
