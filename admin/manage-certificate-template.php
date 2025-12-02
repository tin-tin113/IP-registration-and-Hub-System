<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireRole(['clerk', 'director']);

$success = '';
$error = '';

// Create certificate_template_settings table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS certificate_template_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  setting_key VARCHAR(100) UNIQUE NOT NULL,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Initialize default settings if not exists
$default_settings = [
  'clerk_name' => 'Maria Santos',
  'clerk_title' => 'IP Office Clerk',
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($_POST as $key => $value) {
    if (strpos($key, 'setting_') === 0) {
      $setting_key = str_replace('setting_', '', $key);
      $stmt = $conn->prepare("UPDATE certificate_template_settings SET setting_value = ? WHERE setting_key = ?");
      $stmt->bind_param("ss", $value, $setting_key);
      $stmt->execute();
      $stmt->close();
    }
  }
  auditLog('Update Certificate Template', 'Settings', null);
  $success = 'Certificate template updated successfully!';
}

// Get current settings
$settings_result = $conn->query("SELECT setting_key, setting_value FROM certificate_template_settings");
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
  $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Certificate Template - CHMSU IP System</title>
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
    
    .form-card {
      background: white;
      border-radius: 10px;
      padding: 25px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      margin-bottom: 20px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    label {
      display: block;
      margin-bottom: 8px;
      color: #333;
      font-weight: 600;
      font-size: 13px;
    }
    
    input[type="text"],
    textarea {
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 14px;
      font-family: inherit;
    }
    
    input:focus,
    textarea:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 5px rgba(102,126,234,0.3);
    }
    
    textarea {
      resize: vertical;
      min-height: 80px;
    }
    
    .form-section {
      margin-bottom: 30px;
      padding-bottom: 20px;
      border-bottom: 1px solid #f0f0f0;
    }
    
    .form-section:last-child {
      border-bottom: none;
    }
    
    .form-section h3 {
      color: #333;
      margin-bottom: 15px;
      font-size: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    button {
      background: #1B5C3B;
      color: white;
      padding: 12px 30px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      transition: all 0.2s;
    }
    
    button:hover {
      background: #0F3D2E;
      transform: translateY(-2px);
    }
    
    .help-text {
      font-size: 12px;
      color: #666;
      margin-top: 5px;
      font-style: italic;
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
    
    <div class="header">
      <h1><i class="fas fa-certificate"></i> Manage Certificate Template</h1>
      <p style="color: #666; font-size: 13px; margin-top: 5px;">Edit certificate template settings for IP registrations</p>
    </div>
    
    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="POST">
      <div class="form-card">
        <div class="form-section">
          <h3><i class="fas fa-heading"></i> Header & Title</h3>
          
          <div class="form-group">
            <label for="setting_header_text">Certificate Header Text</label>
            <input type="text" id="setting_header_text" name="setting_header_text" value="<?php echo htmlspecialchars($settings['header_text'] ?? 'Certificate of Registration'); ?>" required>
            <div class="help-text">Main title displayed on the certificate</div>
          </div>
          
          <div class="form-group">
            <label for="setting_subtitle_text">Certificate Subtitle</label>
            <input type="text" id="setting_subtitle_text" name="setting_subtitle_text" value="<?php echo htmlspecialchars($settings['subtitle_text'] ?? 'Intellectual Property Work'); ?>" required>
            <div class="help-text">Subtitle displayed below the main title</div>
          </div>
        </div>
        
        <div class="form-section">
          <h3><i class="fas fa-file-alt"></i> Certificate Body Text</h3>
          
          <div class="form-group">
            <label for="setting_body_text">Opening Text</label>
            <input type="text" id="setting_body_text" name="setting_body_text" value="<?php echo htmlspecialchars($settings['body_text'] ?? 'This is to certify that'); ?>" required>
            <div class="help-text">Text that appears before the recipient's name</div>
          </div>
          
          <div class="form-group">
            <label for="setting_acknowledgment_text">Acknowledgment Text</label>
            <textarea id="setting_acknowledgment_text" name="setting_acknowledgment_text" required><?php echo htmlspecialchars($settings['acknowledgment_text'] ?? 'This certificate acknowledges the registration and documentation of the aforementioned intellectual property work in the CHMSU IP Registry System.'); ?></textarea>
            <div class="help-text">Text that appears after the work details</div>
          </div>
        </div>
        
        <div class="form-section">
          <h3><i class="fas fa-signature"></i> Signatures</h3>
          
          <div class="form-group">
            <label for="setting_clerk_name">Clerk Name</label>
            <input type="text" id="setting_clerk_name" name="setting_clerk_name" value="<?php echo htmlspecialchars($settings['clerk_name'] ?? 'Maria Santos'); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="setting_clerk_title">Clerk Title</label>
            <input type="text" id="setting_clerk_title" name="setting_clerk_title" value="<?php echo htmlspecialchars($settings['clerk_title'] ?? 'IP Office Clerk'); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="setting_director_name">Director Name</label>
            <input type="text" id="setting_director_name" name="setting_director_name" value="<?php echo htmlspecialchars($settings['director_name'] ?? 'Dr. Juan Dela Cruz'); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="setting_director_title">Director Title</label>
            <input type="text" id="setting_director_title" name="setting_director_title" value="<?php echo htmlspecialchars($settings['director_title'] ?? 'IP Office Director'); ?>" required>
          </div>
        </div>
        
        <button type="submit">
          <i class="fas fa-save"></i> Save Template Settings
        </button>
      </div>
    </form>
  </div>
</body>
</html>

