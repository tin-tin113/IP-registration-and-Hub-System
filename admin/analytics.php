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
      font-family: 'Inter', 'Segoe UI', sans-serif;
      background: #f5f7fa;
      min-height: 100vh;
    }
    
    /* Removed margin-left spacing to attach flush to sidebar */
    .container {
      margin-left: 0;
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
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .stat-card {
      background: white;
      border-radius: 10px;
      padding: 25px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      border-left: 5px solid #0A4D2E;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 20px rgba(0,0,0,0.12);
    }
    
    .stat-card.approved {
      border-left-color: #4caf50;
    }
    
    .stat-card.pending {
      border-left-color: #ff9800;
    }
    
    .stat-card.rejected {
      border-left-color: #f44336;
    }
    
    .stat-card.secondary {
      border-left-color: #2196f3;
    }
    
    .stat-card.tertiary {
      border-left-color: #9c27b0;
    }
    
    .stat-number {
      font-size: 32px;
      font-weight: 700;
      color: #0A4D2E;
      margin-bottom: 8px;
    }
    
    .stat-label {
      font-size: 13px;
      color: #666;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .card {
      background: white;
      border-radius: 10px;
      padding: 25px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .card-title {
      font-size: 18px;
      font-weight: 700;
      color: #0A4D2E;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .card-title i {
      color: #DAA520;
    }
    
    .type-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    
    .type-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px;
      background: #f9f9f9;
      border-radius: 8px;
      border-left: 3px solid #DAA520;
      transition: all 0.3s ease;
    }
    
    .type-item:hover {
      background: #f0f0f0;
      transform: translateX(5px);
    }
    
    .type-name {
      font-weight: 600;
      color: #0A4D2E;
      font-size: 14px;
    }
    
    .type-count {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B5C3B 100%);
      color: white;
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 700;
    }
    
    .info-section {
      background: linear-gradient(135deg, #DAA520 0%, #c89d1a 100%);
      color: white;
      padding: 20px;
      border-radius: 8px;
      margin-top: 20px;
    }
    
    .info-section p {
      margin: 8px 0;
      font-size: 13px;
    }
    
    .info-section strong {
      display: inline-block;
      margin-right: 8px;
    }
    
    .recent-table {
      width: 100%;
      border-collapse: collapse;
      overflow: hidden;
    }
    
    .recent-table th {
      background: #f5f7fa;
      padding: 15px;
      text-align: left;
      font-size: 12px;
      font-weight: 700;
      color: #0A4D2E;
      border-bottom: 2px solid #DAA520;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .recent-table td {
      padding: 15px;
      border-bottom: 1px solid #f0f0f0;
      font-size: 13px;
    }
    
    .recent-table tr:hover {
      background: #f9f9f9;
    }
    
    .cert-id {
      font-weight: 700;
      color: #0A4D2E;
    }
    
    .type-badge {
      background: #f0f0f0;
      padding: 4px 10px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 600;
      color: #0A4D2E;
    }
    
    @media (max-width: 1024px) {
      .container {
        margin-left: 0;
      }
    }
    
    @media (max-width: 768px) {
      .container {
        margin-left: 0;
        padding: 20px;
      }
      
      .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      }
    }
    
    @media (max-width: 600px) {
      .container {
        padding: 15px;
      }
      
      .stats-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <?php require_once '../includes/sidebar.php'; ?>
  
  <div class="container">
    <div class="header">
      <h1><i class="fas fa-chart-bar"></i> System Analytics</h1>
      <p>Overview of IP registrations and system activity</p>
    </div>
    
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
        <div class="stat-label">Total Applications</div>
      </div>
      <div class="stat-card approved">
        <div class="stat-number"><?php echo $stats['approved']; ?></div>
        <div class="stat-label">Approved</div>
      </div>
      <div class="stat-card pending">
        <div class="stat-number"><?php echo $stats['pending']; ?></div>
        <div class="stat-label">Pending</div>
      </div>
      <div class="stat-card rejected">
        <div class="stat-number"><?php echo $stats['rejected']; ?></div>
        <div class="stat-label">Rejected</div>
      </div>
      <div class="stat-card secondary">
        <div class="stat-number"><?php echo $stats['total_users']; ?></div>
        <div class="stat-label">Users</div>
      </div>
      <div class="stat-card tertiary">
        <div class="stat-number"><?php echo $stats['total_views']; ?></div>
        <div class="stat-label">Hub Views</div>
      </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
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
        <div class="info-section">
          <p><strong>System:</strong> CHMSU IP Registration & Hub</p>
          <p><strong>Location:</strong> <?php echo IP_OFFICE_LOCATION; ?></p>
          <p><strong>Hours:</strong> <?php echo IP_OFFICE_HOURS; ?></p>
          <p><strong>Contact:</strong> <?php echo IP_OFFICE_EMAIL; ?></p>
        </div>
      </div>
    </div>
    
    <div class="card">
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
              <td><span class="type-badge"><?php echo $work['ip_type']; ?></span></td>
              <td><?php echo htmlspecialchars($work['full_name']); ?></td>
              <td><?php echo date('M d, Y', strtotime($work['approved_at'])); ?></td>
              <td><span class="cert-id"><?php echo $work['certificate_id']; ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
