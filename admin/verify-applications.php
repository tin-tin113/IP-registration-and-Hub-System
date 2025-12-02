<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireRole(['clerk', 'director']); // Allow both clerk and director to access

$error = '';
$success = '';

// Approve for office visit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_office_visit') {
  $app_id = $_POST['app_id'] ?? null;
  $clerk_notes = trim($_POST['clerk_notes'] ?? '');
  
  if ($app_id) {
    $office_visit_date = date('Y-m-d H:i:s', strtotime('+3 days'));
    
    $stmt = $conn->prepare("UPDATE ip_applications SET status='office_visit', clerk_notes=?, office_visit_date=? WHERE id=?");
    $stmt->bind_param("ssi", $clerk_notes, $office_visit_date, $app_id);
    
    if ($stmt->execute()) {
      auditLog('Approve Office Visit', 'Application', $app_id);
      
      $user_stmt = $conn->prepare("SELECT user_id FROM ip_applications WHERE id=?");
      $user_stmt->bind_param("i", $app_id);
      $user_stmt->execute();
      $user_result = $user_stmt->get_result();
      $user_data = $user_result->fetch_assoc();
      $user_stmt->close();
      
      $success = 'Application approved for office visit. User notified to make payment at CHMSU Cashier Office.';
    }
    
    $stmt->close();
  }
}

// Reject application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject') {
  $app_id = $_POST['app_id'] ?? null;
  $rejection_reason = trim($_POST['rejection_reason'] ?? '');
  
  if ($app_id && !empty($rejection_reason)) {
    $stmt = $conn->prepare("UPDATE ip_applications SET status='rejected', rejection_reason=? WHERE id=?");
    $stmt->bind_param("si", $rejection_reason, $app_id);
    
    if ($stmt->execute()) {
      auditLog('Reject Application', 'Application', $app_id);
      $success = 'Application rejected.';
    }
    
    $stmt->close();
  }
}

// Get submitted applications
$result = $conn->query("SELECT a.*, u.full_name, u.email FROM ip_applications a JOIN users u ON a.user_id=u.id WHERE a.status='submitted' ORDER BY a.created_at ASC");
$applications = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Applications - CHMSU IP System</title>
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
      display: flex;
      justify-content: space-between;
      align-items: center;
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
    
    .app-card {
      background: white;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 15px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .app-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 15px;
      padding-bottom: 15px;
      border-bottom: 1px solid #f0f0f0;
    }
    
    .app-title h3 {
      color: #333;
      margin-bottom: 5px;
    }
    
    .app-meta {
      font-size: 13px;
      color: #666;
      display: flex;
      gap: 15px;
    }
    
    .app-description {
      background: #f9f9f9;
      padding: 12px;
      border-radius: 5px;
      font-size: 13px;
      color: #555;
      line-height: 1.5;
      margin-bottom: 15px;
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
    
    textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 13px;
      font-family: inherit;
      min-height: 80px;
      resize: vertical;
    }
    
    textarea:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 5px rgba(102,126,234,0.3);
    }
    
    .actions {
      display: flex;
      gap: 10px;
      margin-top: 15px;
    }
    
    button {
      padding: 10px 15px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
      transition: all 0.2s;
    }
    
    .btn-approve {
      background: #4caf50;
      color: white;
      flex: 1;
    }
    
    .btn-approve:hover {
      background: #45a049;
    }
    
    .btn-reject {
      background: #f44336;
      color: white;
      flex: 1;
    }
    
    .btn-reject:hover {
      background: #da190b;
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
      <h1><i class="fas fa-check-square"></i> Verify Applications</h1>
    </div>
    
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if (count($applications) === 0): ?>
      <div class="app-card empty">
        <i class="fas fa-inbox"></i>
        <p>No submitted applications to review</p>
      </div>
    <?php else: ?>
      <?php foreach ($applications as $app): ?>
        <div class="app-card">
          <div class="app-header">
            <div class="app-title">
              <h3><?php echo htmlspecialchars($app['title']); ?></h3>
              <div class="app-meta">
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($app['full_name']); ?> (<?php echo htmlspecialchars($app['email']); ?>)</span>
                <span><i class="fas fa-tag"></i> <?php echo $app['ip_type']; ?></span>
                <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($app['created_at'])); ?></span>
              </div>
            </div>
          </div>
          
          <div class="app-description">
            <strong>Description:</strong><br>
            <?php echo htmlspecialchars($app['description']); ?>
          </div>
          
          <form method="POST" id="form_<?php echo $app['id']; ?>">
            <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
            
            <div class="form-group">
              <label for="clerk_notes_<?php echo $app['id']; ?>">Clerk Notes (Optional)</label>
              <textarea id="clerk_notes_<?php echo $app['id']; ?>" name="clerk_notes" placeholder="Enter any verification notes for the applicant..."></textarea>
            </div>
            
            <div class="actions">
              <button type="submit" name="action" value="approve_office_visit" class="btn-approve" onclick="this.form.action='?'">
                <i class="fas fa-check"></i> Approve for Office Visit
              </button>
            </div>
          </form>
          
          <form method="POST" style="margin-top: 10px;">
            <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
            <div class="form-group">
              <textarea name="rejection_reason" placeholder="Reason for rejection..." style="margin-bottom: 0;"></textarea>
            </div>
            <button type="submit" name="action" value="reject" class="btn-reject" style="width: 100%; margin-top: 10px;">
              <i class="fas fa-times"></i> Reject Application
            </button>
          </form>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</body>
</html>
