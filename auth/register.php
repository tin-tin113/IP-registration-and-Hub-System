<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

$error = '';
$success = '';
$security_questions = $GLOBALS['security_questions'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = trim($_POST['password'] ?? '');
  $confirm_password = trim($_POST['confirm_password'] ?? '');
  $full_name = trim($_POST['full_name'] ?? '');
  $security_question = trim($_POST['security_question'] ?? '');
  $security_answer = strtolower(trim($_POST['security_answer'] ?? ''));
  $department = trim($_POST['department'] ?? '');
  
  if (empty($email) || empty($password) || empty($full_name) || empty($security_answer)) {
    $error = 'All fields are required';
  } elseif (strlen($password) < 6) {
    $error = 'Password must be at least 6 characters';
  } elseif ($password !== $confirm_password) {
    $error = 'Passwords do not match';
  } else {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
      $error = 'Email already exists';
    } else {
      $hashed_password = password_hash($password, PASSWORD_BCRYPT);
      
      $stmt = $conn->prepare("INSERT INTO users (email, password, full_name, security_question, security_answer, department) VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("ssssss", $email, $hashed_password, $full_name, $security_question, $security_answer, $department);
      
      if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        auditLog('Register', 'User', $user_id);
        $success = 'Account created successfully! Please login.';
        header("Location: login.php?message=" . urlencode($success));
        exit;
      } else {
        $error = 'Registration failed. Please try again.';
      }
    }
    
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - CHMSU IP System</title>
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
      padding: 40px 20px;
      /* Fixed overflow for scrolling */
      overflow-y: auto;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      position: relative;
    }
    
    body::before {
      content: '';
      position: absolute;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(218, 165, 32, 0.15) 0%, transparent 70%);
      top: -250px;
      right: -250px;
      border-radius: 50%;
    }
    
    .register-container {
      background: white;
      border-radius: 24px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      width: 100%;
      max-width: 540px;
      padding: 48px;
      position: relative;
      z-index: 1;
    }
    
    .register-header {
      text-align: center;
      margin-bottom: 36px;
    }
    
    .logo-wrapper {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 16px;
      margin-bottom: 20px;
    }
    
    .logo {
      width: 56px;
      height: 56px;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #DAA520;
      font-size: 28px;
      box-shadow: 0 8px 24px rgba(10, 77, 46, 0.3);
    }
    
    .register-header h1 {
      font-size: 28px;
      font-weight: 700;
      color: #0A4D2E;
      margin-bottom: 6px;
      letter-spacing: -0.5px;
    }
    
    .register-header p {
      color: #64748B;
      font-size: 14px;
      font-weight: 500;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    label {
      display: block;
      margin-bottom: 8px;
      color: #1E293B;
      font-weight: 600;
      font-size: 13px;
    }
    
    input[type="email"],
    input[type="password"],
    input[type="text"],
    select {
      width: 100%;
      padding: 12px 14px;
      border: 2px solid #E2E8F0;
      border-radius: 10px;
      font-size: 14px;
      font-family: inherit;
      transition: all 0.2s;
      background: #F8FAFC;
    }
    
    input:focus,
    select:focus {
      outline: none;
      border-color: #1B7F4D;
      background: white;
      box-shadow: 0 0 0 4px rgba(27, 127, 77, 0.1);
    }
    
    .alert {
      padding: 12px 14px;
      margin-bottom: 20px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .alert-danger {
      background-color: #FEF2F2;
      color: #991B1B;
      border: 1px solid #FECACA;
    }
    
    button {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      margin-top: 8px;
      box-shadow: 0 4px 12px rgba(10, 77, 46, 0.3);
    }
    
    button:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(10, 77, 46, 0.4);
    }
    
    .login-link {
      text-align: center;
      margin-top: 20px;
    }
    
    .login-link a {
      color: #1B7F4D;
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
    }
    
    .login-link a:hover {
      text-decoration: underline;
      color: #0A4D2E;
    }
  </style>
</head>
<body>
  <div class="register-container">
    <div class="register-header">
      <div class="logo-wrapper">
        <div class="logo">
          <i class="fas fa-user-plus"></i>
        </div>
      </div>
      <h1>Create Account</h1>
      <p>CHMSU Intellectual Property System</p>
    </div>
    
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>
    
    <form method="POST">
      <div class="form-group">
        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" required placeholder="Juan Dela Cruz">
      </div>
      
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required placeholder="you@chmsu.edu.ph">
      </div>
      
      <div class="form-group">
        <label for="department">Department/Faculty</label>
        <select id="department" name="department" required>
          <option value="">Select Department</option>
          <option value="College of Arts and Sciences">College of Arts and Sciences</option>
          <option value="College of Engineering">College of Engineering</option>
          <option value="College of Business and Management">College of Business and Management</option>
          <option value="College of Education">College of Education</option>
          <option value="College of Criminal Justice Education">College of Criminal Justice Education</option>
          <option value="College of Industrial Technology">College of Industrial Technology</option>
          <option value="Graduate School">Graduate School</option>
          <option value="Other">Other (Specify)</option>
        </select>
      </div>

      <div class="form-group" id="other_department_group" style="display:none;">
        <label for="other_department">Specify Department</label>
        <input type="text" id="other_department" name="other_department" placeholder="Enter department name">
      </div>

      
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required placeholder="At least 6 characters">
      </div>
      
      <div class="form-group">
        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-enter password">
      </div>
      
      <div class="form-group">
        <label for="security_question">Security Question</label>
        <select id="security_question" name="security_question" required>
          <option value="">Select a question</option>
          <?php foreach ($security_questions as $q): ?>
            <option value="<?php echo htmlspecialchars($q); ?>"><?php echo htmlspecialchars($q); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="form-group">
        <label for="security_answer">Answer</label>
        <input type="text" id="security_answer" name="security_answer" placeholder="Your answer (case-insensitive)" required>
      </div>
      
      <button type="submit">Create Account</button>
      
      <div class="login-link">
        Already have an account? <a href="login.php">Sign in here</a>
      </div>
    </form>
  </div>
</body>

<script>
document.getElementById('department').addEventListener('change', function () {
  const otherGroup = document.getElementById('other_department_group');

  if (this.value === 'Other') {
    otherGroup.style.display = 'block';
    document.getElementById('other_department').setAttribute('required', 'required');
  } else {
    otherGroup.style.display = 'none';
    document.getElementById('other_department').removeAttribute('required');
  }
});
</script>

</html>
