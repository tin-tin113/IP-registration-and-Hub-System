<?php
// Award badges per application based on IP type
function checkAndAwardBadges($application_id) {
  global $conn;
  
  if (!$application_id) {
    return; // Invalid application ID
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
  
  // Update badges table structure if needed
  $columns_check = $conn->query("SHOW COLUMNS FROM badges LIKE 'application_id'");
  if ($columns_check->num_rows === 0) {
    $conn->query("ALTER TABLE badges ADD COLUMN application_id INT NULL");
    $conn->query("ALTER TABLE badges ADD COLUMN ip_type ENUM('Copyright', 'Patent', 'Trademark') NULL");
    $conn->query("ALTER TABLE badges ADD COLUMN work_title VARCHAR(255) NULL");
    $conn->query("ALTER TABLE badges ADD INDEX idx_badge_app (application_id)");
    $conn->query("ALTER TABLE badges ADD FOREIGN KEY (application_id) REFERENCES ip_applications(id) ON DELETE SET NULL");
  }
  
  // Create achievement_certificates table if not exists
  $conn->query("CREATE TABLE IF NOT EXISTS achievement_certificates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    certificate_number VARCHAR(50) UNIQUE NOT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ach_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  // Get application details
  $app_stmt = $conn->prepare("SELECT id, user_id, title, ip_type, status FROM ip_applications WHERE id = ? AND status = 'approved'");
  $app_stmt->bind_param("i", $application_id);
  $app_stmt->execute();
  $app_result = $app_stmt->get_result();
  
  if ($app_result->num_rows === 0) {
    $app_stmt->close();
    return; // Application not found or not approved
  }
  
  $application = $app_result->fetch_assoc();
  $user_id = $application['user_id'];
  $app_title = $application['title'];
  $ip_type = $application['ip_type'];
  $app_stmt->close();
  
  // Get view count for this specific application
  $view_count_stmt = $conn->prepare("SELECT COUNT(DISTINCT v.id) as view_count FROM view_tracking v WHERE v.application_id = ?");
  $view_count_stmt->bind_param("i", $application_id);
  $view_count_stmt->execute();
  $view_result = $view_count_stmt->get_result();
  $view_data = $view_result->fetch_assoc();
  $view_count = intval($view_data['view_count'] ?? 0);
  $view_count_stmt->close();
  
  // All IP types are eligible for all badge tiers
  // Badges are awarded based on views, not IP type
  $available_badges = ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond'];
  
  // Get badge thresholds
  $thresholds_result = $conn->query("SELECT * FROM badge_thresholds ORDER BY views_required ASC");
  
  if (!$thresholds_result || $thresholds_result->num_rows === 0) {
    return; // No thresholds configured
  }
  
  $points_awarded_total = 0;
  
  while ($threshold = $thresholds_result->fetch_assoc()) {
    $views_required = intval($threshold['views_required']);
    $points_awarded = intval($threshold['points_awarded']);
    $badge_type = $threshold['badge_type'];
    
    // Only award badges available for this IP type
    if (!in_array($badge_type, $available_badges)) {
      continue;
    }
    
    // Check if this application has reached this threshold
    if ($view_count >= $views_required) {
      // Check if this application already has this badge
      $check = $conn->prepare("SELECT id FROM badges WHERE application_id = ? AND badge_type = ?");
      $check->bind_param("is", $application_id, $badge_type);
      $check->execute();
      $check_result = $check->get_result();
      
      if ($check_result->num_rows === 0) {
        // Award badge for this application
        $award = $conn->prepare("INSERT INTO badges (user_id, badge_type, views_required, application_id, ip_type, work_title) VALUES (?, ?, ?, ?, ?, ?)");
        $award->bind_param("isiiss", $user_id, $badge_type, $views_required, $application_id, $ip_type, $app_title);
        
        if ($award->execute()) {
          $award->close();
          
          // Award points (accumulate for total points)
          $points_awarded_total += $points_awarded;
          
          auditLog('Auto Award Badge', 'Badge', $user_id, null, json_encode([
            'badge_type' => $badge_type,
            'application_id' => $application_id,
            'work_title' => $app_title,
            'ip_type' => $ip_type,
            'views_required' => $views_required,
            'points_awarded' => $points_awarded,
            'view_count' => $view_count
          ]));
        } else {
          $award->close();
        }
      }
      $check->close();
    }
  }
  
  // Update user's innovation points based on badge thresholds (ensure it matches)
  // Update user's innovation points based on badge thresholds (ensure it matches)
  // Always recalculate to ensure points are in sync with current thresholds
  
  // Recalculate total points based on all badges earned
  $points_stmt = $conn->prepare("
    SELECT SUM(bt.points_awarded) as total_points
    FROM badges b
    JOIN badge_thresholds bt ON b.badge_type = bt.badge_type
    WHERE b.user_id = ?
  ");
  $points_stmt->bind_param("i", $user_id);
  $points_stmt->execute();
  $points_result = $points_stmt->get_result();
  $points_data = $points_result->fetch_assoc();
  $calculated_points = intval($points_data['total_points'] ?? 0);
  $points_stmt->close();
  
  // Get current points
  $current_points_stmt = $conn->prepare("SELECT innovation_points FROM users WHERE id = ?");
  $current_points_stmt->bind_param("i", $user_id);
  $current_points_stmt->execute();
  $current_points_result = $current_points_stmt->get_result();
  $current_points = intval($current_points_result->fetch_assoc()['innovation_points'] ?? 0);
  $current_points_stmt->close();
  
  // Update to match calculated points
  if ($calculated_points != $current_points) {
    $update_points = $conn->prepare("UPDATE users SET innovation_points = ? WHERE id = ?");
    $update_points->bind_param("ii", $calculated_points, $user_id);
    $update_points->execute();
    $update_points->close();
  }
  
  // Check if user has all badges and award achievement certificate
  checkAchievementCertificate($user_id);
  
  $thresholds_result->free();
}

// Check and award achievement certificate when user earns Diamond badge
// Diamond badge means the application reached 500+ views and earned all 5 badge tiers
function checkAchievementCertificate($user_id) {
  global $conn;
  
  // Check if user has earned at least one Diamond badge
  // Earning Diamond means one application reached all 5 badge levels (500+ views)
  $diamond_check = $conn->prepare("SELECT id, application_id, work_title FROM badges WHERE user_id = ? AND badge_type = 'Diamond' LIMIT 1");
  $diamond_check->bind_param("i", $user_id);
  $diamond_check->execute();
  $diamond_result = $diamond_check->get_result();
  
  if ($diamond_result->num_rows > 0) {
    $diamond_badge = $diamond_result->fetch_assoc();
    
    // Check if achievement certificate already exists
    $cert_check = $conn->prepare("SELECT id FROM achievement_certificates WHERE user_id = ?");
    $cert_check->bind_param("i", $user_id);
    $cert_check->execute();
    
    if ($cert_check->get_result()->num_rows === 0) {
      // Create achievement certificate
      $cert_number = 'ACH-' . date('Y') . '-' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
      
      $insert_cert = $conn->prepare("INSERT INTO achievement_certificates (user_id, certificate_number, issued_at) VALUES (?, ?, NOW())");
      $insert_cert->bind_param("is", $user_id, $cert_number);
      $insert_cert->execute();
      $insert_cert->close();
      
      auditLog('Award Achievement Certificate', 'Achievement', $user_id, null, json_encode([
        'certificate_number' => $cert_number,
        'triggered_by_application' => $diamond_badge['application_id'],
        'work_title' => $diamond_badge['work_title']
      ]));
    }
    $cert_check->close();
  }
  $diamond_check->close();
}
?>
