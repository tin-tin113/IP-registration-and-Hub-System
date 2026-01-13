<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';
require_once '../config/badge-auto-award.php';

requireRole(['clerk', 'director']);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_thresholds') {
  $bronze = intval($_POST['bronze_threshold'] ?? 10);
  $silver = intval($_POST['silver_threshold'] ?? 50);
  $gold = intval($_POST['gold_threshold'] ?? 100);
  $platinum = intval($_POST['platinum_threshold'] ?? 250);
  $diamond = intval($_POST['diamond_threshold'] ?? 500);
  
  // Validation: Ensure thresholds are strictly ascending and unique
  if ($bronze >= $silver || $silver >= $gold || $gold >= $platinum || $platinum >= $diamond) {
    $error = "Thresholds must be in strictly ascending order (Bronze < Silver < Gold < Platinum < Diamond) and cannot be equal.";
  } else {
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
    
    // Re-evaluate badges for all approved applications based on new thresholds
    $approved_apps = $conn->query("SELECT id FROM ip_applications WHERE status = 'approved'");
    $badges_awarded = 0;
    while ($app = $approved_apps->fetch_assoc()) {
      // checkAndAwardBadges will award any badges the application now qualifies for
      checkAndAwardBadges($app['id']);
      $badges_awarded++;
    }
    
    auditLog('Update Badge Thresholds', 'Settings', 0, null, json_encode([
      'bronze' => $bronze,
      'silver' => $silver,
      'gold' => $gold,
      'platinum' => $platinum,
      'diamond' => $diamond,
      'applications_evaluated' => $badges_awarded
    ]));
    
    $success = "Badge thresholds updated successfully! Re-evaluated $badges_awarded approved applications for new badges.";
  }
}

$thresholds_result = $conn->query("SELECT * FROM badge_thresholds ORDER BY views_required ASC");
$thresholds = [];
while ($row = $thresholds_result->fetch_assoc()) {
  $thresholds[$row['badge_type']] = $row;
}

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
      font-family: 'Inter', 'Segoe UI', sans-serif;
      background: #f5f7fa;
      min-height: 100vh;
    }
    
    /* Restored original spacing with margin-left instead of flex layout */
    .container {
      margin-left: 0px;
      padding: 30px;
      max-width: 1400px;
    }
    
    .header {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B5C3B 100%);
      color: white;
      padding: 30px;
      border-radius: 10px;
      margin-bottom: 30px;
      box-shadow: 0 4px 15px rgba(10, 77, 46, 0.2);
    }
    
    .header h1 {
      font-size: 28px;
      margin-bottom: 10px;
    }
    
    .header p {
      color: rgba(255, 255, 255, 0.9);
      font-size: 14px;
    }
    
    .alert {
      padding: 15px;
      border-radius: 6px;
      margin-bottom: 20px;
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
      border-left: 4px solid #28a745;
    }
    
    .info-box {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B5C3B 100%);
      color: white;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 30px;
      border-left: 4px solid #DAA520;
    }
    
    .info-box h3 {
      margin-bottom: 15px;
      font-size: 16px;
      color: #DAA520;
    }
    
    .info-box ul {
      margin-left: 20px;
    }
    
    .info-box li {
      margin-bottom: 8px;
      font-size: 13px;
      line-height: 1.6;
    }
    
    .threshold-card {
      background: white;
      border-radius: 10px;
      padding: 30px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      margin-bottom: 30px;
    }
    
    .threshold-card h2 {
      color: #0A4D2E;
      margin-bottom: 20px;
      font-size: 20px;
    }
    
    .threshold-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 20px;
      margin-bottom: 25px;
    }
    
    .threshold-item {
      background: linear-gradient(135deg, #f9f9f9 0%, #f5f5f5 100%);
      padding: 20px;
      border-radius: 8px;
      border-left: 4px solid #0A4D2E;
      transition: all 0.3s ease;
    }
    
    .threshold-item:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .badge-icon {
      font-size: 36px;
      margin-bottom: 15px;
    }
    
    .bronze { color: #CD7F32; }
    .silver { color: #C0C0C0; }
    .gold { color: #FFD700; }
    .platinum { color: #E5E4E2; }
    .diamond { color: #B9F2FF; }
    
    label {
      display: block;
      margin-bottom: 8px;
      color: #0A4D2E;
      font-weight: 700;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    
    input[type="number"] {
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 14px;
      transition: all 0.3s ease;
    }
    
    input[type="number"]:focus {
      outline: none;
      border-color: #0A4D2E;
      box-shadow: 0 0 5px rgba(10, 77, 46, 0.3);
    }
    
    small {
      color: #999;
      font-size: 12px;
      display: block;
      margin-top: 6px;
    }
    
    .save-btn {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B5C3B 100%);
      color: white;
      padding: 14px 30px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 700;
      transition: all 0.3s ease;
    }
    
    .save-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(10, 77, 46, 0.3);
    }
    
    .user-table {
      background: white;
      border-radius: 10px;
      padding: 30px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      overflow-x: auto;
    }
    
    .user-table h2 {
      color: #0A4D2E;
      margin-bottom: 20px;
      font-size: 20px;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
    }
    
    th, td {
      padding: 15px;
      text-align: left;
      border-bottom: 1px solid #f0f0f0;
      font-size: 13px;
    }
    
    th {
      background: #f5f7fa;
      font-weight: 700;
      color: #0A4D2E;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      border-bottom: 2px solid #DAA520;
    }
    
    td strong {
      color: #0A4D2E;
    }
    
    .badge-list {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    
    .badge-tag {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    
    .badge-tag.bronze { background: #CD7F32; color: white; }
    .badge-tag.silver { background: #C0C0C0; color: white; }
    .badge-tag.gold { background: #FFD700; color: #333; }
    .badge-tag.platinum { background: #E5E4E2; color: #333; }
    .badge-tag.diamond { background: #B9F2FF; color: #333; }
    
    .no-badges {
      color: #999;
      font-style: italic;
    }
    
    @media (max-width: 1024px) {
      .container {
        margin-left: 240px;
      }
    }
    
    @media (max-width: 768px) {
      .container {
        margin-left: 0;
        padding: 20px;
      }
      
      .threshold-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      }
      
      .header h1 {
        font-size: 22px;
      }
    }
    
    @media (max-width: 600px) {
      .container {
        padding: 15px;
      }
      
      .threshold-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <?php require_once '../includes/sidebar.php'; ?>
  
  <div class="container">
    <div class="header">
      <h1><i class="fas fa-medal"></i> Badge System Management</h1>
      <p>Manage view thresholds for automatic badge awarding. Badges are awarded automatically when IP works reach view milestones.</p>
    </div>
    
    <?php if (!empty($success)): ?>
      <div class="alert"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
      <div class="alert" style="background: #f8d7da; color: #721c24; border-color: #f5c6cb; border-left-color: #721c24;">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>
    
    <div class="info-box">
      <h3><i class="fas fa-info-circle"></i> How Badge System Works</h3>
      <ul>
        <li><strong>All IP types</strong> (Copyright, Patent, Trademark) can earn all 5 badge tiers equally</li>
        <li>Badges are awarded <strong>per application</strong> based on that work's view count</li>
        <li>Users earn innovation points with each badge (cumulative across all works)</li>
        <li>When you update thresholds, the system <strong>immediately re-evaluates</strong> all approved applications</li>
        <li>When a user earns a <strong>Diamond badge</strong>, they also receive an <strong>Achievement Certificate</strong></li>
      </ul>
    </div>
    
    <div class="threshold-card">
      <h2><i class="fas fa-sliders-h"></i> Badge Thresholds</h2>
      
      <form method="POST">
        <div class="threshold-grid">
          <div class="threshold-item">
            <div class="badge-icon bronze"><i class="fas fa-medal"></i></div>
            <label for="bronze">Bronze Badge</label>
            <input type="number" id="bronze" name="bronze_threshold" value="<?php echo $thresholds['Bronze']['views_required']; ?>" min="1" required>
            <small>Views required • Awards <?php echo $thresholds['Bronze']['points_awarded']; ?> points</small>
          </div>
          
          <div class="threshold-item">
            <div class="badge-icon silver"><i class="fas fa-medal"></i></div>
            <label for="silver">Silver Badge</label>
            <input type="number" id="silver" name="silver_threshold" value="<?php echo $thresholds['Silver']['views_required']; ?>" min="1" required>
            <small>Views required • Awards <?php echo $thresholds['Silver']['points_awarded']; ?> points</small>
          </div>
          
          <div class="threshold-item">
            <div class="badge-icon gold"><i class="fas fa-medal"></i></div>
            <label for="gold">Gold Badge</label>
            <input type="number" id="gold" name="gold_threshold" value="<?php echo $thresholds['Gold']['views_required']; ?>" min="1" required>
            <small>Views required • Awards <?php echo $thresholds['Gold']['points_awarded']; ?> points</small>
          </div>
          
          <div class="threshold-item">
            <div class="badge-icon platinum"><i class="fas fa-medal"></i></div>
            <label for="platinum">Platinum Badge</label>
            <input type="number" id="platinum" name="platinum_threshold" value="<?php echo $thresholds['Platinum']['views_required']; ?>" min="1" required>
            <small>Views required • Awards <?php echo $thresholds['Platinum']['points_awarded']; ?> points</small>
          </div>
          
          <div class="threshold-item">
            <div class="badge-icon diamond"><i class="fas fa-medal"></i></div>
            <label for="diamond">Diamond Badge</label>
            <input type="number" id="diamond" name="diamond_threshold" value="<?php echo $thresholds['Diamond']['views_required']; ?>" min="1" required>
            <small>Views required • Awards <?php echo $thresholds['Diamond']['points_awarded']; ?> points</small>
          </div>
        </div>
        
        <button type="submit" name="action" value="update_thresholds" class="save-btn">
          <i class="fas fa-save"></i> Save Thresholds
        </button>
      </form>
    </div>
    
    <div class="user-table">
      <h2><i class="fas fa-users"></i> User Badge Status</h2>
      
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
                <small style="color: #999;"><?php echo htmlspecialchars($user['email']); ?></small>
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
                  <span class="no-badges">No badges yet</span>
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
