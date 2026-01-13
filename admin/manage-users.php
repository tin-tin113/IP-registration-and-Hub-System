<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireRole(['clerk', 'director']);

$user_role = getUserRole();
$search = trim($_GET['search'] ?? '');
$filter_role = $_GET['role'] ?? 'user';

// Handle user status toggle - REMOVED (is_active column deprecated)

// query to get all users
$query = "SELECT
  u.id,
  u.email,
  u.full_name,
  u.role,
  u.department,
  u.innovation_points,
  u.created_at,
  COUNT(DISTINCT a.id) as total_applications,
  COUNT(DISTINCT CASE WHEN a.status='approved' THEN a.id END) as approved_applications,
  COUNT(DISTINCT b.id) as total_badges,
  COUNT(DISTINCT c.id) as total_certificates
FROM users u
LEFT JOIN ip_applications a ON u.id = a.user_id
LEFT JOIN badges b ON u.id = b.user_id
LEFT JOIN certificates c ON u.id = c.application_id
WHERE 1=1";

$params = [];
$types = '';

if (!empty($search)) {
  $search_escaped = $conn->real_escape_string($search);
  $query .= " AND (u.full_name LIKE '%$search_escaped%' OR u.email LIKE '%$search_escaped%' OR u.department LIKE '%$search_escaped%')";
}

if ($filter_role !== 'all') {
  $filter_role_escaped = $conn->real_escape_string($filter_role);
  $query .= " AND u.role = '$filter_role_escaped'";
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

$result = $conn->query($query);
$users = $result->fetch_all(MYSQLI_ASSOC);

// Get user details for view
$view_user_id = $_GET['view'] ?? null;
$view_user = null;
if ($view_user_id) {
  $stmt = $conn->prepare("
    SELECT u.*,
           COUNT(DISTINCT a.id) as total_applications,
           COUNT(DISTINCT CASE WHEN a.status='approved' THEN a.id END) as approved_applications,
           COUNT(DISTINCT CASE WHEN a.status='draft' THEN a.id END) as draft_applications,
           COUNT(DISTINCT CASE WHEN a.status='submitted' THEN a.id END) as submitted_applications,
           COUNT(DISTINCT v.id) as total_views,
           COUNT(DISTINCT b.id) as total_badges,
           COUNT(DISTINCT c.id) as total_certificates
    FROM users u
    LEFT JOIN ip_applications a ON u.id = a.user_id
    LEFT JOIN view_tracking v ON a.id = v.application_id
    LEFT JOIN badges b ON u.id = b.user_id
    LEFT JOIN certificates c ON u.id = c.application_id
    WHERE u.id = ?
    GROUP BY u.id
  ");
  $stmt->bind_param("i", $view_user_id);
  $stmt->execute();
  $view_user = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  
  // Get user's applications
  $apps_stmt = $conn->prepare("SELECT * FROM ip_applications WHERE user_id = ? ORDER BY created_at DESC");
  $apps_stmt->bind_param("i", $view_user_id);
  $apps_stmt->execute();
  $user_applications = $apps_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $apps_stmt->close();
  
  // Get user's badges
  $badges_stmt = $conn->prepare("SELECT * FROM badges WHERE user_id = ? ORDER BY awarded_at DESC");
  $badges_stmt->bind_param("i", $view_user_id);
  $badges_stmt->execute();
  $user_badges = $badges_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $badges_stmt->close();
  
  // Get user's certificates
  $certs_stmt = $conn->prepare("
    SELECT c.*, a.title, a.ip_type 
    FROM certificates c
    JOIN ip_applications a ON c.application_id = a.id
    WHERE a.user_id = ?
    ORDER BY c.issued_at DESC
  ");
  $certs_stmt->bind_param("i", $view_user_id);
  $certs_stmt->execute();
  $user_certificates = $certs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $certs_stmt->close();
  
  // Get user's verification profile
  $profile_stmt = $conn->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
  $profile_stmt->bind_param("i", $view_user_id);
  $profile_stmt->execute();
  $user_profile = $profile_stmt->get_result()->fetch_assoc();
  $profile_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Users - CHMSU IP System</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: #F1F5F9;
      min-height: 100vh;
    }
    
    .navbar {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      padding: 16px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    
    .navbar a {
      color: white;
      text-decoration: none;
      padding: 10px 18px;
      background: rgba(255,255,255,0.15);
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.2s;
    }
    
    .navbar a:hover {
      background: rgba(255,255,255,0.25);
    }
    
    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 30px 24px;
    }
    
    .header {
      background: white;
      padding: 24px;
      border-radius: 12px;
      margin-bottom: 24px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .header h1 {
      color: #0A4D2E;
      margin-bottom: 20px;
      font-size: 24px;
    }
    
    .search-filter {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
    }
    
    .search-box {
      flex: 1;
      min-width: 250px;
      position: relative;
    }
    
    .search-box input {
      width: 100%;
      padding: 12px 45px 12px 15px;
      border: 2px solid #E2E8F0;
      border-radius: 8px;
      font-size: 14px;
    }
    
    .search-box i {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #94A3B8;
    }
    
    .filter-select {
      padding: 12px 15px;
      border: 2px solid #E2E8F0;
      border-radius: 8px;
      font-size: 14px;
      background: white;
      cursor: pointer;
    }
    
    .users-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .user-card {
      background: white;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      transition: all 0.3s;
      cursor: pointer;
    }
    
    .user-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }
    
    .user-header {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 2px solid #F1F5F9;
    }
    
    .user-avatar {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: #DAA520;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      font-weight: 700;
    }
    
    .user-info h3 {
      color: #0A4D2E;
      margin-bottom: 5px;
      font-size: 18px;
    }
    
    .user-info p {
      color: #64748B;
      font-size: 13px;
    }
    
    .user-stats {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 15px;
      margin-top: 15px;
    }
    
    .stat-box {
      text-align: center;
      padding: 12px;
      background: #F8FAFC;
      border-radius: 8px;
    }
    
    .stat-number {
      font-size: 20px;
      font-weight: 700;
      color: #0A4D2E;
      margin-bottom: 5px;
    }
    
    .stat-label {
      font-size: 11px;
      color: #64748B;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .role-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      margin-top: 10px;
    }
    
    .role-user {
      background: #DBEAFE;
      color: #1E40AF;
    }
    
    .role-clerk {
      background: #FEF3C7;
      color: #92400E;
    }
    
    .role-director {
      background: #FCE7F3;
      color: #9F1239;
    }
    
    .view-btn {
      margin-top: 15px;
      width: 100%;
      padding: 10px;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .view-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(10, 77, 46, 0.3);
    }
    
    /* User Detail Modal */
    .user-detail-modal {
      display: <?php echo $view_user ? 'block' : 'none'; ?>;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      overflow-y: auto;
      padding: 20px;
    }
    
    .modal-content {
      max-width: 900px;
      margin: 0 auto;
      background: white;
      border-radius: 16px;
      padding: 40px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      padding-bottom: 20px;
      border-bottom: 2px solid #F1F5F9;
    }
    
    .modal-header h2 {
      color: #0A4D2E;
      font-size: 24px;
    }
    
    .close-btn {
      background: #F1F5F9;
      border: none;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      cursor: pointer;
      font-size: 20px;
      color: #64748B;
      transition: all 0.2s;
    }
    
    .close-btn:hover {
      background: #E2E8F0;
      color: #0A4D2E;
    }
    
    .detail-section {
      margin-bottom: 30px;
    }
    
    .detail-section h3 {
      color: #0A4D2E;
      margin-bottom: 15px;
      font-size: 18px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
    }
    
    .info-item {
      padding: 15px;
      background: #F8FAFC;
      border-radius: 8px;
    }
    
    .info-label {
      font-size: 12px;
      color: #64748B;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 5px;
    }
    
    .info-value {
      font-size: 16px;
      color: #0A4D2E;
      font-weight: 600;
    }
    
    .applications-table, .badges-list, .certificates-list {
      background: #F8FAFC;
      border-radius: 8px;
      padding: 20px;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
    }
    
    table th {
      text-align: left;
      padding: 12px;
      background: white;
      color: #0A4D2E;
      font-weight: 600;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    table td {
      padding: 12px;
      border-top: 1px solid #E2E8F0;
      font-size: 14px;
    }
    
    .status-badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
      text-transform: capitalize;
    }
    
    .status-approved {
      background: #DCFCE7;
      color: #166534;
    }
    
    .status-submitted {
      background: #FCE4EC;
      color: #C2185B;
    }
    
    .status-draft {
      background: #F0F0F0;
      color: #666;
    }
    
    .badge-item, .cert-item {
      display: inline-block;
      padding: 8px 15px;
      margin: 5px;
      background: white;
      border-radius: 8px;
      font-size: 13px;
    }
  </style>
</head>
<body>
  <div class="navbar">
    <div style="color: white; font-weight: 700; font-size: 18px;">
      <i class="fas fa-users"></i> User Management
    </div>
    <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
  </div>
  
  <div class="container">
    <div class="header">
      <h1><i class="fas fa-users"></i> System Users</h1>
      <div class="search-filter">
        <form method="GET" class="search-box" style="display: flex; flex: 1; min-width: 250px;">
          <input type="text" name="search" placeholder="Search by name, email, or department..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1;">
          <input type="hidden" name="role" value="<?php echo htmlspecialchars($filter_role); ?>">
          <button type="submit" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 8px 12px; cursor: pointer; color: #94A3B8;">
            <i class="fas fa-search"></i>
          </button>
        </form>
        <form method="GET" style="display: inline;">
          <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
          <select name="role" class="filter-select" onchange="this.form.submit()">
            <option value="all" <?php echo $filter_role === 'all' ? 'selected' : ''; ?>>All Roles</option>
            <option value="user" <?php echo $filter_role === 'user' ? 'selected' : ''; ?>>Users</option>
            <option value="clerk" <?php echo $filter_role === 'clerk' ? 'selected' : ''; ?>>Clerks</option>
            <option value="director" <?php echo $filter_role === 'director' ? 'selected' : ''; ?>>Directors</option>
          </select>
        </form>
      </div>
    </div>
    
    <div class="users-grid">
      <?php foreach ($users as $user): ?>
        <div class="user-card" onclick="window.location.href='?view=<?php echo $user['id']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_role !== 'all' ? '&role=' . $filter_role : ''; ?>'">
          <div class="user-header">
            <div class="user-avatar">
              <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
            </div>
            <div class="user-info">
              <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
              <p><?php echo htmlspecialchars($user['email']); ?></p>
              <?php if ($user['department']): ?>
                <p style="font-size: 12px; margin-top: 3px;"><i class="fas fa-building"></i> <?php echo htmlspecialchars($user['department']); ?></p>
              <?php endif; ?>
            </div>
          </div>
          
          <div class="user-stats">
            <div class="stat-box">
              <div class="stat-number"><?php echo $user['total_applications']; ?></div>
              <div class="stat-label">Applications</div>
            </div>
            <div class="stat-box">
              <div class="stat-number"><?php echo $user['approved_applications']; ?></div>
              <div class="stat-label">Approved</div>
            </div>
            <div class="stat-box">
              <div class="stat-number"><?php echo $user['total_badges']; ?></div>
              <div class="stat-label">Badges</div>
            </div>
            <div class="stat-box">
              <div class="stat-number"><?php echo $user['innovation_points'] ?? 0; ?></div>
              <div class="stat-label">Points</div>
            </div>
          </div>
          
          <span class="role-badge role-<?php echo $user['role']; ?>">
            <?php echo ucfirst($user['role']); ?>
          </span>
          
          <button class="view-btn" onclick="event.stopPropagation(); window.location.href='?view=<?php echo $user['id']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_role !== 'all' ? '&role=' . $filter_role : ''; ?>'">
            <i class="fas fa-eye"></i> View Profile
          </button>
        </div>
      <?php endforeach; ?>
    </div>
    
    <?php if (empty($users)): ?>
      <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px;">
        <i class="fas fa-users" style="font-size: 48px; color: #CBD5E1; margin-bottom: 15px;"></i>
        <p style="color: #64748B; font-size: 16px;">No users found</p>
      </div>
    <?php endif; ?>
  </div>
  
  <!-- User Detail Modal -->
  <?php if ($view_user): ?>
    <div class="user-detail-modal" onclick="if(event.target === this) window.location.href='manage-users.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?><?php echo $filter_role !== 'all' ? ($search ? '&' : '?') . 'role=' . $filter_role : ''; ?>'">
      <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header">
          <h2><i class="fas fa-user-circle"></i> User Profile</h2>
          <button class="close-btn" onclick="window.location.href='manage-users.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?><?php echo $filter_role !== 'all' ? ($search ? '&' : '?') . 'role=' . $filter_role : ''; ?>'">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div class="detail-section">
          <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px; padding: 20px; background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%); border-radius: 12px;">
            <div class="user-avatar" style="width: 80px; height: 80px; font-size: 32px;">
              <?php echo strtoupper(substr($view_user['full_name'], 0, 1)); ?>
            </div>
            <div>
              <h2 style="color: #0A4D2E; margin-bottom: 5px;"><?php echo htmlspecialchars($view_user['full_name']); ?></h2>
              <p style="color: #64748B; margin-bottom: 5px;"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($view_user['email']); ?></p>
              <span class="role-badge role-<?php echo $view_user['role']; ?>">
                <?php echo ucfirst($view_user['role']); ?>
              </span>
            </div>
          </div>
          
          <h3><i class="fas fa-info-circle"></i> Personal Information</h3>
          <div class="info-grid">
            <div class="info-item">
              <div class="info-label">Full Name</div>
              <div class="info-value"><?php echo htmlspecialchars($view_user['full_name']); ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Email Address</div>
              <div class="info-value"><?php echo htmlspecialchars($view_user['email']); ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Department</div>
              <div class="info-value"><?php echo htmlspecialchars($view_user['department'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Member Since</div>
              <div class="info-value"><?php echo date('F d, Y', strtotime($view_user['created_at'])); ?></div>
            </div>
          </div>
        </div>
        
        <!-- Verification Profile Section -->
        <?php if ($user_profile): ?>
        <div class="detail-section">
          <h3><i class="fas fa-user-check"></i> Verification Profile 
            <?php if ($user_profile['is_complete']): ?>
              <span style="background: #4CAF50; color: white; padding: 2px 10px; border-radius: 12px; font-size: 11px; margin-left: 10px;">Complete</span>
            <?php else: ?>
              <span style="background: #FF9800; color: white; padding: 2px 10px; border-radius: 12px; font-size: 11px; margin-left: 10px;">Incomplete</span>
            <?php endif; ?>
          </h3>
          <div class="info-grid">
            <div class="info-item">
              <div class="info-label">Verified Name</div>
              <div class="info-value"><?php echo htmlspecialchars(trim($user_profile['first_name'] . ' ' . ($user_profile['middle_name'] ? $user_profile['middle_name'][0] . '. ' : '') . $user_profile['last_name'] . ' ' . $user_profile['suffix'])); ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Student/Employee ID</div>
              <div class="info-value"><?php echo htmlspecialchars($user_profile['employee_id'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">College</div>
              <div class="info-value"><?php echo htmlspecialchars($user_profile['college'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Employment Status</div>
              <div class="info-value"><?php echo htmlspecialchars($user_profile['employment_status'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Birthdate</div>
              <div class="info-value"><?php echo $user_profile['birthdate'] ? date('M d, Y', strtotime($user_profile['birthdate'])) : 'N/A'; ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Gender</div>
              <div class="info-value"><?php echo htmlspecialchars($user_profile['gender'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Nationality</div>
              <div class="info-value"><?php echo htmlspecialchars($user_profile['nationality'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Contact</div>
              <div class="info-value"><?php echo htmlspecialchars($user_profile['contact_number'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-item" style="grid-column: span 2;">
              <div class="info-label">Address</div>
              <div class="info-value"><?php 
                $addr = array_filter([$user_profile['address_street'], $user_profile['address_barangay'], $user_profile['address_city'], $user_profile['address_province'], $user_profile['address_postal']]);
                echo htmlspecialchars(implode(', ', $addr) ?: 'N/A'); 
              ?></div>
            </div>
          </div>
        </div>
        <?php else: ?>
        <div class="detail-section">
          <h3><i class="fas fa-user-times"></i> Verification Profile</h3>
          <div style="background: #FFF3E0; border: 1px solid #FFB74D; border-radius: 8px; padding: 15px; color: #E65100; font-size: 13px;">
            <i class="fas fa-exclamation-triangle"></i> User has not submitted verification profile information yet.
          </div>
        </div>
        <?php endif; ?>
        
        <div class="detail-section">
          <h3><i class="fas fa-chart-bar"></i> Statistics</h3>
          <div class="info-grid">
            <div class="info-item">
              <div class="info-label">Total Applications</div>
              <div class="info-value"><?php echo $view_user['total_applications']; ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Approved Applications</div>
              <div class="info-value"><?php echo $view_user['approved_applications']; ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Draft Applications</div>
              <div class="info-value"><?php echo $view_user['draft_applications']; ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Submitted Applications</div>
              <div class="info-value"><?php echo $view_user['submitted_applications']; ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Total Views</div>
              <div class="info-value"><?php echo $view_user['total_views']; ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Innovation Points</div>
              <div class="info-value"><?php echo $view_user['innovation_points'] ?? 0; ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Total Badges</div>
              <div class="info-value"><?php echo $view_user['total_badges']; ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Total Certificates</div>
              <div class="info-value"><?php echo $view_user['total_certificates']; ?></div>
            </div>
          </div>
        </div>
        
        <?php if (count($user_applications) > 0): ?>
        <div class="detail-section">
          <h3><i class="fas fa-file-alt"></i> Applications (<?php echo count($user_applications); ?>)</h3>
          <div class="applications-table">
            <table>
              <thead>
                <tr>
                  <th>Title</th>
                  <th>IP Type</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($user_applications as $app): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($app['title']); ?></td>
                    <td><?php echo $app['ip_type']; ?></td>
                    <td><span class="status-badge status-<?php echo $app['status']; ?>"><?php echo ucwords(str_replace('_', ' ', $app['status'])); ?></span></td>
                    <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>
        
        <?php if (count($user_badges) > 0): ?>
        <div class="detail-section">
          <h3><i class="fas fa-medal"></i> Badges Earned (<?php echo count($user_badges); ?>)</h3>
          <div class="badges-list">
            <?php foreach ($user_badges as $badge): ?>
              <div class="badge-item">
                <strong><?php echo htmlspecialchars($badge['badge_type']); ?></strong>
                <br><small style="color: #64748B;">Awarded: <?php echo date('M d, Y', strtotime($badge['awarded_at'])); ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        
        <?php if (count($user_certificates) > 0): ?>
        <div class="detail-section">
          <h3><i class="fas fa-certificate"></i> Certificates (<?php echo count($user_certificates); ?>)</h3>
          <div class="certificates-list">
            <?php foreach ($user_certificates as $cert): ?>
              <div class="cert-item">
                <strong><?php echo htmlspecialchars($cert['title']); ?></strong>
                <br><small style="color: #64748B;"><?php echo $cert['ip_type']; ?> - <?php echo $cert['certificate_number']; ?></small>
                <br><small style="color: #64748B;">Issued: <?php echo date('M d, Y', strtotime($cert['issued_at'])); ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

</body>
</html>
