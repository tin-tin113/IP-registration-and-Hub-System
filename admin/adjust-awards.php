<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('director');

$success = '';
$error = '';

// Handle award adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_award') {
  $app_id = $_POST['app_id'] ?? null;
  $award_amount = floatval($_POST['award_amount'] ?? 0);
  $award_reason = trim($_POST['award_reason'] ?? '');
  
  if ($app_id && $award_amount >= 0) {
    $stmt = $conn->prepare("UPDATE ip_applications SET award_amount=?, award_reason=?, award_date=NOW() WHERE id=?");
    $stmt->bind_param("dsi", $award_amount, $award_reason, $app_id);
    
    if ($stmt->execute()) {
      auditLog('Adjust Award', 'Application', $app_id, null, json_encode(['amount' => $award_amount]));
      $success = 'Award adjusted successfully!';
    } else {
      $error = 'Failed to adjust award';
    }
    
    $stmt->close();
  }
}

// Get approved applications
$result = $conn->query("SELECT a.*, u.full_name, u.email FROM ip_applications a JOIN users u ON a.user_id=u.id WHERE a.status='approved' ORDER BY a.approved_at DESC");
$applications = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Adjust Awards - CHMSU IP System</title>
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
      margin-bottom: 8px;
    }
    
    .app-meta {
      font-size: 13px;
      color: #666;
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
    }
    
    .current-award {
      background: #fff3cd;
      padding: 12px;
      border-radius: 5px;
      margin-bottom: 15px;
      border-left: 4px solid #E07D32;
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
    textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 13px;
      font-family: inherit;
    }
    
    textarea {
      min-height: 80px;
      resize: vertical;
    }
    
    input:focus,
    textarea:focus {
      outline: none;
      border-color: #E07D32;
      box-shadow: 0 0 5px rgba(224, 125, 50, 0.3);
    }
    
    button {
      background: linear-gradient(135deg, #E07D32 0%, #d97023 100%);
      color: white;
      padding: 12px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-weight: 600;
      font-size: 13px;
      width: 100%;
    }
    
    button:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(224, 125, 50, 0.3);
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
      <h1><i class="fas fa-trophy"></i> Adjust Awards & Recognition</h1>
      <p style="color: #666; font-size: 13px; margin-top: 5px;">Manage monetary awards and recognition for approved IP works</p>
    </div>
    
    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if (count($applications) === 0): ?>
      <div class="app-card empty">
        <i class="fas fa-inbox"></i>
        <p>No approved applications to manage awards</p>
      </div>
    <?php else: ?>
      <?php foreach ($applications as $app): ?>
        <div class="app-card">
          <div class="app-info">
            <h3><?php echo htmlspecialchars($app['title']); ?></h3>
            <div class="app-meta">
              <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($app['full_name']); ?></span>
              <span><i class="fas fa-tag"></i> <?php echo $app['ip_type']; ?></span>
              <span><i class="fas fa-calendar-check"></i> Approved: <?php echo date('M d, Y', strtotime($app['approved_at'])); ?></span>
            </div>
          </div>
          
          <?php if ($app['award_amount'] > 0): ?>
            <div class="current-award">
              <strong><i class="fas fa-award"></i> Current Award:</strong> ₱<?php echo number_format($app['award_amount'], 2); ?>
              <?php if ($app['award_reason']): ?>
                <br><small style="color: #666;">Reason: <?php echo htmlspecialchars($app['award_reason']); ?></small>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          
          <form method="POST">
            <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
            
            <div class="form-group">
              <label for="award_amount_<?php echo $app['id']; ?>">Award Amount (₱)</label>
              <input type="number" id="award_amount_<?php echo $app['id']; ?>" name="award_amount" step="0.01" min="0" value="<?php echo $app['award_amount'] ?? 0; ?>" placeholder="e.g., 5000.00">
            </div>
            
            <div class="form-group">
              <label for="award_reason_<?php echo $app['id']; ?>">Reason/Notes (Optional)</label>
              <textarea id="award_reason_<?php echo $app['id']; ?>" name="award_reason" placeholder="Enter reason for award amount..."><?php echo htmlspecialchars($app['award_reason'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit" name="action" value="adjust_award">
              <i class="fas fa-trophy"></i> Update Award
            </button>
          </form>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</body>
</html>
