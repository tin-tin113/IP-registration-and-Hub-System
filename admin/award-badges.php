<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireRole(['clerk', 'director']);

// Disable manual badge awarding - badges are now auto-awarded based on views
header("Location: dashboard.php?error=Manual badge awarding is disabled. Badges are automatically awarded based on work views.");
exit;

$success = '';
$error = '';

// Handle badge award
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'award_badge') {
  $user_id = intval($_POST['user_id'] ?? 0);
  $badge_type = trim($_POST['badge_type'] ?? '');
  $points_awarded = intval($_POST['points_awarded'] ?? 0);
  
  if ($user_id && in_array($badge_type, ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond'])) {
    // Check if user already has this badge
    $check = $conn->prepare("SELECT id FROM badges WHERE user_id = ? AND badge_type = ?");
    $check->bind_param("is", $user_id, $badge_type);
    $check->execute();
    
    if ($check->get_result()->num_rows === 0) {
      // Award badge
      $award = $conn->prepare("INSERT INTO badges (user_id, badge_type, views_required) VALUES (?, ?, 0)");
      $award->bind_param("is", $user_id, $badge_type);
      $award->execute();
      $award->close();
      
      // Award points if specified
      if ($points_awarded > 0) {
        $update_points = $conn->prepare("UPDATE users SET innovation_points = innovation_points + ? WHERE id = ?");
        $update_points->bind_param("ii", $points_awarded, $user_id);
        $update_points->execute();
        $update_points->close();
      }
      
      auditLog('Manual Badge Award', 'Badge', $user_id, null, json_encode(['badge_type' => $badge_type, 'points' => $points_awarded]));
      $success = "Badge awarded successfully!";
    } else {
      $error = "User already has this badge.";
    }
    $check->close();
  } else {
    $error = "Invalid user or badge type.";
  }
}

// Get all users
$users_result = $conn->query("
  SELECT 
    u.id, 
    u.full_name, 
    u.email, 
    u.department,
    u.innovation_points,
    GROUP_CONCAT(DISTINCT b.badge_type) as earned_badges
  FROM users u
  LEFT JOIN badges b ON u.id = b.user_id
  WHERE u.role = 'user'
  GROUP BY u.id
  ORDER BY u.full_name ASC
");
$users = $users_result->fetch_all(MYSQLI_ASSOC);

// Get badge thresholds for reference
$thresholds_result = $conn->query("SELECT * FROM badge_thresholds ORDER BY views_required ASC");
$thresholds = [];
while ($row = $thresholds_result->fetch_assoc()) {
  $thresholds[$row['badge_type']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Award Badges - CHMSU IP System</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f5f7fa;
      min-height: 100vh;
      padding: 20px;
    }
    
    .container {
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .back-btn {
      background: #f0f0f0;
      color: #333;
      padding: 10px 15px;
      border: 1px solid #ddd;
      border-radius: 5px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      margin-bottom: 20px;
      font-size: 13px;
    }
    
    .header {
      background: white;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .alert {
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
    }
    
    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    .alert-danger {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .users-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .user-card {
      background: white;
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      border-top: 4px solid #1B5C3B;
    }
    
    .user-card h3 {
      color: #333;
      margin-bottom: 10px;
      font-size: 16px;
    }
    
    .user-info {
      font-size: 13px;
      color: #666;
      margin-bottom: 15px;
    }
    
    .user-info p {
      margin-bottom: 5px;
    }
    
    .badges-list {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 15px;
    }
    
    .badge {
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .badge-bronze {
      background: #cd7f32;
      color: white;
    }
    
    .badge-silver {
      background: #c0c0c0;
      color: white;
    }
    
    .badge-gold {
      background: #ffd700;
      color: #333;
    }
    
    .badge-platinum {
      background: #e5e4e2;
      color: #333;
    }
    
    .badge-diamond {
      background: #b9f2ff;
      color: #333;
    }
    
    .award-form {
      border-top: 1px solid #f0f0f0;
      padding-top: 15px;
    }
    
    .form-group {
      margin-bottom: 12px;
    }
    
    label {
      display: block;
      margin-bottom: 5px;
      color: #333;
      font-weight: 600;
      font-size: 12px;
    }
    
    select,
    input[type="number"] {
      width: 100%;
      padding: 8px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 13px;
    }
    
    button {
      background: #1B5C3B;
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
      width: 100%;
      transition: all 0.2s;
    }
    
    button:hover {
      background: #0F3D2E;
    }
    
    .points-display {
      font-size: 12px;
      color: #666;
      margin-top: 5px;
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
    
    <div class="header">
      <h1><i class="fas fa-trophy"></i> Award Badges to Users</h1>
      <p style="color: #666; font-size: 13px; margin-top: 5px;">Directly award badges to IP users for their credentials</p>
    </div>
    
    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="users-grid">
      <?php foreach ($users as $user): 
        $earned_badges = !empty($user['earned_badges']) ? explode(',', $user['earned_badges']) : [];
      ?>
        <div class="user-card">
          <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
          <div class="user-info">
            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></p>
            <p><i class="fas fa-lightbulb"></i> Innovation Points: <strong><?php echo $user['innovation_points']; ?></strong></p>
          </div>
          
          <?php if (count($earned_badges) > 0): ?>
            <div class="badges-list">
              <?php foreach ($earned_badges as $badge): ?>
                <span class="badge badge-<?php echo strtolower($badge); ?>"><?php echo $badge; ?></span>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p style="font-size: 12px; color: #999; margin-bottom: 15px;">No badges earned yet</p>
          <?php endif; ?>
          
          <div class="award-form">
            <form method="POST">
              <input type="hidden" name="action" value="award_badge">
              <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
              
              <div class="form-group">
                <label for="badge_type_<?php echo $user['id']; ?>">Select Badge</label>
                <select id="badge_type_<?php echo $user['id']; ?>" name="badge_type" required>
                  <option value="">Choose badge...</option>
                  <?php foreach (['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond'] as $badge): 
                    if (!in_array($badge, $earned_badges)):
                      $threshold = $thresholds[$badge] ?? null;
                  ?>
                    <option value="<?php echo $badge; ?>" data-points="<?php echo $threshold['points_awarded'] ?? 0; ?>">
                      <?php echo $badge; ?>
                      <?php if ($threshold): ?>
                        (<?php echo $threshold['points_awarded']; ?> points)
                      <?php endif; ?>
                    </option>
                  <?php endif; endforeach; ?>
                </select>
              </div>
              
              <div class="form-group">
                <label for="points_<?php echo $user['id']; ?>">Points to Award (Optional)</label>
                <input type="number" id="points_<?php echo $user['id']; ?>" name="points_awarded" min="0" value="0">
                <div class="points-display">Leave 0 to use default points from badge threshold</div>
              </div>
              
              <button type="submit">
                <i class="fas fa-award"></i> Award Badge
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  
  <script>
    // Auto-fill points based on badge selection
    document.querySelectorAll('select[name="badge_type"]').forEach(select => {
      select.addEventListener('change', function() {
        const pointsInput = this.closest('form').querySelector('input[name="points_awarded"]');
        const selectedOption = this.options[this.selectedIndex];
        const defaultPoints = selectedOption.getAttribute('data-points') || 0;
        if (defaultPoints > 0 && pointsInput.value == 0) {
          pointsInput.value = defaultPoints;
        }
      });
    });
  </script>
</body>
</html>

