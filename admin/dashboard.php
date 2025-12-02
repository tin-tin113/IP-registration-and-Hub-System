<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireRole(['clerk', 'director']);

$user_role = getUserRole();

// Get dashboard stats
$submitted_count = $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='submitted'")->fetch_assoc()['count'];
$payment_pending = $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='payment_pending'")->fetch_assoc()['count'];
$payment_verified = $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='payment_verified'")->fetch_assoc()['count'];
$approved_count = $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='approved'")->fetch_assoc()['count'];
$total_works = $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='approved'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - CHMSU IP System</title>
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
    }
    
    .navbar {
      background: linear-gradient(135deg, #1B5C3B 0%, #0F3D2E 100%);
      color: white;
      padding: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.2);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .navbar-brand {
      font-size: 18px;
      font-weight: bold;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .navbar-brand img {
      height: 35px;
      width: auto;
    }
    
    .nav-menu {
      display: flex;
      gap: 20px;
      align-items: center;
    }
    
    .nav-menu a {
      color: white;
      text-decoration: none;
      font-size: 13px;
      padding: 8px 15px;
      border-radius: 5px;
      transition: all 0.3s;
    }
    
    .nav-menu a:hover {
      background: rgba(255,255,255,0.2);
    }
    
    .nav-menu a.active {
      background: #E07D32;
    }
    
    .container {
      max-width: 1200px;
      margin: 30px auto;
      padding: 0 20px;
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .stat-card {
      background: white;
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      border-top: 4px solid #1B5C3B;
    }
    
    .stat-number {
      font-size: 32px;
      font-weight: bold;
      color: #1B5C3B;
      margin-bottom: 5px;
    }
    
    .stat-label {
      font-size: 13px;
      color: #666;
    }
    
    .actions-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 15px;
      margin-bottom: 30px;
    }
    
    .action-card {
      background: white;
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      text-align: center;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      color: #333;
      border-top: 3px solid #1B5C3B;
    }
    
    .action-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 20px rgba(27, 92, 59, 0.3);
    }
    
    .action-card i {
      font-size: 28px;
      color: #1B5C3B;
      margin-bottom: 10px;
      display: block;
    }
    
    .action-card span {
      font-size: 13px;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="navbar">
    <div class="navbar-brand">
      <img src="../public/logos/chmsu-logo.png" alt="CHMSU Logo" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 4px solid #E07D32; background: white; padding: 3px; box-shadow: 0 8px 20px rgba(27, 92, 59, 0.3), 0 0 0 2px rgba(255, 255, 255, 0.1); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);" onerror="this.src='../public/logos/chmsu-logo.jpg'; this.onerror=null;" onmouseover="this.style.transform='scale(1.15) rotate(8deg)'; this.style.boxShadow='0 12px 30px rgba(27, 92, 59, 0.5), 0 0 25px rgba(224, 125, 50, 0.7)'; this.style.borderColor='#FFD700';" onmouseout="this.style.transform='scale(1) rotate(0deg)'; this.style.boxShadow='0 8px 20px rgba(27, 92, 59, 0.3), 0 0 0 2px rgba(255, 255, 255, 0.1)'; this.style.borderColor='#E07D32';">
      <span>Admin Panel</span>
    </div>
    <div class="nav-menu">
      <span style="font-size: 13px;">Role: <strong><?php echo ucfirst($user_role); ?></strong></span>
      <a href="../dashboard.php">Dashboard</a>
      <a href="../?logout">Logout</a>
    </div>
  </div>
  
  <div class="container">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-number"><?php echo $submitted_count; ?></div>
        <div class="stat-label">Submitted Applications</div>
      </div>
      <div class="stat-card" style="border-top-color: #E07D32;">
        <div class="stat-number" style="color: #E07D32;"><?php echo $payment_pending; ?></div>
        <div class="stat-label">Awaiting Payment</div>
      </div>
      <div class="stat-card" style="border-top-color: #2196f3;">
        <div class="stat-number" style="color: #2196f3;"><?php echo $payment_verified; ?></div>
        <div class="stat-label">Payment Verified</div>
      </div>
      <div class="stat-card" style="border-top-color: #4caf50;">
        <div class="stat-number" style="color: #4caf50;"><?php echo $approved_count; ?></div>
        <div class="stat-label">Approved Works</div>
      </div>
    </div>
    
    <h2 style="color: #333; margin-bottom: 20px; font-size: 18px;">Quick Actions</h2>
    
    <div class="actions-grid">
      <a href="apply-for-others.php" class="action-card">
        <i class="fas fa-user-plus"></i>
        <span>Apply for Others</span>
      </a>
      <a href="verify-applications.php" class="action-card">
        <i class="fas fa-check-square"></i>
        <span>Review Applications</span>
      </a>
      <a href="verify-payments.php" class="action-card">
        <i class="fas fa-credit-card"></i>
        <span>Verify Payments</span>
      </a>
      <a href="manage-badges.php" class="action-card">
        <i class="fas fa-medal"></i>
        <span>Manage Badges</span>
      </a>
      <a href="manage-certificate-template.php" class="action-card">
        <i class="fas fa-certificate"></i>
        <span>Certificate Template</span>
      </a>
      <a href="award-badges.php" class="action-card">
        <i class="fas fa-trophy"></i>
        <span>Award Badges</span>
      </a>
      <?php if ($user_role === 'director'): ?>
        <a href="approve-applications.php" class="action-card">
          <i class="fas fa-thumbs-up"></i>
          <span>Approve Applications</span>
        </a>
        <a href="verify-payments.php" class="action-card">
          <i class="fas fa-money-check-alt"></i>
          <span>Payment Management</span>
        </a>
        <a href="adjust-awards.php" class="action-card">
          <i class="fas fa-trophy"></i>
          <span>Adjust Awards</span>
        </a>
        <a href="analytics.php" class="action-card">
          <i class="fas fa-chart-bar"></i>
          <span>Analytics</span>
        </a>
        <a href="audit-log.php" class="action-card">
          <i class="fas fa-history"></i>
          <span>Audit Trail</span>
        </a>
      <?php endif; ?>
    </div>
  </div>
  
  <!-- Footer -->
  <footer class="footer" style="background: #1E293B; color: white; padding: 40px 24px; text-align: center; margin-top: 60px;">
    <p style="opacity: 0.8; font-size: 14px;">&copy; 2025 Carlos Hilado Memorial State University. All rights reserved.</p>
    <p style="margin-top: 8px; font-size: 12px; opacity: 0.8;">Intellectual Property Office • Carlos Hilado Memorial State University • Talisay City Negros Occidental</p>
  </footer>
</body>
</html>
