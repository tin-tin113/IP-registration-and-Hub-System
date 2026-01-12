<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('director');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
  $app_id = $_POST['app_id'] ?? null;
  $director_feedback = trim($_POST['director_feedback'] ?? '');
  
  if ($app_id) {
    $certificate_number = "CHMSU-CERT-" . date('Y') . "-" . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    $reference_number = "CHMSU-IP-" . date('Y') . "-" . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $approved_at = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("UPDATE ip_applications SET status='approved', certificate_id=?, reference_number=?, director_feedback=?, approved_at=?, publish_permission='pending', publish_permission_date=? WHERE id=?");
    $stmt->bind_param("sssssi", $certificate_number, $reference_number, $director_feedback, $approved_at, $approved_at, $app_id);
    
    if ($stmt->execute()) {
      // Use ON DUPLICATE KEY UPDATE to prevent fatal errors if certificate already exists
      $stmt2 = $conn->prepare("INSERT INTO certificates (application_id, certificate_number, reference_number) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE certificate_number=VALUES(certificate_number), reference_number=VALUES(reference_number)");
      $stmt2->bind_param("iss", $app_id, $certificate_number, $reference_number);
      $stmt2->execute();
      $stmt2->close();
      // Fetch application details for audit log
      $app_query = $conn->query("SELECT a.title, a.inventor_name, u.full_name FROM ip_applications a JOIN users u ON a.user_id=u.id WHERE a.id='$app_id'");
      $app_data = $app_query->fetch_assoc();
      
      $log_details = json_encode([
        'Action' => 'Approved & Generated Certificate',
        'Application Title' => $app_data['title'],
        'Applicant' => $app_data['full_name'],
        'Inventors' => $app_data['inventor_name'],
        'Certificate No' => $certificate_number
      ]);
      
      auditLog('Approve Application', 'Application', $app_id, null, $log_details);
      $success = 'Application approved! Certificate generated.';
    } else {
      $error = 'Failed to approve application';
    }
    
    $stmt->close();
  }
}

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

$result = $conn->query("SELECT a.*, u.full_name, u.email, u.department, u.contact_number,
  p.first_name, p.middle_name, p.last_name, p.suffix, p.birthdate, p.gender, 
  p.nationality, p.employment_status, p.employee_id, p.college, p.contact_number as profile_contact,
  p.address_street, p.address_barangay, p.address_city, p.address_province, p.address_postal, p.is_complete as profile_complete
  FROM ip_applications a 
  JOIN users u ON a.user_id=u.id 
  LEFT JOIN user_profiles p ON u.id=p.user_id
  WHERE a.status='payment_verified' ORDER BY a.created_at ASC");
$applications = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Approve Applications - CHMSU IP System</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: #F8FAFC;
      color: #1E293B;
    }
    
    .container {
      margin-left: 0px;
      padding: 32px;
      max-width: 1400px;
    }
    
    .page-header {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      padding: 32px;
      border-radius: 16px;
      margin-bottom: 32px;
      box-shadow: 0 4px 16px rgba(10, 77, 46, 0.2);
    }
    
    .page-header h1 {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 4px;
    }
    
    .alert {
      padding: 16px;
      border-radius: 12px;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 12px;
      border-left: 4px solid;
    }
    
    .alert-success {
      background: #D1FAE5;
      color: #065F46;
      border-left-color: #10B981;
    }
    
    .alert-danger {
      background: #FEE2E2;
      color: #7F1D1D;
      border-left-color: #EF4444;
    }
    
    .app-card {
      background: white;
      border-radius: 16px;
      padding: 28px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
      border: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .app-info {
      margin-bottom: 24px;
      padding-bottom: 20px;
      border-bottom: 1px solid #E2E8F0;
    }
    
    .app-info h3 {
      color: #1E293B;
      margin-bottom: 12px;
      font-size: 18px;
    }
    
    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 16px;
      font-size: 13px;
    }
    
    .info-item {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    
    .info-label {
      color: #64748B;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-size: 11px;
    }
    
    .info-value {
      color: #1E293B;
      font-weight: 500;
    }
    
    .app-abstract {
      background: #F8FAFC;
      padding: 16px;
      border-radius: 12px;
      font-size: 14px;
      color: #475569;
      line-height: 1.6;
      margin-bottom: 24px;
      border-left: 4px solid #DAA520;
    }
    
    .form-group {
      margin-bottom: 16px;
    }
    
    label {
      display: block;
      margin-bottom: 6px;
      color: #1E293B;
      font-weight: 600;
      font-size: 13px;
    }
    
    textarea {
      width: 100%;
      padding: 12px;
      border: 1px solid #E2E8F0;
      border-radius: 8px;
      font-size: 14px;
      font-family: inherit;
      min-height: 100px;
      resize: vertical;
    }
    
    textarea:focus {
      outline: none;
      border-color: #0A4D2E;
      box-shadow: 0 0 0 3px rgba(10, 77, 46, 0.1);
    }
    
    .actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-top: 20px;
    }
    
    button {
      padding: 12px 20px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    
    .btn-approve {
      background: linear-gradient(135deg, #10B981 0%, #059669 100%);
      color: white;
    }
    
    .btn-approve:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .btn-reject {
      background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
      color: white;
    }
    
    .btn-reject:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }
    
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      background: white;
      border-radius: 16px;
    }
    
    .empty-state i {
      font-size: 56px;
      color: #CBD5E1;
      margin-bottom: 16px;
    }
    
    /* Document styles */
    .documents-section {
      margin-bottom: 20px;
    }
    
    .documents-title {
      font-size: 14px;
      font-weight: 600;
      color: #1E293B;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .document-card {
      background: #F8FAFC;
      border: 2px solid #E2E8F0;
      border-radius: 10px;
      padding: 14px;
      margin-bottom: 10px;
      transition: all 0.2s;
    }
    
    .document-card:hover {
      border-color: #1B7F4D;
      background: white;
    }
    
    .document-info {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
    }
    
    .document-name {
      font-weight: 600;
      color: #0A4D2E;
      font-size: 13px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .document-size {
      font-size: 12px;
      color: #64748B;
    }
    
    .document-actions {
      display: flex;
      gap: 8px;
    }
    
    .btn-view, .btn-download {
      padding: 8px 14px;
      border-radius: 6px;
      text-decoration: none;
      font-size: 12px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s;
    }
    
    .btn-view {
      background: #F1F5F9;
      color: #475569;
      border: 1px solid #E2E8F0;
    }
    
    .btn-view:hover {
      background: #E2E8F0;
      color: #0A4D2E;
    }
    
    .btn-download {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      border: none;
    }
    
    .btn-download:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(10, 77, 46, 0.3);
    }
    
    @media (max-width: 1024px) {
      .container {
        margin-left: 240px;
        padding: 24px;
      }
      .info-grid {
        grid-template-columns: 1fr;
      }
      .actions {
        grid-template-columns: 1fr;
      }
    }
    
    @media (max-width: 768px) {
      .container {
        margin-left: 0;
        padding: 16px;
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
    <!-- Page Header -->
    <div class="page-header">
      <h1><i class="fas fa-thumbs-up" style="margin-right: 12px;"></i>Approve Applications</h1>
      <p>Final director approval for payment-verified applications</p>
    </div>
    
    <!-- Alerts -->
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
    
    <!-- Applications -->
    <?php if (count($applications) === 0): ?>
      <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <p>No applications ready for approval</p>
      </div>
    <?php else: ?>
      <?php foreach ($applications as $app): ?>
        <div class="app-card">
          <div class="app-info">
            <h3><?php echo htmlspecialchars($app['title']); ?></h3>
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">Applicant</span>
                <span class="info-value"><?php echo htmlspecialchars($app['full_name']); ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">Inventor(s)</span>
                <span class="info-value"><?php echo nl2br(htmlspecialchars($app['inventor_name'] ?? '')); ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">Email</span>
                <span class="info-value"><?php echo htmlspecialchars($app['email']); ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">Department</span>
                <span class="info-value"><?php echo htmlspecialchars($app['department'] ?? 'N/A'); ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">IP Type</span>
                <span class="info-value"><?php echo $app['ip_type']; ?></span>
              </div>
            </div>
          </div>
          
          <div class="app-abstract">
            <strong>Abstract:</strong><br>
            <?php echo htmlspecialchars($app['abstract']); ?>
          </div>
          
          <!-- Applicant Verification Details -->
          <?php if (!empty($app['first_name']) || !empty($app['last_name'])): ?>
          <div style="background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%); border: 1px solid #81C784; border-radius: 10px; padding: 20px; margin-bottom: 20px;">
            <h4 style="color: #2E7D32; margin-bottom: 15px; font-size: 14px;">
              <i class="fas fa-user-check"></i> Applicant Verification Info
              <?php if ($app['profile_complete']): ?>
                <span style="background: #4CAF50; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 8px;">Verified</span>
              <?php endif; ?>
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; font-size: 13px;">
              <div><strong>Full Name:</strong> <?php echo htmlspecialchars(trim($app['first_name'] . ' ' . ($app['middle_name'] ? $app['middle_name'][0] . '. ' : '') . $app['last_name'] . ' ' . $app['suffix'])); ?></div>
              <div><strong>Employee/Student ID:</strong> <?php echo htmlspecialchars($app['employee_id'] ?? 'N/A'); ?></div>
              <div><strong>College:</strong> <?php echo htmlspecialchars($app['college'] ?? 'N/A'); ?></div>
              <div><strong>Employment Status:</strong> <?php echo htmlspecialchars($app['employment_status'] ?? 'N/A'); ?></div>
              <div><strong>Contact:</strong> <?php echo htmlspecialchars($app['profile_contact'] ?? 'N/A'); ?></div>
              <div><strong>Birthdate:</strong> <?php echo $app['birthdate'] ? date('M d, Y', strtotime($app['birthdate'])) : 'N/A'; ?></div>
              <div><strong>Gender:</strong> <?php echo htmlspecialchars($app['gender'] ?? 'N/A'); ?></div>
              <div><strong>Nationality:</strong> <?php echo htmlspecialchars($app['nationality'] ?? 'N/A'); ?></div>
            </div>
            <?php if (!empty($app['address_city']) || !empty($app['address_province'])): ?>
            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #A5D6A7; font-size: 13px;">
              <strong><i class="fas fa-map-marker-alt"></i> Address:</strong> 
              <?php 
                $address_parts = array_filter([
                  $app['address_street'], 
                  $app['address_barangay'], 
                  $app['address_city'], 
                  $app['address_province'],
                  $app['address_postal']
                ]);
                echo htmlspecialchars(implode(', ', $address_parts) ?: 'N/A');
              ?>
            </div>
            <?php endif; ?>
          </div>
          <?php else: ?>
          <div style="background: #FFF3E0; border: 1px solid #FFB74D; border-radius: 10px; padding: 15px; margin-bottom: 20px; font-size: 13px; color: #E65100;">
            <i class="fas fa-exclamation-triangle"></i> <strong>Profile Not Complete:</strong> Applicant has not submitted verification information yet.
          </div>
          <?php endif; ?>
          
          <?php
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
          ?>
          
          <?php if (!empty($document_files)): ?>
          <div class="documents-section">
            <div class="documents-title">
              <i class="fas fa-folder-open"></i> Attached Documents
            </div>
            <?php foreach ($document_files as $file): ?>
              <?php 
                $file_path = UPLOAD_DIR . $file;
                $file_exists = file_exists($file_path);
                $file_size = $file_exists ? filesize($file_path) : 0;
                $file_size_kb = round($file_size / 1024, 1);
                $file_ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
              ?>
              <div class="document-card">
                <div class="document-info">
                  <div>
                    <div class="document-name">
                      <i class="fas fa-file-<?php echo $file_ext === 'pdf' ? 'pdf' : 'alt'; ?>"></i>
                      <?php echo htmlspecialchars(basename($file)); ?>
                    </div>
                    <div class="document-size">
                      <?php echo $file_exists ? $file_size_kb . ' KB' : 'File not found'; ?>
                    </div>
                  </div>
                  <?php if ($file_exists): ?>
                  <div class="document-actions">
                    <a href="../uploads/<?php echo htmlspecialchars($file); ?>" target="_blank" class="btn-view">
                      <i class="fas fa-eye"></i> View
                    </a>
                    
                    <a href="../uploads/<?php echo htmlspecialchars($file); ?>" download class="btn-download">
                      <i class="fas fa-download"></i> Download
                    </a>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          

          
          <form method="POST">
            <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
            
            <div class="form-group">
              <label for="feedback_<?php echo $app['id']; ?>">Director Comments (Optional)</label>
              <textarea id="feedback_<?php echo $app['id']; ?>" name="director_feedback" placeholder="Add any comments about this IP registration..."></textarea>
            </div>
            
            <div class="actions">
              <button type="button" class="btn-approve" onclick="document.getElementById('approveModal_<?php echo $app['id']; ?>').style.display='flex'">
                <i class="fas fa-check"></i> Approve & Generate Certificate
              </button>
              <button type="button" class="btn-reject" onclick="document.getElementById('rejectForm_<?php echo $app['id']; ?>').style.display='block'">
                <i class="fas fa-times"></i> Reject
              </button>
            </div>
            
            <!-- Approval Confirmation Modal -->
            <div id="approveModal_<?php echo $app['id']; ?>" class="modal-overlay">
              <div class="modal-content">
                <div class="modal-icon">
                  <i class="fas fa-check-circle"></i>
                </div>
                <h2 style="margin-bottom: 12px; color: #1E293B;">Confirm Approval</h2>
                <p style="color: #64748B; margin-bottom: 8px;">Are you sure you want to approve <strong><?php echo htmlspecialchars($app['title']); ?></strong>?</p>
                <p style="font-size: 13px; color: #10B981; background: #ECFDF5; padding: 8px; border-radius: 6px; display: inline-block;">
                  <i class="fas fa-certificate"></i> This will generate an official certificate.
                </p>
                
                <div class="modal-actions">
                  <button type="submit" name="action" value="approve" class="btn-approve" style="width: auto; padding: 12px 24px;">
                    Confirm Approval
                  </button>
                  <button type="button" onclick="document.getElementById('approveModal_<?php echo $app['id']; ?>').style.display='none'" style="background: #E2E8F0; color: #475569; width: auto; padding: 12px 24px;">
                    Cancel
                  </button>
                </div>
              </div>
            </div>
          </form>
          
          <div id="rejectForm_<?php echo $app['id']; ?>" style="display:none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #E2E8F0;">
            <form method="POST">
              <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
              <div class="form-group">
                <label for="reject_reason_<?php echo $app['id']; ?>">Rejection Reason *</label>
                <textarea id="reject_reason_<?php echo $app['id']; ?>" name="rejection_reason" placeholder="Explain why..." required></textarea>
              </div>
              <div class="actions">
                <button type="submit" name="action" value="reject" class="btn-reject">
                  <i class="fas fa-times"></i> Confirm Rejection
                </button>
                <button type="button" style="background: #94A3B8; color: white;" onclick="document.getElementById('rejectForm_<?php echo $app['id']; ?>').style.display='none'">
                  Cancel
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
