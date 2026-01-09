<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireRole(['clerk', 'director']);

$user_role = getUserRole();
$user_name = $_SESSION['full_name'] ?? 'Admin User';

// Get dashboard stats
$submitted_count = $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='submitted'")->fetch_assoc()['count'];
$payment_pending = $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='payment_pending'")->fetch_assoc()['count'];
$payment_verified = $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='payment_verified'")->fetch_assoc()['count'];
$approved_count = $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='approved'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - CHMSU IP System</title>
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
    
    /* Desktop Sidebar Offset */
    body.sidebar-active {
      margin-left: 280px;
      transition: margin-left 0.3s ease;
    }

/* Improved responsive layout with proper spacing */
.top-bar {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      padding: 20px 32px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 24px;
      flex-wrap: wrap;
    }

    .top-bar-left {
      flex: 1;
      min-width: 250px;
    }

    .top-bar-left h1 {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 4px;
      letter-spacing: -0.5px;
    }

    .top-bar-left p {
      opacity: 0.9;
      font-size: 14px;
      font-weight: 500;
    }

    /* Profile section with circular avatar in top right */
    .profile-section {
      display: flex;
      align-items: center;
      gap: 12px;
      background: rgba(255,255,255,0.1);
      padding: 12px 20px;
      border-radius: 12px;
      backdrop-filter: blur(10px);
      white-space: nowrap;
    }

    .profile-avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: linear-gradient(135deg, #DAA520 0%, #FFD700 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #0A4D2E;
      font-weight: 700;
      font-size: 18px;
      box-shadow: 0 4px 12px rgba(218, 165, 32, 0.4);
      flex-shrink: 0;
    }

    .profile-info {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .profile-name {
      font-weight: 600;
      font-size: 14px;
    }

    .profile-role {
      font-size: 12px;
      opacity: 0.9;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .logout-btn {
      background: rgba(255,255,255,0.15);
      color: white;
      padding: 10px 16px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
    }

    .logout-btn:hover {
      background: rgba(255,255,255,0.25);
      transform: translateY(-1px);
    }

    .container {
      max-width: 128  0px;
      margin: 0 auto;
      padding: 32px 24px;
    }
    
    /* Responsive grid for stat cards */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 24px;
      margin-bottom: 40px;
    }
    
    .stat-card {
      background: white;
      border-radius: 16px;
      padding: 28px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.06);
      border: 1px solid rgba(0,0,0,0.05);
      transition: all 0.3s;
    }

    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 32px rgba(0,0,0,0.12);
    }
    
    .stat-number {
      font-size: 36px;
      font-weight: 700;
      color: #0A4D2E;
      margin-bottom: 8px;
      letter-spacing: -1px;
    }
    
    .stat-label {
      font-size: 13px;
      color: #64748B;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .stat-icon {
      width: 56px;
      height: 56px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      box-shadow: 0 8px 20px rgba(10, 77, 46, 0.2);
    }
    
    /* Responsive grid for action cards */
    .actions-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 20px;
      margin-bottom: 40px;
    }
    
    .action-card {
      background: white;
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.06);
      text-align: center;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      color: #1E293B;
      border: 1px solid rgba(0,0,0,0.05);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 12px;
    }
    
    .action-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 12px 32px rgba(10, 77, 46, 0.15);
      border-color: #1B7F4D;
    }

    .action-card-icon {
      font-size: 28px;
      width: 56px;
      height: 56px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .action-card span {
      font-size: 13px;
      font-weight: 600;
      color: #1E293B;
    }

    /* Responsive tablet view */
    @media (max-width: 1024px) {
      .top-bar {
        flex-direction: column;
        align-items: flex-start;
        padding: 16px 24px;
      }

      .profile-section {
        width: 100%;
        justify-content: space-between;
        order: -1;
      }

      body.sidebar-active {
        margin-left: 240px;
      }
      div[style*="margin-left: 280px"] {
        margin-left: 240px !important;
      }

      .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      }

      .actions-grid {
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      }
    }

    /* Responsive mobile view */
    @media (max-width: 768px) {
      .top-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
      }

      .top-bar-left {
        width: 100%;
        min-width: auto;
      }

      .top-bar-left h1 {
        font-size: 22px;
      }

      .profile-section {
        width: 100%;
        justify-content: space-between;
        gap: 8px;
        padding: 10px 16px;
      }

      .profile-avatar {
        width: 40px;
        height: 40px;
        font-size: 16px;
      }

      .profile-name {
        font-size: 13px;
      }

      .profile-role {
        font-size: 11px;
      }

      .logout-btn {
        padding: 8px 12px;
        font-size: 12px;
      }

      body.sidebar-active {
        margin-left: 0;
      }
      div[style*="margin-left: 280px"] {
        margin-left: 0 !important;
      }

      .container {
        padding: 20px 16px;
      }

      .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
      }

      .stat-card {
        padding: 20px;
      }

      .stat-number {
        font-size: 28px;
      }

      .actions-grid {
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 16px;
      }

      .action-card {
        padding: 16px 12px;
      }

      .action-card-icon {
        font-size: 24px;
      }
    }
  </style>
</head>
<body>
  <?php require_once '../includes/sidebar.php'; ?>
  
  <!-- Main Content -->
  <div style="padding: 0;">
    <!-- Top Bar with Profile -->
    <div class="top-bar">
      <div class="top-bar-left">
        <h1>Admin Dashboard</h1>
        <p>Welcome back, manage your intellectual property system</p>
      </div>
      <!-- Moved profile to top right with circular photo -->
      <div class="profile-section">
        <div style="display: flex; align-items: center; gap: 12px;">
          <div class="profile-avatar">
            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
          </div>
          <div class="profile-info">
            <div class="profile-name"><?php echo htmlspecialchars($user_name); ?></div>
            <div class="profile-role">Role: <?php echo ucfirst($user_role); ?></div>
          </div>
        </div>
        <a href="../?logout" class="logout-btn">
          <i class="fas fa-sign-out-alt"></i>
          Logout
        </a>
      </div>
    </div>

    <!-- Container -->
    <div class="container">
      <!-- Stats Grid -->
      <div class="stats-grid">
        <!-- Submitted Applications -->
        <div class="stat-card">
          <div style="display: flex; align-items: flex-start; justify-content: space-between;">
            <div>
              <p class="stat-label">Submitted</p>
              <p class="stat-number"><?php echo $submitted_count; ?></p>
            </div>
            <div class="stat-icon" style="background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%); color: white;">
              <i class="fas fa-file"></i>
            </div>
          </div>
          <p style="font-size: 13px; color: #94A3B8; margin-top: 12px;">
            <i class="fas fa-arrow-up" style="color: #10B981; margin-right: 4px;"></i>
            <span>Applications pending review</span>
          </p>
        </div>

        <!-- Payment Pending -->
        <div class="stat-card">
          <div style="display: flex; align-items: flex-start; justify-content: space-between;">
            <div>
              <p class="stat-label">Awaiting Payment</p>
              <p class="stat-number"><?php echo $payment_pending; ?></p>
            </div>
            <div class="stat-icon" style="background: linear-gradient(135deg, #DAA520 0%, #F4A460 100%); color: white;">
              <i class="fas fa-money-bill"></i>
            </div>
          </div>
          <p style="font-size: 13px; color: #94A3B8; margin-top: 12px;">
            <i class="fas fa-arrow-up" style="color: #F59E0B; margin-right: 4px;"></i>
            <span>Awaiting cashier payment</span>
          </p>
        </div>

        <!-- Payment Verified -->
        <div class="stat-card">
          <div style="display: flex; align-items: flex-start; justify-content: space-between;">
            <div>
              <p class="stat-label">Payment Verified</p>
              <p class="stat-number"><?php echo $payment_verified; ?></p>
            </div>
            <div class="stat-icon" style="background: linear-gradient(135deg, #06B6D4 0%, #0891B2 100%); color: white;">
              <i class="fas fa-circle-check"></i>
            </div>
          </div>
          <p style="font-size: 13px; color: #94A3B8; margin-top: 12px;">
            <i class="fas fa-check-circle" style="color: #06B6D4; margin-right: 4px;"></i>
            <span>Payment successfully confirmed</span>
          </p>
        </div>

        <!-- Approved -->
        <div class="stat-card">
          <div style="display: flex; align-items: flex-start; justify-content: space-between;">
            <div>
              <p class="stat-label">Approved</p>
              <p class="stat-number"><?php echo $approved_count; ?></p>
            </div>
            <div class="stat-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white;">
              <i class="fas fa-thumbs-up"></i>
            </div>
          </div>
          <p style="font-size: 13px; color: #94A3B8; margin-top: 12px;">
            <i class="fas fa-trending-up" style="color: #10B981; margin-right: 4px;"></i>
            <span>Final approvals issued</span>
          </p>
        </div>
      </div>

      <!-- Quick Actions -->
      <div>
        <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 20px; color: #1E293B;">
          <i class="fas fa-lightning-bolt" style="color: #DAA520; margin-right: 8px;"></i>
          Quick Actions
        </h2>
        <div class="actions-grid">
          <!-- ... existing action cards ... -->
          <a href="verify-applications.php" class="action-card">
            <div class="action-card-icon" style="color: #0A4D2E;">
              <i class="fas fa-check-square"></i>
            </div>
            <div style="font-weight: 600; font-size: 14px;">Review Applications</div>
          </a>
          <a href="verify-payments.php" class="action-card">
            <div class="action-card-icon" style="color: #DAA520;">
              <i class="fas fa-money-check-alt"></i>
            </div>
            <div style="font-weight: 600; font-size: 14px;">Payment Management</div>
          </a>
          <a href="manage-badges.php" class="action-card">
            <div class="action-card-icon" style="color: #8B5CF6;">
              <i class="fas fa-medal"></i>
            </div>
            <div style="font-weight: 600; font-size: 14px;">Manage Badges</div>
          </a>
          <a href="manage-certificate-template.php" class="action-card">
            <div class="action-card-icon" style="color: #06B6D4;">
              <i class="fas fa-certificate"></i>
            </div>
            <div style="font-weight: 600; font-size: 14px;">Certificate Template</div>
          </a>
          <a href="manage-users.php" class="action-card">
            <div class="action-card-icon" style="color: #EC4899;">
              <i class="fas fa-users"></i>
            </div>
            <div style="font-weight: 600; font-size: 14px;">Manage Users</div>
          </a>
          <a href="manage-form-fields.php" class="action-card">
            <div class="action-card-icon" style="color: #F59E0B;">
              <i class="fas fa-puzzle-piece"></i>
            </div>
            <div style="font-weight: 600; font-size: 14px;">Form Builder</div>
          </a>
          <?php if ($user_role === 'director'): ?>
          <a href="approve-applications.php" class="action-card">
            <div class="action-card-icon" style="color: #10B981;">
              <i class="fas fa-thumbs-up"></i>
            </div>
            <div style="font-weight: 600; font-size: 14px;">Approve Applications</div>
          </a>
          <a href="analytics.php" class="action-card">
            <div class="action-card-icon" style="color: #8B5CF6;">
              <i class="fas fa-chart-bar"></i>
            </div>
            <div style="font-weight: 600; font-size: 14px;">Analytics</div>
          </a>
          <a href="audit-log.php" class="action-card">
            <div class="action-card-icon" style="color: #64748B;">
              <i class="fas fa-history"></i>
            </div>
            <div style="font-weight: 600; font-size: 14px;">Audit Trail</div>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <footer style="background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%); color: white; padding: 32px 24px; text-align: center; margin-top: 60px;">
      <p style="font-size: 14px; opacity: 0.9;">&copy; <?php echo date('Y'); ?> Carlos Hilado Memorial State University. All rights reserved.</p>
      <p style="margin-top: 8px; font-size: 12px; opacity: 0.8;">Intellectual Property Office • Talisay City Negros Occidental</p>
    </footer>
  </div>
</body>
</html>
