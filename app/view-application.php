<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireLogin();

$app_id = $_GET['id'] ?? null;
$user_id = getCurrentUserId();

if (!$app_id) {
  header("Location: my-applications.php");
  exit;
}

// Get application with certificate info
$stmt = $conn->prepare("SELECT a.*, c.id as cert_id FROM ip_applications a LEFT JOIN certificates c ON a.id = c.application_id WHERE a.id = ? AND a.user_id = ?");
$stmt->bind_param("ii", $app_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  header("Location: my-applications.php?error=Application not found");
  exit;
}

$app = $result->fetch_assoc();
$stmt->close();

$document_files = [];
if (!empty($app['document_file'])) {
  $decoded = json_decode($app['document_file'], true);
  if (is_array($decoded)) {
    $document_files = $decoded;
  } else {
    // Backward compatibility for single file
    $document_files = [$app['document_file']];
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Application - CHMSU IP System</title>
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
      max-width: 900px;
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
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 2px solid #f0f0f0;
    }
    
    .card-title {
      font-size: 20px;
      color: #333;
      margin-bottom: 10px;
    }
    
    .meta-info {
      font-size: 13px;
      color: #666;
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
    }
    
    .status-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      background: #c8e6c9;
      color: #2e7d32;
    }
    
    .status-badge.pending {
      background: #fff3e0;
      color: #e65100;
    }
    
    .status-badge.rejected {
      background: #ffcdd2;
      color: #c62828;
    }
    
    .info-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 30px;
      margin-bottom: 20px;
    }
    
    .info-group {
      padding: 15px;
      background: #f9f9f9;
      border-radius: 5px;
    }
    
    .info-label {
      font-size: 12px;
      color: #999;
      text-transform: uppercase;
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .info-value {
      font-size: 14px;
      color: #333;
    }
    
    .abstract {
      background: #f9f9f9;
      padding: 15px;
      border-radius: 5px;
      border-left: 4px solid #667eea;
      margin: 20px 0;
      line-height: 1.6;
      overflow-wrap: break-word;
    }
    
    .workflow-steps {
      display: flex;
      justify-content: space-between;
      margin: 30px 0;
      position: relative;
    }
    
    .step {
      flex: 1;
      text-align: center;
      position: relative;
    }
    
    .step::before {
      content: '';
      position: absolute;
      top: 20px;
      left: 50%;
      width: 40%;
      height: 2px;
      background: #ddd;
    }
    
    .step:first-child::before {
      display: none;
    }
    
    .step.active::before {
      background: #667eea;
    }
    
    .step-circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: white;
      border: 2px solid #ddd;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 10px;
      font-weight: 600;
      color: #999;
      font-size: 12px;
    }
    
    .step.active .step-circle {
      background: #667eea;
      color: white;
      border-color: #667eea;
    }
    
    .step-label {
      font-size: 12px;
      color: #999;
      margin-top: 10px;
    }
    
    .feedback-box {
      background: #fff3cd;
      border: 1px solid #ffc107;
      border-radius: 5px;
      padding: 15px;
      margin-top: 15px;
    }
    
    .feedback-box.rejected {
      background: #f8d7da;
      border-color: #f5c6cb;
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="my-applications.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Applications</a>
    
    <div class="card">
      <div class="card-header">
        <div>
          <h2 class="card-title"><?php echo htmlspecialchars($app['title']); ?></h2>
          <div class="meta-info">
            <span><i class="fas fa-tag"></i> <?php echo $app['ip_type']; ?></span>
            <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($app['created_at'])); ?></span>
            <span><i class="fas fa-file-upload"></i> ID: <?php echo $app['id']; ?></span>
          </div>
        </div>
        <span class="status-badge <?php echo in_array($app['status'], ['rejected']) ? 'rejected' : (in_array($app['status'], ['submitted', 'payment_pending']) ? 'pending' : ''); ?>">
          <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
        </span>
      </div>
      
      <div class="info-row">
        <div class="info-group">
          <div class="info-label">Inventor Name</div>
          <div class="info-value"><?php echo nl2br(htmlspecialchars($app['inventor_name'] ?? '')); ?></div>
        </div>
        <div class="info-group">
          <div class="info-label">Certificate ID</div>
          <div class="info-value"><?php echo $app['certificate_id'] ?? 'Not Yet Generated'; ?></div>
        </div>
        <?php if (in_array($app['status'], ['office_visit', 'payment_pending', 'payment_verified'])): 
          $payment_amount = !empty($app['payment_amount']) ? $app['payment_amount'] : IP_REGISTRATION_FEE;
        ?>
        <div class="info-group">
          <div class="info-label">Registration Fee</div>
          <div class="info-value" style="color: #E07D32; font-weight: 600;">₱<?php echo number_format($payment_amount, 2); ?></div>
        </div>
        <?php endif; ?>
      </div>
      
      <?php if ($app['status'] === 'payment_pending' && !empty($app['payment_rejection_reason'])): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545; margin-bottom: 20px;">
          <strong><i class="fas fa-exclamation-triangle"></i> Payment Receipt Rejected</strong>
          <p style="margin: 10px 0 0 0; font-size: 14px;">
            <strong>Reason:</strong><br>
            <?php echo nl2br(htmlspecialchars($app['payment_rejection_reason'])); ?>
          </p>
          <p style="margin: 10px 0 0 0; font-size: 13px; color: #856404;">
            <i class="fas fa-info-circle"></i> Please upload a new payment receipt. You can resubmit from the "My Applications" page.
          </p>
        </div>
      <?php endif; ?>
      
      <div style="margin-bottom: 20px;">
        <h3 style="color: #333; margin-bottom: 10px; font-size: 14px;">Abstract</h3>

        <div class="abstract"><?php echo htmlspecialchars($app['abstract']); ?></div>
      </div>
      
      <!-- Updated document viewing section to display multiple files -->
      <?php if (!empty($document_files)): ?>
        <div style="background: #f0f8ff; padding: 20px; border-radius: 5px; border-left: 4px solid #1B5C3B; margin-bottom: 20px;">
          <h3 style="color: #1B5C3B; margin-bottom: 15px; font-size: 14px;">
            <i class="fas fa-file-upload"></i> Submitted Documents (<?php echo count($document_files); ?> file<?php echo count($document_files) > 1 ? 's' : ''; ?>)
          </h3>
          <?php foreach ($document_files as $file): ?>
            <?php 
              $file_path = UPLOAD_DIR . $file;
              $file_exists = file_exists($file_path);
              $file_size = $file_exists ? filesize($file_path) : 0;
              $file_size_mb = round($file_size / (1024 * 1024), 2);
            ?>
            <div style="padding: 15px; background: white; border-radius: 5px; border: 1px solid #ddd; margin-bottom: 10px;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="flex: 1;">
                  <a href="../uploads/<?php echo htmlspecialchars($file); ?>" target="_blank" style="color: #1B5C3B; text-decoration: none; font-weight: 600; display: block; margin-bottom: 5px;">
                    <i class="fas fa-file-<?php echo pathinfo($file, PATHINFO_EXTENSION) === 'pdf' ? 'pdf' : 'alt'; ?>"></i> 
                    <?php echo htmlspecialchars($file); ?>
                  </a>
                  <p style="font-size: 12px; color: #999; margin: 0;">
                    <?php if ($file_exists): ?>
                      <i class="fas fa-database"></i> Size: <?php echo $file_size_mb; ?> MB
                    <?php else: ?>
                      <i class="fas fa-exclamation-triangle"></i> File not found
                    <?php endif; ?>
                  </p>
                </div>
                <div class="document-actions">
                    <a href="../uploads/<?php echo htmlspecialchars($file); ?>" target="_blank" class="btn-view" style="background: #F1F5F9; color: #475569; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-size: 13px; border: 1px solid #E2E8F0; display: inline-flex; align-items: center; gap: 6px;">
                      <i class="fas fa-eye"></i> View
                    </a>
                    <a href="../uploads/<?php echo htmlspecialchars($file); ?>" download style="background: #1B5C3B; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                      <i class="fas fa-download"></i> Download
                    </a>
                  </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      
      <?php if ($app['clerk_notes']): ?>
        <div class="feedback-box">
          <strong><i class="fas fa-sticky-note"></i> Clerk Notes:</strong>
          <p style="margin-top: 5px;"><?php echo htmlspecialchars($app['clerk_notes']); ?></p>
        </div>
      <?php endif; ?>
      
      <?php if ($app['director_feedback']): ?>
        <div class="feedback-box <?php echo $app['status'] === 'rejected' ? 'rejected' : ''; ?>">
          <strong><i class="fas fa-comments"></i> Director Feedback:</strong>
          <p style="margin-top: 5px;"><?php echo htmlspecialchars($app['director_feedback']); ?></p>
        </div>
      <?php endif; ?>
      
      <?php if ($app['status'] === 'approved' && !empty($app['cert_id'])): ?>
        <div style="margin-top: 20px; padding: 20px; background: linear-gradient(135deg, #DAA520 0%, #FFD700 100%); border-radius: 8px; text-align: center;">
          <h3 style="color: white; margin-bottom: 15px; font-size: 18px;">
            <i class="fas fa-certificate"></i> Certificate Available
          </h3>
          <p style="color: rgba(255,255,255,0.9); margin-bottom: 15px; font-size: 14px;">
            Your IP application has been approved and a certificate has been generated.
          </p>
          <a href="../certificate/generate.php?id=<?php echo $app['cert_id']; ?>" target="_blank" style="display: inline-block; background: white; color: #DAA520; padding: 12px 24px; border-radius: 5px; text-decoration: none; font-weight: 600; font-size: 14px; transition: transform 0.2s;">
            <i class="fas fa-certificate"></i> View Certificate
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
