<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('director');

$success = '';
$error = '';

// Generate unique certificate and reference numbers
function generateCertificateNumber() {
  $year = date('Y');
  $random = str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
  return "CHMSU-CERT-$year-$random";
}

function generateReferenceNumber() {
  $year = date('Y');
  $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
  return "CHMSU-IP-$year-$random";
}

// Approve application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
  $app_id = $_POST['app_id'] ?? null;
  $director_feedback = trim($_POST['director_feedback'] ?? '');
  
  if ($app_id) {
    $certificate_number = generateCertificateNumber();
    $reference_number = generateReferenceNumber();
    $approved_at = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("UPDATE ip_applications SET status='approved', certificate_id=?, reference_number=?, director_feedback=?, approved_at=? WHERE id=?");
    $stmt->bind_param("ssssi", $certificate_number, $reference_number, $director_feedback, $approved_at, $app_id);
    
    if ($stmt->execute()) {
      // Create certificate entry
      $stmt2 = $conn->prepare("INSERT INTO certificates (application_id, certificate_number, reference_number) VALUES (?, ?, ?)");
      $stmt2->bind_param("iss", $app_id, $certificate_number, $reference_number);
      $stmt2->execute();
      $stmt2->close();
      
      auditLog('Approve Application', 'Application', $app_id);
      $success = 'Application approved! Certificate generated.';
    } else {
      $error = 'Failed to approve application';
    }
    
    $stmt->close();
  }
}

// Reject application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject') {
  $app_id = $_POST['app_id'] ?? null;
  $rejection_reason = trim($_POST['rejection_reason'] ?? '');
  
  if ($app_id && !empty($rejection_reason)) {
    $rejected_at = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("UPDATE ip_applications SET status='rejected', rejection_reason=?, rejected_at=? WHERE id=?");
    $stmt->bind_param("ssi", $rejection_reason, $rejected_at, $app_id);
    
    if ($stmt->execute()) {
      auditLog('Reject Application', 'Application', $app_id);
      $success = 'Application rejected.';
    }
    
    $stmt->close();
  }
}

// Get applications ready for approval with full user information
$result = $conn->query("SELECT a.*, u.full_name, u.email, u.department, u.contact_number, u.innovation_points, u.created_at as user_created_at FROM ip_applications a JOIN users u ON a.user_id=u.id WHERE a.status='payment_verified' ORDER BY a.created_at ASC");
$applications = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Approve Applications - CHMSU IP System</title>
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
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid #f0f0f0;
    }
    
    .app-info h3 {
      color: #333;
      margin-bottom: 10px;
    }
    
    .app-meta {
      font-size: 13px;
      color: #666;
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
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
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-top: 15px;
    }
    
    button {
      padding: 12px;
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
    }
    
    .btn-approve:hover {
      background: #45a049;
      transform: translateY(-2px);
    }
    
    .btn-reject {
      background: #f44336;
      color: white;
    }
    
    .btn-reject:hover {
      background: #da190b;
      transform: translateY(-2px);
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
    
    .info-section {
      background: #f8f9fa;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 15px;
      border-left: 4px solid #667eea;
    }
    
    .info-section h4 {
      color: #333;
      margin-bottom: 12px;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 12px;
      font-size: 13px;
    }
    
    .info-item {
      display: flex;
      flex-direction: column;
      gap: 3px;
    }
    
    .info-label {
      color: #666;
      font-weight: 600;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .info-value {
      color: #333;
      font-weight: 500;
    }
    
    .documents-section {
      background: #fff;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 15px;
    }
    
    .documents-section h4 {
      color: #333;
      margin-bottom: 12px;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .document-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    
    .document-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px;
      background: #f8f9fa;
      border-radius: 5px;
      border: 1px solid #e0e0e0;
    }
    
    .document-item a {
      color: #667eea;
      text-decoration: none;
      font-weight: 600;
      font-size: 13px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .document-item a:hover {
      text-decoration: underline;
    }
    
    .toggle-section {
      background: #e3f2fd;
      padding: 10px;
      border-radius: 5px;
      cursor: pointer;
      margin-bottom: 15px;
      font-size: 13px;
      font-weight: 600;
      color: #1976d2;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s;
    }
    
    .toggle-section:hover {
      background: #bbdefb;
    }
    
    .collapsible-content {
      display: none;
    }
    
    .collapsible-content.show {
      display: block;
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
    
    <div class="header">
      <h1><i class="fas fa-thumbs-up"></i> Approve Applications for Registration</h1>
      <p style="color: #666; font-size: 13px; margin-top: 5px;">Review and approve applications with payment verified</p>
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
        <p>No applications ready for approval</p>
      </div>
    <?php else: ?>
      <?php foreach ($applications as $app): 
        // Parse document files
        $document_files = [];
        if (!empty($app['document_file'])) {
          $decoded = json_decode($app['document_file'], true);
          if (is_array($decoded)) {
            $document_files = $decoded;
          } else {
            $document_files = [$app['document_file']];
          }
        }
        
        // Parse supporting documents if exists
        $supporting_docs = [];
        if (!empty($app['supporting_documents'])) {
          $decoded = json_decode($app['supporting_documents'], true);
          if (is_array($decoded)) {
            $supporting_docs = $decoded;
          } else {
            $supporting_docs = [$app['supporting_documents']];
          }
        }
      ?>
        <div class="app-card">
          <div class="app-info">
            <h3><?php echo htmlspecialchars($app['title']); ?></h3>
            <div class="app-meta">
              <span><i class="fas fa-user"></i> <strong>Applicant:</strong> <?php echo htmlspecialchars($app['full_name']); ?></span>
              <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($app['email']); ?></span>
              <span><i class="fas fa-tag"></i> <strong>Type:</strong> <?php echo $app['ip_type']; ?></span>
              <span><i class="fas fa-calendar"></i> <strong>Submitted:</strong> <?php echo date('M d, Y', strtotime($app['created_at'])); ?></span>
            </div>
          </div>
          
          <!-- Personal Information Section -->
          <div class="toggle-section" onclick="toggleSection('personal_<?php echo $app['id']; ?>')">
            <i class="fas fa-chevron-down" id="icon_personal_<?php echo $app['id']; ?>"></i>
            <span>View Applicant Personal Information</span>
          </div>
          <div class="collapsible-content" id="personal_<?php echo $app['id']; ?>">
            <div class="info-section">
              <h4><i class="fas fa-user-circle"></i> Applicant Details</h4>
              <div class="info-grid">
                <div class="info-item">
                  <span class="info-label">Full Name</span>
                  <span class="info-value"><?php echo htmlspecialchars($app['full_name']); ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Email Address</span>
                  <span class="info-value"><?php echo htmlspecialchars($app['email']); ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Department</span>
                  <span class="info-value"><?php echo htmlspecialchars($app['department'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Contact Number</span>
                  <span class="info-value"><?php echo htmlspecialchars($app['contact_number'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Innovation Points</span>
                  <span class="info-value"><?php echo $app['innovation_points'] ?? 0; ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Member Since</span>
                  <span class="info-value"><?php echo date('M d, Y', strtotime($app['user_created_at'])); ?></span>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Documents Section -->
          <div class="toggle-section" onclick="toggleSection('documents_<?php echo $app['id']; ?>')">
            <i class="fas fa-chevron-down" id="icon_documents_<?php echo $app['id']; ?>"></i>
            <span>View All Related Documents</span>
          </div>
          <div class="collapsible-content" id="documents_<?php echo $app['id']; ?>">
            <div class="documents-section">
              <h4><i class="fas fa-file-alt"></i> Application Documents</h4>
              <?php if (count($document_files) > 0): ?>
                <div class="document-list">
                  <?php foreach ($document_files as $doc): ?>
                    <div class="document-item">
                      <a href="../uploads/<?php echo htmlspecialchars($doc); ?>" target="_blank">
                        <i class="fas fa-file-pdf"></i>
                        <?php echo htmlspecialchars($doc); ?>
                      </a>
                      <a href="../uploads/<?php echo htmlspecialchars($doc); ?>" download style="font-size: 11px;">
                        <i class="fas fa-download"></i> Download
                      </a>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p style="color: #999; font-size: 13px;">No documents uploaded</p>
              <?php endif; ?>
              
              <?php if (!empty($app['payment_receipt'])): ?>
                <h4 style="margin-top: 20px;"><i class="fas fa-receipt"></i> Payment Receipt</h4>
                <div class="document-item">
                  <a href="../uploads/<?php echo htmlspecialchars($app['payment_receipt']); ?>" target="_blank">
                    <i class="fas fa-file-image"></i>
                    <?php echo htmlspecialchars($app['payment_receipt']); ?>
                  </a>
                  <a href="../uploads/<?php echo htmlspecialchars($app['payment_receipt']); ?>" download style="font-size: 11px;">
                    <i class="fas fa-download"></i> Download
                  </a>
                </div>
              <?php endif; ?>
              
              <?php if (count($supporting_docs) > 0): ?>
                <h4 style="margin-top: 20px;"><i class="fas fa-folder-open"></i> Supporting Documents</h4>
                <div class="document-list">
                  <?php foreach ($supporting_docs as $doc): ?>
                    <div class="document-item">
                      <a href="../uploads/<?php echo htmlspecialchars($doc); ?>" target="_blank">
                        <i class="fas fa-file"></i>
                        <?php echo htmlspecialchars($doc); ?>
                      </a>
                      <a href="../uploads/<?php echo htmlspecialchars($doc); ?>" download style="font-size: 11px;">
                        <i class="fas fa-download"></i> Download
                      </a>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
          
          <div class="app-description">
            <strong>Description:</strong><br>
            <?php echo htmlspecialchars($app['description']); ?>
          </div>
          
          <?php if ($app['clerk_notes']): ?>
            <div style="background: #e8f5e9; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 12px; border-left: 3px solid #4caf50;">
              <strong><i class="fas fa-sticky-note"></i> Clerk Notes:</strong><br>
              <?php echo htmlspecialchars($app['clerk_notes']); ?>
            </div>
          <?php endif; ?>
          
          <?php if ($app['payment_date']): ?>
            <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 12px; border-left: 3px solid #ffc107;">
              <strong><i class="fas fa-money-check-alt"></i> Payment Information:</strong><br>
              Date: <?php echo date('M d, Y', strtotime($app['payment_date'])); ?><br>
              Amount: ₱<?php echo number_format($app['payment_amount'] ?? 0, 2); ?>
            </div>
          <?php endif; ?>
          
          <form method="POST">
            <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
            
            <div class="form-group">
              <label for="feedback_<?php echo $app['id']; ?>">Director Comments (Optional)</label>
              <textarea id="feedback_<?php echo $app['id']; ?>" name="director_feedback" placeholder="Add any comments or notes about this IP registration..."></textarea>
            </div>
            
            <div class="actions">
              <button type="submit" name="action" value="approve" class="btn-approve">
                <i class="fas fa-check"></i> Approve & Generate Certificate
              </button>
              <button type="button" class="btn-reject" onclick="document.getElementById('rejectForm_<?php echo $app['id']; ?>').style.display='block'">
                <i class="fas fa-times"></i> Reject
              </button>
            </div>
          </form>
          
          <div id="rejectForm_<?php echo $app['id']; ?>" style="display:none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #f0f0f0;">
            <form method="POST">
              <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
              <div class="form-group">
                <label for="reject_reason_<?php echo $app['id']; ?>">Rejection Reason *</label>
                <textarea id="reject_reason_<?php echo $app['id']; ?>" name="rejection_reason" placeholder="Explain why this application is being rejected..." required></textarea>
              </div>
              <div style="display: flex; gap: 10px;">
                <button type="submit" name="action" value="reject" class="btn-reject" style="flex: 1;">
                  <i class="fas fa-times"></i> Confirm Rejection
                </button>
                <button type="button" onclick="document.getElementById('rejectForm_<?php echo $app['id']; ?>').style.display='none'" style="flex: 1; background: #ccc; color: #333;">
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  
  <script>
    function toggleSection(id) {
      const content = document.getElementById(id);
      const icon = document.getElementById('icon_' + id);
      
      if (content.classList.contains('show')) {
        content.classList.remove('show');
        icon.style.transform = 'rotate(0deg)';
      } else {
        content.classList.add('show');
        icon.style.transform = 'rotate(180deg)';
      }
    }
  </script>
</body>
</html>
