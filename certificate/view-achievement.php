<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

// Allow public access based on ID, but basic auth for direct access
$cert_number = $_GET['id'] ?? null;
$user_id = null;
$user_role = null;

if (isLoggedIn()) {
  $user_id = getCurrentUserId();
  $user_role = getUserRole();
}

if (!$cert_number) {
  // If no ID passed and logged in user has one, show theirs
  if ($user_id) {
    $stmt = $conn->prepare("SELECT certificate_number FROM achievement_certificates WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
      $cert_number = $result->fetch_assoc()['certificate_number'];
    } else {
      header("Location: ../dashboard.php");
      exit;
    }
    $stmt->close();
  } else {
    header("Location: ../login.php");
    exit;
  }
}

// Get certificate details
$stmt = $conn->prepare("
  SELECT ac.*, u.full_name, u.department, u.email
  FROM achievement_certificates ac
  JOIN users u ON ac.user_id = u.id
  WHERE ac.certificate_number = ?
");
$stmt->bind_param("s", $cert_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  die("Certificate not found");
}

$cert = $result->fetch_assoc();
$stmt->close();

// Get the user's total approved works and views for context
$stats_query = $conn->query("
  SELECT 
    COUNT(DISTINCT a.id) as approved_works,
    COUNT(DISTINCT v.id) as total_views
  FROM ip_applications a
  LEFT JOIN view_tracking v ON a.id = v.application_id
  WHERE a.user_id = {$cert['user_id']} AND a.status='approved'
");
$stats = $stats_query->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Achievement Certificate - <?php echo htmlspecialchars($cert['full_name']); ?> - CHMSU</title>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      font-family: 'Inter', sans-serif;
      background: #f0f2f5;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 40px 20px;
    }
    
    .cert-container {
      background: white;
      width: 100%;
      max-width: 900px;
      position: relative;
      box-shadow: 0 20px 60px rgba(0,0,0,0.15);
      margin-bottom: 30px;
      overflow: hidden;
    }
    
    .cert-border {
      padding: 40px;
      border: 15px solid #0A4D2E;
      position: relative;
      background: 
        radial-gradient(circle at center, rgba(255,255,255,1) 0%, rgba(250,250,250,1) 100%);
    }
    
    .cert-inner-border {
      border: 2px solid #DAA520;
      padding: 40px;
      position: relative;
    }
    
    .corner-decoration {
      position: absolute;
      width: 60px;
      height: 60px;
      background-size: contain;
      background-repeat: no-repeat;
      z-index: 10;
    }
    
    .top-left { top: 10px; left: 10px; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cpath fill='%23DAA520' d='M0 0v50l10-10V10h40L40 0H0z'/%3E%3C/svg%3E"); }
    .top-right { top: 10px; right: 10px; transform: rotate(90deg); background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cpath fill='%23DAA520' d='M0 0v50l10-10V10h40L40 0H0z'/%3E%3C/svg%3E"); }
    .bottom-left { bottom: 10px; left: 10px; transform: rotate(-90deg); background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cpath fill='%23DAA520' d='M0 0v50l10-10V10h40L40 0H0z'/%3E%3C/svg%3E"); }
    .bottom-right { bottom: 10px; right: 10px; transform: rotate(180deg); background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cpath fill='%23DAA520' d='M0 0v50l10-10V10h40L40 0H0z'/%3E%3C/svg%3E"); }
    
    .header {
      text-align: center;
      margin-bottom: 40px;
    }
    
    .logo {
      width: 80px;
      height: 80px;
      margin-bottom: 20px;
      /* Placeholder for logo */
      background: #0A4D2E;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 40px;
    }
    
    .title {
      font-family: 'Cinzel', serif;
      font-size: 42px;
      font-weight: 700;
      color: #0A4D2E;
      text-transform: uppercase;
      margin-bottom: 10px;
      letter-spacing: 2px;
    }
    
    .subtitle {
      font-family: 'Cinzel', serif;
      font-size: 18px;
      color: #DAA520;
      text-transform: uppercase;
      letter-spacing: 4px;
      margin-bottom: 40px;
    }
    
    .content {
      text-align: center;
      margin-bottom: 40px;
    }
    
    .presented-to {
      font-size: 16px;
      color: #666;
      margin-bottom: 15px;
      font-style: italic;
    }
    
    .recipient-name {
      font-family: 'Cinzel', serif;
      font-size: 36px;
      color: #1a1a1a;
      border-bottom: 2px solid #DAA520;
      display: inline-block;
      padding-bottom: 10px;
      margin-bottom: 30px;
      min-width: 400px;
    }
    
    .description {
      font-size: 16px;
      line-height: 1.8;
      color: #444;
      max-width: 700px;
      margin: 0 auto 40px;
    }
    
    .badge-icon {
      font-size: 60px;
      color: #DAA520;
      margin-bottom: 20px;
      filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));
    }

    .meta-info {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      margin-top: 60px;
      padding-top: 20px;
      border-top: 1px solid #eee;
    }
    
    .date-section {
      text-align: center;
    }
    
    .date-line {
      font-weight: 600;
      color: #333;
      border-bottom: 1px solid #ccc;
      padding-bottom: 5px;
      margin-bottom: 5px;
      min-width: 150px;
      display: inline-block;
    }
    
    .date-label {
      font-size: 12px;
      color: #666;
      text-transform: uppercase;
    }
    
    .cert-id {
      text-align: right;
      font-size: 12px;
      color: #999;
      font-family: monospace;
    }
    
    .seal {
      position: absolute;
      bottom: 40px;
      right: 50%;
      transform: translateX(50%);
      width: 120px;
      height: 120px;
      opacity: 0.1;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='45' fill='none' stroke='%23000' stroke-width='2'/%3E%3Cpath d='M50 10 L60 40 L90 50 L60 60 L50 90 L40 60 L10 50 L40 40 Z' fill='none' stroke='%23000' stroke-width='2'/%3E%3C/svg%3E");
    }
    
    .controls {
      display: flex;
      gap: 15px;
      margin-top: 20px;
    }
    
    .btn {
      padding: 12px 24px;
      border-radius: 8px;
      border: none;
      cursor: pointer;
      font-weight: 600;
      font-size: 14px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      transition: all 0.2s;
    }
    
    .btn-primary {
      background: #0A4D2E;
      color: white;
    }
    
    .btn-primary:hover {
      background: #064024;
      transform: translateY(-2px);
    }
    
    .btn-secondary {
      background: white;
      color: #333;
      border: 1px solid #ddd;
    }
    
    .btn-secondary:hover {
      background: #f8f9fa;
      transform: translateY(-2px);
    }
    
    .share-section {
      margin-top: 20px;
      background: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      width: 100%;
      max-width: 900px;
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .share-url {
      flex: 1;
      background: #f8f9fa;
      padding: 10px 15px;
      border-radius: 6px;
      border: 1px solid #e2e8f0;
      color: #666;
      font-family: monospace;
      font-size: 13px;
    }
    
    /* Print Styles */
    @media print {
      body {
        background: white;
        padding: 0;
      }
      .cert-container {
        box-shadow: none;
        margin: 0;
        max-width: 100%;
      }
      .controls, .share-section {
        display: none;
      }
      @page {
        size: landscape;
        margin: 0;
      }
    }
    
    @media (max-width: 768px) {
      .cert-border { padding: 20px; border-width: 8px; }
      .cert-inner-border { padding: 20px; }
      .title { font-size: 28px; }
      .recipient-name { font-size: 24px; min-width: 100%; }
      .meta-info { flex-direction: column; gap: 20px; align-items: center; }
      .cert-id { text-align: center; }
    }
  </style>
</head>
<body>

  <div class="cert-container">
    <div class="cert-border">
      <div class="corner-decoration top-left"></div>
      <div class="corner-decoration top-right"></div>
      <div class="corner-decoration bottom-left"></div>
      <div class="corner-decoration bottom-right"></div>
      
      <div class="cert-inner-border">
        <div class="seal"></div>
        
        <div class="header">
          <div class="logo">
            <i class="fas fa-university"></i>
          </div>
          <h1 class="title">Certificate of Achievement</h1>
          <div class="subtitle">Carlos Hilado Memorial State University</div>
        </div>
        
        <div class="content">
          <div class="presented-to">This certificate is proudly presented to</div>
          <div class="recipient-name"><?php echo htmlspecialchars($cert['full_name']); ?></div>
          
          <div class="badge-icon">
            <i class="fas fa-trophy"></i>
          </div>
          
          <div class="description">
            For outstanding performance in Intellectual Property creation and dissemination.
            This award recognizes the recipient's dedication as demonstrated by achieving 
            <strong>Diamond-tier visibility</strong> on their registered IP works, 
            contributing significantly to the university's research and innovation goals.
          </div>
        </div>
        
        <div class="meta-info">
          <div class="date-section">
            <div class="date-line"><?php echo date('F d, Y', strtotime($cert['issued_at'])); ?></div>
            <div class="date-label">Date Awarded</div>
          </div>
          
          <div class="cert-id">
            Certificate No: <?php echo htmlspecialchars($cert['certificate_number']); ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="share-section">
    <i class="fas fa-share-alt" style="color: #0A4D2E; font-size: 18px;"></i>
    <div style="font-weight: 600; font-size: 14px; color: #333;">Share Link:</div>
    <div class="share-url"><?php echo BASE_URL . 'certificate/view-achievement.php?id=' . $cert['certificate_number']; ?></div>
    <button class="btn btn-primary" style="padding: 8px 16px; font-size: 12px;" onclick="copyLink()">
      <i class="fas fa-copy"></i> Copy
    </button>
  </div>

  <div class="controls">
    <button class="btn btn-primary" onclick="window.print()">
      <i class="fas fa-print"></i> Print Certificate
    </button>
    <a href="../profile/badges-certificates.php" class="btn btn-secondary">
      <i class="fas fa-arrow-left"></i> Back to Profile
    </a>
  </div>
  
  <script>
    function copyLink() {
      const url = document.querySelector('.share-url').textContent;
      navigator.clipboard.writeText(url).then(() => {
        const btn = document.querySelector('.share-section .btn-primary');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        setTimeout(() => {
          btn.innerHTML = originalText;
        }, 2000);
      });
    }
  </script>

</body>
</html>
