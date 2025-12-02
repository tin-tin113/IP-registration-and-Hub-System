<?php
function checkAndAwardBadges($user_id) {
  global $conn;
  
  if (!$user_id) {
    return; // Invalid user ID
  }
  
  // Ensure badge_thresholds table exists and has default data
  $conn->query("CREATE TABLE IF NOT EXISTS badge_thresholds (
    id INT PRIMARY KEY AUTO_INCREMENT,
    badge_type ENUM('Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond') NOT NULL UNIQUE,
    views_required INT NOT NULL,
    points_awarded INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_badge_type_threshold (badge_type)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  // Check if thresholds exist, if not, insert defaults
  $check_thresholds = $conn->query("SELECT COUNT(*) as count FROM badge_thresholds");
  $threshold_count = $check_thresholds->fetch_assoc()['count'];
  
  if ($threshold_count == 0) {
    $default_thresholds = [
      ['Bronze', 10, 50],
      ['Silver', 50, 150],
      ['Gold', 100, 300],
      ['Platinum', 250, 500],
      ['Diamond', 500, 1000]
    ];
    
    $insert_stmt = $conn->prepare("INSERT INTO badge_thresholds (badge_type, views_required, points_awarded) VALUES (?, ?, ?)");
    foreach ($default_thresholds as $threshold) {
      $insert_stmt->bind_param("sii", $threshold[0], $threshold[1], $threshold[2]);
      $insert_stmt->execute();
    }
    $insert_stmt->close();
  }
  
  // Get user's total views across all approved works
  // Count all views (both logged in and anonymous) for approved works
  $view_count_query = $conn->prepare("
    SELECT COUNT(DISTINCT v.id) as total_views
    FROM view_tracking v
    JOIN ip_applications a ON v.application_id = a.id
    WHERE a.user_id = ? AND a.status = 'approved'
  ");
  $view_count_query->bind_param("i", $user_id);
  $view_count_query->execute();
  $view_result = $view_count_query->get_result();
  $view_data = $view_result->fetch_assoc();
  $total_views = intval($view_data['total_views'] ?? 0);
  $view_count_query->close();
  
  // Get badge thresholds
  $thresholds_result = $conn->query("SELECT * FROM badge_thresholds ORDER BY views_required ASC");
  
  if (!$thresholds_result || $thresholds_result->num_rows === 0) {
    return; // No thresholds configured
  }
  
  while ($threshold = $thresholds_result->fetch_assoc()) {
    $views_required = intval($threshold['views_required']);
    $points_awarded = intval($threshold['points_awarded']);
    $badge_type = $threshold['badge_type'];
    
    // Check if user has reached this threshold
    if ($total_views >= $views_required) {
      // Check if user already has this badge
      $check = $conn->prepare("SELECT id FROM badges WHERE user_id = ? AND badge_type = ?");
      $check->bind_param("is", $user_id, $badge_type);
      $check->execute();
      $check_result = $check->get_result();
      
      if ($check_result->num_rows === 0) {
        // Award badge
        $award = $conn->prepare("INSERT INTO badges (user_id, badge_type, views_required) VALUES (?, ?, ?)");
        $award->bind_param("isi", $user_id, $badge_type, $views_required);
        
        if ($award->execute()) {
          $award->close();
          
          // Award points
          if ($points_awarded > 0) {
            $update_points = $conn->prepare("UPDATE users SET innovation_points = innovation_points + ? WHERE id = ?");
            $update_points->bind_param("ii", $points_awarded, $user_id);
            $update_points->execute();
            $update_points->close();
          }
          
          auditLog('Auto Award Badge', 'Badge', $user_id, null, json_encode([
            'badge_type' => $badge_type,
            'views_required' => $views_required,
            'points_awarded' => $points_awarded,
            'total_views' => $total_views
          ]));
        } else {
          $award->close();
        }
      }
      $check->close();
    }
  }
  $thresholds_result->free();
}
?>
