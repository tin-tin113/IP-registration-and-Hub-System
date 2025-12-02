<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

$search = trim($_GET['search'] ?? '');
$filter_type = trim($_GET['type'] ?? 'all');
$user_id = isLoggedIn() ? getCurrentUserId() : null;

// Build query
$query = "SELECT a.*, u.full_name, u.email, COUNT(DISTINCT v.id) as view_count FROM ip_applications a JOIN users u ON a.user_id=u.id LEFT JOIN view_tracking v ON a.id=v.application_id WHERE a.status='approved'";

if (!empty($search)) {
  $search_term = '%' . $conn->real_escape_string($search) . '%';
  $query .= " AND (a.title LIKE '$search_term' OR a.description LIKE '$search_term' OR u.full_name LIKE '$search_term')";
}

if ($filter_type !== 'all') {
  $filter_type = $conn->real_escape_string($filter_type);
  $query .= " AND a.ip_type='$filter_type'";
}

$query .= " GROUP BY a.id ORDER BY a.approved_at DESC";

$result = $conn->query($query);
$works = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>IP Hub - CHMSU</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: #F8FAFC;
      min-height: 100vh;
    }
    
    /* Modern navbar with CHMSU branding */
    .navbar {
      background: white;
      border-bottom: 1px solid #E2E8F0;
      padding: 16px 24px;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .nav-container {
      max-width: 1400px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .logo {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 18px;
      font-weight: 700;
      color: #0A4D2E;
      text-decoration: none;
    }
    
    .logo-img {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
      border: 4px solid #E07D32;
      background: white;
      padding: 3px;
      box-shadow: 0 8px 20px rgba(27, 92, 59, 0.3), 0 0 0 2px rgba(255, 255, 255, 0.1);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .logo-img:hover {
      transform: scale(1.15) rotate(8deg);
      box-shadow: 0 12px 30px rgba(27, 92, 59, 0.5), 0 0 25px rgba(224, 125, 50, 0.7);
      border-color: #FFD700;
    }
    
    .nav-right {
      display: flex;
      gap: 12px;
      align-items: center;
    }
    
    .nav-right a {
      color: #64748B;
      text-decoration: none;
      font-size: 14px;
      font-weight: 600;
      padding: 8px 16px;
      border-radius: 8px;
      transition: all 0.2s;
    }
    
    .nav-right a:hover {
      background: #F1F5F9;
      color: #0A4D2E;
    }
    
    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 40px 24px;
    }
    
    /* Hero section for IP Hub */
    .header {
      text-align: center;
      margin-bottom: 48px;
    }
    
    .header h1 {
      font-size: 48px;
      font-weight: 800;
      color: #0A4D2E;
      margin-bottom: 12px;
      letter-spacing: -1.5px;
    }
    
    .header p {
      font-size: 18px;
      color: #64748B;
      max-width: 600px;
      margin: 0 auto;
    }
    
    .search-box {
      display: flex;
      gap: 12px;
      margin-bottom: 32px;
      max-width: 800px;
      margin-left: auto;
      margin-right: auto;
    }
    
    .search-input {
      flex: 1;
      padding: 14px 20px;
      border: 2px solid #E2E8F0;
      border-radius: 12px;
      font-size: 15px;
      background: white;
      transition: all 0.2s;
    }
    
    .search-input:focus {
      outline: none;
      border-color: #1B7F4D;
      box-shadow: 0 0 0 4px rgba(27, 127, 77, 0.1);
    }
    
    .search-btn {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      padding: 14px 28px;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      font-weight: 600;
      font-size: 15px;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s;
      box-shadow: 0 4px 12px rgba(10, 77, 46, 0.3);
    }
    
    .search-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(10, 77, 46, 0.4);
    }
    
    .filters {
      display: flex;
      gap: 12px;
      margin-bottom: 40px;
      flex-wrap: wrap;
      justify-content: center;
    }
    
    .filter-btn {
      padding: 10px 20px;
      border: 2px solid #E2E8F0;
      background: white;
      border-radius: 50px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      color: #64748B;
      transition: all 0.2s;
    }
    
    .filter-btn:hover {
      border-color: #1B7F4D;
      color: #0A4D2E;
    }
    
    .filter-btn.active {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      border-color: #0A4D2E;
    }
    
    .works-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 24px;
    }
    
    /* Modern card design */
    .work-card {
      background: white;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      transition: all 0.3s;
      border: 1px solid #E2E8F0;
      display: block;
      text-decoration: none;
      color: inherit;
    }
    
    .work-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 12px 40px rgba(10, 77, 46, 0.15);
      border-color: #1B7F4D;
    }
    
    .work-header {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      padding: 24px;
      position: relative;
      overflow: hidden;
    }
    
    .work-header::before {
      content: '';
      position: absolute;
      width: 200px;
      height: 200px;
      background: radial-gradient(circle, rgba(218, 165, 32, 0.2) 0%, transparent 70%);
      top: -100px;
      right: -100px;
      border-radius: 50%;
    }
    
    .work-title {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 12px;
      line-height: 1.3;
      position: relative;
    }
    
    .work-meta {
      font-size: 13px;
      opacity: 0.95;
      display: flex;
      gap: 16px;
      position: relative;
    }
    
    .work-body {
      padding: 20px;
    }
    
    .work-type {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .type-copyright {
      background: #DBEAFE;
      color: #1E40AF;
    }
    
    .type-patent {
      background: #FCE7F3;
      color: #9F1239;
    }
    
    .type-trademark {
      background: #FEF3C7;
      color: #92400E;
    }
    
    .work-description {
      font-size: 14px;
      color: #64748B;
      line-height: 1.6;
      margin-bottom: 16px;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    
    .work-creator {
      font-size: 13px;
      color: #475569;
      margin-bottom: 12px;
      padding: 12px;
      background: #F8FAFC;
      border-radius: 8px;
      font-weight: 600;
    }
    
    .work-stats {
      display: flex;
      justify-content: space-between;
      padding-top: 16px;
      border-top: 1px solid #E2E8F0;
      font-size: 13px;
      color: #64748B;
      font-weight: 600;
    }
    
    .empty {
      text-align: center;
      padding: 80px 20px;
      color: #94A3B8;
      grid-column: 1 / -1;
    }
    
    .empty i {
      font-size: 64px;
      margin-bottom: 20px;
      color: #CBD5E1;
    }
    
    .empty p {
      font-size: 18px;
      font-weight: 600;
    }
    
    @media (max-width: 768px) {
      .header h1 {
        font-size: 36px;
      }
      
      .works-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="navbar">
    <div class="nav-container">
      <a href="../index.php" class="logo">
        <img src="../public/logos/chmsu-logo.png" alt="CHMSU" class="logo-img" onerror="this.src='../public/logos/chmsu-logo.jpg'; this.onerror=null;">
        <span>CHMSU IP Hub</span>
      </a>
      <div class="nav-right">
        <?php if (isLoggedIn()): ?>
          <a href="../dashboard.php"><i class="fas fa-gauge"></i> Dashboard</a>
          <a href="../auth/login.php?logout"><i class="fas fa-arrow-right-from-bracket"></i> Logout</a>
        <?php else: ?>
          <a href="../auth/login.php"><i class="fas fa-arrow-right-to-bracket"></i> Sign In</a>
          <a href="../auth/register.php"><i class="fas fa-user-plus"></i> Register</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <div class="container">
    <div class="header">
      <h1>IP Innovation Hub</h1>
      <p>Discover and explore registered intellectual property from the CHMSU community</p>
    </div>
    
    <form method="GET" class="search-box">
      <input type="text" name="search" class="search-input" placeholder="Search by title, description, or author..." value="<?php echo htmlspecialchars($search); ?>">
      <button type="submit" class="search-btn">
        <i class="fas fa-magnifying-glass"></i> Search
      </button>
    </form>
    
    <div class="filters">
      <a href="?type=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo $filter_type === 'all' ? 'active' : ''; ?>">
        All Types
      </a>
      <a href="?type=Copyright<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo $filter_type === 'Copyright' ? 'active' : ''; ?>">
        Copyright
      </a>
      <a href="?type=Patent<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo $filter_type === 'Patent' ? 'active' : ''; ?>">
        Patent
      </a>
      <a href="?type=Trademark<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo $filter_type === 'Trademark' ? 'active' : ''; ?>">
        Trademark
      </a>
    </div>
    
    <div class="works-grid">
      <?php if (count($works) === 0): ?>
        <div class="empty">
          <i class="fas fa-inbox"></i>
          <p>No IP works found</p>
        </div>
      <?php else: ?>
        <?php foreach ($works as $work): ?>
          <a href="view.php?id=<?php echo $work['id']; ?>" class="work-card">
            <div class="work-header">
              <div class="work-title"><?php echo htmlspecialchars($work['title']); ?></div>
              <div class="work-meta">
                <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($work['approved_at'])); ?></span>
              </div>
            </div>
            <div class="work-body">
              <span class="work-type type-<?php echo strtolower($work['ip_type']); ?>"><?php echo $work['ip_type']; ?></span>
              
              <div class="work-description"><?php echo htmlspecialchars($work['description']); ?></div>
              
              <div class="work-creator">
                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($work['full_name']); ?>
              </div>
              
              <div class="work-stats">
                <span><i class="fas fa-eye"></i> <?php echo $work['view_count']; ?> views</span>
                <span><i class="fas fa-certificate"></i> Certified</span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
