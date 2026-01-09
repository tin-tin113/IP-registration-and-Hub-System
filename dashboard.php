<?php
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'config/session.php';

requireLogin();

$user_id = getCurrentUserId();
$user_role = getUserRole();
$user_email = $_SESSION['email'];
$user_name = $_SESSION['full_name'];

// Get user stats
$stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved FROM ip_applications WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get innovation points
$points_stmt = $conn->prepare("SELECT innovation_points FROM users WHERE id = ?");
$points_stmt->bind_param("i", $user_id);
$points_stmt->execute();
$points_result = $points_stmt->get_result();
$user_points = $points_result->fetch_assoc()['innovation_points'] ?? 0;
$points_stmt->close();

// Get recent applications
$stmt = $conn->prepare("SELECT id, title, ip_type, status, created_at FROM ip_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Check for pending publish permission requests
$pending_permission_stmt = $conn->prepare("SELECT COUNT(*) as count FROM ip_applications WHERE user_id = ? AND publish_permission = 'pending'");
$pending_permission_stmt->bind_param("i", $user_id);
$pending_permission_stmt->execute();
$pending_permissions = $pending_permission_stmt->get_result()->fetch_assoc()['count'];
$pending_permission_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - CHMSU IP System</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="public/logo-styles.css" rel="stylesheet">
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
    
    .navbar {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
      padding: 16px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 100;
    }
    
    .logo {
      font-size: 18px;
      font-weight: 700;
      color: white;
      display: flex;
      align-items: center;
      gap: 12px;
      letter-spacing: -0.3px;
    }
    
    .logo-img {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
      border: 4px solid #E07D32;
      background: white;
      padding: 3px;
      box-shadow: 0 8px 20px rgba(27, 92, 59, 0.3), 0 0 0 2px rgba(255, 255, 255, 0.1);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      animation: float 3s ease-in-out infinite;
    }
    
    .logo-img:hover {
      transform: scale(1.15) rotate(8deg);
      box-shadow: 0 12px 30px rgba(27, 92, 59, 0.5), 0 0 25px rgba(224, 125, 50, 0.7);
      border-color: #FFD700;
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-5px); }
    }
    
    .user-info {
      display: flex;
      align-items: center;
      gap: 12px;
      color: white;
      padding: 8px 16px;
      background: rgba(255,255,255,0.1);
      border-radius: 12px;
      backdrop-filter: blur(10px);
    }
    
    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, #DAA520 0%, #FFD700 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #0A4D2E;
      font-weight: 700;
      font-size: 16px;
      box-shadow: 0 4px 12px rgba(218, 165, 32, 0.3);
    }
    
    .user-details {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    
    .user-name {
      font-weight: 600;
      font-size: 14px;
    }
    
    .user-role {
      font-size: 12px;
      opacity: 0.9;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .btn-logout {
      background: rgba(255,255,255,0.15);
      color: white;
      padding: 10px 18px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.2s;
      backdrop-filter: blur(10px);
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-logout:hover {
      background: rgba(255,255,255,0.25);
      transform: translateY(-1px);
    }
    
    .container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 32px 24px;
    }
    
    .welcome-card {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      border-radius: 20px;
      padding: 40px;
      margin-bottom: 32px;
      box-shadow: 0 8px 32px rgba(10, 77, 46, 0.3);
      color: white;
      position: relative;
      overflow: hidden;
    }
    
    .welcome-card::before {
      content: '';
      position: absolute;
      width: 300px;
      height: 300px;
      background: radial-gradient(circle, rgba(218, 165, 32, 0.2) 0%, transparent 70%);
      top: -150px;
      right: -150px;
      border-radius: 50%;
    }
    
    .welcome-card h1 {
      font-size: 36px;
      font-weight: 700;
      margin-bottom: 8px;
      position: relative;
      letter-spacing: -0.5px;
    }
    
    .welcome-card p {
      font-size: 16px;
      opacity: 0.9;
      font-weight: 500;
      position: relative;
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 24px;
      margin-bottom: 32px;
    }
    
    .stat-card {
      background: white;
      border-radius: 16px;
      padding: 28px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.06);
      transition: all 0.3s;
      border: 1px solid rgba(0,0,0,0.05);
    }
    
    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 32px rgba(0,0,0,0.12);
    }
    
    .stat-icon {
      width: 56px;
      height: 56px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      margin-bottom: 20px;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: #DAA520;
      box-shadow: 0 8px 20px rgba(10, 77, 46, 0.2);
    }
    
    .stat-number {
      font-size: 36px;
      font-weight: 700;
      color: #0A4D2E;
      margin-bottom: 8px;
      letter-spacing: -1px;
    }
    
    .stat-label {
      color: #64748B;
      font-size: 14px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .actions-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
      margin-bottom: 32px;
    }
    
    .action-btn {
      background: white;
      border: 1px solid rgba(0,0,0,0.06);
      border-radius: 16px;
      padding: 28px 24px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      color: #1E293B;
      box-shadow: 0 4px 16px rgba(0,0,0,0.04);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 16px;
    }
    
    .action-btn:hover {
      transform: translateY(-6px);
      box-shadow: 0 12px 32px rgba(10, 77, 46, 0.15);
      border-color: #1B7F4D;
    }
    
    .action-icon {
      width: 64px;
      height: 64px;
      border-radius: 16px;
      background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      color: #0A4D2E;
    }
    
    .action-btn span {
      font-weight: 600;
      font-size: 15px;
      color: #1E293B;
    }
    
    .recent-apps {
      background: white;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 16px rgba(0,0,0,0.06);
      border: 1px solid rgba(0,0,0,0.05);
    }
    
    .section-header {
      padding: 24px 28px;
      border-bottom: 1px solid #F1F5F9;
      font-size: 18px;
      font-weight: 700;
      color: #0A4D2E;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .app-row {
      padding: 20px 28px;
      border-bottom: 1px solid #F1F5F9;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: background 0.2s;
    }
    
    .app-row:hover {
      background: #F8FAFC;
    }
    
    .app-row:last-child {
      border-bottom: none;
    }
    
    .app-info h4 {
      color: #1E293B;
      margin-bottom: 6px;
      font-weight: 600;
      font-size: 15px;
    }
    
    .app-info p {
      font-size: 13px;
      color: #64748B;
      font-weight: 500;
    }
    
    .status-badge {
      display: inline-block;
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      background: #DCFCE7;
      color: #166534;
      text-transform: capitalize;
    }
    
    .top-bar {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      padding: 20px 32px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 24px;
    }

    .top-bar-left {
      width: 100%;
    }

    .top-bar-left h1 {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 4px;
    }

    .top-bar-left p {
      opacity: 0.9;
      font-size: 14px;
    }

    .profile-section {
      display: flex;
      align-items: center;
      gap: 16px;
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

    @media (max-width: 1024px) {
      .top-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
        padding: 16px 24px;
      }
      
      .top-bar-left {
        width: 100%;
      }
      
      .profile-section {
        width: 100%;
        justify-content: space-between;
        margin-top: 8px;
      }

      body.sidebar-active {
        margin-left: 240px;
      }
      
      div[style*="margin-left: 280px"] {
        margin-left: 240px !important;
      }

      .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      }
    }

    @media (max-width: 768px) {
      body.sidebar-active {
        margin-left: 0;
      }
      
      div[style*="margin-left: 280px"] {
        margin-left: 0 !important;
      }

      .container {
        padding: 20px 16px;
      }

      .top-bar {
        padding: 16px;
      }

      .top-bar-left h1 {
        font-size: 20px;
      }

      .profile-section {
        padding: 10px 16px;
        background: rgba(255,255,255,0.15);
      }
      
      .stats-grid {
        grid-template-columns: 1fr; /* Stack vertically on mobile */
        gap: 16px;
      }

      .stat-card {
        padding: 20px;
      }
      
      .stat-number {
        font-size: 28px;
      }

      .actions-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
      }
      
      .action-btn {
        padding: 20px 12px;
      }
      
      .action-icon {
        width: 48px;
        height: 48px;
        font-size: 24px;
      }
      
      .app-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
      }
      
      .status-badge {
        align-self: flex-start;
      }
    }

    @media (max-width: 480px) {
      .actions-grid {
        grid-template-columns: 1fr;
      }

      .top-bar-left h1 {
        font-size: 18px;
      }
      
      .profile-section {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
      }
      
      .logout-btn {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
</head>
<body>
  <?php require_once 'includes/sidebar.php'; ?>
  
  <!-- Main Content -->
  <div style="padding: 0;">
    <!-- Top Bar -->
    <div class="top-bar">
      <div class="top-bar-left">
        <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>!</h1>
        <p>CHMSU Intellectual Property Registration and Hub System</p>
      </div>

      <div class="profile-section">
        <div style="display: flex; align-items: center; gap: 12px;">
          <div class="profile-avatar">
            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
          </div>
          <div class="profile-info">
            <div class="profile-name"><?php echo htmlspecialchars($user_name); ?></div>
            <div class="profile-role"><?php echo ucfirst($user_role); ?></div>
          </div>
        </div>
        <a href="?logout" class="logout-btn">
          <i class="fas fa-sign-out-alt"></i>
          Logout
        </a>
      </div>
    </div>

    <!-- Container -->
    <div class="container">
      <!-- Alert Messages -->
      <?php if (isset($_GET['success'])): ?>
        <div style="background: #D1FAE5; color: #065F46; padding: 16px; border-radius: 12px; margin-bottom: 24px; border-left: 4px solid #10B981; display: flex; align-items: center; gap: 12px;">
          <i class="fas fa-check-circle" style="font-size: 18px;"></i>
          <span style="font-weight: 500;"><?php echo htmlspecialchars($_GET['success']); ?></span>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['error'])): ?>
        <div style="background: #FEE2E2; color: #7F1D1D; padding: 16px; border-radius: 12px; margin-bottom: 24px; border-left: 4px solid #EF4444; display: flex; align-items: center; gap: 12px;">
          <i class="fas fa-exclamation-triangle" style="font-size: 18px;"></i>
          <span style="font-weight: 500;"><?php echo htmlspecialchars($_GET['error']); ?></span>
        </div>
      <?php endif; ?>

      <?php if ($pending_permissions > 0): ?>
        <div style="background: #FEF3C7; color: #92400E; padding: 16px; border-radius: 12px; margin-bottom: 24px; border-left: 4px solid #F59E0B; display: flex; align-items: center; gap: 12px;">
          <i class="fas fa-bell" style="font-size: 18px;"></i>
          <span style="font-weight: 500;">You have <?php echo $pending_permissions; ?> approved work(s) waiting for publishing permission. <a href="app/permission-request.php" style="color: #92400E; font-weight: 700; text-decoration: underline;">Review now</a></span>
        </div>
      <?php endif; ?>

      <!-- Stats Grid -->
      <div class="stats-grid">
        <div class="stat-card">
          <div style="display: flex; align-items: flex-start; justify-content: space-between;">
            <div>
              <p class="stat-label">Total Applications</p>
              <p class="stat-number"><?php echo $stats['total']; ?></p>
            </div>
            <div class="stat-icon">
              <i class="fas fa-file-lines"></i>
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div style="display: flex; align-items: flex-start; justify-content: space-between;">
            <div>
              <p class="stat-label">Approved Works</p>
              <p class="stat-number"><?php echo $stats['approved']; ?></p>
            </div>
            <div class="stat-icon">
              <i class="fas fa-circle-check"></i>
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div style="display: flex; align-items: flex-start; justify-content: space-between;">
            <div>
              <p class="stat-label">Innovation Points</p>
              <p class="stat-number"><?php echo $user_points; ?></p>
            </div>
            <div class="stat-icon">
              <i class="fas fa-lightbulb"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div style="margin-bottom: 32px;">
        <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 20px; color: #1E293B;">
          <i class="fas fa-lightning-bolt" style="color: #DAA520; margin-right: 8px;"></i>
          Quick Actions
        </h2>
        <div class="actions-grid">
          <a href="app/apply.php" class="action-btn">
            <div class="action-icon">
              <i class="fas fa-plus-circle"></i>
            </div>
            <div>Submit New IP</div>
          </a>
          <a href="app/my-applications.php" class="action-btn">
            <div class="action-icon">
              <i class="fas fa-folder-open"></i>
            </div>
            <div>View Applications</div>
          </a>
          <a href="hub/browse.php" class="action-btn">
            <div class="action-icon">
              <i class="fas fa-magnifying-glass"></i>
            </div>
            <div>Browse IP Hub</div>
          </a>
          <a href="profile/badges-certificates.php" class="action-btn">
            <div class="action-icon">
              <i class="fas fa-user-circle"></i>
            </div>
            <div>My Profile</div>
          </a>
          <a href="help.php" class="action-btn">
            <div class="action-icon">
              <i class="fas fa-circle-question"></i>
            </div>
            <div>Help & Guide</div>
          </a>
        </div>
      </div>

      <!-- Recent Applications -->
      <?php if (count($recent) > 0): ?>
      <div class="recent-apps">
        <div class="section-header">
          <h2 style="margin: 0;">Recent Applications</h2>
        </div>
        <?php foreach ($recent as $app): ?>
          <div class="app-row">
            <div class="app-info">
              <h4><?php echo htmlspecialchars($app['title']); ?></h4>
              <p><?php echo date('M d, Y', strtotime($app['created_at'])); ?> • <?php echo $app['ip_type']; ?></p>
            </div>
            <span class="status-badge"><?php echo str_replace('_', ' ', $app['status']); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer style="background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%); color: white; padding: 32px 24px; text-align: center; margin-top: 60px;">
      <p style="font-size: 14px; opacity: 0.9;">&copy; <?php echo date('Y'); ?> Carlos Hilado Memorial State University. All rights reserved.</p>
      <p style="margin-top: 8px; font-size: 12px; opacity: 0.8;">Intellectual Property Office • Talisay City Negros Occidental</p>
    </footer>
  </div>
</body>
</html>
