<?php
// Sidebar navigation component for CHMSU IP System
// Usage: require_once 'includes/sidebar.php';

$current_page = basename($_SERVER['PHP_SELF']);
$base_path = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : '';
$is_admin = in_array(getUserRole(), ['clerk', 'director']);
$user_role = getUserRole();
$user_name = $_SESSION['full_name'] ?? 'User';
$user_email = $_SESSION['email'] ?? '';
?>

<style>
  /* Sidebar Styles */
  .sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 280px;
    height: 100vh;
    background: linear-gradient(180deg, #0A4D2E 0%, #0F3D2E 100%);
    color: white;
    overflow-y: auto;
    z-index: 999;
    padding-top: 0;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
  }

  .sidebar-header {
    padding: 24px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.2);
    display: flex;
    align-items: center;
    gap: 12px;
    position: sticky;
    top: 0;
    z-index: 1000;
  }

  .sidebar-logo {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    flex-shrink: 0;
  }

  .sidebar-logo img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
  }

  .sidebar-brand {
    flex: 1;
  }

  .sidebar-brand h3 {
    font-size: 14px;
    font-weight: 700;
    margin: 0;
    line-height: 1.2;
    letter-spacing: -0.3px;
  }

  .sidebar-brand p {
    font-size: 11px;
    opacity: 0.8;
    margin: 4px 0 0 0;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .sidebar-toggle {
    display: none;
    background: none;
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    padding: 8px;
    margin-left: auto;
  }

  .sidebar-content {
    padding: 20px 0;
  }

  .sidebar-section {
    margin-bottom: 24px;
  }

  .sidebar-section-title {
    padding: 12px 20px;
    font-size: 11px;
    font-weight: 700;
    color: #DAA520;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.9;
  }

  .sidebar-nav a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
    border-left: 3px solid transparent;
    position: relative;
  }

  .sidebar-nav a:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border-left-color: #DAA520;
  }

  .sidebar-nav a.active {
    background: rgba(218, 165, 32, 0.2);
    color: #DAA520;
    border-left-color: #DAA520;
    font-weight: 600;
  }

  .sidebar-nav a i {
    width: 20px;
    text-align: center;
    font-size: 16px;
  }

  .sidebar-nav a.active i {
    color: #DAA520;
  }

  /* Profile section in sidebar - hidden by default */
  .sidebar-profile {
    display: none;
    padding: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.2);
    margin-top: auto;
  }

  .profile-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: rgba(218, 165, 32, 0.1);
    border-radius: 10px;
    margin-bottom: 12px;
  }

  .profile-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #DAA520 0%, #FFD700 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0A4D2E;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(218, 165, 32, 0.3);
  }

  .profile-info {
    flex: 1;
    min-width: 0;
  }

  .profile-name {
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .profile-email {
    font-size: 11px;
    opacity: 0.8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* Main content offset */
  body {
    margin-left: 0;
  }

  body.sidebar-active {
    margin-left: 280px;
  }

  .navbar {
    margin-left: 0;
    position: relative;
    z-index: 100;
  }

  /* Sidebar Footer */
  .sidebar-footer {
    padding: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin-top: auto;
    text-align: center;
    font-size: 12px;
    opacity: 0.7;
  }
  
  /* Mobile Menu Trigger Button */
  #mobile-menu-trigger {
    position: fixed;
    top: 16px;
    left: 16px;
    z-index: 998; /* Below sidebar (999), above content */
    background: white;
    color: #0A4D2E;
    border: none;
    border-radius: 8px;
    width: 40px;
    height: 40px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    cursor: pointer;
    transition: all 0.2s;
  }
  
  #mobile-menu-trigger:active {
    transform: scale(0.95);
  }

  /* Enhanced responsive design for tablets and mobile */
  @media (max-width: 1200px) {
    .sidebar {
      width: 240px;
    }
    body.sidebar-active {
      margin-left: 240px;
    }
  }

  @media (max-width: 1024px) {
    .sidebar {
      width: 240px;
    }
    body.sidebar-active {
      margin-left: 240px;
    }
    .sidebar-profile {
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .profile-card {
      width: 100%;
    }
  }

  @media (max-width: 768px) {
    .sidebar {
      width: 280px;
      height: 100vh;
      position: fixed;
      left: 0;
      top: 0;
      transform: translateX(-100%);
      box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
      z-index: 10000;
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .sidebar.open {
      transform: translateX(0);
    }

    body {
      margin-left: 0 !important;
    }

    body.sidebar-active {
      margin-left: 0;
    }

    .sidebar-toggle {
      display: block;
    }

    .sidebar-profile {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 16px;
    }

    .profile-card {
      width: 100%;
      margin-bottom: 16px;
    }
    
    /* Show persistent mobile trigger */
    #mobile-menu-trigger {
      display: flex;
    }
    
    /* Adjust top bar padding to accommodate the button */
    .top-bar {
      padding-left: 64px !important;
    }
  }

  @media (max-width: 480px) {
    .sidebar.open {
      width: 85%;
    }
    .sidebar-section-title {
      font-size: 10px;
    }
    .sidebar-nav a {
      padding: 12px 16px;
      font-size: 13px;
    }
    .sidebar-logo {
      width: 40px;
      height: 40px;
    }
    .sidebar-brand h3 {
      font-size: 12px;
    }
    .sidebar-brand p {
      font-size: 9px;
    }
  }
</style>

<!-- Mobile Menu Trigger -->
<button id="mobile-menu-trigger" aria-label="Open sidebar">
  <i class="fas fa-bars"></i>
</button>

<div class="sidebar" id="sidebar">
  <!-- Sidebar Header -->
  <div class="sidebar-header">
    <div class="sidebar-logo">
      <img src="<?php echo $base_path; ?>public/logos/chmsu-logo.png" alt="CHMSU" onerror="this.src='<?php echo $base_path; ?>public/logos/chmsu-logo.jpg'; this.onerror=null;">
    </div>
    <div class="sidebar-brand">
      <h3><?php echo $is_admin ? 'Admin' : 'Dashboard'; ?></h3>
      <p><?php echo ucfirst($user_role); ?></p>
    </div>
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Close sidebar">
      <i class="fas fa-times"></i>
    </button>
  </div>

  <!-- Sidebar Content -->
  <div class="sidebar-content">
    <!-- User Navigation -->
    <?php if (!$is_admin): ?>
      <div class="sidebar-section">
        <div class="sidebar-section-title">Menu</div>
        <div class="sidebar-nav">
          <a href="<?php echo $base_path; ?>dashboard.php" <?php echo ($current_page === 'dashboard.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-gauge"></i>
            <span>Dashboard</span>
          </a>
          <a href="<?php echo $base_path; ?>app/my-applications.php" <?php echo ($current_page === 'my-applications.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-file-lines"></i>
            <span>My Applications</span>
          </a>
          <a href="<?php echo $base_path; ?>hub/browse.php" <?php echo ($current_page === 'browse.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-magnifying-glass"></i>
            <span>IP Hub</span>
          </a>
          <a href="<?php echo $base_path; ?>profile/badges-certificates.php" <?php echo ($current_page === 'badges-certificates.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-medal"></i>
            <span>My Profile</span>
          </a>
          <a href="<?php echo $base_path; ?>help.php" <?php echo ($current_page === 'help.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-circle-question"></i>
            <span>Help & Guide</span>
          </a>
        </div>
      </div>

      <!-- User Actions -->
      <div class="sidebar-section">
        <div class="sidebar-section-title">Actions</div>
        <div class="sidebar-nav">
          <a href="<?php echo $base_path; ?>app/apply.php">
            <i class="fas fa-plus-circle"></i>
            <span>Submit New IP</span>
          </a>
        </div>
      </div>
    <?php endif; ?>

    <!-- Admin Navigation -->
    <?php if ($is_admin): ?>
      <div class="sidebar-section">
        <div class="sidebar-section-title">Dashboard</div>
        <div class="sidebar-nav">
          <a href="<?php echo $base_path; ?>admin/dashboard.php" <?php echo ($current_page === 'admin/dashboard.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-gauge"></i>
            <span>Overview</span>
          </a>
          <a href="<?php echo $base_path; ?>hub/browse.php" <?php echo ($current_page === 'browse.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-magnifying-glass"></i>
            <span>Browse IP Hub</span>
          </a>
        </div>
      </div>

      <div class="sidebar-section">
        <div class="sidebar-section-title">Management</div>
        <div class="sidebar-nav">
          <a href="<?php echo $base_path; ?>admin/verify-applications.php" <?php echo ($current_page === 'verify-applications.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-check-square"></i>
            <span>Review Applications</span>

          <a href="<?php echo $base_path; ?>admin/verify-payments.php" <?php echo ($current_page === 'verify-payments.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-money-check-alt"></i>
            <span>Payment Verification</span>
          </a>
          <a href="<?php echo $base_path; ?>admin/manage-badges.php" <?php echo ($current_page === 'manage-badges.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-medal"></i>
            <span>Manage Badges</span>
          </a>
          <a href="<?php echo $base_path; ?>admin/manage-users.php" <?php echo ($current_page === 'manage-users.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-users"></i>
            <span>Manage Users</span>
          </a>
          <a href="<?php echo $base_path; ?>admin/manage-certificate-template.php" <?php echo ($current_page === 'manage-certificate-template.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-certificate"></i>
            <span>Certificate Template</span>
          </a>
          <a href="<?php echo $base_path; ?>admin/manage-form-fields.php" <?php echo ($current_page === 'manage-form-fields.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-puzzle-piece"></i>
            <span>Form Builder</span>
          </a>
        </div>
      </div>

      <?php if ($user_role === 'director'): ?>
      <div class="sidebar-section">
        <div class="sidebar-section-title">Director Only</div>
        <div class="sidebar-nav">
          <a href="<?php echo $base_path; ?>admin/approve-applications.php" <?php echo ($current_page === 'approve-applications.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-thumbs-up"></i>
            <span>Approve Applications</span>
          </a>
          <a href="<?php echo $base_path; ?>admin/analytics.php" <?php echo ($current_page === 'analytics.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-chart-bar"></i>
            <span>Analytics</span>
          </a>
          <a href="<?php echo $base_path; ?>admin/audit-log.php" <?php echo ($current_page === 'audit-log.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-history"></i>
            <span>Audit Trail</span>
          </a>
        </div>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Added profile section on sidebar for mobile/tablet views -->
  <div class="sidebar-profile">
    <div class="profile-card">
      <div class="profile-avatar">
        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
      </div>
      <div class="profile-info">
        <div class="profile-name"><?php echo htmlspecialchars($user_name); ?></div>
        <div class="profile-email"><?php echo htmlspecialchars($user_email); ?></div>
      </div>
    </div>
    <a href="<?php echo $base_path; ?>?logout" style="display: flex; align-items: center; gap: 8px; color: white; text-decoration: none; font-size: 13px; font-weight: 600; padding: 10px 16px; background: rgba(255,255,255,0.1); border-radius: 8px; transition: all 0.2s; text-align: center; justify-content: center; width: 100%;">
      <i class="fas fa-sign-out-alt"></i>
      Logout
    </a>
  </div>

  <!-- Sidebar Footer -->
  <div class="sidebar-footer">
    <p>&copy; <?php echo date('Y'); ?> CHMSU IP Office</p>
  </div>
</div>

<script>
  // Apply sidebar-active class to body for desktop view layout
  if (window.innerWidth > 768) {
    document.body.classList.add('sidebar-active');
  }

  // Mobile sidebar toggle functionality
  const sidebar = document.getElementById('sidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const mobileTrigger = document.getElementById('mobile-menu-trigger');
  let overlay = document.querySelector('.sidebar-overlay');

  // Create overlay if it doesn't exist
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100vw';
    overlay.style.height = '100vh';
    overlay.style.background = 'rgba(0,0,0,0.5)';
    overlay.style.zIndex = '9999';
    overlay.style.opacity = '0';
    overlay.style.visibility = 'hidden';
    overlay.style.transition = 'all 0.3s ease';
    document.body.appendChild(overlay);
  }

  function toggleSidebar() {
    const isOpen = sidebar.classList.contains('open');
    if (isOpen) {
      sidebar.classList.remove('open');
      overlay.style.opacity = '0';
      overlay.style.visibility = 'hidden';
      document.body.style.overflow = ''; // Restore scrolling
    } else {
      sidebar.classList.add('open');
      overlay.style.opacity = '1';
      overlay.style.visibility = 'visible';
      document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }
  }

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function(e) {
      e.stopPropagation();
      toggleSidebar();
    });
  }
  
  // Attach event to mobile trigger
  if (mobileTrigger) {
    mobileTrigger.addEventListener('click', function(e) {
      e.stopPropagation();
      toggleSidebar();
    });
  }

  // Close sidebar when clicking overlay
  overlay.addEventListener('click', function() {
    if (sidebar.classList.contains('open')) {
      toggleSidebar();
    }
  });

  // Close sidebar when clicking on a link (mobile only)
  if (window.innerWidth <= 768) {
    const sidebarLinks = sidebar.querySelectorAll('.sidebar-nav a, .sidebar-profile a');
    sidebarLinks.forEach(link => {
      link.addEventListener('click', function() {
        if (sidebar.classList.contains('open')) {
          toggleSidebar();
        }
      });
    });
  }

  // Handle resize events
  window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
      // Reset state for desktop
      sidebar.classList.remove('open');
      overlay.style.opacity = '0';
      overlay.style.visibility = 'hidden';
      document.body.style.overflow = '';
      document.body.classList.add('sidebar-active');
    } else {
      document.body.classList.remove('sidebar-active');
    }
  });
</script>
