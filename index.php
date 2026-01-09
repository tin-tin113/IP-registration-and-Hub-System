<?php
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'config/session.php';

// If logged in, redirect to dashboard
if (isLoggedIn()) {
  header('Location: dashboard.php');
  exit;
}

// Get some stats for the landing page
$total_ips = $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='approved'")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='user'")->fetch_assoc()['count'];
$recent_ips = $conn->query("SELECT title, ip_type, DATE_FORMAT(approved_at, '%b %Y') as date FROM ip_applications WHERE status='approved' ORDER BY approved_at DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CHMSU Intellectual Property Office - Protecting Innovation</title>
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
      background: #FAFAFA;
      color: #1E293B;
      overflow-x: hidden;
    }
    
    /* Modern navigation with CHMSU branding */
    .navbar {
      background: white;
      border-bottom: 1px solid #E2E8F0;
      padding: 16px 24px;
      position: sticky;
      top: 0;
      z-index: 100;
      backdrop-filter: blur(10px);
      background: rgba(255,255,255,0.95);
    }
    
    .nav-container {
      max-width: 1280px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .logo-section {
      display: flex;
      align-items: center;
      gap: 12px;
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
    }
    
    .logo-img:hover {
      transform: scale(1.15) rotate(8deg);
      box-shadow: 0 12px 30px rgba(27, 92, 59, 0.5), 0 0 25px rgba(224, 125, 50, 0.7);
      border-color: #FFD700;
    }
    
    .logo-text {
      font-size: 18px;
      font-weight: 700;
      color: #0A4D2E;
      letter-spacing: -0.3px;
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
      color: #0A4D2E;
      border: 1px solid #E2E8F0;
    }
    
    .btn-login:hover {
      background: #F8FAFC;
      border-color: #0A4D2E;
    }
    
    .btn-signup {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      border: none;
      box-shadow: 0 4px 12px rgba(10, 77, 46, 0.3);
    }
    
    .btn-signup:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(10, 77, 46, 0.4);
    }
    
    /* Hero section inspired by modern SaaS landing pages */
    .hero {
      padding: 100px 24px;
      text-align: center;
      background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 50%, #FEF3C7 100%);
      position: relative;
      overflow: hidden;
    }
    
    .hero::before {
      content: '';
      position: absolute;
      width: 600px;
      height: 600px;
      background: radial-gradient(circle, rgba(10, 77, 46, 0.1) 0%, transparent 70%);
      top: -300px;
      right: -300px;
      border-radius: 50%;
    }
    
    .hero-content {
      max-width: 900px;
      margin: 0 auto;
      position: relative;
    }
    
    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: white;
      padding: 8px 16px;
      border-radius: 50px;
      font-size: 13px;
      font-weight: 600;
      color: #0A4D2E;
      margin-bottom: 24px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    
    .hero h1 {
      font-size: 64px;
      font-weight: 800;
      color: #0A4D2E;
      margin-bottom: 24px;
      line-height: 1.1;
      letter-spacing: -2px;
    }
    
    .hero p {
      font-size: 20px;
      color: #475569;
      margin-bottom: 40px;
      line-height: 1.6;
      max-width: 700px;
      margin-left: auto;
      margin-right: auto;
    }
    
    .hero-buttons {
      display: flex;
      gap: 16px;
      justify-content: center;
      flex-wrap: wrap;
    }
    
    .btn-primary {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      padding: 16px 32px;
      border-radius: 12px;
      text-decoration: none;
      font-weight: 700;
      font-size: 16px;
      box-shadow: 0 8px 24px rgba(10, 77, 46, 0.3);
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 10px;
    }
    
    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 32px rgba(10, 77, 46, 0.4);
    }
    
    .btn-secondary {
      background: white;
      color: #0A4D2E;
      padding: 16px 32px;
      border-radius: 12px;
      text-decoration: none;
      font-weight: 700;
      font-size: 16px;
      border: 2px solid #0A4D2E;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 10px;
    }
    
    .btn-secondary:hover {
      background: #0A4D2E;
      color: white;
      transform: translateY(-3px);
    }
    
    /* Stats section */
    .stats {
      background: white;
      padding: 60px 24px;
      border-top: 1px solid #E2E8F0;
      border-bottom: 1px solid #E2E8F0;
    }
    
    .stats-container {
      max-width: 1280px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 40px;
      text-align: center;
    }
    
    .stat-item {
      padding: 24px;
    }
    
    .stat-number {
      font-size: 48px;
      font-weight: 800;
      color: #0A4D2E;
      margin-bottom: 8px;
      letter-spacing: -2px;
    }
    
    .stat-label {
      font-size: 16px;
      color: #64748B;
      font-weight: 600;
    }
    
    /* Features section */
    .features {
      padding: 100px 24px;
      background: white;
    }
    
    .features-container {
      max-width: 1280px;
      margin: 0 auto;
    }
    
    .section-header {
      text-align: center;
      margin-bottom: 64px;
    }
    
    .section-badge {
      display: inline-block;
      background: linear-gradient(135deg, #DCFCE7 0%, #FEF3C7 100%);
      color: #0A4D2E;
      padding: 6px 14px;
      border-radius: 50px;
      font-size: 13px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 16px;
    }
    
    .section-title {
      font-size: 48px;
      font-weight: 800;
      color: #0A4D2E;
      margin-bottom: 16px;
      letter-spacing: -1.5px;
    }
    
    .section-description {
      font-size: 18px;
      color: #64748B;
      max-width: 600px;
      margin: 0 auto;
      line-height: 1.6;
    }
    
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 32px;
    }
    
    .feature-card {
      background: #F8FAFC;
      border: 1px solid #E2E8F0;
      border-radius: 16px;
      padding: 32px;
      transition: all 0.3s;
    }
    
    .feature-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 12px 40px rgba(0,0,0,0.1);
      border-color: #1B7F4D;
    }
    
    .feature-icon {
      width: 56px;
      height: 56px;
      border-radius: 14px;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: #DAA520;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      margin-bottom: 20px;
      box-shadow: 0 8px 20px rgba(10, 77, 46, 0.2);
    }
    
    .feature-card h3 {
      font-size: 20px;
      color: #0A4D2E;
      margin-bottom: 12px;
      font-weight: 700;
    }
    
    .feature-card p {
      color: #64748B;
      line-height: 1.6;
      font-size: 15px;
    }
    
    /* CTA section */
    .cta {
      padding: 100px 24px;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      text-align: center;
      color: white;
      position: relative;
      overflow: hidden;
    }
    
    .cta::before {
      content: '';
      position: absolute;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(218, 165, 32, 0.2) 0%, transparent 70%);
      top: -250px;
      right: -250px;
      border-radius: 50%;
    }
    
    .cta-content {
      max-width: 700px;
      margin: 0 auto;
      position: relative;
    }
    
    .cta h2 {
      font-size: 48px;
      font-weight: 800;
      margin-bottom: 20px;
      letter-spacing: -1.5px;
    }
    
    .cta p {
      font-size: 20px;
      margin-bottom: 40px;
      opacity: 0.95;
    }
    
    .cta .btn-primary {
      background: white;
      color: #0A4D2E;
    }
    
    .cta .btn-primary:hover {
      background: #DAA520;
      color: white;
    }
    
    /* Footer */
    .footer {
      background: #1E293B;
      color: white;
      padding: 40px 24px;
      text-align: center;
    }
    
    .footer p {
      opacity: 0.8;
      font-size: 14px;
    }
    
    /* Responsive Design */
    @media (max-width: 1024px) {
      .hero h1 {
        font-size: 56px;
      }
      
      .stats-container {
        gap: 20px;
      }
    }

    @media (max-width: 768px) {
      .navbar {
        padding: 12px 16px;
      }

      .nav-container {
        flex-direction: column;
        gap: 16px;
      }
      
      .logo-section {
        width: 100%;
        justify-content: center;
      }

      .nav-buttons {
        width: 100%;
        justify-content: center;
      }

      .hero {
        padding: 60px 20px;
      }

      .hero h1 {
        font-size: 36px;
        letter-spacing: -1px;
      }
      
      .hero p {
        font-size: 16px;
        margin-bottom: 30px;
      }
      
      .hero-buttons {
        flex-direction: column;
        gap: 12px;
      }
      
      .btn-primary, .btn-secondary {
        width: 100%;
        justify-content: center;
        padding: 14px 24px;
      }

      .section-title {
        font-size: 32px;
      }

      .stats {
        padding: 40px 20px;
      }

      .stat-item {
        padding: 16px;
      }

      .stat-number {
        font-size: 36px;
      }

      .features {
        padding: 60px 20px;
      }

      .section-header {
        margin-bottom: 40px;
      }

      .cta {
        padding: 60px 20px;
      }

      .cta h2 {
        font-size: 32px;
      }

      .cta p {
        font-size: 16px;
      }
    }

    @media (max-width: 480px) {
      .logo-text {
        font-size: 16px;
      }

      .logo-img {
        width: 40px;
        height: 40px;
      }

      .btn {
        padding: 8px 16px;
        font-size: 13px;
        flex: 1;
        justify-content: center;
      }

      .hero h1 {
        font-size: 32px;
      }

      .hero::before {
        display: none; /* Remove large blob on small screens to prevent overflow/distraction */
      }
      
      .cta::before {
        display: none;
      }
    }
  </style>
</head>
<body>
  <!-- Navigation -->
  <nav class="navbar">
    <div class="nav-container">
      <div class="logo-section">
        <img src="public/logos/chmsu-logo.png" alt="CHMSU" class="logo-img" onerror="this.src='public/logos/chmsu-logo.jpg'; this.onerror=null;">
        <span class="logo-text">CHMSU IP Registration and Hub </span>
      </div>
      <div class="nav-buttons">
        <a href="auth/login.php" class="btn btn-login">
          <i class="fas fa-arrow-right-to-bracket"></i> Sign In
        </a>
        <a href="auth/register.php" class="btn btn-signup">
          Get Started
        </a>
      </div>
    </div>
  </nav>
  
  <!-- Hero Section -->
  <section class="hero">
    <div class="hero-content">
      <div class="hero-badge">
        <i class="fas fa-sparkles"></i>
        Protecting Innovation Since 2025
      </div>
      <h1>Protect Your Intellectual Property</h1>
      <p>The official CHMSU Intellectual Property Registration and Management System. Register, protect, and showcase your innovative works with our streamlined digital platform.</p>
      <div class="hero-buttons">
        <a href="auth/register.php" class="btn-primary">
          Start Registration <i class="fas fa-arrow-right"></i>
        </a>
        <a href="hub/browse.php" class="btn-secondary">
          <i class="fas fa-magnifying-glass"></i> Browse IP Hub
        </a>
      </div>
    </div>
  </section>
  
  <!-- Stats Section -->
  <section class="stats">
    <div class="stats-container">
      <div class="stat-item">
        <div class="stat-number"><?php echo $total_ips; ?>+</div>
        <div class="stat-label">Registered IP Works</div>
      </div>
      <div class="stat-item">
        <div class="stat-number"><?php echo $total_users; ?>+</div>
        <div class="stat-label">Active Researchers</div>
      </div>
      <div class="stat-item">
        <div class="stat-number">100%</div>
        <div class="stat-label">Digital & Secure</div>
      </div>
    </div>
  </section>
  
  <!-- Features Section -->
  <section class="features">
    <div class="features-container">
      <div class="section-header">
        <span class="section-badge">Our Services</span>
        <h2 class="section-title">Everything You Need</h2>
        <p class="section-description">A comprehensive platform designed to protect, manage, and showcase intellectual property at CHMSU</p>
      </div>
      
      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-file-circle-check"></i>
          </div>
          <h3>Easy Registration</h3>
          <p>Submit your intellectual property works through our streamlined digital application process with secure document upload.</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-shield-halved"></i>
          </div>
          <h3>Secure Protection</h3>
          <p>Your IP is protected with enterprise-grade security, encrypted storage, and official certification from CHMSU.</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-certificate"></i>
          </div>
          <h3>Official Certificates</h3>
          <p>Receive official IP registration certificates with unique verification numbers and QR codes for authenticity.</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-chart-line"></i>
          </div>
          <h3>Track Progress</h3>
          <p>Monitor your application status in real-time from submission through clerk validation, payment, and director approval.</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-globe"></i>
          </div>
          <h3>IP Hub Showcase</h3>
          <p>Share your approved works in our public IP Hub where the CHMSU community can discover and appreciate innovations.</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-award"></i>
          </div>
          <h3>Badges & Recognition</h3>
          <p>Earn automatic badges and innovation points as your IP works gain visibility and recognition in the community.</p>
        </div>
      </div>
    </div>
  </section>
  
  <!-- CTA Section -->
  <section class="cta">
    <div class="cta-content">
      <h2>Ready to Protect Your Innovation?</h2>
      <p>Join the growing community of CHMSU researchers and innovators. Start your IP registration journey today.</p>
      <a href="auth/register.php" class="btn-primary">
        Create Free Account <i class="fas fa-arrow-right"></i>
      </a>
    </div>
  </section>
  
  <!-- Footer -->
  <footer class="footer">
    <p>&copy; 2025 Carlos Hilado Memorial State University. All rights reserved.</p>
    <p style="margin-top: 8px; font-size: 12px;">Intellectual Property Office • Carlos Hilado Memorial State University • Talisay City Negros Occidental</p>
  </footer>
</body>
</html>
