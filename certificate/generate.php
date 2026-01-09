<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireLogin();

$cert_id = $_GET['id'] ?? null;
$user_id = getCurrentUserId();
$user_role = getUserRole();

if (!$cert_id) {
  header("Location: ../dashboard.php");
  exit;
}

// Get certificate and application data
if (in_array($user_role, ['clerk', 'director'])) {
  // Admin can view any certificate
  $stmt = $conn->prepare("
    SELECT c.*, a.title, a.ip_type, a.abstract, a.approved_at, a.inventor_name,
           u.full_name, u.email, u.department
    FROM certificates c
    JOIN ip_applications a ON c.application_id = a.id
    JOIN users u ON a.user_id = u.id
    WHERE c.id = ?
  ");
  $stmt->bind_param("i", $cert_id);
} else {
  // Users can only view their own certificates
  $stmt = $conn->prepare("
    SELECT c.*, a.title, a.ip_type, a.abstract, a.approved_at, a.inventor_name,
           u.full_name, u.email, u.department
    FROM certificates c
    JOIN ip_applications a ON c.application_id = a.id
    JOIN users u ON a.user_id = u.id
    WHERE c.id = ? AND a.user_id = ?
  ");
  $stmt->bind_param("ii", $cert_id, $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  header("Location: ../dashboard.php?error=Certificate not found");
  exit;
}

$cert = $result->fetch_assoc();
$stmt->close();

// Create certificate_template_settings table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS certificate_template_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  setting_key VARCHAR(100) UNIQUE NOT NULL,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Initialize default settings if not exists
$default_settings = [
  'director_name' => 'Dr. Juan Dela Cruz',
  'director_title' => 'IP Office Director',
  'header_text' => 'Certificate of Registration',
  'subtitle_text' => 'Intellectual Property Work',
  'body_text' => 'This is to certify that',
  'acknowledgment_text' => 'This certificate acknowledges the registration and documentation of the aforementioned intellectual property work in the CHMSU IP Registry System.'
];

foreach ($default_settings as $key => $value) {
  $check = $conn->prepare("SELECT id FROM certificate_template_settings WHERE setting_key = ?");
  $check->bind_param("s", $key);
  $check->execute();
  if ($check->get_result()->num_rows === 0) {
    $insert = $conn->prepare("INSERT INTO certificate_template_settings (setting_key, setting_value) VALUES (?, ?)");
    $insert->bind_param("ss", $key, $value);
    $insert->execute();
    $insert->close();
  }
  $check->close();
}

// Get certificate template settings
$settings_result = $conn->query("SELECT setting_key, setting_value FROM certificate_template_settings");
$template_settings = [];
while ($row = $settings_result->fetch_assoc()) {
  $template_settings[$row['setting_key']] = $row['setting_value'];
}

// Use template settings or defaults
$director_name = $template_settings['director_name'] ?? 'Dr. Juan Dela Cruz';
$director_title = $template_settings['director_title'] ?? 'IP Office Director';
$header_text = $template_settings['header_text'] ?? 'Certificate of Registration';
$subtitle_text = $template_settings['subtitle_text'] ?? 'Intellectual Property Work';
$body_text = $template_settings['body_text'] ?? 'This is to certify that';
$acknowledgment_text = $template_settings['acknowledgment_text'] ?? 'This certificate acknowledges the registration and documentation of the aforementioned intellectual property work in the CHMSU IP Registry System.';

$issue_date = date('F d, Y', strtotime($cert['issued_at']));
$approval_date = date('F d, Y', strtotime($cert['approved_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>IP Certificate - <?php echo htmlspecialchars($cert['certificate_number']); ?></title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Times New Roman', serif;
      background: #f5f7fa;
      padding: 20px;
    }
    
    .controls {
      max-width: 900px;
      margin: 0 auto 20px;
      display: flex;
      gap: 10px;
      justify-content: flex-end;
    }
    
    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-back {
      background: #6c757d;
      color: white;
    }
    
    .btn-download {
      background: #1B5C3B;
      color: white;
    }
    
    .btn-print {
      background: #E07D32;
      color: white;
    }
    
    .certificate-container {
      max-width: 900px;
      margin: 0 auto;
      background: white;
      box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    }
    
    .certificate {
      padding: 60px;
      background: 
        linear-gradient(to right, #1B5C3B 2px, transparent 2px) 0 0,
        linear-gradient(to right, #1B5C3B 2px, transparent 2px) 0 100%,
        linear-gradient(to left, #1B5C3B 2px, transparent 2px) 100% 0,
        linear-gradient(to left, #1B5C3B 2px, transparent 2px) 100% 100%,
        linear-gradient(to bottom, #1B5C3B 2px, transparent 2px) 0 0,
        linear-gradient(to bottom, #1B5C3B 2px, transparent 2px) 100% 0,
        linear-gradient(to top, #1B5C3B 2px, transparent 2px) 0 100%,
        linear-gradient(to top, #1B5C3B 2px, transparent 2px) 100% 100%;
      background-repeat: no-repeat;
      background-size: 40px 40px;
      position: relative;
    }
    
    .certificate::before {
      content: '';
      position: absolute;
      top: 15px;
      left: 15px;
      right: 15px;
      bottom: 15px;
      border: 3px solid #E07D32;
      pointer-events: none;
    }
    
    .header {
      text-align: center;
      margin-bottom: 40px;
      border-bottom: 3px double #1B5C3B;
      padding-bottom: 30px;
    }
    
    .logo {
      width: 100px;
      height: 100px;
      margin: 0 auto 20px;
      background: url('../public/logos/chmsu-logo.png') center/contain no-repeat;
      background-size: contain;
    }
    
    .logo img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    
    .university-name {
      font-size: 28px;
      font-weight: bold;
      color: #1B5C3B;
      margin-bottom: 5px;
      text-transform: uppercase;
      letter-spacing: 2px;
    }
    
    .department {
      font-size: 16px;
      color: #666;
      font-style: italic;
    }
    
    .cert-title {
      text-align: center;
      margin: 40px 0;
    }
    
    .cert-title h1 {
      font-size: 36px;
      color: #1B5C3B;
      margin-bottom: 10px;
      text-transform: uppercase;
      letter-spacing: 3px;
    }
    
    .cert-subtitle {
      font-size: 18px;
      color: #E07D32;
      font-weight: 600;
    }
    
    .cert-body {
      text-align: center;
      line-height: 2;
      font-size: 16px;
      color: #333;
      margin: 40px 0;
    }
    
    .cert-body p {
      margin-bottom: 20px;
    }
    
    .recipient-name {
      font-size: 32px;
      font-weight: bold;
      color: #1B5C3B;
      margin: 20px 0;
      text-decoration: underline;
      text-decoration-color: #E07D32;
      text-underline-offset: 8px;
    }
    
    .work-title {
      font-size: 22px;
      font-weight: bold;
      color: #E07D32;
      font-style: italic;
      margin: 20px 0;
    }
    
    .ip-type {
      display: inline-block;
      background: #1B5C3B;
      color: white;
      padding: 8px 20px;
      border-radius: 5px;
      font-weight: bold;
      margin: 10px 0;
    }
    
    .cert-details {
      margin: 40px 0;
      padding: 20px;
      background: #f9f9f9;
      border-left: 5px solid #E07D32;
    }
    
    .detail-row {
      display: flex;
      justify-content: space-between;
      margin: 10px 0;
      font-size: 14px;
    }
    
    .detail-label {
      font-weight: bold;
      color: #666;
    }
    
    .signatures {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 40px;
      margin-top: 60px;
      text-align: center;
    }
    
    .signature {
      border-top: 2px solid #333;
      padding-top: 10px;
    }
    
    .signature-name {
      font-weight: bold;
      font-size: 16px;
      color: #1B5C3B;
    }
    
    .signature-title {
      font-size: 13px;
      color: #666;
      font-style: italic;
    }
    
    .footer {
      margin-top: 40px;
      text-align: center;
      font-size: 11px;
      color: #999;
      border-top: 1px solid #ddd;
      padding-top: 20px;
    }
    
    .qr-section {
      position: absolute;
      bottom: 60px;
      right: 60px;
      text-align: center;
    }
    
    .qr-code {
      width: 80px;
      height: 80px;
      border: 2px solid #1B5C3B;
      padding: 5px;
      background: white;
    }
    
    .qr-label {
      font-size: 10px;
      color: #666;
      margin-top: 5px;
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
      }
      
      @page {
        size: A4 landscape;
        margin: 0;
      }
    }
  </style>
</head>
<body>
  <div class="controls">
    <?php
      // Check if we came from profile page
      $referer = $_SERVER['HTTP_REFERER'] ?? '';
      $from_profile = strpos($referer, 'profile/badges-certificates.php') !== false;
      $back_url = $from_profile ? '../profile/badges-certificates.php' : '../dashboard.php';
    ?>
    <a href="<?php echo $back_url; ?>" class="btn btn-back">
      <i class="fas fa-arrow-left"></i> Back
    </a>
    <button onclick="window.print()" class="btn btn-print">
      <i class="fas fa-print"></i> Print
    </button>
    <button onclick="downloadPDF()" class="btn btn-download">
      <i class="fas fa-download"></i> Download PDF
    </button>
  </div>
  
  <div class="certificate-container" id="certificate">
    <div class="certificate">
      <div class="header">
        <div class="logo">
          <img src="../public/logos/chmsu-logo.png" alt="CHMSU Logo" onerror="this.style.display='none';">
        </div>
        <div class="university-name"><?php echo UNIVERSITY_NAME; ?></div>
        <div class="department">Intellectual Property Office</div>
      </div>
      
      <div class="cert-title">
        <h1><?php echo htmlspecialchars($header_text); ?></h1>
        <div class="cert-subtitle"><?php echo htmlspecialchars($subtitle_text); ?></div>
      </div>
      
      <div class="cert-body">
        <p><?php echo htmlspecialchars($body_text); ?></p>
        
        <div class="recipient-name" style="text-align: center; white-space: pre-line;"><?php echo strtoupper(htmlspecialchars($cert['inventor_name'] ?? $cert['full_name'])); ?></div>
        
        <?php if ($cert['department']): ?>
          <p style="font-size: 14px; color: #666;">
            <?php echo htmlspecialchars($cert['department']); ?>
          </p>
        <?php endif; ?>
        
        <p style="margin-top: 30px;">has successfully registered the intellectual property work entitled</p>
        
        <div class="work-title">"<?php echo htmlspecialchars($cert['title']); ?>"</div>
        
        <p>categorized as</p>
        
        <div class="ip-type"><?php echo $cert['ip_type']; ?></div>
        
        <p style="margin-top: 30px;">
          <?php echo nl2br(htmlspecialchars($acknowledgment_text)); ?>
        </p>
      </div>
      
      <div class="cert-details">
        <div class="detail-row">
          <span class="detail-label">Certificate Number:</span>
          <span><?php echo $cert['certificate_number']; ?></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Reference Number:</span>
          <span><?php echo $cert['reference_number']; ?></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Date of Approval:</span>
          <span><?php echo $approval_date; ?></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Date Issued:</span>
          <span><?php echo $issue_date; ?></span>
        </div>
      </div>
      
      <div class="signatures">
        <div class="signature">
          <div class="signature-name"><?php echo htmlspecialchars($director_name); ?></div>
          <div class="signature-title"><?php echo htmlspecialchars($director_title); ?></div>
        </div>
      </div>
      
      <div class="qr-section">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=<?php echo urlencode(BASE_URL . 'verify-certificate.php?cert=' . $cert['certificate_number']); ?>" alt="QR Code" class="qr-code">
        <div class="qr-label">Scan to Verify</div>
      </div>
      
      <div class="footer">
        <p><?php echo UNIVERSITY_NAME; ?> • <?php echo IP_OFFICE_LOCATION; ?></p>
        <p style="margin-top: 5px; font-size: 10px;">
          <?php echo CERTIFICATE_VALIDITY; ?>
        </p>
      </div>
    </div>
  </div>
  
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
  <script>
    function downloadPDF() {
      const element = document.getElementById('certificate');
      const opt = {
        margin: 0,
        filename: 'CHMSU_Certificate_<?php echo $cert['certificate_number']; ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
      };
      html2pdf().set(opt).from(element).save();
    }
  </script>
</body>
</html>
