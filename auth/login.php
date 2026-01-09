<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

$error = '';
$message = '';

// Reset any in-progress forgot-password state when returning here
unset($_SESSION['reset_email'], $_SESSION['reset_user_id'], $_SESSION['security_question'], $_SESSION['step3_verified']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = trim($_POST['password'] ?? '');
  
  if (empty($email) || empty($password)) {
    $error = 'Email and password are required';
  } else {
    $stmt = $conn->prepare("SELECT id, email, password, full_name, role FROM users WHERE email = ? AND is_active = 1");
    if (!$stmt) {
      $error = 'Database error';
    } else {
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
          $_SESSION['user_id'] = $user['id'];
          $_SESSION['email'] = $user['email'];
          $_SESSION['full_name'] = $user['full_name'];
          $_SESSION['role'] = $user['role'];
          $_SESSION['user_data'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
          ];
          
          auditLog('Login', 'User', $user['id']);
          
          // Role-based redirect
          if (in_array($user['role'], ['clerk', 'director'])) {
            header("Location: " . BASE_URL . "admin/dashboard.php");
          } else {
            header("Location: " . BASE_URL . "dashboard.php");
          }
          exit;
        } else {
          $error = 'Invalid email or password';
        }
      } else {
        $error = 'Invalid email or password';
      }
      
      $stmt->close();
    }
  }
}

if (isset($_GET['error'])) {
  $error = $_GET['error'];
}
if (isset($_GET['message'])) {
  $message = $_GET['message'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - CHMSU IP System</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 50%, #2A9D5F 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
      overflow-y: auto;
      margin: 0;
    }
    
    body::before {
      content: '';
      position: absolute;
      width: 300px;
      height: 300px;
      background: radial-gradient(circle, rgba(218, 165, 32, 0.15) 0%, transparent 70%);
      top: -150px;
      right: -150px;
      border-radius: 50%;
    }
    
    body::after {
      content: '';
      position: absolute;
      width: 250px;
      height: 250px;
      background: radial-gradient(circle, rgba(218, 165, 32, 0.1) 0%, transparent 70%);
      bottom: -125px;
      left: -125px;
      border-radius: 50%;
    }
    
    .login-container {
      background: white;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.1);
      width: 100%;
      max-width: 420px;
      padding: 35px;
      position: relative;
      z-index: 1;
      backdrop-filter: blur(10px);
      margin: auto;
    }
    
    .login-header {
      text-align: center;
      margin-bottom: 40px;
    }
    
    .logo-wrapper {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 16px;
      margin-bottom: 24px;
    }
    
    .logo {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      border: 4px solid #E07D32;
      box-shadow: 0 8px 24px rgba(10, 77, 46, 0.3);
      background: white;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .logo img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    
    .login-header h1 {
      font-size: 32px;
      font-weight: 700;
      color: #0A4D2E;
      margin-bottom: 8px;
      letter-spacing: -0.5px;
    }
    
    .login-header p {
      color: #64748B;
      font-size: 15px;
      font-weight: 500;
    }
    
    .form-group {
      margin-bottom: 24px;
    }
    
    label {
      display: block;
      margin-bottom: 10px;
      color: #1E293B;
      font-weight: 600;
      font-size: 14px;
      letter-spacing: -0.2px;
    }
    
    input[type="email"],
    input[type="password"],
    input[type="text"] {
      width: 100%;
      padding: 14px 16px;
      border: 2px solid #E2E8F0;
      border-radius: 12px;
      font-size: 15px;
      font-family: inherit;
      transition: all 0.2s;
      background: #F8FAFC;
    }
    
    input[type="email"]:focus,
    input[type="password"]:focus,
    input[type="text"]:focus {
      outline: none;
      border-color: #1B7F4D;
      background: white;
      box-shadow: 0 0 0 4px rgba(27, 127, 77, 0.1);
    }
    
    input[type="checkbox"] {
      width: 18px;
      height: 18px;
      cursor: pointer;
      accent-color: #1B7F4D;
    }
    
    .alert {
      padding: 14px 16px;
      margin-bottom: 24px;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .alert-danger {
      background-color: #FEF2F2;
      color: #991B1B;
      border: 1px solid #FECACA;
    }
    
    .alert-success {
      background-color: #F0FDF4;
      color: #166534;
      border: 1px solid #BBF7D0;
    }
    
    button {
      width: 100%;
      padding: 16px;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      box-shadow: 0 4px 12px rgba(10, 77, 46, 0.3);
      letter-spacing: -0.2px;
    }
    
    button:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(10, 77, 46, 0.4);
    }
    
    button:active {
      transform: translateY(0);
    }
    
    .links {
      display: flex;
      justify-content: space-between;
      margin-top: 20px;
      font-size: 14px;
    }
    
    .links a {
      color: #1B7F4D;
      text-decoration: none;
      font-weight: 500;
      transition: color 0.2s;
    }
    
    .links a:hover {
      color: #0A4D2E;
      text-decoration: underline;
    }
    
    .sample-accounts {
      background: linear-gradient(135deg, #F0FDF4 0%, #ECFDF5 100%);
      border-left: 4px solid #1B7F4D;
      padding: 20px;
      margin-top: 32px;
      border-radius: 12px;
      font-size: 13px;
    }
    
    .sample-accounts h4 {
      color: #0A4D2E;
      margin-bottom: 12px;
      font-size: 14px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .sample-account {
      color: #1E293B;
      margin-bottom: 8px;
      padding: 8px 0;
      font-size: 13px;
      line-height: 1.6;
    }
    
    .sample-account strong {
      color: #0A4D2E;
      font-weight: 600;
    }
    
    @media (max-width: 500px) {
      body {
        /* Allow scroll but prevent horizontal bounce */
        overflow-x: hidden;
        padding: 20px 16px;
      }
      
      /* Scale down decorative elements or hide them on mobile */
      body::before, body::after {
        opacity: 0.5;
        transform: scale(0.7);
      }

      .login-container {
        padding: 32px 20px;
        width: 100%;
      }
      
      .login-header h1 {
        font-size: 24px;
      }
      
      .logo {
        width: 64px;
        height: 64px;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-header">
      <div class="logo-wrapper">
        <div class="logo">
          <img src="../public/logos/chmsu-logo.png" alt="CHMSU Logo" onerror="this.src='../public/logos/chmsu-logo.jpg'; this.onerror=null;">
        </div>
      </div>
      <h1>CHMSU IP System</h1>
      <p>Intellectual Property Registration & Hub</p>
    </div>
    
    <?php 
    // Show only the latest message (error takes priority over success message)
    if (!empty($error)): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php elseif (!empty($message)): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>
    
    <form method="POST">
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required placeholder="you@chmsu.edu.ph">
      </div>
      
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required placeholder="Enter your password">
        <div style="margin-top: 10px; display: flex; align-items: center; gap: 8px;">
          <input type="checkbox" id="showPassword" style="width: auto; margin: 0; cursor: pointer;">
          <label for="showPassword" style="margin: 0; font-weight: normal; font-size: 14px; color: #64748B; cursor: pointer;">Show Password</label>
        </div>
      </div>
      
      <button type="submit">Sign In</button>
      
      <div class="links">
        <a href="register.php">Create Account</a>
        <a href="forgot-password.php">Forgot Password?</a>
      </div>
    </form>
    
  </div>
  
  <script>
    // Toggle password visibility with checkbox
    document.getElementById('showPassword').addEventListener('change', function() {
      const passwordInput = document.getElementById('password');
      
      if (this.checked) {
        passwordInput.type = 'text';
      } else {
        passwordInput.type = 'password';
      }
    });
    
    // Ensure password input is functional
    document.getElementById('password').addEventListener('input', function() {
      // Password input is working
      this.setAttribute('value', this.value);
    });
  </script>
</body>
</html>
