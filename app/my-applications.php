<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireLogin();

$user_id = getCurrentUserId();
$user_role = getUserRole();
$filter = $_GET['filter'] ?? 'all';

// Validate filter to prevent SQL injection
$allowed_statuses = ['all', 'draft', 'submitted', 'office_visit', 'payment_pending', 'payment_verified', 'approved', 'rejected'];
if (!in_array($filter, $allowed_statuses)) {
  $filter = 'all';
}

// For admins (clerk/director), show all applications they submitted for others
// For regular users, show only their own applications
if (in_array($user_role, ['clerk', 'director'])) {
  if ($filter === 'all') {
    $query = "SELECT a.*, u.full_name, u.email, c.id as cert_id FROM ip_applications a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN certificates c ON a.id = c.application_id ORDER BY a.created_at DESC";
    $stats_query = "SELECT status, COUNT(*) as count FROM ip_applications GROUP BY status";
  } else {
    $status_escaped = $conn->real_escape_string($filter);
    $query = "SELECT a.*, u.full_name, u.email, c.id as cert_id FROM ip_applications a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN certificates c ON a.id = c.application_id WHERE a.status = '$status_escaped' ORDER BY a.created_at DESC";
    $stats_query = "SELECT status, COUNT(*) as count FROM ip_applications WHERE status = '$status_escaped' GROUP BY status";
  }
} else {
  if ($filter === 'all') {
    $query = "SELECT a.*, u.full_name, u.email, c.id as cert_id FROM ip_applications a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN certificates c ON a.id = c.application_id WHERE a.user_id = $user_id ORDER BY a.created_at DESC";
    $stats_query = "SELECT status, COUNT(*) as count FROM ip_applications WHERE user_id = $user_id GROUP BY status";
  } else {
    $status_escaped = $conn->real_escape_string($filter);
    $query = "SELECT a.*, u.full_name, u.email, c.id as cert_id FROM ip_applications a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN certificates c ON a.id = c.application_id WHERE a.user_id = $user_id AND a.status = '$status_escaped' ORDER BY a.created_at DESC";
    $stats_query = "SELECT status, COUNT(*) as count FROM ip_applications WHERE user_id = $user_id AND status = '$status_escaped' GROUP BY status";
  }
}

$result = $conn->query($query);
$applications = $result->fetch_all(MYSQLI_ASSOC);

$stats_result = $conn->query($stats_query);
$stats = [];
while ($row = $stats_result->fetch_assoc()) {
  $stats[$row['status']] = $row['count'];
}

$upload_success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo in_array($user_role, ['clerk', 'director']) ? 'All Applications' : 'My Applications'; ?> - CHMSU IP System</title>
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
    
    .header {
      background: white;
      padding: 30px;
      border-radius: 10px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .header h1 {
      color: #333;
      font-size: 24px;
    }
    
    .btn-new {
      background: linear-gradient(135deg, #E07D32 0%, #155724 100%);
      color: white;
      padding: 12px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-weight: 600;
    }
    
    .btn-new:hover {
      transform: translateY(-2px);
    }
    
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
    }
    
    .stat-card {
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      text-align: center;
      cursor: pointer;
      transition: all 0.3s;
      border: 2px solid transparent;
    }
    
    .stat-card:hover {
      border-color: #155724;
    }
    
    .stat-card.active {
      background: linear-gradient(135deg, #155724 0%, #155724 100%);
      color: white;
    }
    
    .stat-number {
      font-size: 32px;
      font-weight: bold;
      margin-bottom: 5px;
    }
    
    .stat-label {
      font-size: 12px;
      opacity: 0.7;
    }
    
    .filters {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    
    .filter-btn {
      padding: 8px 15px;
      border: 1px solid #ddd;
      background: white;
      border-radius: 20px;
      cursor: pointer;
      font-size: 13px;
      transition: all 0.3s;
    }
    
    .filter-btn:hover {
      border-color: #155724;
    }
    
    .filter-btn.active {
      background: #155724;
      color: white;
      border-color: #155724;
    }
    
    .applications {
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .app-item {
      padding: 20px;
      border-bottom: 1px solid #f0f0f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: background 0.2s;
    }
    
    .app-item:hover {
      background: #f9f9f9;
    }
    
    .app-info h3 {
      color: #333;
      margin-bottom: 8px;
    }
    
    .app-meta {
      font-size: 13px;
      color: #666;
      display: flex;
      gap: 20px;
    }
    
    .app-type {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      margin: 5px 0 0 0;
    }
    
    .type-copyright {
      background: #e3f2fd;
      color: #1976d2;
    }
    
    .type-patent {
      background: #f3e5f5;
      color: #7b1fa2;
    }
    
    .type-trademark {
      background: #fff3e0;
      color: #e65100;
    }
    
    .status-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }
    
    .status-draft {
      background: #f0f0f0;
      color: #666;
    }
    
    .status-submitted {
      background: #fce4ec;
      color: #c2185b;
    }
    
    .status-office_visit {
      background: #e0f2f1;
      color: #00695c;
    }
    
    .status-payment_pending {
      background: #fff3e0;
      color: #e65100;
    }
    
    .status-payment_verified {
      background: #f3e5f5;
      color: #7b1fa2;
    }
    
    .status-approved {
      background: #c8e6c9;
      color: #2e7d32;
    }
    
    .status-rejected {
      background: #ffcdd2;
      color: #c62828;
    }
    
    .app-actions {
      display: flex;
      gap: 8px;
    }
    
    .btn-small {
      padding: 8px 12px;
      border: 1px solid #ddd;
      background: white;
      border-radius: 5px;
      cursor: pointer;
      font-size: 12px;
      transition: all 0.2s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    
    .btn-small:hover {
      border-color: #155724;
      color: yellow;
    }
    
    .empty-state {
      padding: 50px 20px;
      text-align: center;
      color: #999;
    }
    
    .empty-state i {
      font-size: 48px;
      margin-bottom: 15px;
      color: #ddd;
    }
    
    /* Added notification styles */
    .notification {
      background: linear-gradient(135deg, #FF6B6B, #E07D32);
      color: white;
      padding: 15px 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 12px;
      box-shadow: 0 4px 15px rgba(224, 125, 50, 0.3);
      animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
      from { transform: translateY(-20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    
    .notification i {
      font-size: 24px;
    }
    
    .notification strong {
      font-size: 16px;
    }
    
    @media (max-width: 768px) {
      .container {
        padding: 0;
      }
      
      .header {
        padding: 20px;
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
      }
      
      .stats {
        grid-template-columns: 1fr 1fr;
      }
      
      .app-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
      }
      
      .app-info {
        width: 100%;
      }
      
      .app-meta {
        flex-direction: column;
        gap: 8px;
        margin-bottom: 8px;
      }
      
      .status-badge {
        align-self: flex-start;
        margin-bottom: 8px;
      }
      
      .app-actions {
        width: 100%;
        flex-wrap: wrap;
      }
      
      .btn-small {
        flex: 1;
        justify-content: center;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div style="display: flex; align-items: center; gap: 15px;">
        <a href="../dashboard.php" class="btn-small" style="background: linear-gradient(135deg, #155724 0%, #155724 100%); color: white; padding: 10px 15px;">
          <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1><i class="fas fa-folder"></i> <?php echo in_array($user_role, ['clerk', 'director']) ? 'All Applications' : 'My Applications'; ?></h1>
      </div>
      <?php if (in_array($user_role, ['clerk', 'director'])): ?>
        <!-- No apply for others button -->
      <?php else: ?>
        <a href="apply.php" class="btn-new"><i class="fas fa-plus"></i> New Application</a>
      <?php endif; ?>
    </div>
    
    <!-- Added success notification for payment upload -->
    <?php if ($upload_success === 'payment_uploaded'): ?>
      <div class="notification">
        <i class="fas fa-check-circle"></i>
        <div>
          <strong>Payment Receipt Uploaded Successfully!</strong>
          <p style="margin: 0; font-size: 14px; opacity: 0.9;">Your receipt is being verified by the clerk. You'll be notified once approved.</p>
        </div>
      </div>
    <?php endif; ?>
    
    <div class="stats">
      <a href="?filter=all" class="stat-card <?php echo $filter === 'all' ? 'active' : ''; ?>">
        <div class="stat-number"><?php echo array_sum($stats); ?></div>
        <div class="stat-label">Total</div>
      </a>
      <a href="?filter=draft" class="stat-card <?php echo $filter === 'draft' ? 'active' : ''; ?>">
        <div class="stat-number"><?php echo $stats['draft'] ?? 0; ?></div>
        <div class="stat-label">Draft</div>
      </a>
      <a href="?filter=submitted" class="stat-card <?php echo $filter === 'submitted' ? 'active' : ''; ?>">
        <div class="stat-number"><?php echo $stats['submitted'] ?? 0; ?></div>
        <div class="stat-label">Submitted</div>
      </a>
      <a href="?filter=approved" class="stat-card <?php echo $filter === 'approved' ? 'active' : ''; ?>">
        <div class="stat-number"><?php echo $stats['approved'] ?? 0; ?></div>
        <div class="stat-label">Approved</div>
      </a>
    </div>
    
    <?php if (count($applications) === 0): ?>
      <div class="applications">
        <div class="empty-state">
          <i class="fas fa-inbox"></i>
          <p>No applications found</p>
          <a href="apply.php" class="btn-new" style="margin-top: 15px;">Submit Your First Application</a>
        </div>
      </div>
    <?php else: ?>
      <div class="applications">
        <?php foreach ($applications as $app): ?>
          <div class="app-item">
            <div class="app-info">
              <h3><?php echo htmlspecialchars($app['title']); ?></h3>
              <div class="app-meta">
                <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($app['created_at'])); ?></span>
                <span><i class="fas fa-file-alt"></i> <?php echo $app['ip_type']; ?></span>
                <?php if (in_array($user_role, ['clerk', 'director']) && !empty($app['full_name'])): ?>
                  <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($app['full_name']); ?></span>
                <?php endif; ?>
              </div>
              <span class="app-type type-<?php echo strtolower($app['ip_type']); ?>"><?php echo htmlspecialchars($app['ip_type']); ?></span>
              
              <!-- Added payment notification for office_visit status -->
              <?php if ($app['status'] === 'office_visit'): 
                $payment_amount = !empty($app['payment_amount']) ? $app['payment_amount'] : IP_REGISTRATION_FEE;
              ?>
                <div style="margin-top: 10px; background: #FFF3CD; border-left: 4px solid #E07D32; padding: 10px; border-radius: 4px;">
                  <strong style="color: #E07D32; display: flex; align-items: center; gap: 6px; font-size: 13px;">
                    <i class="fas fa-exclamation-triangle"></i> Payment Required
                  </strong>
                  <p style="margin: 5px 0 0 0; font-size: 12px; color: #856404;">
                    Visit CHMSU Cashier Office to pay registration fee of <strong>₱<?php echo number_format($payment_amount, 2); ?></strong>, then upload your receipt.
                  </p>
                </div>
              <?php endif; ?>
              
              <!-- Payment rejection notification -->
              <?php if ($app['status'] === 'payment_pending' && !empty($app['payment_rejection_reason'])): 
                $payment_amount = !empty($app['payment_amount']) ? $app['payment_amount'] : IP_REGISTRATION_FEE;
              ?>
                <div style="margin-top: 10px; background: #f8d7da; border-left: 4px solid #dc3545; padding: 10px; border-radius: 4px;">
                  <strong style="color: #721c24; display: flex; align-items: center; gap: 6px; font-size: 13px;">
                    <i class="fas fa-times-circle"></i> Payment Receipt Rejected
                  </strong>
                  <p style="margin: 5px 0 0 0; font-size: 12px; color: #721c24;">
                    <strong>Reason:</strong> <?php echo htmlspecialchars($app['payment_rejection_reason']); ?>
                  </p>
                  <p style="margin: 8px 0 0 0; font-size: 12px; color: #856404;">
                    Please upload a new payment receipt. Required amount: <strong>₱<?php echo number_format($payment_amount, 2); ?></strong>
                  </p>
                </div>
              <?php endif; ?>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
              <span class="status-badge status-<?php echo $app['status']; ?>"><?php echo ucwords(str_replace('_', ' ', $app['status'])); ?></span>
              <div class="app-actions">
                <?php if ($app['status'] === 'draft'): ?>
                  <a href="apply.php?id=<?php echo $app['id']; ?>" class="btn-small" style="background: #f0f4ff; color: #667eea; border-color: #667eea;"><i class="fas fa-pen"></i> Continue</a>
                <?php elseif ($app['status'] === 'rejected'): ?>
                  <a href="view-application.php?id=<?php echo $app['id']; ?>" class="btn-small"><i class="fas fa-eye"></i> View</a>
                  <a href="apply.php?id=<?php echo $app['id']; ?>&resubmit=1" class="btn-small" style="background: #E07D32; color: white; border-color: #E07D32;"><i class="fas fa-redo"></i> Edit & Resubmit</a>
                <?php else: ?>
                  <a href="view-application.php?id=<?php echo $app['id']; ?>" class="btn-small"><i class="fas fa-eye"></i> View</a>
                <?php endif; ?>
                <?php if ($app['status'] === 'office_visit' || ($app['status'] === 'payment_pending' && !empty($app['payment_rejection_reason']))): ?>
                  <a href="upload-payment.php?id=<?php echo $app['id']; ?>" class="btn-small" style="background: #E07D32; color: white; border-color: #E07D32;">
                    <i class="fas fa-receipt"></i> <?php echo !empty($app['payment_rejection_reason']) ? 'Resubmit Payment' : 'Upload Payment'; ?>
                  </a>
                <?php endif; ?>
                <?php if ($app['status'] === 'approved' && !empty($app['cert_id'])): ?>
                  <a href="../certificate/generate.php?id=<?php echo $app['cert_id']; ?>" class="btn-small" style="background: #DAA520; color: white; border-color: #DAA520;" target="_blank">
                    <i class="fas fa-certificate"></i> View Certificate
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
