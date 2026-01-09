<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';
require_once '../config/badge-auto-award.php';

$work_id = intval($_GET['id'] ?? 0);
$viewer_id = isLoggedIn() ? getCurrentUserId() : null;

if (!$work_id) {
  header("Location: browse.php");
  exit;
}

// Get IP work details (without view count - will be calculated after tracking)
$stmt = $conn->prepare("
  SELECT a.*, u.full_name, u.email, u.department, u.id as owner_id
  FROM ip_applications a 
  JOIN users u ON a.user_id = u.id 
  WHERE a.id = ? AND a.status = 'approved' AND a.publish_permission = 'granted'
");
$stmt->bind_param("i", $work_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  header("Location: browse.php?error=Work not found");
  exit;
}

$work = $result->fetch_assoc();
$stmt->close();

// Get initial view count
$view_count_stmt = $conn->prepare("
  SELECT COUNT(DISTINCT v.id) as view_count
  FROM view_tracking v
  WHERE v.application_id = ?
");
$view_count_stmt->bind_param("i", $work_id);
$view_count_stmt->execute();
$view_count_result = $view_count_stmt->get_result();
$work['view_count'] = intval($view_count_result->fetch_assoc()['view_count'] ?? 0);
$view_count_stmt->close();

// Track view - allow tracking even if viewer is owner (for analytics)
// But only count unique views per user/IP combination
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($viewer_id) {
  // Logged in user - check by viewer_id
  $check = $conn->prepare("SELECT id FROM view_tracking WHERE application_id = ? AND viewer_id = ?");
  $check->bind_param("ii", $work_id, $viewer_id);
  $check->execute();
  
  if ($check->get_result()->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO view_tracking (application_id, viewer_id, ip_address) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $work_id, $viewer_id, $ip_address);
    $stmt->execute();
    $stmt->close();
    
    // Award badges and points if threshold is met (per application)
    checkAndAwardBadges($work_id);
    
    // Refresh view count for display
    $refresh_stmt = $conn->prepare("
      SELECT COUNT(DISTINCT v.id) as view_count
      FROM view_tracking v
      WHERE v.application_id = ?
    ");
    $refresh_stmt->bind_param("i", $work_id);
    $refresh_stmt->execute();
    $refresh_result = $refresh_stmt->get_result();
    $work['view_count'] = $refresh_result->fetch_assoc()['view_count'];
    $refresh_stmt->close();
    
    auditLog('View IP Work', 'Application', $work_id);
  }
  $check->close();
} else {
  // Anonymous user - check by IP address (only count once per IP per work)
  $check = $conn->prepare("SELECT id FROM view_tracking WHERE application_id = ? AND viewer_id IS NULL AND ip_address = ?");
  $check->bind_param("is", $work_id, $ip_address);
  $check->execute();
  
  if ($check->get_result()->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO view_tracking (application_id, viewer_id, ip_address) VALUES (?, NULL, ?)");
    $stmt->bind_param("is", $work_id, $ip_address);
    $stmt->execute();
    $stmt->close();
    
    // Award badges and points if threshold is met (per application, even for anonymous views)
    checkAndAwardBadges($work_id);
    
    // Refresh view count for display
    $refresh_stmt = $conn->prepare("
      SELECT COUNT(DISTINCT v.id) as view_count
      FROM view_tracking v
      WHERE v.application_id = ?
    ");
    $refresh_stmt->bind_param("i", $work_id);
    $refresh_stmt->execute();
    $refresh_result = $refresh_stmt->get_result();
    $work['view_count'] = $refresh_result->fetch_assoc()['view_count'];
    $refresh_stmt->close();
    
    auditLog('View IP Work (Anonymous)', 'Application', $work_id);
  }
  $check->close();
}

// Parse document files
$document_files = [];
if (!empty($work['document_file'])) {
  $decoded = json_decode($work['document_file'], true);
  if (is_array($decoded)) {
    $document_files = $decoded;
  } else {
    $document_files = [$work['document_file']];
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($work['title']); ?> - CHMSU IP Hub</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: #F8FAFC;
      min-height: 100vh;
      padding-bottom: 60px;
    }
    
    .navbar {
      background: white;
      border-bottom: 1px solid #E2E8F0;
      padding: 16px 24px;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .nav-container {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .back-btn {
      color: #64748B;
      text-decoration: none;
      font-size: 14px;
      font-weight: 600;
      padding: 8px 16px;
      border-radius: 8px;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    
    .back-btn:hover {
      background: #F1F5F9;
      color: #0A4D2E;
    }
    
    .container {
      max-width: 1200px;
      margin: 40px auto;
      padding: 0 24px;
    }
    
    .work-header {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      padding: 48px;
      border-radius: 20px;
      margin-bottom: 32px;
      position: relative;
      overflow: hidden;
    }
    
    .work-header::before {
      content: '';
      position: absolute;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(218, 165, 32, 0.2) 0%, transparent 70%);
      top: -200px;
      right: -200px;
      border-radius: 50%;
    }
    
    .work-badge {
      display: inline-block;
      padding: 6px 14px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 16px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      background: rgba(255,255,255,0.2);
      position: relative;
    }
    
    .work-title {
      font-size: 42px;
      font-weight: 800;
      margin-bottom: 16px;
      line-height: 1.2;
      letter-spacing: -1px;
      position: relative;
    }
    
    .work-meta {
      display: flex;
      gap: 24px;
      font-size: 15px;
      opacity: 0.95;
      flex-wrap: wrap;
      position: relative;
    }
    
    .work-meta span {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .content-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 32px;
    }
    
    .main-content {
      background: white;
      border-radius: 16px;
      padding: 32px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      border: 1px solid #E2E8F0;
    }
    
    .section-title {
      font-size: 20px;
      font-weight: 700;
      color: #0A4D2E;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .abstract {
      font-size: 15px;
      color: #475569;
      line-height: 1.8;
      margin-bottom: 32px;
      word-wrap: break-word;
      overflow-wrap: break-word;
      width: 100%;
      white-space: pre-wrap;
    }
    
    /* Document viewing and download section */
    .documents {
      margin-top: 32px;
    }
    
    .document-card {
      background: #F8FAFC;
      border: 2px solid #E2E8F0;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 16px;
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
      margin-bottom: 12px;
    }
    
    .document-name {
      font-weight: 600;
      color: #0A4D2E;
      font-size: 15px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .document-size {
      font-size: 13px;
      color: #64748B;
    }
    
    .document-actions {
      display: flex;
      gap: 10px;
    }
    
    .btn-view, .btn-download {
      padding: 10px 20px;
      border-radius: 8px;
      text-decoration: none;
      font-size: 14px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 8px;
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
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(10, 77, 46, 0.3);
    }
    
    .sidebar {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }
    
    .info-card {
      background: white;
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      border: 1px solid #E2E8F0;
    }
    
    .info-item {
      margin-bottom: 20px;
    }
    
    .info-item:last-child {
      margin-bottom: 0;
    }
    
    .info-label {
      font-size: 12px;
      color: #94A3B8;
      text-transform: uppercase;
      font-weight: 700;
      letter-spacing: 0.5px;
      margin-bottom: 6px;
    }
    
    .info-value {
      font-size: 15px;
      color: #1E293B;
      font-weight: 600;
    }
    
    .author-card {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 20px;
      background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%);
      border-radius: 12px;
    }
    
    .author-avatar {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: #DAA520;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      font-weight: 700;
      box-shadow: 0 4px 12px rgba(10, 77, 46, 0.2);
    }
    
    .author-info h4 {
      color: #0A4D2E;
      font-size: 16px;
      margin-bottom: 4px;
    }
    
    .author-info p {
      color: #64748B;
      font-size: 13px;
    }
    
    @media (max-width: 968px) {
      .content-grid {
        grid-template-columns: 1fr;
      }
      
      .work-title {
        font-size: 32px;
      }
      
      .document-actions {
        flex-direction: column;
      }
      
      .abstract {
        font-size: 14px;
      }
    }
    
    @media (max-width: 768px) {
      .container {
        margin: 20px auto;
        padding: 0 16px;
      }
      
      .work-header {
        padding: 32px 20px;
        border-radius: 16px;
      }
      
      .work-title {
        font-size: 24px;
      }
      
      .main-content {
        padding: 20px;
      }
      
      .abstract {
        font-size: 13px;
        line-height: 1.6;
      }
      
      .work-meta {
        gap: 12px;
        font-size: 13px;
      }
    }
    
    @media (max-width: 480px) {
      .navbar {
        padding: 12px 16px;
      }
      
      .nav-container {
        flex-direction: column;
        gap: 12px;
      }
      
      .container {
        margin: 16px auto;
        padding: 0 12px;
      }
      
      .work-header {
        padding: 24px 16px;
      }
      
      .work-title {
        font-size: 20px;
      }
      
      .work-badge {
        font-size: 11px;
      }
      
      .main-content {
        padding: 16px;
      }
      
      .section-title {
        font-size: 16px;
      }
      
      .abstract {
        font-size: 12px;
        line-height: 1.5;
      }
      
      .document-card {
        padding: 16px;
      }
      
      .document-name {
        font-size: 13px;
      }
      
      .document-size {
        font-size: 12px;
      }
      
      .btn-view, .btn-download {
        font-size: 12px;
        padding: 8px 16px;
      }
      
      .info-card {
        padding: 16px;
      }
      
      .info-label {
        font-size: 11px;
      }
      
      .info-value {
        font-size: 13px;
      }
      
      .author-card {
        padding: 16px;
      }
      
      .author-avatar {
        width: 48px;
        height: 48px;
        font-size: 20px;
      }
      
      .author-info h4 {
        font-size: 14px;
      }
      
      .author-info p {
        font-size: 12px;
      }
    }
  </style>
</head>
<body>
  <div class="navbar">
    <div class="nav-container">
      <a href="browse.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Hub
      </a>
      <a href="../dashboard.php" class="back-btn">
        <i class="fas fa-gauge"></i> Dashboard
      </a>
    </div>
  </div>
  
  <div class="container">
    <div class="work-header">
      <span class="work-badge"><?php echo $work['ip_type']; ?></span>
      <h1 class="work-title"><?php echo htmlspecialchars($work['title']); ?></h1>
      <div class="work-meta">
        <span><i class="fas fa-calendar"></i> <?php echo date('F d, Y', strtotime($work['approved_at'])); ?></span>
        <span><i class="fas fa-eye"></i> <?php echo $work['view_count']; ?> views</span>
        <span><i class="fas fa-certificate"></i> <?php echo $work['certificate_id'] ?? 'Pending'; ?></span>
      </div>
    </div>
    
    <div class="content-grid">
      <div class="main-content">
        <h2 class="section-title">
          <i class="fas fa-file-lines"></i> Abstract
        </h2>
        <div class="abstract">
          <?php echo nl2br(htmlspecialchars($work['abstract'])); ?>
        </div>
        
        <!-- Documents section with view and download options -->
        <?php if (!empty($document_files)): ?>
          <div class="documents">
            <h2 class="section-title">
              <i class="fas fa-folder-open"></i> Documents
            </h2>
            <?php foreach ($document_files as $file): ?>
              <?php 
                $file_path = UPLOAD_DIR . $file;
                $file_exists = file_exists($file_path);
                $file_size = $file_exists ? filesize($file_path) : 0;
                $file_size_mb = round($file_size / (1024 * 1024), 2);
                $file_ext = pathinfo($file, PATHINFO_EXTENSION);
              ?>
              <div class="document-card">
                <div class="document-info">
                  <div>
                    <div class="document-name">
                      <i class="fas fa-file-<?php echo $file_ext === 'pdf' ? 'pdf' : 'alt'; ?>"></i>
                      <?php echo htmlspecialchars($file); ?>
                    </div>
                    <div class="document-size">
                      <?php echo $file_exists ? $file_size_mb . ' MB' : 'File not found'; ?>
                    </div>
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
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      
      <div class="sidebar">
        <div class="info-card">
          <h3 class="section-title" style="font-size: 16px;">
            <i class="fas fa-circle-info"></i> Details
          </h3>
          <div class="info-item">
            <div class="info-label">IP Type</div>
            <div class="info-value"><?php echo $work['ip_type']; ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">Reference Number</div>
            <div class="info-value"><?php echo $work['reference_number'] ?? 'Pending'; ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">Registration Date</div>
            <div class="info-value"><?php echo date('M d, Y', strtotime($work['approved_at'])); ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">Status</div>
            <div class="info-value" style="color: #16A34A;">Approved & Certified</div>
          </div>
        </div>
        
        <div class="info-card">
          <h3 class="section-title" style="font-size: 16px; margin-bottom: 16px;">
            <i class="fas fa-user"></i> Author
          </h3>
          <div class="author-card">
            <div class="author-avatar">
              <?php echo strtoupper(substr($work['full_name'], 0, 1)); ?>
            </div>
            <div class="author-info">
              <h4><?php echo htmlspecialchars($work['full_name']); ?></h4>
              <p><?php echo htmlspecialchars($work['department'] ?? 'CHMSU'); ?></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
