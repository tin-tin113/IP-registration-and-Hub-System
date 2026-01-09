<?php
// Header component for CHMSU IP System
// Usage: require_once 'includes/header.php';

// Get user info if logged in
$is_logged_in = isLoggedIn();
$user_name = $is_logged_in ? $_SESSION['full_name'] ?? 'User' : '';
$user_role = $is_logged_in ? getUserRole() : '';
$user_id = $is_logged_in ? getCurrentUserId() : null;

// Determine header type: 'public' or 'authenticated'
$header_type = $is_logged_in ? 'authenticated' : 'public';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'CHMSU IP System'; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="public/logo-styles.css" rel="stylesheet">
  <?php if (isset($additional_css)): ?>
    <?php foreach ($additional_css as $css): ?>
      <link href="<?php echo htmlspecialchars($css); ?>" rel="stylesheet">
    <?php endforeach; ?>
  <?php endif; ?>
  <style>
    /* Header Styles */
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
      50% { transform: translateY(-8px); }
    }
    
    .nav-right {
      display: flex;
      align-items: center;
      gap: 12px;
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
    
    .nav-buttons {
      display: flex;
      gap: 12px;
      align-items: center;
    }
    
    .btn {
      padding: 10px 20px;
      border-radius: 10px;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-login {
      background: transparent;
      color: white;
      border: 1px solid rgba(255,255,255,0.3);
    }
    
    .btn-login:hover {
      background: rgba(255,255,255,0.1);
    }
    
    .btn-signup {
      background: rgba(255,255,255,0.2);
      color: white;
      border: none;
    }
    
    .btn-signup:hover {
      background: rgba(255,255,255,0.3);
      transform: translateY(-2px);
    }
  </style>
</head>
<body>
  <div class="navbar">
    <div class="logo">
      <img src="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/app/') !== false || strpos($_SERVER['PHP_SELF'], '/auth/') !== false || strpos($_SERVER['PHP_SELF'], '/hub/') !== false || strpos($_SERVER['PHP_SELF'], '/profile/') !== false || strpos($_SERVER['PHP_SELF'], '/certificate/') !== false) ? '../' : ''; ?>public/logos/chmsu-logo.png" alt="CHMSU" class="logo-img" onerror="this.src='<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/app/') !== false || strpos($_SERVER['PHP_SELF'], '/auth/') !== false || strpos($_SERVER['PHP_SELF'], '/hub/') !== false || strpos($_SERVER['PHP_SELF'], '/profile/') !== false || strpos($_SERVER['PHP_SELF'], '/certificate/') !== false) ? '../' : ''; ?>public/logos/chmsu-logo.jpg'; this.onerror=null;">
      <span><?php echo isset($header_title) ? htmlspecialchars($header_title) : 'CHMSU IP System'; ?></span>
    </div>
    <div class="nav-right">
      <?php if ($is_logged_in): ?>
        <div class="user-info">
          <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
          <div class="user-details">
            <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
            <div class="user-role"><?php echo ucfirst($user_role); ?></div>
          </div>
        </div>
        <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/app/') !== false || strpos($_SERVER['PHP_SELF'], '/auth/') !== false || strpos($_SERVER['PHP_SELF'], '/hub/') !== false || strpos($_SERVER['PHP_SELF'], '/profile/') !== false || strpos($_SERVER['PHP_SELF'], '/certificate/') !== false) ? '../' : ''; ?>?logout" class="btn-logout">
          <i class="fas fa-arrow-right-from-bracket"></i>
          Logout
        </a>
      <?php else: ?>
        <div class="nav-buttons">
          <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/app/') !== false || strpos($_SERVER['PHP_SELF'], '/auth/') !== false || strpos($_SERVER['PHP_SELF'], '/hub/') !== false || strpos($_SERVER['PHP_SELF'], '/profile/') !== false || strpos($_SERVER['PHP_SELF'], '/certificate/') !== false) ? '../' : ''; ?>auth/login.php" class="btn btn-login">
            <i class="fas fa-arrow-right-to-bracket"></i> Sign In
          </a>
          <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/app/') !== false || strpos($_SERVER['PHP_SELF'], '/auth/') !== false || strpos($_SERVER['PHP_SELF'], '/hub/') !== false || strpos($_SERVER['PHP_SELF'], '/profile/') !== false || strpos($_SERVER['PHP_SELF'], '/certificate/') !== false) ? '../' : ''; ?>auth/register.php" class="btn btn-signup">
            Get Started
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>
