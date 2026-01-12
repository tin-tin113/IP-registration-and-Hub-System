<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';
require_once '../config/form_fields_helper.php';

requireRole(['clerk', 'director']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_office_visit') {
  $app_id = $_POST['app_id'] ?? null;
  $clerk_notes = trim($_POST['clerk_notes'] ?? '');
  
  if ($app_id) {
    $office_visit_date = date('Y-m-d H:i:s', strtotime('+3 days'));
    
    $stmt = $conn->prepare("UPDATE ip_applications SET status='office_visit', clerk_notes=?, office_visit_date=? WHERE id=?");
    $stmt->bind_param("ssi", $clerk_notes, $office_visit_date, $app_id);
    
    if ($stmt->execute()) {
      // Fetch application details for audit log
      $app_query = $conn->query("SELECT a.title, u.full_name FROM ip_applications a JOIN users u ON a.user_id=u.id WHERE a.id='$app_id'");
      $app_data = $app_query->fetch_assoc();
      
      $log_details = json_encode([
        'Action' => 'Documents Verified / Office Visit Approved',
        'Application Title' => $app_data['title'],
        'Applicant' => $app_data['full_name']
      ]);

      auditLog('Approve Office Visit', 'Application', $app_id, null, $log_details);
      $success = 'Application approved for office visit. User notified to make payment at CHMSU Cashier Office.';
    }
    
    $stmt->close();
  }
}

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

$result = $conn->query("SELECT a.*, u.full_name, u.email, 
  p.first_name, p.middle_name, p.last_name, p.suffix, p.birthdate, p.gender, 
  p.nationality, p.employment_status, p.employee_id, p.college, p.contact_number,
  p.address_street, p.address_barangay, p.address_city, p.address_province, p.address_postal, p.is_complete as profile_complete
  FROM ip_applications a 
  JOIN users u ON a.user_id=u.id 
  LEFT JOIN user_profiles p ON u.id=p.user_id
  WHERE a.status='submitted' ORDER BY a.created_at ASC");
$applications = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Review Applications - CHMSU IP System</title>
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
      margin-top: 0;
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
    
    .page-header p {
      opacity: 0.9;
      font-size: 14px;
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
      transition: all 0.3s;
    }
    
    .app-card:hover {
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    }
    
    .app-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid #E2E8F0;
    }
    
    .app-title h3 {
      color: #1E293B;
      margin-bottom: 8px;
      font-size: 18px;
    }
    
    .app-meta {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 12px;
      font-size: 13px;
      color: #64748B;
    }
    
    .meta-item {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .app-abstract {
      background: #F8FAFC;
      padding: 16px;
      border-radius: 12px;
      font-size: 14px;
      color: #475569;
      line-height: 1.6;
      margin-bottom: 20px;
      border-left: 4px solid #DAA520;
      overflow-wrap: break-word;
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
    
    .empty-state p {
      color: #64748B;
      font-size: 16px;
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
      <h1><i class="fas fa-check-square" style="margin-right: 12px;"></i>Review Applications</h1>
      <p>Submitted applications awaiting clerk verification</p>
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
        <p>No submitted applications to review</p>
      </div>
    <?php else: ?>
      <?php foreach ($applications as $app): ?>
        <div class="app-card">
          <div class="app-header">
            <div class="app-title">
              <h3><?php echo htmlspecialchars($app['title']); ?></h3>
              <div class="app-meta">
                <div class="meta-item"><i class="fas fa-user"></i> <?php echo htmlspecialchars($app['full_name']); ?></div>
                <div class="meta-item"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($app['email']); ?></div>
                <div class="meta-item"><i class="fas fa-tag"></i> <?php echo $app['ip_type']; ?></div>
                <div class="meta-item"><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($app['created_at'])); ?></div>
              </div>
            </div>
          </div>
          
          <div class="app-abstract">
            <strong>Inventor(s):</strong><br>
            <?php echo nl2br(htmlspecialchars($app['inventor_name'] ?? '')); ?>
            <br><br>
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
            
            <?php
            // Get active form fields for dynamic display
            $activeFields = getActiveFormFields($conn);
            $builtinFieldNames = ['first_name', 'middle_name', 'last_name', 'suffix', 'address_street', 'address_barangay', 'address_city', 'address_province', 'address_postal'];
            $customFields = !empty($app['custom_fields']) ? json_decode($app['custom_fields'], true) : [];
            ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; font-size: 13px;">
              <!-- Full Name (combined) -->
              <div><strong>Full Name:</strong> <?php echo htmlspecialchars(trim($app['first_name'] . ' ' . ($app['middle_name'] ? $app['middle_name'][0] . '. ' : '') . $app['last_name'] . ' ' . $app['suffix'])); ?></div>
              
              <?php foreach ($activeFields as $field): 
                // Skip name fields (combined above) and address fields (shown separately)
                if (in_array($field['field_name'], $builtinFieldNames)) continue;
                
                // Get value
                $value = '';
                if ($field['is_builtin']) {
                  $value = $app[$field['field_name']] ?? '';
                } else {
                  $value = $customFields[$field['field_name']] ?? '';
                }
                
                // Format special fields
                if ($field['field_name'] === 'birthdate' && $value) {
                  $value = date('M d, Y', strtotime($value));
                }
              ?>
              <div>
                <strong><?php echo htmlspecialchars($field['field_label']); ?>:</strong> 
                <?php echo htmlspecialchars($value ?: 'N/A'); ?>
              </div>
              <?php endforeach; ?>
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
          

          
          <form method="POST" id="approveForm_<?php echo $app['id']; ?>">
            <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
            <input type="hidden" name="action" value="approve_office_visit">
            
            <div class="form-group">
              <label for="clerk_notes_<?php echo $app['id']; ?>">Verification Notes (Optional)</label>
              <textarea id="clerk_notes_<?php echo $app['id']; ?>" name="clerk_notes" placeholder="Enter any verification notes for the applicant..."></textarea>
            </div>
            
            <div class="actions">
              <button type="button" class="btn-approve" onclick="showApproveModal(<?php echo $app['id']; ?>)">
                <i class="fas fa-check"></i> Approve for Office Visit
              </button>
              <button type="button" class="btn-reject" onclick="document.getElementById('rejectForm_<?php echo $app['id']; ?>').style.display='block'">
                <i class="fas fa-times"></i> Reject Application
              </button>
            </div>
          </form>
          
          <div id="rejectForm_<?php echo $app['id']; ?>" style="display:none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #E2E8F0;">
            <form method="POST">
              <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
              <div class="form-group">
                <label for="reject_reason_<?php echo $app['id']; ?>">Rejection Reason *</label>
                <textarea id="reject_reason_<?php echo $app['id']; ?>" name="rejection_reason" placeholder="Explain why this application is being rejected..." required></textarea>
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

  <!-- Confirmation Modal -->
  <div class="modal-overlay" id="approveModal">
    <div class="modal-content">
      <div class="modal-icon">
        <i class="fas fa-check-circle"></i>
      </div>
      <h2 style="margin-bottom: 10px; color: #1E293B;">Confirm Approval</h2>
      <p style="color: #64748B; margin-bottom: 20px;">Are you sure you want to approve this application for Office Visit?</p>
      
      <div class="modal-actions">
        <button type="button" style="background: #E2E8F0; color: #475569;" onclick="closeModal()">
          Cancel
        </button>
        <button type="button" class="btn-approve" onclick="confirmApprove()">
          Yes, Approve
        </button>
      </div>
    </div>
  </div>

  <script>
    let currentAppId = null;

    function showApproveModal(appId) {
      currentAppId = appId;
      document.getElementById('approveModal').style.display = 'flex';
    }

    function closeModal() {
      document.getElementById('approveModal').style.display = 'none';
      currentAppId = null;
    }

    function confirmApprove() {
      if (currentAppId) {
        document.getElementById('approveForm_' + currentAppId).submit();
      }
    }

    // Close modal when clicking outside
    document.getElementById('approveModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeModal();
      }
    });
  </script

</body>
</html>
