<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireLogin();

$user_id = getCurrentUserId();
$user_role = getUserRole();

// Allow all users including clerk and director to view their profile

// Get user info
$stmt = $conn->prepare("
  SELECT u.full_name, u.email, u.department, u.innovation_points, u.created_at, p.contact_number 
  FROM users u 
  LEFT JOIN user_profiles p ON u.id = p.user_id 
  WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get user works (for all roles, in case clerk/director also have IP applications)
$works_result = $conn->query("
  SELECT a.*, 
         COUNT(DISTINCT v.id) as view_count,
         c.certificate_number,
         c.id as certificate_id
  FROM ip_applications a
  LEFT JOIN view_tracking v ON a.id = v.application_id
  LEFT JOIN certificates c ON a.id = c.application_id
  WHERE a.user_id = $user_id
  GROUP BY a.id
  ORDER BY a.created_at DESC
");
$works = $works_result->fetch_all(MYSQLI_ASSOC);

// Get user badges with application info
$badges_result = $conn->query("
  SELECT b.*, a.title as work_title, a.ip_type as work_ip_type
  FROM badges b
  LEFT JOIN ip_applications a ON b.application_id = a.id
  WHERE b.user_id = $user_id 
  ORDER BY b.awarded_at DESC
");
$badges = $badges_result->fetch_all(MYSQLI_ASSOC);

// Check for achievement certificate
$achievement_cert_stmt = $conn->prepare("SELECT * FROM achievement_certificates WHERE user_id = ?");
$achievement_cert_stmt->bind_param("i", $user_id);
$achievement_cert_stmt->execute();
$achievement_cert = $achievement_cert_stmt->get_result()->fetch_assoc();
$achievement_cert_stmt->close();

// Get user certificates
$certs_result = $conn->query("
  SELECT c.*, a.title, a.ip_type, a.abstract 
  FROM certificates c
  JOIN ip_applications a ON c.application_id = a.id
  WHERE a.user_id = $user_id
  ORDER BY c.issued_at DESC
");
$certificates = $certs_result->fetch_all(MYSQLI_ASSOC);

// Get user stats
$stats_query = $conn->query("
  SELECT 
    COUNT(DISTINCT a.id) as total_works,
    COUNT(DISTINCT v.id) as total_views,
    SUM(CASE WHEN a.status='approved' THEN 1 ELSE 0 END) as approved_works
  FROM ip_applications a
  LEFT JOIN view_tracking v ON a.id = v.application_id
  WHERE a.user_id = $user_id
");
$stats = $stats_query->fetch_assoc();

// Get pending publish permission requests
$pending_permission_stmt = $conn->prepare("SELECT COUNT(*) as count FROM ip_applications WHERE user_id = ? AND publish_permission = 'pending'");
$pending_permission_stmt->bind_param("i", $user_id);
$pending_permission_stmt->execute();
$pending_permissions = $pending_permission_stmt->get_result()->fetch_assoc()['count'];
$pending_permission_stmt->close();

// Get admin stats for clerk/director
$admin_stats = [];
if (in_array($user_role, ['clerk', 'director'])) {
  $admin_stats['submitted_count'] = $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='submitted'")->fetch_assoc()['count'];
  $admin_stats['payment_pending'] = $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='payment_pending'")->fetch_assoc()['count'];
  $admin_stats['payment_verified'] = $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='payment_verified'")->fetch_assoc()['count'];
  $admin_stats['total_applications'] = $conn->query("SELECT COUNT(*) as count FROM ip_applications")->fetch_assoc()['count'];
  $admin_stats['approved_count'] = $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='approved'")->fetch_assoc()['count'];
  if ($user_role === 'director') {
    $admin_stats['pending_approval'] = $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='payment_verified'")->fetch_assoc()['count'];
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile - CHMSU IP System</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
    
    .back-btn {
      background: rgba(255,255,255,0.15);
      color: white;
      padding: 10px 18px;
      border-radius: 10px;
      text-decoration: none;
      font-size: 14px;
      font-weight: 600;
      transition: all 0.2s;
      backdrop-filter: blur(10px);
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .back-btn:hover {
      background: rgba(255,255,255,0.25);
      transform: translateY(-1px);
    }
    
    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 40px 24px;
    }
    
    /* Profile header */
    .profile-header {
      background: white;
      border-radius: 20px;
      padding: 40px;
      margin-bottom: 32px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.06);
      border: 1px solid rgba(0,0,0,0.05);
      display: flex;
      align-items: center;
      gap: 32px;
    }
    
    .profile-avatar {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: #DAA520;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 48px;
      font-weight: 700;
      box-shadow: 0 8px 24px rgba(10, 77, 46, 0.3);
    }
    
    .profile-info {
      flex: 1;
    }
    
    .profile-name {
      font-size: 32px;
      font-weight: 800;
      color: #0A4D2E;
      margin-bottom: 8px;
      letter-spacing: -0.5px;
    }
    
    .profile-dept {
      font-size: 16px;
      color: #64748B;
      margin-bottom: 20px;
    }
    
    .profile-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 24px;
    }
    
    .stat-item {
      text-align: center;
      padding: 16px;
      background: #F8FAFC;
      border-radius: 12px;
    }
    
    .stat-number {
      font-size: 28px;
      font-weight: 700;
      color: #0A4D2E;
      margin-bottom: 4px;
    }

    .work-abstract {
    font-size: 13px;
    color: #555;
    margin-bottom: 10px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
    overflow-wrap: anywhere;
    word-break: break-word;
    max-width: 100%;
    }
    .stat-label {
      font-size: 13px;
      color: #64748B;
      font-weight: 600;
    }
    
    .content-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
      gap: 32px;
    }
    
    .section {
      background: white;
      border-radius: 20px;
      padding: 32px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.06);
      border: 1px solid rgba(0,0,0,0.05);
    }
    
    .section-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 24px;
      padding-bottom: 16px;
      border-bottom: 2px solid #F1F5F9;
    }
    
    .section-title {
      font-size: 20px;
      font-weight: 700;
      color: #0A4D2E;
    }
    
    /* Badges */
    .badges-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 20px;
    }
    
    .badge-card {
      text-align: center;
      padding: 24px;
      background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%);
      border-radius: 16px;
      border: 2px solid #BBF7D0;
      transition: all 0.3s;
    }
    
    .badge-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 24px rgba(10, 77, 46, 0.15);
    }
    
    .badge-icon {
      width: 80px;
      height: 80px;
      margin: 0 auto 16px;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 36px;
      color: #DAA520;
      box-shadow: 0 8px 20px rgba(10, 77, 46, 0.3);
    }
    
    .badge-name {
      font-size: 16px;
      font-weight: 700;
      color: #0A4D2E;
      margin-bottom: 8px;
    }
    
    .badge-desc {
      font-size: 13px;
      color: #64748B;
      margin-bottom: 12px;
    }
    
    .badge-date {
      font-size: 12px;
      color: #94A3B8;
    }
    
    /* Certificates */
    .cert-list {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    
    .cert-card {
      padding: 24px;
      background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
      border-radius: 16px;
      border: 2px solid #FCD34D;
      transition: all 0.3s;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 20px;
    }
    
    .cert-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 24px rgba(218, 165, 32, 0.3);
    }
    
    .cert-info {
      flex: 1;
    }
    
    .cert-title {
      font-size: 18px;
      font-weight: 700;
      color: #92400E;
      margin-bottom: 8px;
    }
    
    .cert-meta {
      display: flex;
      gap: 16px;
      font-size: 13px;
      color: #78350F;
      margin-bottom: 8px;
    }
    
    .cert-number {
      font-size: 12px;
      color: #A16207;
      font-family: monospace;
    }
    
    .cert-btn {
      background: linear-gradient(135deg, #92400E 0%, #78350F 100%);
      color: white;
      padding: 12px 24px;
      border-radius: 10px;
      text-decoration: none;
      font-size: 14px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s;
      box-shadow: 0 4px 12px rgba(146, 64, 14, 0.3);
    }
    
    .cert-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(146, 64, 14, 0.4);
    }
    
    .empty {
      text-align: center;
      padding: 60px 20px;
      color: #94A3B8;
    }
    
    .empty i {
      font-size: 48px;
      margin-bottom: 16px;
      color: #CBD5E1;
    }
    
    .empty p {
      font-size: 16px;
      font-weight: 600;
    }
    
    @media (max-width: 768px) {
      .profile-header {
        flex-direction: column;
        text-align: center;
      }
      
      .content-grid {
        grid-template-columns: 1fr;
      }
      
      .cert-card {
        flex-direction: column;
        text-align: center;
      }
    }
  </style>
</head>
<body>
  <div class="navbar">
    <div class="logo">
      <i class="fas fa-user-circle"></i>
      <span>My Profile</span>
    </div>
    <a href="../dashboard.php" class="back-btn">
      <i class="fas fa-arrow-left"></i> Dashboard
    </a>
  </div>
  
  <div class="container">
    <div class="profile-header">
      <div class="profile-avatar">
        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
      </div>
      <div class="profile-info">
        <h1 class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></h1>
        <p class="profile-dept"><?php echo htmlspecialchars($user['department'] ?? 'CHMSU'); ?></p>
        <div class="profile-stats">
          <?php if ($user_role === 'user'): ?>
          <div class="stat-item">
            <div class="stat-number"><?php echo $stats['total_works']; ?></div>
            <div class="stat-label">Total Works</div>
          </div>
          <div class="stat-item">
            <div class="stat-number"><?php echo $stats['approved_works']; ?></div>
            <div class="stat-label">Approved</div>
          </div>
          <div class="stat-item">
            <div class="stat-number"><?php echo $stats['total_views']; ?></div>
            <div class="stat-label">Total Views</div>
          </div>
          <div class="stat-item">
            <div class="stat-number"><?php echo $user['innovation_points'] ?? 0; ?></div>
            <div class="stat-label">Points</div>
          </div>
          <?php elseif (in_array($user_role, ['clerk', 'director'])): ?>
          <div class="stat-item">
            <div class="stat-number"><?php echo $admin_stats['total_applications'] ?? 0; ?></div>
            <div class="stat-label">Total Applications</div>
          </div>
          <div class="stat-item">
            <div class="stat-number"><?php echo $admin_stats['submitted_count'] ?? 0; ?></div>
            <div class="stat-label">Pending Review</div>
          </div>
          <div class="stat-item">
            <div class="stat-number"><?php echo $admin_stats['payment_pending'] ?? 0; ?></div>
            <div class="stat-label">Awaiting Payment</div>
          </div>
          <div class="stat-item">
            <div class="stat-number"><?php echo $admin_stats['approved_count'] ?? 0; ?></div>
            <div class="stat-label">Approved</div>
          </div>
          <?php if ($user_role === 'director'): ?>
          <div class="stat-item">
            <div class="stat-number"><?php echo $admin_stats['pending_approval'] ?? 0; ?></div>
            <div class="stat-label">Pending Approval</div>
          </div>
          <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <?php if ($pending_permissions > 0): ?>
    <div style="background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%); border: 2px solid #F59E0B; border-radius: 16px; padding: 24px; margin-bottom: 32px; display: flex; align-items: center; justify-content: space-between; gap: 20px;">
      <div style="display: flex; align-items: center; gap: 16px;">
        <div style="width: 56px; height: 56px; background: #F59E0B; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
          <i class="fas fa-bell" style="font-size: 24px; color: white;"></i>
        </div>
        <div>
          <h3 style="color: #92400E; font-size: 18px; margin-bottom: 4px;">Publishing Permission Required</h3>
          <p style="color: #78350F; font-size: 14px;">You have <?php echo $pending_permissions; ?> approved work(s) waiting for your permission to be displayed in the public IP Hub.</p>
        </div>
      </div>
      <a href="../app/permission-request.php" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); color: white; padding: 14px 28px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); white-space: nowrap;">
        <i class="fas fa-globe"></i> Review Permissions
      </a>
    </div>
    <?php endif; ?>
    
    <div class="content-grid">
      <!-- Personal Information Section -->
      <div class="section">
        <div class="section-header">
          <i class="fas fa-user" style="font-size: 24px; color: #667eea;"></i>
          <h2 class="section-title">Personal Information</h2>
        </div>
        <div style="padding: 20px;">
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div>
              <div style="font-size: 12px; color: #666; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;">Full Name</div>
              <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($user['full_name']); ?></div>
            </div>
            <div>
              <div style="font-size: 12px; color: #666; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;">Email</div>
              <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
            <div>
              <div style="font-size: 12px; color: #666; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;">Department</div>
              <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></div>
            </div>
            <div>
              <div style="font-size: 12px; color: #666; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;">Contact Number</div>
              <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($user['contact_number'] ?? 'N/A'); ?></div>
            </div>
            <div>
              <div style="font-size: 12px; color: #666; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;">Member Since</div>
              <div style="font-weight: 600; color: #333;"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
            </div>
          </div>
        </div>
      </div>
      
      <?php if ($user_role === 'user'): ?>
      
      <!-- Works Section -->
      <div class="section">
        <div class="section-header">
          <i class="fas fa-lightbulb" style="font-size: 24px; color: #6366F1;"></i>
          <h2 class="section-title">My IP Works</h2>
        </div>
        <?php if (count($works) === 0): ?>
          <div class="empty">
            <i class="fas fa-folder-open"></i>
            <p>No IP works yet</p>
            <p style="font-size: 13px; margin-top: 8px;">Start by submitting your first IP application!</p>
          </div>
        <?php else: ?>
          <div style="padding: 20px;">
            <?php foreach ($works as $work): ?>
              <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 15px; border-left: 4px solid #1B5C3B;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                <div style="flex: 1; min-width: 0;">
                    <h3 style="color: #333; margin-bottom: 8px; font-size: 16px;"><?php echo htmlspecialchars($work['title']); ?></h3>
                    <div style="display: flex; gap: 15px; font-size: 12px; color: #666; margin-bottom: 8px;">
                      <span><i class="fas fa-tag"></i> <?php echo $work['ip_type']; ?></span>
                      <span><i class="fas fa-eye"></i> <?php echo $work['view_count']; ?> views</span>
                      <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($work['created_at'])); ?></span>
                    </div>
                    <div style="font-size: 13px; color: #555; margin-bottom: 10px; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word;">
                      <?php echo htmlspecialchars($work['abstract']); ?>
                    </div>
                  </div>
                  <div style="margin-left: 15px;">
                    <span style="display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #DCFCE7; color: #166534; text-transform: capitalize;">
                      <?php echo str_replace('_', ' ', $work['status']); ?>
                    </span>
                  </div>
                </div>
                <div style="display: flex; gap: 10px;">
                  <a href="../app/view-application.php?id=<?php echo $work['id']; ?>" style="padding: 8px 15px; background: #1B5C3B; color: white; text-decoration: none; border-radius: 5px; font-size: 12px; font-weight: 600;">
                    <i class="fas fa-eye"></i> View Details
                  </a>
                  <?php if (!empty($work['certificate_id'])): ?>
                    <a href="../certificate/generate.php?id=<?php echo $work['certificate_id']; ?>" style="padding: 8px 15px; background: #DAA520; color: white; text-decoration: none; border-radius: 5px; font-size: 12px; font-weight: 600;">
                      <i class="fas fa-certificate"></i> View Certificate
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php endif; ?>
      
      <?php if (in_array($user_role, ['clerk', 'director']) && count($works) > 0): ?>
      <!-- My IP Works Section (for clerk/director who have applications) -->
      <div class="section">
        <div class="section-header">
          <i class="fas fa-lightbulb" style="font-size: 24px; color: #6366F1;"></i>
          <h2 class="section-title">My IP Works</h2>
        </div>
        <div style="padding: 20px;">
          <?php foreach ($works as $work): ?>
            <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 15px; border-left: 4px solid #1B5C3B;">
              <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
              <div style="flex: 1; min-width: 0;">
                  <h3 style="color: #333; margin-bottom: 8px; font-size: 16px;"><?php echo htmlspecialchars($work['title']); ?></h3>
                  <div style="display: flex; gap: 15px; font-size: 12px; color: #666; margin-bottom: 8px;">
                    <span><i class="fas fa-tag"></i> <?php echo $work['ip_type']; ?></span>
                    <span><i class="fas fa-eye"></i> <?php echo $work['view_count']; ?> views</span>
                    <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($work['created_at'])); ?></span>
                  </div>
                  <div class="work-abstract">
                    <?php echo nl2br(htmlspecialchars($work['abstract'])); ?>
                  </div>
                </div>
                <div style="margin-left: 15px;">
                  <span style="display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #DCFCE7; color: #166534; text-transform: capitalize;">
                    <?php echo str_replace('_', ' ', $work['status']); ?>
                  </span>
                </div>
              </div>
              <div style="display: flex; gap: 10px;">
                <a href="../app/view-application.php?id=<?php echo $work['id']; ?>" style="padding: 8px 15px; background: #1B5C3B; color: white; text-decoration: none; border-radius: 5px; font-size: 12px; font-weight: 600;">
                  <i class="fas fa-eye"></i> View Details
                </a>
                <?php if (!empty($work['certificate_id'])): ?>
                  <a href="../certificate/generate.php?id=<?php echo $work['certificate_id']; ?>" style="padding: 8px 15px; background: #DAA520; color: white; text-decoration: none; border-radius: 5px; font-size: 12px; font-weight: 600;">
                    <i class="fas fa-certificate"></i> View Certificate
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      
      <?php if (in_array($user_role, ['clerk', 'director'])): ?>
      <!-- Admin Quick Actions Section -->
      <div class="section">
        <div class="section-header">
          <i class="fas fa-tasks" style="font-size: 24px; color: #1B5C3B;"></i>
          <h2 class="section-title">Quick Actions</h2>
        </div>
        <div style="padding: 20px;">
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <!-- Removed Apply for Others link -->
            <a href="../admin/verify-applications.php" style="padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px; text-decoration: none; text-align: center; transition: transform 0.2s; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);">
              <i class="fas fa-check-square" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
              <div style="font-weight: 600; font-size: 14px;">Review Applications</div>
            </a>
            <a href="../admin/verify-payments.php" style="padding: 20px; background: linear-gradient(135deg, #E07D32 0%, #D2691E 100%); color: white; border-radius: 12px; text-decoration: none; text-align: center; transition: transform 0.2s; box-shadow: 0 4px 12px rgba(224, 125, 50, 0.3);">
              <i class="fas fa-credit-card" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
              <div style="font-weight: 600; font-size: 14px;">Verify Payments</div>
            </a>
            <?php if ($user_role === 'director'): ?>
            <a href="../admin/approve-applications.php" style="padding: 20px; background: linear-gradient(135deg, #DAA520 0%, #FFD700 100%); color: white; border-radius: 12px; text-decoration: none; text-align: center; transition: transform 0.2s; box-shadow: 0 4px 12px rgba(218, 165, 32, 0.3);">
              <i class="fas fa-gavel" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
              <div style="font-weight: 600; font-size: 14px;">Approve Applications</div>
            </a>
            <a href="../admin/analytics.php" style="padding: 20px; background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%); color: white; border-radius: 12px; text-decoration: none; text-align: center; transition: transform 0.2s; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);">
              <i class="fas fa-chart-bar" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
              <div style="font-weight: 600; font-size: 14px;">Analytics</div>
            </a>
            <?php endif; ?>
            <a href="../admin/manage-users.php" style="padding: 20px; background: linear-gradient(135deg, #0EA5E9 0%, #0284C7 100%); color: white; border-radius: 12px; text-decoration: none; text-align: center; transition: transform 0.2s; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);">
              <i class="fas fa-users" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
              <div style="font-weight: 600; font-size: 14px;">Manage Users</div>
            </a>
            <a href="../admin/dashboard.php" style="padding: 20px; background: linear-gradient(135deg, #64748B 0%, #475569 100%); color: white; border-radius: 12px; text-decoration: none; text-align: center; transition: transform 0.2s; box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);">
              <i class="fas fa-gear" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
              <div style="font-weight: 600; font-size: 14px;">Admin Panel</div>
            </a>
          </div>
        </div>
      </div>
      <?php endif; ?>
      
      <?php if ($user_role === 'user'): ?>
      <div class="section">
        <div class="section-header">
          <i class="fas fa-medal" style="font-size: 24px; color: #DAA520;"></i>
          <h2 class="section-title">Badges Earned</h2>
        </div>
        
        <?php if (count($badges) === 0): ?>
          <div class="empty">
            <i class="fas fa-trophy"></i>
            <p>No badges earned yet</p>
            <p style="font-size: 13px; margin-top: 8px;">Share your IP works to earn views and unlock badges!</p>
          </div>
        <?php else: ?>
          <div class="badges-grid">
            <?php foreach ($badges as $badge): ?>
              <div class="badge-card">
                <div class="badge-icon">
                  <i class="fas fa-award"></i>
                </div>
                <div class="badge-name"><?php echo htmlspecialchars($badge['badge_type']); ?> Badge</div>
                <?php if (!empty($badge['work_title'])): ?>
                  <div class="badge-desc" style="font-weight: 600; margin-bottom: 5px;">For: <?php echo htmlspecialchars($badge['work_title']); ?></div>
                  <div class="badge-desc" style="font-size: 11px; color: #64748B;"><?php echo htmlspecialchars($badge['work_ip_type'] ?? $badge['ip_type'] ?? 'IP Work'); ?> • <?php echo $badge['views_required']; ?>+ views</div>
                <?php else: ?>
                  <div class="badge-desc"><?php echo $badge['views_required']; ?>+ views milestone</div>
                <?php endif; ?>
                <div class="badge-date">Earned <?php echo date('M d, Y', strtotime($badge['awarded_at'])); ?></div>
                <a href="../app/view-badge.php?id=<?php echo $badge['id']; ?>" class="cert-btn" style="margin-top: 12px; display: block; text-align: center;" target="_blank">
                  <i class="fas fa-eye"></i> View Badge
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      
      <?php if ($user_role === 'user'): ?>
      <?php if ($achievement_cert): ?>
      <div class="section">
        <div class="section-header">
          <i class="fas fa-trophy" style="font-size: 24px; color: #FFD700;"></i>
          <h2 class="section-title">Achievement Certificate</h2>
        </div>
        <div style="padding: 20px; background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%); border-radius: 16px; text-align: center;">
          <div style="font-size: 48px; margin-bottom: 15px;">🏆</div>
          <h3 style="color: #92400E; margin-bottom: 10px; font-size: 20px;">Diamond Achievement Unlocked!</h3>
          <p style="color: #78350F; margin-bottom: 15px;">Congratulations! Your IP work has reached Diamond tier (500+ views) and you have earned an Achievement Certificate!</p>
          
          <div style="background: white; padding: 15px; border-radius: 8px; margin-top: 15px; margin-bottom: 15px;">
            <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Certificate Number</div>
            <div style="font-weight: 700; color: #92400E; font-size: 18px;"><?php echo htmlspecialchars($achievement_cert['certificate_number']); ?></div>
            <div style="font-size: 12px; color: #666; margin-top: 8px;">Issued: <?php echo date('F d, Y', strtotime($achievement_cert['issued_at'])); ?></div>
          </div>
          
          <a href="../certificate/view-achievement.php?id=<?php echo htmlspecialchars($achievement_cert['certificate_number']); ?>" 
             style="display: inline-block; background: #92400E; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; box-shadow: 0 4px 12px rgba(146, 64, 14, 0.3); transition: transform 0.2s;" target="_blank">
            <i class="fas fa-eye" style="margin-right: 8px;"></i> View Full Certificate
          </a>
        </div>
      </div>
      <?php endif; ?>
      
      <div class="section">
        <div class="section-header">
          <i class="fas fa-certificate" style="font-size: 24px; color: #92400E;"></i>
          <h2 class="section-title">IP Registration Certificates</h2>
        </div>
        
        <?php if (count($certificates) === 0): ?>
          <div class="empty">
            <i class="fas fa-scroll"></i>
            <p>No certificates yet</p>
            <p style="font-size: 13px; margin-top: 8px;">Certificates are issued when your IP works are approved</p>
          </div>
        <?php else: ?>
          <div class="cert-list">
            <?php foreach ($certificates as $cert): ?>
              <div class="cert-card">
                <div class="cert-info">
                  <div class="cert-title"><?php echo htmlspecialchars($cert['title']); ?></div>
                  <div class="cert-meta">
                    <span><i class="fas fa-tag"></i> <?php echo $cert['ip_type']; ?></span>
                    <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($cert['issued_at'])); ?></span>
                  </div>
                  <div class="cert-number">
                    <i class="fas fa-hashtag"></i> <?php echo $cert['certificate_number']; ?>
                  </div>
                </div>
                <a href="../certificate/generate.php?id=<?php echo $cert['id']; ?>" class="cert-btn" target="_blank">
                  <i class="fas fa-eye"></i> View Certificate
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    
    <!-- Settings Bar - Bottom -->
    <?php if ($user_role === 'user'): ?>
    <div style="background: white; border-radius: 16px; padding: 20px 32px; margin-top: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
      <div style="font-size: 14px; color: #64748B;">
        <i class="fas fa-cog" style="margin-right: 8px;"></i> Account Settings
      </div>
      <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <a href="../app/permission-request.php" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; color: #475569; text-decoration: none; font-size: 13px; font-weight: 500; transition: all 0.2s;">
          <i class="fas fa-globe"></i> Privacy & Hub Visibility
        </a>
        <a href="../app/my-applications.php" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; color: #475569; text-decoration: none; font-size: 13px; font-weight: 500; transition: all 0.2s;">
          <i class="fas fa-folder"></i> My Applications
        </a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>
