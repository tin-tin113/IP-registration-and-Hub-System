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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 50%, #0A4D2E 100%);
      min-height: 100vh;
      padding: 40px 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow-x: hidden;
    }
    
    /* Animated background elements */
    body::before {
      content: '';
      position: fixed;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle at 20% 80%, rgba(218, 165, 32, 0.15) 0%, transparent 50%),
                  radial-gradient(circle at 80% 20%, rgba(255, 215, 0, 0.1) 0%, transparent 50%),
                  radial-gradient(circle at 50% 50%, rgba(27, 127, 77, 0.2) 0%, transparent 70%);
      animation: backgroundPulse 15s ease-in-out infinite;
      z-index: -1;
    }
    
    @keyframes backgroundPulse {
      0%, 100% { transform: scale(1) rotate(0deg); }
      50% { transform: scale(1.1) rotate(5deg); }
    }
    
    .badge-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      width: 100%;
      max-width: 700px;
      border-radius: 32px;
      overflow: hidden;
      box-shadow: 0 30px 100px rgba(0, 0, 0, 0.3),
                  0 0 0 1px rgba(255, 255, 255, 0.2),
                  inset 0 1px 0 rgba(255, 255, 255, 0.4);
      animation: containerFloat 0.8s ease-out;
    }
    
    @keyframes containerFloat {
      from { opacity: 0; transform: translateY(40px) scale(0.95); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }
    
    .badge-header {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 50%, #0F5D3A 100%);
      padding: 50px 40px;
      text-align: center;
      color: white;
      position: relative;
      overflow: hidden;
    }
    
    .badge-header::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0; bottom: 0;
      background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
      opacity: 0.5;
    }
    
    .badge-icon-wrapper {
      position: relative;
      display: inline-block;
      margin-bottom: 24px;
    }
    
    .badge-icon-glow {
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      width: 160px;
      height: 160px;
      background: radial-gradient(circle, rgba(218, 165, 32, 0.6) 0%, transparent 70%);
      border-radius: 50%;
      animation: iconGlow 3s ease-in-out infinite;
    }
    
    @keyframes iconGlow {
      0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.6; }
      50% { transform: translate(-50%, -50%) scale(1.2); opacity: 0.3; }
    }
    
    .badge-icon-large {
      position: relative;
      width: 130px;
      height: 130px;
      margin: 0 auto;
      background: linear-gradient(145deg, #FFD700 0%, #DAA520 50%, #B8860B 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 56px;
      color: #0A4D2E;
      box-shadow: 0 15px 40px rgba(218, 165, 32, 0.5),
                  inset 0 -4px 10px rgba(0, 0, 0, 0.2),
                  inset 0 4px 10px rgba(255, 255, 255, 0.4);
      border: 4px solid rgba(255, 255, 255, 0.3);
      animation: badgeShine 4s ease-in-out infinite;
    }
    
    @keyframes badgeShine {
      0%, 100% { transform: rotateY(0deg); }
      50% { transform: rotateY(10deg); }
    }
    
    .badge-type {
      font-size: 32px;
      font-weight: 800;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 3px;
      text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
      position: relative;
    }
    
    .badge-subtitle {
      font-size: 14px;
      opacity: 0.85;
      font-weight: 500;
      letter-spacing: 1px;
      position: relative;
    }
    
    .badge-content {
      padding: 40px;
    }
    
    .recipient-section {
      text-align: center;
      margin-bottom: 32px;
      padding-bottom: 32px;
      border-bottom: 1px solid #E2E8F0;
      position: relative;
    }
    
    .recipient-section::after {
      content: '';
      position: absolute;
      bottom: -1px;
      left: 50%;
      transform: translateX(-50%);
      width: 60px;
      height: 3px;
      background: linear-gradient(90deg, #DAA520, #FFD700, #DAA520);
      border-radius: 2px;
    }
    
    .recipient-name {
      font-size: 28px;
      font-weight: 700;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 8px;
    }
    
    .recipient-info {
      font-size: 14px;
      color: #64748B;
      font-weight: 500;
    }
    
    .badge-details {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
      margin-bottom: 28px;
    }
    
    .detail-card {
      background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%);
      padding: 20px 12px;
      border-radius: 16px;
      text-align: center;
      border: 1px solid #E2E8F0;
      transition: all 0.3s ease;
    }
    
    .detail-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 30px rgba(10, 77, 46, 0.1);
      border-color: #1B7F4D;
    }
    
    .detail-icon {
      width: 40px;
      height: 40px;
      margin: 0 auto 12px;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 16px;
    }
    
    .detail-label {
      font-size: 10px;
      color: #94A3B8;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 4px;
      font-weight: 600;
    }
    
    .detail-value {
      font-size: 22px;
      font-weight: 700;
      color: #0A4D2E;
    }
    
    .achievement-text {
      background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%);
      padding: 24px;
      border-radius: 16px;
      border-left: 4px solid #1B7F4D;
      margin-bottom: 24px;
      position: relative;
      overflow: hidden;
    }
    
    .achievement-text::before {
      content: '"';
      position: absolute;
      top: 10px;
      left: 16px;
      font-size: 60px;
      color: #1B7F4D;
      opacity: 0.1;
      font-family: Georgia, serif;
      line-height: 1;
    }
    
    .achievement-text p {
      font-size: 15px;
      line-height: 1.8;
      color: #1E293B;
      position: relative;
    }
    
    .achievement-text p strong {
      color: #0A4D2E;
    }
    
    .share-section {
      background: #F8FAFC;
      padding: 20px;
      border-radius: 16px;
      border: 1px solid #E2E8F0;
    }
    
    .share-section h3 {
      font-size: 14px;
      color: #475569;
      margin-bottom: 12px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .share-section h3 i {
      color: #1B7F4D;
    }
    
    .share-input-group {
      display: flex;
      gap: 8px;
    }
    
    .share-link {
      flex: 1;
      background: white;
      padding: 14px 16px;
      border-radius: 12px;
      border: 1px solid #E2E8F0;
      font-family: 'SF Mono', Monaco, monospace;
      font-size: 12px;
      color: #64748B;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .controls {
      padding: 24px 40px 32px;
      background: linear-gradient(to top, rgba(248, 250, 252, 1) 0%, rgba(255, 255, 255, 0) 100%);
      display: flex;
      gap: 12px;
      justify-content: center;
    }
    
    button, a.btn-back {
      padding: 14px 28px;
      border: none;
      border-radius: 14px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      transition: all 0.3s ease;
    }
    
    .btn-print {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      box-shadow: 0 4px 15px rgba(10, 77, 46, 0.3);
    }
    
    .btn-print:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(10, 77, 46, 0.4);
    }
    
    .btn-back {
      background: white;
      color: #475569;
      border: 1px solid #E2E8F0;
    }
    
    .btn-back:hover {
      background: #F8FAFC;
      border-color: #CBD5E1;
      transform: translateY(-2px);
    }
    
    .btn-copy {
      background: linear-gradient(135deg, #DAA520 0%, #B8860B 100%);
      color: white;
      padding: 14px 20px;
      font-size: 13px;
      box-shadow: 0 4px 15px rgba(218, 165, 32, 0.3);
    }
    
    .btn-copy:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(218, 165, 32, 0.4);
    }
    
    /* Toast notification */
    .toast {
      position: fixed;
      bottom: 30px;
      left: 50%;
      transform: translateX(-50%) translateY(100px);
      background: #0A4D2E;
      color: white;
      padding: 16px 28px;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 500;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
      opacity: 0;
      transition: all 0.4s ease;
      z-index: 1000;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .toast.show {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }
    
    @media (max-width: 768px) {
      body { padding: 20px 16px; }
      .badge-container { border-radius: 24px; }
      .badge-header { padding: 40px 24px; }
      .badge-icon-large { width: 100px; height: 100px; font-size: 44px; }
      .badge-type { font-size: 24px; letter-spacing: 2px; }
      .badge-content { padding: 28px 20px; }
      .badge-details { grid-template-columns: repeat(2, 1fr); gap: 10px; }
      .detail-card { padding: 16px 10px; }
      .detail-value { font-size: 20px; }
      .controls { flex-direction: column; padding: 20px; }
      .share-input-group { flex-direction: column; }
      .recipient-name { font-size: 22px; }
    }
    
    @media print {
      body {
        background: white;
        padding: 0;
      }
      
      body::before { display: none; }
      
      .controls, .share-section, .toast { display: none !important; }
      
      .badge-container {
        box-shadow: none;
        border-radius: 0;
        max-width: none;
      }
      
      .badge-icon-glow { display: none; }
      
      @keyframes none {}
    }
  </style>
</head>
<body>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  
  <div class="badge-container">
    <div class="badge-header">
      <div class="badge-icon-wrapper">
        <div class="badge-icon-glow"></div>
        <div class="badge-icon-large">
          <i class="fas fa-award"></i>
        </div>
      </div>
      <div class="badge-type"><?php echo htmlspecialchars($badge['badge_type']); ?> Badge</div>
      <div class="badge-subtitle">Intellectual Property Achievement Award</div>
    </div>
    
    <div class="badge-content">
      <div class="recipient-section">
        <div class="recipient-name"><?php echo htmlspecialchars($badge['full_name']); ?></div>
        <div class="recipient-info">
          <i class="fas fa-building" style="margin-right: 4px;"></i>
          <?php echo htmlspecialchars($badge['department'] ?? 'CHMSU'); ?> &nbsp;•&nbsp; 
          <i class="fas fa-calendar" style="margin-right: 4px;"></i>
          Earned <?php echo date('F d, Y', strtotime($badge['awarded_at'])); ?>
        </div>
      </div>
      
      <div class="badge-details">
        <div class="detail-card">
          <div class="detail-icon"><i class="fas fa-eye"></i></div>
          <div class="detail-label">Views Required</div>
          <div class="detail-value"><?php echo $badge['views_required']; ?>+</div>
        </div>
        <div class="detail-card">
          <div class="detail-icon"><i class="fas fa-chart-line"></i></div>
          <div class="detail-label">Total Views</div>
          <div class="detail-value"><?php echo $total_views; ?></div>
        </div>
        <div class="detail-card">
          <div class="detail-icon"><i class="fas fa-file-alt"></i></div>
          <div class="detail-label">Approved Works</div>
          <div class="detail-value"><?php echo $approved_works; ?></div>
        </div>
        <div class="detail-card">
          <div class="detail-icon"><i class="fas fa-star"></i></div>
          <div class="detail-label">Innovation Pts</div>
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
          <p style="font-size: 13px; color: #64748B; margin-top: 12px;">
            <i class="fas fa-trophy" style="color: #DAA520; margin-right: 6px;"></i>
            This badge recognizes outstanding contribution to the CHMSU IP community and awards 
            <strong style="color: #0A4D2E;"><?php echo $threshold['points_awarded']; ?> innovation points</strong>.
          </p>
        <?php endif; ?>
      </div>
      
      <div class="share-section">
        <h3><i class="fas fa-share-alt"></i> Share This Achievement</h3>
        <div class="share-input-group">
          <div class="share-link" id="shareLink"><?php echo BASE_URL . 'app/view-badge.php?id=' . $badge_id; ?></div>
          <button class="btn-copy" onclick="copyToClipboard()">
            <i class="fas fa-copy"></i> Copy
          </button>
        </div>
      </div>
    </div>
    
    <div class="controls">
      <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print Badge</button>
      <a href="../profile/badges-certificates.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
  </div>
  
  <!-- Toast Notification -->
  <div class="toast" id="toast">
    <i class="fas fa-check-circle"></i>
    <span>Link copied to clipboard!</span>
  </div>
  
  <script>
    function copyToClipboard() {
      const link = document.getElementById('shareLink').textContent;
      navigator.clipboard.writeText(link).then(function() {
        showToast();
      }, function() {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = link;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast();
      });
    }
    
    function showToast() {
      const toast = document.getElementById('toast');
      toast.classList.add('show');
      setTimeout(() => {
        toast.classList.remove('show');
      }, 3000);
    }
  </script>
</body>
</html>
