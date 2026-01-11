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
      font-family: 'Inter', 'Segoe UI', sans-serif;
      background: #f5f7fa;
      min-height: 100vh;
    }
    
    /* Restored original spacing with margin-left */
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
    
    .filters-card {
      background: white;
      padding: 25px;
      border-radius: 10px;
      margin-bottom: 30px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .filter-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 15px;
    }
    
    input[type="text"],
    input[type="date"] {
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 13px;
      transition: all 0.3s ease;
    }
    
    input:focus {
      outline: none;
      border-color: #0A4D2E;
      box-shadow: 0 0 5px rgba(10, 77, 46, 0.3);
    }
    
    .filter-btn {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B5C3B 100%);
      color: white;
      padding: 12px 20px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .filter-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(10, 77, 46, 0.3);
    }
    
    .table-container {
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      overflow-x: auto;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
    }
    
    th {
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
    
    td {
      padding: 15px;
      border-bottom: 1px solid #f0f0f0;
      font-size: 13px;
    }
    
    tr:hover {
      background: #f9f9f9;
    }
    
    .action-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 700;
      background: linear-gradient(135deg, #DAA520 0%, #c89d1a 100%);
      color: white;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    
    .timestamp {
      font-family: 'Monaco', 'Courier New', monospace;
      font-size: 11px;
      color: #999;
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
      
      .filter-row {
        grid-template-columns: 1fr;
      }
    }
    
    @media (max-width: 600px) {
      .container {
        padding: 15px;
      }
    }
    
    /* Modal Styles */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }
    
    .modal-content {
      background: white;
      padding: 0;
      border-radius: 12px;
      width: 90%;
      max-width: 600px;
      max-height: 80vh;
      display: flex;
      flex-direction: column;
      box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    }
    
    .modal-header {
      padding: 20px;
      border-bottom: 1px solid #eee;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #f8f9fa;
      border-radius: 12px 12px 0 0;
    }
    
    .modal-header h3 {
      font-size: 18px;
      margin: 0;
      color: #1a1a1a;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .modal-close {
      background: none;
      border: none;
      font-size: 20px;
      color: #666;
      cursor: pointer;
    }
    
    .modal-body {
      padding: 20px;
      overflow-y: auto;
    }
    
    .detail-group {
      margin-bottom: 15px;
    }
    
    .detail-label {
      font-size: 12px;
      font-weight: 700;
      color: #666;
      text-transform: uppercase;
      margin-bottom: 5px;
    }
    
    .detail-value {
      font-size: 14px;
      color: #333;
      background: #f8f9fa;
      padding: 10px;
      border-radius: 6px;
      border: 1px solid #eee;
      word-break: break-all;
    }
    
    .clean-details {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    
    .clean-row {
      display: flex;
      border-bottom: 1px solid #f0f0f0;
      padding-bottom: 8px;
    }
    
    .clean-row:last-child {
      border-bottom: none;
    }
    
    .clean-label {
      font-weight: 600;
      color: #555;
      width: 140px;
      flex-shrink: 0;
    }
    
    .clean-value {
      color: #111;
      word-break: break-word;
    }

    tr { cursor: pointer; transition: background 0.2s; }
  </style>
</head>
<body>
  <?php require_once '../includes/sidebar.php'; ?>
  
  <div class="container">
    <div class="header">
      <h1><i class="fas fa-history"></i> System Audit Trail</h1>
      <p>Complete log of all system activities and changes</p>
    </div>
    
    <div class="filters-card">
      <form method="GET">
        <div class="filter-row">
          <input type="text" name="action" placeholder="Filter by action..." value="<?php echo htmlspecialchars($filter_action); ?>">
          <input type="text" name="user" placeholder="Filter by user..." value="<?php echo htmlspecialchars($filter_user); ?>">
          <input type="date" name="from" value="<?php echo htmlspecialchars($from_date); ?>">
          <input type="date" name="to" value="<?php echo htmlspecialchars($to_date); ?>">
        </div>
        <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Apply Filters</button>
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
              <tr onclick="showDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)">
                <td><span class="timestamp"><?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></span></td>
                <td><?php echo $log['full_name'] ? htmlspecialchars($log['full_name']) : '<em style="color: #999;">System</em>'; ?></td>
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

  <div id="detailsModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-info-circle" style="color: #0A4D2E;"></i> Audit Details</h3>
        <button class="modal-close" onclick="closeModal()">&times;</button>
      </div>
      <div class="modal-body">
        <div class="detail-group">
          <div class="detail-label">Action</div>
          <div class="detail-value" id="modalAction"></div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
          <div class="detail-group">
            <div class="detail-label">User</div>
            <div class="detail-value" id="modalUser"></div>
          </div>
          <div class="detail-group">
            <div class="detail-label">IP Address</div>
            <div class="detail-value" id="modalIp"></div>
          </div>
          <div class="detail-group">
            <div class="detail-label">Timestamp</div>
            <div class="detail-value" id="modalTime"></div>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
          <div class="detail-group">
            <div class="detail-label">Entity Type</div>
            <div class="detail-value" id="modalEntity"></div>
          </div>
          <div class="detail-group">
            <div class="detail-label">Entity ID</div>
            <div class="detail-value" id="modalEntityId"></div>
          </div>
        </div>

        <div class="detail-group" id="oldValueGroup" style="display: none;">
          <div class="detail-label">Previous Value</div>
          <div class="detail-value json-view" id="modalOldValue"></div>
        </div>

        <div class="detail-group" id="newValueGroup" style="display: none;">
          <div class="detail-label">Details</div>
          <div class="detail-value json-view" id="modalNewValue"></div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function formatToHtml(data) {
      if (!data) return '';
      try {
        const json = JSON.parse(data);
        if (typeof json === 'object' && json !== null) {
          let html = '<div class="clean-details">';
          for (const [key, value] of Object.entries(json)) {
             // Handle nested arrays/objects simply or recursively if needed
             let displayValue = value;
             if (typeof value === 'object' && value !== null) {
               displayValue = JSON.stringify(value).replace(/[{"}]/g, '').replace(/,/g, ', '); 
             }
             // Be more specific about titles
             let label = key.replace(/_/g, ' ');
             // Capitalize
             label = label.charAt(0).toUpperCase() + label.slice(1);
             
             html += `<div class="clean-row">
                        <span class="clean-label">${label}:</span>
                        <span class="clean-value">${displayValue}</span>
                      </div>`;
          }
          html += '</div>';
          return html;
        }
        return data; 
      } catch (e) {
        return data; // Not JSON, return as is
      }
    }

    function showDetails(log) {
      document.getElementById('modalAction').textContent = log.action;
      document.getElementById('modalUser').textContent = log.full_name || 'System';
      document.getElementById('modalIp').textContent = log.ip_address || 'N/A';
      document.getElementById('modalTime').textContent = log.timestamp;
      document.getElementById('modalEntity').textContent = log.entity_type || 'N/A';
      document.getElementById('modalEntityId').textContent = log.entity_id || 'N/A';

      // Handle Old Value
      const oldGroup = document.getElementById('oldValueGroup');
      const oldElem = document.getElementById('modalOldValue');
      
      if (log.old_value) {
        oldElem.innerHTML = formatToHtml(log.old_value);
        oldGroup.style.display = 'block';
      } else {
        oldGroup.style.display = 'none';
      }

      // Handle New Value
      const newGroup = document.getElementById('newValueGroup');
      const newElem = document.getElementById('modalNewValue');
      
      if (log.new_value) {
        newElem.innerHTML = formatToHtml(log.new_value);
        newGroup.style.display = 'block';
      } else {
        newGroup.style.display = 'none';
      }

      document.getElementById('detailsModal').style.display = 'flex';
    }

    function closeModal() {
      document.getElementById('detailsModal').style.display = 'none';
    }

    // Close on outside click
    document.getElementById('detailsModal').addEventListener('click', function(e) {
      if (e.target === this) closeModal();
    });
  </script>
</body>
</html>
