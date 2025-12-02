<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';
require_once '../lib/qr-generator.php';

requireLogin();

$app_id = $_GET['id'] ?? null;
$user_id = getCurrentUserId();
$user_role = getUserRole();

if (!$app_id) {
  header("Location: ../dashboard.php");
  exit;
}

// Get application (user can only view their own, unless director/clerk)
if ($user_role === 'user') {
  $stmt = $conn->prepare("SELECT a.* FROM ip_applications a WHERE a.id=? AND a.user_id=?");
  $stmt->bind_param("ii", $app_id, $user_id);
} else {
  $stmt = $conn->prepare("SELECT a.* FROM ip_applications a WHERE a.id=?");
  $stmt->bind_param("i", $app_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  header("Location: ../dashboard.php?error=Certificate not found");
  exit;
}

$app = $result->fetch_assoc();
$stmt->close();

if ($app['status'] !== 'approved' || !$app['certificate_id']) {
  header("Location: ../dashboard.php?error=Certificate not available");
  exit;
}

// Get creator info
$creator_stmt = $conn->prepare("SELECT full_name, innovation_points FROM users WHERE id=?");
$creator_stmt->bind_param("i", $app['user_id']);
$creator_stmt->execute();
$creator = $creator_stmt->get_result()->fetch_assoc();
$creator_stmt->close();

// Generate QR code data (includes certificate number and reference number)
$qr_data = "Certificate: " . $app['certificate_id'] . " | Reference: " . $app['reference_number'] . " | IP: " . $app['ip_type'];
$qr_code_url = QRGenerator::generateQRCode($qr_data, 250);

// Handle download as HTML
if (isset($_GET['download'])) {
  $filename = 'CHMSU-' . str_replace('-', '', $app['certificate_id']) . '.html';
  header('Content-Type: text/html; charset=utf-8');
  header("Content-Disposition: attachment; filename=\"$filename\"");
  
  echo file_get_contents('certificate-template.php?id=' . $app_id);
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>IP Certificate - CHMSU</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Georgia', serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .certificate-container {
      background: white;
      width: 100%;
      max-width: 900px;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 10px 50px rgba(0,0,0,0.3);
    }
    
    .certificate-content {
      aspect-ratio: 16 / 12;
      padding: 60px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      background: linear-gradient(to right, rgba(102,126,234,0.05) 0%, rgba(118,75,162,0.05) 100%);
      border: 2px solid #ddd;
      position: relative;
      overflow: hidden;
    }
    
    .certificate-content::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 2px;
      background: linear-gradient(90deg, #667eea, #764ba2);
    }
    
    .certificate-content::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 2px;
      background: linear-gradient(90deg, #667eea, #764ba2);
    }
    
    .certificate-header {
      margin-bottom: 20px;
    }
    
    .seal {
      width: 80px;
      height: 80px;
      margin: 0 auto 15px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .seal img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    
    .university-name {
      font-size: 11px;
      color: #999;
      letter-spacing: 2px;
      text-transform: uppercase;
      margin-bottom: 5px;
    }
    
    .certificate-title {
      font-size: 32px;
      color: #333;
      margin-bottom: 5px;
      font-weight: bold;
    }
    
    .certificate-subtitle {
      font-size: 14px;
      color: #666;
      margin-bottom: 30px;
    }
    
    .certificate-body {
      font-size: 13px;
      line-height: 1.8;
      color: #555;
      margin-bottom: 30px;
    }
    
    .certificate-body strong {
      color: #333;
    }
    
    .work-details {
      background: rgba(102,126,234,0.1);
      padding: 20px;
      border-radius: 5px;
      margin-bottom: 30px;
      border-left: 4px solid #667eea;
    }
    
    .detail-row {
      margin-bottom: 10px;
      font-size: 13px;
    }
    
    .detail-label {
      font-weight: 600;
      color: #667eea;
      display: inline-block;
      width: 120px;
    }
    
    .detail-value {
      color: #333;
    }
    
    .certificate-footer {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      margin-top: 40px;
      padding-top: 20px;
      border-top: 1px solid #ddd;
      width: 100%;
    }
    
    .qr-code {
      width: 80px;
      text-align: center;
    }
    
    .qr-code img {
      width: 100%;
      height: auto;
    }
    
    .qr-label {
      font-size: 9px;
      color: #999;
      margin-top: 5px;
    }
    
    .signature-line {
      flex: 1;
      text-align: center;
    }
    
    .signature {
      border-top: 1px solid #333;
      padding-top: 5px;
      width: 150px;
      margin: 0 auto;
      font-size: 11px;
      font-weight: 600;
    }
    
    .certificate-numbers {
      flex: 1;
      text-align: center;
      font-size: 11px;
    }
    
    .cert-number {
      font-family: monospace;
      color: #667eea;
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .ref-number {
      font-family: monospace;
      color: #764ba2;
      font-weight: 600;
    }
    
    .disclaimer {
      font-size: 10px;
      color: #999;
      font-style: italic;
      margin-top: 20px;
      text-align: center;
    }
    
    .controls {
      padding: 30px;
      background: white;
      display: flex;
      gap: 10px;
      justify-content: center;
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
    
    @media print {
      body {
        background: white;
        padding: 0;
      }
      
      .controls {
        display: none;
      }
      
      .certificate-container {
        box-shadow: none;
        border-radius: 0;
        max-width: none;
      }
    }
  </style>
</head>
<body>
  <div style="width: 100%; max-width: 900px;">
    <div class="certificate-container">
      <div class="certificate-content">
        <div class="certificate-header">
          <div class="seal">
            <img src="../public/logos/chmsu-logo.png" alt="CHMSU Logo" onerror="this.style.display='none';">
          </div>
          <div class="university-name">Carlos Hilado Memorial State University</div>
          <div style="font-size: 11px; color: #999; letter-spacing: 1px; margin-bottom: 10px;">INTELLECTUAL PROPERTY OFFICE</div>
        </div>
        
        <div class="certificate-title">Certificate of Registration</div>
        <div class="certificate-subtitle">For Intellectual Property Protection</div>
        
        <div class="certificate-body">
          This certificate is proudly presented to<br>
          <strong><?php echo htmlspecialchars($creator['full_name']); ?></strong><br>
          in recognition of the registration of an Intellectual Property Work
        </div>
        
        <div class="work-details">
          <div class="detail-row">
            <span class="detail-label">Work Title:</span>
            <span class="detail-value"><?php echo htmlspecialchars($app['title']); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">IP Type:</span>
            <span class="detail-value"><?php echo $app['ip_type']; ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Approved:</span>
            <span class="detail-value"><?php echo date('F d, Y', strtotime($app['approved_at'])); ?></span>
          </div>
        </div>
        
        <div class="certificate-footer">
          <div class="qr-code">
            <img src="<?php echo htmlspecialchars($qr_code_url); ?>" alt="QR Code">
            <div class="qr-label">Verify</div>
          </div>
          
          <div class="signature-line">
            <div style="color: #667eea; font-size: 13px; font-weight: 600; margin-bottom: 10px;">Director</div>
            <div class="signature">IP Office Director</div>
          </div>
          
          <div class="certificate-numbers">
            <div class="cert-number"><?php echo htmlspecialchars($app['certificate_id']); ?></div>
            <div class="ref-number"><?php echo htmlspecialchars($app['reference_number']); ?></div>
          </div>
        </div>
        
        <div class="disclaimer">
          <?php echo CERTIFICATE_VALIDITY; ?>
        </div>
      </div>
    </div>
    
    <div class="controls">
      <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print Certificate</button>
      <a href="../app/my-applications.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
  </div>
  
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</body>
</html>
