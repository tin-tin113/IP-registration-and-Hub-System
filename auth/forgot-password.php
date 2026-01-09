<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

$error = '';
$message = '';
$step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['step1'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
      $error = 'Email is required';
    } else {
      $stmt = $conn->prepare("SELECT id, full_name, security_question FROM users WHERE email = ?");
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (empty($user['security_question'])) {
          $error = 'Security question is not set for this account. Please contact the administrator to update your profile.';
        } else {
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_user_id'] = $user['id'];
        $_SESSION['security_question'] = $user['security_question'];
        $step = 2;
        auditLog('Password Recovery Started', 'User', $user['id']);
        }
      } else {
        $error = 'Email not found';
      }
      
      $stmt->close();
    }
  } elseif (isset($_POST['step2'])) {
    $security_answer = strtolower(trim($_POST['security_answer'] ?? ''));
    $email = $_SESSION['reset_email'] ?? '';
    
    if (empty($email)) {
      $error = 'Session expired. Please start over.';
      $step = 1;
      session_destroy();
    } else {
    error_log("[v0] Step2 - Email: " . $email);
    error_log("[v0] Step2 - User provided answer: " . $security_answer);
    
    $stmt = $conn->prepare("SELECT id, security_answer FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
      $user = $result->fetch_assoc();
      $stored_answer = strtolower(trim($user['security_answer']));
      
      error_log("[v0] Step2 - Stored answer: " . $stored_answer);
      error_log("[v0] Step2 - Stored answer length: " . strlen($stored_answer));
      error_log("[v0] Step2 - User answer length: " . strlen($security_answer));
      error_log("[v0] Step2 - Match result: " . ($security_answer === $stored_answer ? 'MATCH' : 'NO MATCH'));
      
        if ($stored_answer === '') {
          // Allow legacy accounts without a stored security answer to proceed
          $_SESSION['step3_verified'] = true;
          $message = 'Security question not set. You may reset your password now.';
          $step = 3;
        } elseif ($security_answer === $stored_answer) {
        $_SESSION['step3_verified'] = true;
        $step = 3;
      } else {
        $error = 'Incorrect answer. Please try again.';
        $step = 2;
      }
    } else {
      $error = 'User not found';
      $step = 2;
    }
    
    $stmt->close();
    }
  } elseif (isset($_POST['step3'])) {
    if (!isset($_SESSION['step3_verified'])) {
      $error = 'Unauthorized access. Please start over.';
      $step = 1;
      session_destroy();
    } else {
      $new_password = trim($_POST['new_password'] ?? '');
      $confirm_password = trim($_POST['confirm_password'] ?? '');
      
      if (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters';
        $step = 3;
      } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
        $step = 3;
      } else {
        $user_id = $_SESSION['reset_user_id'] ?? null;
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
          auditLog('Password Reset', 'User', $user_id);
          session_destroy();
          header("Location: login.php?message=" . urlencode("Password reset successfully. Please login."));
          exit;
        } else {
          $error = 'Password reset failed';
          $step = 3;
        }
        
        $stmt->close();
      }
    }
  }
}

if (!isset($_POST['step1']) && !isset($_POST['step2']) && !isset($_POST['step3'])) {
  if (isset($_SESSION['step3_verified']) && $_SESSION['step3_verified']) {
    $step = 3;
  } elseif (isset($_SESSION['reset_email']) && isset($_SESSION['security_question'])) {
    $step = 2;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - CHMSU IP System</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      min-height: 100vh;
      padding: 40px 20px;
      overflow-y: auto;
      display: flex;
      align-items: flex-start;
      justify-content: center;
    }
    
    .container {
      background: white;
      border-radius: 10px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
      width: 100%;
      max-width: 450px;
      padding: 40px;
    }
    
    .header {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .logo {
      font-size: 48px;
      color: #1B5C3B;
      margin-bottom: 10px;
    }
    
    .header h1 {
      font-size: 24px;
      color: #333;
      margin-bottom: 5px;
    }
    
    .header p {
      color: #666;
      font-size: 14px;
    }
    
    .progress {
      display: flex;
      justify-content: space-between;
      margin-bottom: 30px;
    }
    
    .progress-item {
      flex: 1;
      text-align: center;
      color: #999;
      font-size: 12px;
      position: relative;
    }
    
    .progress-item.active {
      color: #1B5C3B;
    }
    
    .progress-item::before {
      content: '';
      display: block;
      width: 30px;
      height: 30px;
      border: 2px solid #ddd;
      border-radius: 50%;
      margin: 0 auto 5px;
      background: white;
    }
    
    .progress-item.active::before {
      border-color: #1B5C3B;
      background: #1B5C3B;
      color: white;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    label {
      display: block;
      margin-bottom: 8px;
      color: #333;
      font-weight: 500;
    }
    
    input[type="email"],
    input[type="password"],
    input[type="text"] {
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 14px;
    }
    
    input:focus {
      outline: none;
      border-color: #1B5C3B;
      box-shadow: 0 0 5px rgba(27, 92, 59, 0.3);
    }
    
    .alert {
      padding: 12px 15px;
      margin-bottom: 20px;
      border-radius: 5px;
      font-size: 14px;
    }
    
    .alert-danger {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    button {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #1B5C3B 0%, #0F3D2E 100%);
      color: white;
      border: none;
      border-radius: 5px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
    }
    
    button:hover {
      transform: translateY(-2px);
    }
    
    .back-link {
      text-align: center;
      margin-top: 15px;
    }
    
    .back-link a {
      color: #1B5C3B;
      text-decoration: none;
      font-size: 14px;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo"><i class="fas fa-key"></i></div>
      <h1>Reset Password</h1>
      <p>CHMSU Intellectual Property System</p>
    </div>
    
    <div class="progress">
      <div class="progress-item <?php echo $step >= 1 ? 'active' : ''; ?>">Email</div>
      <div class="progress-item <?php echo $step >= 2 ? 'active' : ''; ?>">Verify</div>
      <div class="progress-item <?php echo $step >= 3 ? 'active' : ''; ?>">Reset</div>
    </div>
    
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($step === 1): ?>
      <form method="POST">
        <div class="form-group">
          <label for="email">Enter your email address</label>
          <input type="email" id="email" name="email" required autofocus>
        </div>
        <button type="submit" name="step1" value="1">Continue</button>
      </form>
    <?php elseif ($step === 2): ?>
      <form method="POST">
        <div class="form-group">
          <label><?php echo htmlspecialchars($_SESSION['security_question']); ?></label>
          <input type="text" name="security_answer" placeholder="Your answer" required autofocus>
        </div>
        <button type="submit" name="step2" value="1">Verify Answer</button>
      </form>
    <?php elseif ($step === 3): ?>
      <form method="POST">
        <div class="form-group">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" required autofocus>
        </div>
        <div class="form-group">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <button type="submit" name="step3" value="1">Reset Password</button>
      </form>
    <?php endif; ?>
    
    <div class="back-link">
      <a href="login.php">Back to Login</a>
    </div>
  </div>
</body>
</html>
