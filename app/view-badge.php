<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireLogin();

$badge_id = $_GET['id'] ?? null;
$user_id = getCurrentUserId();
$user_role = getUserRole();

if (!$badge_id) {
  header("Location: ../dashboard.php");
  exit;
}

// Get badge information
if ($user_role === 'user') {
  // Users can only view their own badges
  $stmt = $conn->prepare("
    SELECT b.*, u.full_name, u.email, u.department, u.innovation_points
    FROM badges b
    JOIN users u ON b.user_id = u.id
    WHERE b.id = ? AND b.user_id = ?
  ");
  $stmt->bind_param("ii", $badge_id, $user_id);
} else {
  // Admin can view any badge
  $stmt = $conn->prepare("
    SELECT b.*, u.full_name, u.email, u.department, u.innovation_points
    FROM badges b
    JOIN users u ON b.user_id = u.id
    WHERE b.id = ?
  ");
  $stmt->bind_param("i", $badge_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  header("Location: ../dashboard.php?error=Badge not found");
  exit;
}

$badge = $result->fetch_assoc();
$stmt->close();

// Get badge threshold info
$threshold_stmt = $conn->prepare("SELECT * FROM badge_thresholds WHERE badge_type = ?");
$threshold_stmt->bind_param("s", $badge['badge_type']);
$threshold_stmt->execute();
$threshold_result = $threshold_stmt->get_result();
$threshold = $threshold_result->fetch_assoc();
$threshold_stmt->close();

// Get user's total views
$views_stmt = $conn->prepare("
  SELECT COUNT(DISTINCT v.id) as total_views
  FROM view_tracking v
  JOIN ip_applications a ON v.application_id = a.id
  WHERE a.user_id = ? AND a.status = 'approved'
");
$views_stmt->bind_param("i", $badge['user_id']);
$views_stmt->execute();
$views_result = $views_stmt->get_result();
$views_data = $views_result->fetch_assoc();
$total_views = intval($views_data['total_views'] ?? 0);
$views_stmt->close();

// Get user's approved works count
$works_stmt = $conn->prepare("SELECT COUNT(*) as count FROM ip_applications WHERE user_id = ? AND status = 'approved'");
$works_stmt->bind_param("i", $badge['user_id']);
$works_stmt->execute();
$works_result = $works_stmt->get_result();
$works_data = $works_result->fetch_assoc();
$approved_works = intval($works_data['count'] ?? 0);
$works_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Badge - <?php echo htmlspecialchars($badge['badge_type']); ?> - CHMSU</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .badge-container {
      background: white;
      width: 100%;
      max-width: 800px;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 10px 50px rgba(0,0,0,0.3);
    }
    
    .badge-header {
      background: linear-gradient(135deg, #1B5C3B 0%, #0F3D2E 100%);
      padding: 40px;
      text-align: center;
      color: white;
    }
    
    .badge-icon-large {
      width: 120px;
      height: 120px;
      margin: 0 auto 20px;
      background: linear-gradient(135deg, #DAA520 0%, #FFD700 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 60px;
      color: #1B5C3B;
      box-shadow: 0 10px 30px rgba(218, 165, 32, 0.5);
      border: 5px solid white;
    }
    
    .badge-type {
      font-size: 36px;
      font-weight: 700;
      margin-bottom: 10px;
      text-transform: uppercase;
      letter-spacing: 2px;
    }
    
    .badge-subtitle {
      font-size: 16px;
      opacity: 0.9;
    }
    
    .badge-content {
      padding: 40px;
    }
    
    .recipient-section {
      text-align: center;
      margin-bottom: 30px;
      padding-bottom: 30px;
      border-bottom: 2px solid #f0f0f0;
    }
    
    .recipient-name {
      font-size: 28px;
      font-weight: 700;
      color: #1B5C3B;
      margin-bottom: 10px;
    }
    
    .recipient-info {
      font-size: 14px;
      color: #666;
    }
    
    .badge-details {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .detail-card {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 10px;
      text-align: center;
      border: 2px solid #e9ecef;
    }
    
    .detail-label {
      font-size: 12px;
      color: #666;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 8px;
    }
    
    .detail-value {
      font-size: 24px;
      font-weight: 700;
      color: #1B5C3B;
    }
    
    .achievement-text {
      background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%);
      padding: 25px;
      border-radius: 10px;
      border-left: 5px solid #1B5C3B;
      margin-bottom: 30px;
    }
    
    .achievement-text p {
      font-size: 16px;
      line-height: 1.8;
      color: #333;
      margin-bottom: 10px;
    }
    
    .share-section {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
    }
    
    .share-section h3 {
      font-size: 16px;
      color: #333;
      margin-bottom: 15px;
    }
    
    .share-link {
      background: white;
      padding: 15px;
      border-radius: 5px;
      border: 1px solid #ddd;
      font-family: monospace;
      font-size: 13px;
      word-break: break-all;
      margin-bottom: 10px;
    }
    
    .controls {
      padding: 30px;
      background: white;
      display: flex;
      gap: 10px;
      justify-content: center;
      border-top: 1px solid #f0f0f0;
    }
    
    button, a {
      padding: 12px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      display: inline-block;
      transition: all 0.2s;
    }
    
    .btn-print {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }
    
    .btn-print:hover {
      transform: translateY(-2px);
    }
    
    .btn-back {
      background: #f0f0f0;
      color: #333;
      border: 1px solid #ddd;
    }
    
    .btn-back:hover {
      background: #e8e8e8;
    }
    
    .btn-copy {
      background: #1B5C3B;
      color: white;
      padding: 8px 15px;
      font-size: 12px;
    }
    
    .btn-copy:hover {
      background: #0F3D2E;
    }
    
    @media print {
      body {
        background: white;
        padding: 0;
      }
      
      .controls {
        display: none;
      }
      
      .badge-container {
        box-shadow: none;
        border-radius: 0;
        max-width: none;
      }
    }
  </style>
</head>
<body>
  <div class="badge-container">
    <div class="badge-header">
      <div class="badge-icon-large">
        <i class="fas fa-award"></i>
      </div>
      <div class="badge-type"><?php echo htmlspecialchars($badge['badge_type']); ?> Badge</div>
      <div class="badge-subtitle">Intellectual Property Achievement</div>
    </div>
    
    <div class="badge-content">
      <div class="recipient-section">
        <div class="recipient-name"><?php echo htmlspecialchars($badge['full_name']); ?></div>
        <div class="recipient-info">
          <?php echo htmlspecialchars($badge['department'] ?? 'CHMSU'); ?> • 
          Earned <?php echo date('F d, Y', strtotime($badge['awarded_at'])); ?>
        </div>
      </div>
      
      <div class="badge-details">
        <div class="detail-card">
          <div class="detail-label">Views Required</div>
          <div class="detail-value"><?php echo $badge['views_required']; ?>+</div>
        </div>
        <div class="detail-card">
          <div class="detail-label">Total Views</div>
          <div class="detail-value"><?php echo $total_views; ?></div>
        </div>
        <div class="detail-card">
          <div class="detail-label">Approved Works</div>
          <div class="detail-value"><?php echo $approved_works; ?></div>
        </div>
        <div class="detail-card">
          <div class="detail-label">Innovation Points</div>
          <div class="detail-value"><?php echo $badge['innovation_points'] ?? 0; ?></div>
        </div>
      </div>
      
      <div class="achievement-text">
        <p>
          <strong><?php echo htmlspecialchars($badge['full_name']); ?></strong> has earned the 
          <strong><?php echo htmlspecialchars($badge['badge_type']); ?> Badge</strong> for achieving 
          <strong><?php echo $badge['views_required']; ?>+ views</strong> on their approved intellectual property works.
        </p>
        <?php if ($threshold): ?>
          <p style="font-size: 14px; color: #666; margin-top: 10px;">
            This badge recognizes outstanding contribution to the CHMSU Intellectual Property community and awards 
            <strong><?php echo $threshold['points_awarded']; ?> innovation points</strong>.
          </p>
        <?php endif; ?>
      </div>
      
      <div class="share-section">
        <h3><i class="fas fa-share-alt"></i> Share This Badge</h3>
        <div class="share-link" id="shareLink"><?php echo BASE_URL . 'app/view-badge.php?id=' . $badge_id; ?></div>
        <button class="btn-copy" onclick="copyToClipboard()">
          <i class="fas fa-copy"></i> Copy Link
        </button>
      </div>
    </div>
    
    <div class="controls">
      <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print Badge</button>
      <a href="../profile/badges-certificates.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
  </div>
  
  <script>
    function copyToClipboard() {
      const link = document.getElementById('shareLink').textContent;
      navigator.clipboard.writeText(link).then(function() {
        alert('Badge link copied to clipboard!');
      }, function() {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = link;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('Badge link copied to clipboard!');
      });
    }
  </script>
</body>
</html>

