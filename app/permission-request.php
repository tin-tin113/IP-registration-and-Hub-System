<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireLogin();

$user_id = getCurrentUserId();
$success = '';
$error = '';

// Handle grant/deny actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $app_id = intval($_POST['app_id'] ?? 0);
  $action = $_POST['permission_action'] ?? '';
  
  if ($app_id && in_array($action, ['grant', 'deny'])) {
    // Verify ownership
    $check = $conn->prepare("SELECT id FROM ip_applications WHERE id = ? AND user_id = ? AND status = 'approved'");
    $check->bind_param("ii", $app_id, $user_id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
      $permission = ($action === 'grant') ? 'granted' : 'denied';
      $now = date('Y-m-d H:i:s');
      
      $stmt = $conn->prepare("UPDATE ip_applications SET publish_permission = ?, publish_permission_date = ? WHERE id = ?");
      $stmt->bind_param("ssi", $permission, $now, $app_id);
      
      if ($stmt->execute()) {
        $action_text = ($action === 'grant') ? 'granted. Your work will now appear in the IP Hub.' : 'denied. Your work will remain private.';
        $success = "Publishing permission $action_text";
        auditLog('Update Publish Permission', 'Application', $app_id, null, $permission);
      } else {
        $error = 'Failed to update permission';
      }
      $stmt->close();
    } else {
      $error = 'Application not found or not owned by you';
    }
    $check->close();
  }
}

$stmt = $conn->prepare("
  SELECT id, title, ip_type, abstract, approved_at, certificate_id 
  FROM ip_applications 
  WHERE user_id = ? AND status = 'approved' AND publish_permission = 'pending'
  ORDER BY approved_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get existing permissions (granted/denied) for management
$existing_stmt = $conn->prepare("
  SELECT id, title, ip_type, approved_at, certificate_id, publish_permission, publish_permission_date 
  FROM ip_applications 
  WHERE user_id = ? AND status = 'approved' AND publish_permission IN ('granted', 'denied')
  ORDER BY publish_permission_date DESC
");
$existing_stmt->bind_param("i", $user_id);
$existing_stmt->execute();
$existing_apps = $existing_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$existing_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Publishing Permission - CHMSU IP System</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #F8FAFC;
      min-height: 100vh;
      padding: 20px;
    }
    
    .container {
      max-width: 900px;
      margin: 0 auto;
    }
    
    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: #0A4D2E;
      text-decoration: none;
      font-weight: 600;
      margin-bottom: 20px;
      padding: 10px 16px;
      background: white;
      border-radius: 8px;
      border: 1px solid #E2E8F0;
    }
    
    .back-btn:hover { background: #F1F5F9; }
    
    .header {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      padding: 32px;
      border-radius: 16px;
      margin-bottom: 24px;
    }
    
    .header h1 { font-size: 24px; margin-bottom: 8px; }
    .header p { opacity: 0.9; font-size: 14px; }
    
    .alert {
      padding: 16px;
      border-radius: 12px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .alert-success {
      background: #D1FAE5;
      color: #065F46;
      border-left: 4px solid #10B981;
    }
    
    .alert-danger {
      background: #FEE2E2;
      color: #7F1D1D;
      border-left: 4px solid #EF4444;
    }
    
    .info-box {
      background: #E0F2FE;
      border: 1px solid #7DD3FC;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 24px;
      font-size: 14px;
      color: #0369A1;
    }
    
    .info-box i { margin-right: 8px; }
    
    .app-card {
      background: white;
      border-radius: 16px;
      padding: 24px;
      margin-bottom: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      border: 1px solid #E2E8F0;
    }
    
    .app-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 16px;
    }
    
    .app-title { font-size: 18px; font-weight: 700; color: #1E293B; margin-bottom: 8px; }
    
    .app-meta { font-size: 13px; color: #64748B; }
    .app-meta span { margin-right: 16px; }
    
    .app-type {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .type-copyright { background: #DBEAFE; color: #1E40AF; }
    .type-patent { background: #FCE7F3; color: #9F1239; }
    .type-trademark { background: #FEF3C7; color: #92400E; }
    
    .app-abstract {
      font-size: 14px;
      color: #475569;
      line-height: 1.6;
      margin-bottom: 20px;
      padding: 16px;
      background: #F8FAFC;
      border-radius: 8px;
      border-left: 4px solid #DAA520;
      overflow-wrap: break-word;

    }
    
    .action-buttons {
      display: flex;
      gap: 12px;
    }
    
    .btn {
      padding: 12px 24px;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s;
    }
    
    .btn-grant {
      background: linear-gradient(135deg, #10B981 0%, #059669 100%);
      color: white;
    }
    
    .btn-grant:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .btn-deny {
      background: #F1F5F9;
      color: #64748B;
      border: 1px solid #E2E8F0;
    }
    
    .btn-deny:hover {
      background: #FEE2E2;
      color: #DC2626;
      border-color: #FECACA;
    }
    
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      background: white;
      border-radius: 16px;
      border: 1px solid #E2E8F0;
    }
    
    .empty-state i { font-size: 56px; color: #CBD5E1; margin-bottom: 16px; }
    .empty-state h3 { color: #64748B; margin-bottom: 8px; }
    .empty-state p { color: #94A3B8; font-size: 14px; }
    
    @media (max-width: 768px) {
      .app-header { flex-direction: column; gap: 12px; }
      .action-buttons { flex-direction: column; }
      .btn { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="../profile/badges-certificates.php" class="back-btn">
      <i class="fas fa-arrow-left"></i> Back to Profile
    </a>
    
    <div class="header">
      <h1><i class="fas fa-globe"></i> Publishing Permission Requests</h1>
      <p>Review and decide whether to share your approved IP works in the public IP Hub</p>
    </div>
    
    <?php if (!empty($success)): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($success); ?></span>
      </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
    <?php endif; ?>
    
    <div class="info-box">
      <i class="fas fa-info-circle"></i>
      <strong>What is this?</strong> After your IP application is approved, you can choose whether to display it publicly in the IP Hub. 
      Granting permission allows others to view your work. You can change this later.
    </div>
    
    <?php if (count($pending_apps) === 0): ?>
      <div class="empty-state">
        <i class="fas fa-check-circle"></i>
        <h3>All Done!</h3>
        <p>You have no pending publishing permission requests</p>
      </div>
    <?php else: ?>
      <?php foreach ($pending_apps as $app): ?>
        <div class="app-card">
          <div class="app-header">
            <div>
              <div class="app-title"><?php echo htmlspecialchars($app['title']); ?></div>
              <div class="app-meta">
                <span><i class="fas fa-calendar"></i> Approved: <?php echo date('M d, Y', strtotime($app['approved_at'])); ?></span>
                <span><i class="fas fa-certificate"></i> <?php echo htmlspecialchars($app['certificate_id']); ?></span>
              </div>
            </div>
            <span class="app-type type-<?php echo strtolower($app['ip_type']); ?>"><?php echo $app['ip_type']; ?></span>
          </div>
          
          <div class="app-abstract">
            <?php echo htmlspecialchars(substr($app['abstract'], 0, 300)) . (strlen($app['abstract']) > 300 ? '...' : ''); ?>
          </div>
          
          <div class="action-buttons">
            <form method="POST" style="display: inline;">
              <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
              <button type="submit" name="permission_action" value="grant" class="btn btn-grant">
                <i class="fas fa-check"></i> Grant Permission (Show in Hub)
              </button>
            </form>
            <form method="POST" style="display: inline;">
              <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
              <button type="submit" name="permission_action" value="deny" class="btn btn-deny">
                <i class="fas fa-times"></i> Deny (Keep Private)
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Manage Existing Permissions -->
    <?php if (count($existing_apps) > 0): ?>
    <div style="margin-top: 40px;">
      <h2 style="font-size: 20px; font-weight: 700; color: #1E293B; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-cog" style="color: #64748B;"></i> Manage Existing Permissions
      </h2>
      <p style="color: #64748B; font-size: 14px; margin-bottom: 20px;">Change the visibility of your published works at any time.</p>
      
      <?php foreach ($existing_apps as $app): ?>
        <div class="app-card" style="border-left: 4px solid <?php echo $app['publish_permission'] === 'granted' ? '#10B981' : '#EF4444'; ?>;">
          <div class="app-header">
            <div>
              <div class="app-title"><?php echo htmlspecialchars($app['title']); ?></div>
              <div class="app-meta">
                <span><i class="fas fa-certificate"></i> <?php echo htmlspecialchars($app['certificate_id']); ?></span>
                <span>
                  <i class="fas fa-<?php echo $app['publish_permission'] === 'granted' ? 'eye' : 'eye-slash'; ?>"></i>
                  <?php echo $app['publish_permission'] === 'granted' ? 'Public (In Hub)' : 'Private'; ?>
                </span>
                <span><i class="fas fa-clock"></i> Changed: <?php echo date('M d, Y', strtotime($app['publish_permission_date'])); ?></span>
              </div>
            </div>
            <span class="app-type type-<?php echo strtolower($app['ip_type']); ?>"><?php echo $app['ip_type']; ?></span>
          </div>
          
          <div class="action-buttons">
            <?php if ($app['publish_permission'] === 'granted'): ?>
              <form method="POST" style="display: inline;">
                <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                <button type="submit" name="permission_action" value="deny" class="btn btn-deny">
                  <i class="fas fa-eye-slash"></i> Make Private
                </button>
              </form>
            <?php else: ?>
              <form method="POST" style="display: inline;">
                <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                <button type="submit" name="permission_action" value="grant" class="btn btn-grant">
                  <i class="fas fa-eye"></i> Make Public
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>
