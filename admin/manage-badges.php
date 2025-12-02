<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireRole(['clerk', 'director']);

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_thresholds') {
  $bronze = intval($_POST['bronze_threshold'] ?? 10);
  $silver = intval($_POST['silver_threshold'] ?? 50);
  $gold = intval($_POST['gold_threshold'] ?? 100);
  $platinum = intval($_POST['platinum_threshold'] ?? 250);
  $diamond = intval($_POST['diamond_threshold'] ?? 500);
  
  // Update thresholds in database
  $conn->query("DELETE FROM badge_thresholds");
  $stmt = $conn->prepare("INSERT INTO badge_thresholds (badge_type, views_required, points_awarded) VALUES (?, ?, ?)");
  
  $badges = [
    ['Bronze', $bronze, 50],
    ['Silver', $silver, 150],
    ['Gold', $gold, 300],
    ['Platinum', $platinum, 500],
    ['Diamond', $diamond, 1000]
  ];
  
  foreach ($badges as $badge) {
    $stmt->bind_param("sii", $badge[0], $badge[1], $badge[2]);
    $stmt->execute();
  }
  $stmt->close();
  
  auditLog('Update Badge Thresholds', 'Settings', 0);
  $success = 'Badge thresholds updated successfully!';
}

// Get current thresholds
$thresholds_result = $conn->query("SELECT * FROM badge_thresholds ORDER BY views_required ASC");
$thresholds = [];
while ($row = $thresholds_result->fetch_assoc()) {
  $thresholds[$row['badge_type']] = $row;
}

// If no thresholds exist, set defaults
if (empty($thresholds)) {
  $default_thresholds = [
    'Bronze' => ['views_required' => 10, 'points_awarded' => 50],
    'Silver' => ['views_required' => 50, 'points_awarded' => 150],
    'Gold' => ['views_required' => 100, 'points_awarded' => 300],
    'Platinum' => ['views_required' => 250, 'points_awarded' => 500],
    'Diamond' => ['views_required' => 500, 'points_awarded' => 1000]
  ];
  foreach ($default_thresholds as $type => $data) {
    $thresholds[$type] = ['badge_type' => $type, 'views_required' => $data['views_required'], 'points_awarded' => $data['points_awarded']];
  }
}

$users_result = $conn->query("
  SELECT 
    u.id, 
    u.full_name, 
    u.email, 
    u.innovation_points,
    COUNT(DISTINCT a.id) as approved_works,
    COUNT(DISTINCT v.id) as total_views,
    GROUP_CONCAT(DISTINCT b.badge_type) as earned_badges
  FROM users u
  LEFT JOIN ip_applications a ON u.id = a.user_id AND a.status = 'approved'
  LEFT JOIN view_tracking v ON a.id = v.application_id
  LEFT JOIN badges b ON u.id = b.user_id
  WHERE u.role = 'user'
  GROUP BY u.id
  ORDER BY total_views DESC
");
$users = $users_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Badge System Management - CHMSU IP System</title>
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
      padding: 30px;
      border-radius: 10px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .header h1 {
      color: #1B5C3B;
      margin-bottom: 10px;
    }
    
    .alert {
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    .info-box {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
    }
    
    .info-box h3 {
      margin-bottom: 10px;
      font-size: 18px;
    }
    
    .threshold-card {
      background: white;
      border-radius: 10px;
      padding: 30px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      margin-bottom: 20px;
    }
    
    .threshold-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
    }
    
    .threshold-item {
      background: #f9f9f9;
      padding: 20px;
      border-radius: 8px;
      border-left: 4px solid #1B5C3B;
    }
    
    .badge-icon {
      font-size: 32px;
      margin-bottom: 10px;
    }
    
    .bronze { color: #CD7F32; }
    .silver { color: #C0C0C0; }
    .gold { color: #FFD700; }
    .platinum { color: #E5E4E2; }
    .diamond { color: #B9F2FF; }
    
    label {
      display: block;
      margin-bottom: 5px;
      color: #333;
      font-weight: 600;
      font-size: 13px;
    }
    
    input[type="number"] {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 14px;
    }
    
    button {
      background: #1B5C3B;
      color: white;
      padding: 12px 30px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
    }
    
    button:hover {
      background: #0F3D2E;
    }
    
    .user-table {
      background: white;
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      overflow-x: auto;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
    }
    
    th, td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid #f0f0f0;
      font-size: 13px;
    }
    
    th {
      background: #f9f9f9;
      font-weight: 600;
      color: #333;
    }
    
    .badge-list {
      display: flex;
      gap: 5px;
      flex-wrap: wrap;
    }
    
    .badge-tag {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
    }
    
    .badge-tag.bronze { background: #CD7F32; color: white; }
    .badge-tag.silver { background: #C0C0C0; color: white; }
    .badge-tag.gold { background: #FFD700; color: #333; }
    .badge-tag.platinum { background: #E5E4E2; color: #333; }
    .badge-tag.diamond { background: #B9F2FF; color: #333; }
  </style>
</head>
<body>
  <div class="container">
    <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
    
    <div class="header">
      <h1><i class="fas fa-medal"></i> Badge System Management</h1>
      <p style="color: #666; font-size: 14px; margin-top: 5px;">
        Manage view thresholds for automatic badge awarding. Badges are awarded automatically when IP works reach view milestones.
      </p>
    </div>
    
    <?php if (!empty($success)): ?>
      <div class="alert"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <div class="info-box">
      <h3><i class="fas fa-info-circle"></i> How Badge System Works</h3>
      <ul style="font-size: 14px; line-height: 1.8; margin-left: 20px;">
        <li>Badges are automatically awarded when approved IP works reach view thresholds</li>
        <li>Users earn innovation points with each badge</li>
        <li>You can adjust thresholds below - system will automatically award badges based on new settings</li>
        <li>Each badge can only be earned once per user</li>
      </ul>
    </div>
    
    <div class="threshold-card">
      <h2 style="margin-bottom: 20px; color: #333;"><i class="fas fa-sliders-h"></i> Badge Thresholds</h2>
      
      <form method="POST">
        <div class="threshold-grid">
          <div class="threshold-item">
            <div class="badge-icon bronze"><i class="fas fa-medal"></i></div>
            <label for="bronze">Bronze Badge</label>
            <input type="number" id="bronze" name="bronze_threshold" value="<?php echo $thresholds['Bronze']['views_required']; ?>" min="1" required>
            <small style="color: #666;">Views required • Awards <?php echo $thresholds['Bronze']['points_awarded']; ?> points</small>
          </div>
          
          <div class="threshold-item">
            <div class="badge-icon silver"><i class="fas fa-medal"></i></div>
            <label for="silver">Silver Badge</label>
            <input type="number" id="silver" name="silver_threshold" value="<?php echo $thresholds['Silver']['views_required']; ?>" min="1" required>
            <small style="color: #666;">Views required • Awards <?php echo $thresholds['Silver']['points_awarded']; ?> points</small>
          </div>
          
          <div class="threshold-item">
            <div class="badge-icon gold"><i class="fas fa-medal"></i></div>
            <label for="gold">Gold Badge</label>
            <input type="number" id="gold" name="gold_threshold" value="<?php echo $thresholds['Gold']['views_required']; ?>" min="1" required>
            <small style="color: #666;">Views required • Awards <?php echo $thresholds['Gold']['points_awarded']; ?> points</small>
          </div>
          
          <div class="threshold-item">
            <div class="badge-icon platinum"><i class="fas fa-medal"></i></div>
            <label for="platinum">Platinum Badge</label>
            <input type="number" id="platinum" name="platinum_threshold" value="<?php echo $thresholds['Platinum']['views_required']; ?>" min="1" required>
            <small style="color: #666;">Views required • Awards <?php echo $thresholds['Platinum']['points_awarded']; ?> points</small>
          </div>
          
          <div class="threshold-item">
            <div class="badge-icon diamond"><i class="fas fa-medal"></i></div>
            <label for="diamond">Diamond Badge</label>
            <input type="number" id="diamond" name="diamond_threshold" value="<?php echo $thresholds['Diamond']['views_required']; ?>" min="1" required>
            <small style="color: #666;">Views required • Awards <?php echo $thresholds['Diamond']['points_awarded']; ?> points</small>
          </div>
        </div>
        
        <button type="submit" name="action" value="update_thresholds">
          <i class="fas fa-save"></i> Save Thresholds
        </button>
      </form>
    </div>
    
    <div class="user-table">
      <h2 style="margin-bottom: 20px; color: #333;"><i class="fas fa-users"></i> User Badge Status</h2>
      
      <table>
        <thead>
          <tr>
            <th>User</th>
            <th>Works</th>
            <th>Total Views</th>
            <th>Points</th>
            <th>Earned Badges</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
            <tr>
              <td>
                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                <small style="color: #666;"><?php echo htmlspecialchars($user['email']); ?></small>
              </td>
              <td><?php echo $user['approved_works']; ?></td>
              <td><i class="fas fa-eye"></i> <?php echo $user['total_views']; ?></td>
              <td><?php echo $user['innovation_points']; ?></td>
              <td>
                <?php if ($user['earned_badges']): ?>
                  <div class="badge-list">
                    <?php 
                    $badges = explode(',', $user['earned_badges']);
                    foreach ($badges as $badge): 
                      $badge_class = strtolower(trim($badge));
                    ?>
                      <span class="badge-tag <?php echo $badge_class; ?>"><?php echo trim($badge); ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <span style="color: #999;">No badges yet</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
