<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('director');

// Get statistics
$stats = [
  'total_applications' => $conn->query("SELECT COUNT(*) as count FROM ip_applications")->fetch_assoc()['count'],
  'approved' => $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='approved'")->fetch_assoc()['count'],
  'pending' => $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status IN ('submitted', 'office_visit', 'payment_pending', 'payment_verified')")->fetch_assoc()['count'],
  'rejected' => $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='rejected'")->fetch_assoc()['count'],
  'total_users' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role='user'")->fetch_assoc()['count'],
  'total_views' => $conn->query("SELECT COUNT(*) as count FROM view_tracking")->fetch_assoc()['count'],
];

// IP type distribution
$type_dist = $conn->query("SELECT ip_type, COUNT(*) as count FROM ip_applications WHERE status='approved' GROUP BY ip_type");
$types = [];
while ($row = $type_dist->fetch_assoc()) {
  $types[] = $row;
}

// Recent approvals
$recent = $conn->query("SELECT a.*, u.full_name FROM ip_applications a JOIN users u ON a.user_id=u.id WHERE a.status='approved' ORDER BY a.approved_at DESC LIMIT 10");
$recent_approvals = $recent->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analytics - CHMSU IP System</title>
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
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
    }
    
    .stat-card {
      background: white;
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      border-top: 4px solid #667eea;
    }
    
    .stat-number {
      font-size: 28px;
      font-weight: bold;
      color: #333;
      margin-bottom: 5px;
    }
    
    .stat-label {
      font-size: 12px;
      color: #666;
    }
    
    .card {
      background: white;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .card-title {
      font-size: 16px;
      font-weight: 600;
      color: #333;
      margin-bottom: 15px;
    }
    
    .type-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    
    .type-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px;
      background: #f9f9f9;
      border-radius: 5px;
    }
    
    .type-name {
      font-weight: 600;
      color: #333;
    }
    
    .type-count {
      background: #667eea;
      color: white;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }
    
    .recent-table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .recent-table th {
      background: #f5f7fa;
      padding: 12px;
      text-align: left;
      font-size: 12px;
      font-weight: 600;
      color: #666;
      border-bottom: 1px solid #ddd;
    }
    
    .recent-table td {
      padding: 12px;
      border-bottom: 1px solid #f0f0f0;
      font-size: 13px;
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
    
    <div class="header">
      <h1><i class="fas fa-chart-bar"></i> System Analytics</h1>
      <p style="color: #666; font-size: 13px; margin-top: 5px;">Overview of IP registrations and system activity</p>
    </div>
    
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
        <div class="stat-label">Total Applications</div>
      </div>
      <div class="stat-card" style="border-top-color: #4caf50;">
        <div class="stat-number"><?php echo $stats['approved']; ?></div>
        <div class="stat-label">Approved</div>
      </div>
      <div class="stat-card" style="border-top-color: #ff9800;">
        <div class="stat-number"><?php echo $stats['pending']; ?></div>
        <div class="stat-label">Pending</div>
      </div>
      <div class="stat-card" style="border-top-color: #f44336;">
        <div class="stat-number"><?php echo $stats['rejected']; ?></div>
        <div class="stat-label">Rejected</div>
      </div>
      <div class="stat-card" style="border-top-color: #2196f3;">
        <div class="stat-number"><?php echo $stats['total_users']; ?></div>
        <div class="stat-label">Users</div>
      </div>
      <div class="stat-card" style="border-top-color: #9c27b0;">
        <div class="stat-number"><?php echo $stats['total_views']; ?></div>
        <div class="stat-label">Hub Views</div>
      </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
      <div class="card">
        <h2 class="card-title"><i class="fas fa-layer-group"></i> IP Type Distribution</h2>
        <div class="type-list">
          <?php foreach ($types as $type): ?>
            <div class="type-item">
              <span class="type-name"><?php echo $type['ip_type']; ?></span>
              <span class="type-count"><?php echo $type['count']; ?> registrations</span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      
      <div class="card">
        <h2 class="card-title"><i class="fas fa-info-circle"></i> System Information</h2>
        <div style="font-size: 13px; color: #666; line-height: 2;">
          <p><strong>System:</strong> CHMSU IP Registration & Hub</p>
          <p><strong>Location:</strong> <?php echo IP_OFFICE_LOCATION; ?></p>
          <p><strong>Hours:</strong> <?php echo IP_OFFICE_HOURS; ?></p>
          <p><strong>Contact:</strong> <?php echo IP_OFFICE_EMAIL; ?></p>
        </div>
      </div>
    </div>
    
    <div class="card" style="margin-top: 20px;">
      <h2 class="card-title"><i class="fas fa-check-circle"></i> Recently Approved Works</h2>
      <table class="recent-table">
        <thead>
          <tr>
            <th>Title</th>
            <th>Type</th>
            <th>Applicant</th>
            <th>Approved Date</th>
            <th>Certificate #</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent_approvals as $work): ?>
            <tr>
              <td><?php echo htmlspecialchars($work['title']); ?></td>
              <td><span style="background: #f0f0f0; padding: 4px 8px; border-radius: 3px; font-size: 11px;"><?php echo $work['ip_type']; ?></span></td>
              <td><?php echo htmlspecialchars($work['full_name']); ?></td>
              <td><?php echo date('M d, Y', strtotime($work['approved_at'])); ?></td>
              <td><strong><?php echo $work['certificate_id']; ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
