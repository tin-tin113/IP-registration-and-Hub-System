<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';
require_once '../config/form_fields_helper.php';

requireRole('director');

// Get statistics
$stats = [
  'total_applications' => $conn->query("SELECT COUNT(*) as count FROM ip_applications")->fetch_assoc()['count'],
  'approved' => $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='approved'")->fetch_assoc()['count'],
  // Pending includes various waiting states
  'pending' => $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status IN ('submitted', 'office_visit', 'payment_pending', 'payment_verified')")->fetch_assoc()['count'],
  'rejected' => $conn->query("SELECT COUNT(*) as count FROM ip_applications WHERE status='rejected'")->fetch_assoc()['count'],
  'total_users' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role='user'")->fetch_assoc()['count'],
];

// IP type distribution for Doughnut Chart
$all_types = ['Copyright', 'Patent', 'Trademark'];
$type_counts = array_fill_keys($all_types, 0);

$type_dist = $conn->query("SELECT ip_type, COUNT(*) as count FROM ip_applications WHERE status='approved' GROUP BY ip_type");
while ($row = $type_dist->fetch_assoc()) {
  if (isset($type_counts[$row['ip_type']])) {
    $type_counts[$row['ip_type']] = $row['count'];
  }
}

$types_labels = array_keys($type_counts);
$types_data = array_values($type_counts);

// Monthly Trends for Bar Chart (Last 6 months)
// Using helper to get last 6 months list ensuring no gaps (0 for empty months if needed, 
// for simplicity here we just fetch existing data, assuming activity. 
// For better charts, filling gaps is ideal, but let's stick to basic SQL for now).

// Applications by Department - Fixed order from form builder
$all_depts = getFormFieldOptions($conn, 'college');
// Filter out empty values from options
$all_depts = array_filter($all_depts, function($v) { return !empty(trim($v)); });

// Initialize all departments with 0 count in form builder order
$dept_counts = [];
foreach ($all_depts as $dept) {
    $dept_counts[$dept] = 0;
}

// Fetch actual application counts
$dept_query = "SELECT u.department, COUNT(*) as count FROM ip_applications a JOIN users u ON a.user_id=u.id WHERE u.department IS NOT NULL AND u.department != '' GROUP BY u.department";
$dept_res = $conn->query($dept_query);

if ($dept_res) {
    while($row = $dept_res->fetch_assoc()) {
        $dept_name = $row['department'];
        // Only update if department exists in our form builder list
        if (isset($dept_counts[$dept_name])) {
            $dept_counts[$dept_name] = (int)$row['count'];
        }
    }
}

// If form builder has no departments configured, show placeholder
if (empty($dept_counts)) {
    $dept_counts = ['No Departments Configured' => 0];
}

$dept_labels = array_keys($dept_counts);
$dept_data = array_values($dept_counts);

// Recent approvals
$recent = $conn->query("SELECT a.*, u.full_name FROM ip_applications a JOIN users u ON a.user_id=u.id WHERE a.status='approved' ORDER BY a.approved_at DESC LIMIT 5");
$recent_approvals = $recent->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analytics Dashboard - CHMSU IP System</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <script src="../assets/js/chart.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: #F8FAFC; color: #1E293B; }
    
    .container { padding: 32px; max-width: 1400px; margin: 0 auto; }
    
    .page-header {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white; padding: 32px; border-radius: 16px; margin-bottom: 32px;
      box-shadow: 0 4px 16px rgba(10, 77, 46, 0.2);
    }
    .page-header h1 { font-size: 28px; font-weight: 700; margin-bottom: 4px; }
    .page-header p { opacity: 0.9; font-size: 14px; }
    
    /* Stats Grid */
    .stats-grid {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px; margin-bottom: 32px;
    }
    
    .stat-card {
      background: white; border-radius: 16px; padding: 24px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.05);
      transition: transform 0.2s; cursor: pointer;
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 16px rgba(0,0,0,0.1); border-color: #1B7F4D; }
    
    .stat-label { font-size: 13px; font-weight: 600; color: #64748B; text-transform: uppercase; margin-bottom: 8px; }
    .stat-number { font-size: 36px; font-weight: 700; color: #0A4D2E; }
    .stat-icon { float: right; font-size: 24px; opacity: 0.2; color: #0A4D2E; }

    /* Charts Section */
    .charts-row {
      display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 32px;
    }
    
    .chart-card {
      background: white; border-radius: 16px; padding: 24px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.05);
    }
    
    .chart-header {
      display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;
    }
    .chart-title { font-size: 18px; font-weight: 700; color: #1E293B; display: flex; align-items: center; gap: 8px; }
    
    /* Table Section */
    .table-card {
      background: white; border-radius: 16px; overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.05);
    }
    
    .table-header { padding: 24px; border-bottom: 1px solid #F1F5F9; }
    
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 16px 24px; font-size: 12px; font-weight: 600; color: #64748B; text-transform: uppercase; background: #F8FAFC; border-bottom: 1px solid #E2E8F0; }
    td { padding: 16px 24px; border-bottom: 1px solid #F1F5F9; font-size: 14px; }
    tr:last-child td { border-bottom: none; }
    
    .type-badge {
      display: inline-block; padding: 4px 10px; border-radius: 20px;
      font-size: 11px; font-weight: 600; background: #F0FDF4; color: #15803D;
    }

    @media (max-width: 1024px) {
      .charts-row { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
      .container { padding: 16px; min-height: 100vh; }
    }

    /* Modal Styles */
    .modal-overlay {
      position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px);
      display: none; justify-content: center; align-items: center;
      z-index: 9999; padding: 20px;
    }
    .modal-overlay.active { display: flex; }
    
    .modal-content {
      background: white; border-radius: 20px; max-width: 900px; width: 100%;
      max-height: 80vh; overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.25);
      animation: modalSlideIn 0.3s ease;
    }
    @keyframes modalSlideIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .modal-header {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white; padding: 24px 28px;
      display: flex; justify-content: space-between; align-items: center;
    }
    .modal-header h2 { font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 12px; }
    .modal-close {
      background: rgba(255,255,255,0.2); border: none; color: white;
      width: 36px; height: 36px; border-radius: 50%; cursor: pointer;
      font-size: 18px; transition: all 0.2s;
    }
    .modal-close:hover { background: rgba(255,255,255,0.3); transform: scale(1.1); }
    
    .modal-body {
      padding: 0; max-height: calc(80vh - 80px); overflow-y: auto;
    }
    
    .modal-table { width: 100%; border-collapse: collapse; }
    .modal-table th {
      text-align: left; padding: 14px 20px; font-size: 11px; font-weight: 700;
      color: #64748B; text-transform: uppercase; background: #F8FAFC;
      border-bottom: 2px solid #E2E8F0; position: sticky; top: 0;
    }
    .modal-table td {
      padding: 14px 20px; border-bottom: 1px solid #F1F5F9; font-size: 13px;
    }
    .modal-table tr:hover { background: #F8FAFC; }
    
    .modal-empty {
      text-align: center; padding: 60px 20px; color: #94A3B8;
    }
    .modal-empty i { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
    
    .modal-loading {
      text-align: center; padding: 60px 20px; color: #64748B;
    }
    .modal-loading i { font-size: 32px; animation: spin 1s linear infinite; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    
    .status-badge {
      display: inline-block; padding: 4px 10px; border-radius: 20px;
      font-size: 10px; font-weight: 600; text-transform: uppercase;
    }
    .status-approved { background: #D1FAE5; color: #065F46; }
    .status-pending { background: #FEF3C7; color: #92400E; }
    .status-submitted { background: #DBEAFE; color: #1E40AF; }
  </style>
</head>
<body>
  <?php require_once '../includes/sidebar.php'; ?>
  
  <div class="container">
    <div class="page-header">
      <h1><i class="fas fa-chart-line" style="margin-right: 12px;"></i>Analytics Dashboard</h1>
      <p>Real-time insights on intellectual property registrations and system performance.</p>
    </div>
    
    <!-- Stats Row -->
    <div class="stats-grid">
      <div class="stat-card" data-type="total_applications" onclick="showDetailList(this)" title="Click to view all applications">
        <i class="fas fa-file-alt stat-icon"></i>
        <div class="stat-label">Total Applications</div>
        <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
      </div>
      <div class="stat-card" data-type="approved" onclick="showDetailList(this)" title="Click to view approved applications">
        <i class="fas fa-check-circle stat-icon" style="color: #10B981; opacity: 0.5;"></i>
        <div class="stat-label">Approved</div>
        <div class="stat-number" style="color: #10B981;"><?php echo $stats['approved']; ?></div>
      </div>
      <div class="stat-card" data-type="pending" onclick="showDetailList(this)" title="Click to view pending applications">
        <i class="fas fa-clock stat-icon" style="color: #F59E0B; opacity: 0.5;"></i>
        <div class="stat-label">Pending Process</div>
        <div class="stat-number" style="color: #F59E0B;"><?php echo $stats['pending']; ?></div>
      </div>
      <div class="stat-card" data-type="total_users" onclick="showDetailList(this)" title="Click to view researchers">
        <i class="fas fa-users stat-icon" style="color: #3B82F6; opacity: 0.5;"></i>
        <div class="stat-label">Researchers</div>
        <div class="stat-number" style="color: #3B82F6;"><?php echo $stats['total_users']; ?></div>
      </div>
    </div>
    
    <!-- Charts Row -->
    <div class="charts-row">
      <!-- Trends Chart -->
      <div class="chart-card">
        <div class="chart-header">
          <div class="chart-title"><i class="fas fa-building" style="color: #0A4D2E;"></i> Applications by Department</div>
        </div>
        <div style="height: 300px; position: relative;">
          <canvas id="trendsChart"></canvas>
        </div>
      </div>
      
      <!-- Distribution Chart -->
      <div class="chart-card">
        <div class="chart-header">
          <div class="chart-title"><i class="fas fa-chart-pie" style="color: #DAA520;"></i> IP Distribution</div>
        </div>
        <div style="height: 300px; position: relative; display: flex; justify-content: center;">
          <canvas id="distChart"></canvas>
        </div>
      </div>
    </div>
    
    <!-- Recent Approvals Table -->
    <div class="table-card">
      <div class="table-header">
        <div class="chart-title"><i class="fas fa-certificate" style="color: #10B981;"></i> Recently Approved</div>
      </div>
      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Applicant</th>
              <th>Type</th>
              <th>Date Approved</th>
              <th>Certificate ID</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent_approvals)): ?>
              <tr><td colspan="5" style="text-align: center; color: #999;">No approved applications yet.</td></tr>
            <?php else: ?>
              <?php foreach ($recent_approvals as $row): ?>
              <tr>
                <td style="font-weight: 500; color: #1E293B;"><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><span class="type-badge"><?php echo $row['ip_type']; ?></span></td>
                <td><?php echo date('M d, Y', strtotime($row['approved_at'])); ?></td>
                <td style="font-family: monospace; color: #64748B;"><?php echo htmlspecialchars($row['certificate_id']); ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    
  </div>

  <script>
    // --- Chart 1: Applications by Department ---
    const ctxTrends = document.getElementById('trendsChart').getContext('2d');
    
    // Distinct colors for departments (Tailwind-ish palette)
    const deptColors = [
      '#EF4444', // Red (CAS)
      '#F59E0B', // Amber (CBA)
      '#3B82F6', // Blue (CCJE)
      '#10B981', // Emerald (COED)
      '#6366F1', // Indigo (CET)
      '#06B6D4', // Cyan (CF)
      '#8B5CF6', // Purple (CIT)
      '#EC4899', // Pink (CON)
      '#64748B'  // Slate (GS)
    ];

    new Chart(ctxTrends, {
      type: 'bar',
      data: {
        labels: <?php echo json_encode($dept_labels); ?>,
        datasets: [{
          label: 'Applications',
          data: <?php echo json_encode($dept_data); ?>,
          backgroundColor: deptColors,
          borderRadius: 6,
          barThickness: 30
        }]
      },
      options: {
        indexAxis: 'y', // Horizontal bars
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false } // Identifying by axis label is enough
        },
        scales: {
          x: { beginAtZero: true, grid: { color: '#F1F5F9' } },
          y: { 
            grid: { display: false },
            ticks: { font: { weight: '600' } } // Make labels bolder
          }
        }
      }
    });

    // --- Chart 2: IP Distribution ---
    const ctxDist = document.getElementById('distChart').getContext('2d');
    const typeLabels = <?php echo json_encode($types_labels); ?>;
    const typeData = <?php echo json_encode($types_data); ?>;
    
    // Fallback if empty
    if (typeLabels.length === 0) {
       // Optional: show placeholder chart or message
    }

    new Chart(ctxDist, {
      type: 'doughnut',
      data: {
        labels: typeLabels,
        datasets: [{
          data: typeData,
          backgroundColor: [
            '#0A4D2E', // Dark Green
            '#DAA520', // Gold
            '#10B981', // Emerald
            '#3B82F6', // Blue
            '#F59E0B'  // Amber
          ],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
          legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } }
        }
      }
    });
  </script>

  <!-- Detail Modal -->
  <div class="modal-overlay" id="detailModal" onclick="closeModal(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
      <div class="modal-header">
        <h2 id="modalTitle"><i class="fas fa-list"></i> <span>Loading...</span></h2>
        <button class="modal-close" onclick="closeModal()">&times;</button>
      </div>
      <div class="modal-body" id="modalBody">
        <div class="modal-loading">
          <i class="fas fa-spinner"></i>
          <p>Loading data...</p>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Modal functionality for clickable stat cards
    function showDetailList(card) {
      const type = card.dataset.type;
      const modal = document.getElementById('detailModal');
      const modalTitle = document.getElementById('modalTitle');
      const modalBody = document.getElementById('modalBody');
      
      // Show modal with loading state
      modal.classList.add('active');
      modalBody.innerHTML = '<div class="modal-loading"><i class="fas fa-spinner"></i><p>Loading data...</p></div>';
      
      // Fetch data from API
      fetch('analytics-data.php?type=' + type)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const icon = getIconForType(type);
            modalTitle.innerHTML = '<i class="' + icon + '"></i> <span>' + data.title + '</span>';
            
            if (data.data.length === 0) {
              modalBody.innerHTML = '<div class="modal-empty"><i class="fas fa-inbox"></i><p>No data available</p></div>';
              return;
            }
            
            // Build table
            let tableHTML = '<table class="modal-table"><thead><tr>';
            data.columns.forEach(col => {
              tableHTML += '<th>' + col + '</th>';
            });
            tableHTML += '</tr></thead><tbody>';
            
            data.data.forEach(row => {
              tableHTML += '<tr>';
              if (type === 'total_users') {
                tableHTML += '<td style="font-weight: 500;">' + row.name + '</td>';
                tableHTML += '<td>' + row.email + '</td>';
                tableHTML += '<td>' + row.department + '</td>';
                tableHTML += '<td>' + row.date + '</td>';
              } else if (type === 'approved') {
                tableHTML += '<td style="font-weight: 500;">' + row.title + '</td>';
                tableHTML += '<td>' + row.applicant + '</td>';
                tableHTML += '<td><span class="type-badge">' + row.ip_type + '</span></td>';
                tableHTML += '<td style="font-family: monospace; color: #64748B;">' + row.certificate_id + '</td>';
                tableHTML += '<td>' + row.date + '</td>';
              } else {
                tableHTML += '<td style="font-weight: 500;">' + row.title + '</td>';
                tableHTML += '<td>' + row.applicant + '</td>';
                tableHTML += '<td><span class="type-badge">' + row.ip_type + '</span></td>';
                tableHTML += '<td><span class="status-badge ' + getStatusClass(row.status) + '">' + row.status + '</span></td>';
                tableHTML += '<td>' + row.date + '</td>';
              }
              tableHTML += '</tr>';
            });
            
            tableHTML += '</tbody></table>';
            modalBody.innerHTML = tableHTML;
          } else {
            modalBody.innerHTML = '<div class="modal-empty"><i class="fas fa-exclamation-triangle"></i><p>Error loading data</p></div>';
          }
        })
        .catch(error => {
          console.error('Error:', error);
          modalBody.innerHTML = '<div class="modal-empty"><i class="fas fa-exclamation-triangle"></i><p>Failed to load data</p></div>';
        });
    }
    
    function closeModal(event) {
      if (event && event.target !== event.currentTarget) return;
      document.getElementById('detailModal').classList.remove('active');
    }
    
    function getIconForType(type) {
      const icons = {
        'total_applications': 'fas fa-file-alt',
        'approved': 'fas fa-check-circle',
        'pending': 'fas fa-clock',
        'total_users': 'fas fa-users'
      };
      return icons[type] || 'fas fa-list';
    }
    
    function getStatusClass(status) {
      const lower = status.toLowerCase();
      if (lower.includes('approved')) return 'status-approved';
      if (lower.includes('pending') || lower.includes('payment') || lower.includes('office') || lower.includes('verified')) return 'status-pending';
      return 'status-submitted';
    }
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeModal();
    });
  </script>
</body>
</html>
