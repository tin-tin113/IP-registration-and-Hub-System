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
      background: #F1F5F9;
      min-height: 100vh;
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
    
    @media (max-width: 768px) {
      .user-details {
        display: none;
      }
      
      .welcome-card h1 {
        font-size: 28px;
      }
    }
  </style>
</head>
<body>
  <div class="navbar">
    <div class="logo">
      <img src="public/logos/chmsu-logo.png" alt="CHMSU" class="logo-img" onerror="this.src='public/logos/chmsu-logo.jpg'; this.onerror=null;">
      <span>CHMSU IP System</span>
    </div>
    <div class="nav-right">
      <div class="user-info">
        <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
        <div class="user-details">
          <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
          <div class="user-role"><?php echo ucfirst($user_role); ?></div>
        </div>
      </div>
      <a href="?logout" class="btn-logout">
        <i class="fas fa-arrow-right-from-bracket"></i>
        Logout
      </a>
    </div>
  </div>
  
  <div class="container">
    <div class="welcome-card">
      <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>!</h1>
      <p>CHMSU Intellectual Property Registration and Hub System</p>
    </div>
    
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-file-lines"></i>
        </div>
        <div class="stat-number"><?php echo $stats['total']; ?></div>
        <div class="stat-label">Total Applications</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #DAA520 0%, #FFD700 100%); color: white;">
          <i class="fas fa-circle-check"></i>
        </div>
        <div class="stat-number" style="color: #DAA520;"><?php echo $stats['approved']; ?></div>
        <div class="stat-label">Approved Works</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%); color: white;">
          <i class="fas fa-lightbulb"></i>
        </div>
        <div class="stat-number" style="color: #6366F1;"><?php echo $user_points; ?></div>
        <div class="stat-label">Innovation Points</div>
      </div>
    </div>
    
    <div class="actions-grid">
      <?php if ($user_role === 'user'): ?>
        <a href="app/apply.php" class="action-btn">
          <div class="action-icon">
            <i class="fas fa-plus-circle"></i>
          </div>
          <span>Submit New IP</span>
        </a>
        <a href="app/my-applications.php" class="action-btn">
          <div class="action-icon">
            <i class="fas fa-folder-open"></i>
          </div>
          <span>View Applications</span>
        </a>
      <?php endif; ?>
      <a href="hub/browse.php" class="action-btn">
        <div class="action-icon">
          <i class="fas fa-magnifying-glass"></i>
        </div>
        <span>Browse IP Hub</span>
      </a>
      <a href="profile/badges-certificates.php" class="action-btn">
        <div class="action-icon">
          <i class="fas fa-user-circle"></i>
        </div>
        <span>My Profile</span>
      </a>
      <!-- Updated help link to new help.php page -->
      <a href="help.php" class="action-btn">
        <div class="action-icon">
          <i class="fas fa-circle-question"></i>
        </div>
        <span>Help & Guide</span>
      </a>
      <?php if (in_array($user_role, ['clerk', 'director'])): ?>
        <a href="admin/dashboard.php" class="action-btn">
          <div class="action-icon" style="background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);">
            <i class="fas fa-gear"></i>
          </div>
          <span>Admin Panel</span>
        </a>
      <?php endif; ?>
    </div>
    
    <?php if (count($recent) > 0): ?>
      <div class="recent-apps">
        <div class="section-header">
          <i class="fas fa-clock-rotate-left"></i>
          Recent Applications
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
  <footer class="footer" style="background: #1E293B; color: white; padding: 40px 24px; text-align: center; margin-top: 60px;">
    <p style="opacity: 0.8; font-size: 14px;">&copy; 2025 Carlos Hilado Memorial State University. All rights reserved.</p>
    <p style="margin-top: 8px; font-size: 12px; opacity: 0.8;">Intellectual Property Office • Carlos Hilado Memorial State University • Talisay City Negros Occidental</p>
  </footer>
</body>
</html>
