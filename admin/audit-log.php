<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('director');

$filter_action = trim($_GET['action'] ?? '');
$filter_user = trim($_GET['user'] ?? '');
$from_date = $_GET['from'] ?? '';
$to_date = $_GET['to'] ?? '';

$query = "SELECT al.*, u.full_name FROM audit_log al LEFT JOIN users u ON al.user_id=u.id WHERE 1=1";

if (!empty($filter_action)) {
  $filter_action = $conn->real_escape_string($filter_action);
  $query .= " AND al.action LIKE '%$filter_action%'";
}

if (!empty($filter_user)) {
  $filter_user = $conn->real_escape_string($filter_user);
  $query .= " AND u.full_name LIKE '%$filter_user%'";
}

if (!empty($from_date)) {
  $from_date = $conn->real_escape_string($from_date);
  $query .= " AND DATE(al.timestamp) >= '$from_date'";
}

if (!empty($to_date)) {
  $to_date = $conn->real_escape_string($to_date);
  $query .= " AND DATE(al.timestamp) <= '$to_date'";
}

$query .= " ORDER BY al.timestamp DESC LIMIT 500";

$result = $conn->query($query);
$logs = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit Trail - CHMSU IP System</title>
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
    
    .filters {
      background: white;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .filter-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 15px;
    }
    
    input[type="text"],
    input[type="date"] {
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 13px;
    }
    
    input:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 5px rgba(102,126,234,0.3);
    }
    
    button {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 10px 15px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
    }
    
    button:hover {
      transform: translateY(-2px);
    }
    
    .table-container {
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      overflow-x: auto;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
    }
    
    th {
      background: #f5f7fa;
      padding: 12px;
      text-align: left;
      font-size: 12px;
      font-weight: 600;
      color: #666;
      border-bottom: 1px solid #ddd;
    }
    
    td {
      padding: 12px;
      border-bottom: 1px solid #f0f0f0;
      font-size: 13px;
    }
    
    tr:hover {
      background: #f9f9f9;
    }
    
    .action-badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 3px;
      font-size: 11px;
      font-weight: 600;
      background: #e3f2fd;
      color: #1976d2;
    }
    
    .timestamp {
      font-family: monospace;
      font-size: 11px;
      color: #999;
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
    
    <div class="header">
      <h1><i class="fas fa-history"></i> System Audit Trail</h1>
      <p style="color: #666; font-size: 13px; margin-top: 5px;">Complete log of all system activities and changes</p>
    </div>
    
    <div class="filters">
      <form method="GET">
        <div class="filter-row">
          <input type="text" name="action" placeholder="Filter by action..." value="<?php echo htmlspecialchars($filter_action); ?>">
          <input type="text" name="user" placeholder="Filter by user..." value="<?php echo htmlspecialchars($filter_user); ?>">
          <input type="date" name="from" value="<?php echo htmlspecialchars($from_date); ?>">
          <input type="date" name="to" value="<?php echo htmlspecialchars($to_date); ?>">
        </div>
        <button type="submit"><i class="fas fa-filter"></i> Apply Filters</button>
      </form>
    </div>
    
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>User</th>
            <th>Action</th>
            <th>Entity Type</th>
            <th>Entity ID</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><span class="timestamp"><?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></span></td>
              <td><?php echo $log['full_name'] ? htmlspecialchars($log['full_name']) : 'System'; ?></td>
              <td><span class="action-badge"><?php echo htmlspecialchars($log['action']); ?></span></td>
              <td><?php echo htmlspecialchars($log['entity_type']); ?></td>
              <td><?php echo $log['entity_id']; ?></td>
              <td><span class="timestamp"><?php echo htmlspecialchars($log['ip_address']); ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
