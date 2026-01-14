<?php
require_once 'config/config.php';
require_once 'config/db.php';

$cert_number = $_GET['cert'] ?? null;
$certificate = null;
$error = null;

if ($cert_number) {
    $stmt = $conn->prepare("
        SELECT c.*, a.title, a.ip_type, a.approved_at, a.inventor_name, u.full_name, u.department 
        FROM certificates c
        JOIN ip_applications a ON c.application_id = a.id
        LEFT JOIN users u ON a.user_id = u.id
        WHERE c.certificate_number = ?
    ");
    $stmt->bind_param("s", $cert_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $certificate = $result->fetch_assoc();
    } else {
        $error = "Certificate not found.";
    }
    $stmt->close();
} else {
   // Optional: Redirect to home or show search form if no cert parameter
   // header("Location: index.php"); 
   // exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Certificate - <?php echo UNIVERSITY_SHORT; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?php echo defined('PRIMARY_COLOR') ? PRIMARY_COLOR : '#1B5C3B'; ?>;
            --secondary: <?php echo defined('SECONDARY_COLOR') ? SECONDARY_COLOR : '#0F3D2E'; ?>;
            --accent: <?php echo defined('ACCENT_COLOR') ? ACCENT_COLOR : '#E07D32'; ?>;
            --bg-color: #f5f7fa;
            --card-bg: #ffffff;
            --text-main: #333333;
            --text-muted: #666666;
            --success: #28a745;
            --danger: #dc3545;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 600px;
            padding: 20px;
        }

        .verification-card {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            text-align: center;
        }

        .header {
            background-color: var(--primary);
            color: white;
            padding: 30px 20px;
            position: relative;
        }

        .logo {
            width: 80px;
            height: 80px;
            background-color: white;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .logo img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 14px;
            margin-top: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .status-valid {
            color: var(--success);
        }

        .status-invalid {
            color: var(--danger);
        }

        .content {
            padding: 30px;
        }

        .cert-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 25px;
            line-height: 1.4;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
            flex: 1;
        }

        .detail-value {
            color: var(--text-main);
            font-weight: 600;
            font-size: 14px;
            flex: 1.5;
            text-align: right;
            word-break: break-word;
        }

        .footer {
            background-color: #f8f9fa;
            padding: 15px;
            font-size: 12px;
            color: var(--text-muted);
            border-top: 1px solid #eee;
        }

        .btn-home {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 25px;
            background-color: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .btn-home:hover {
            background-color: var(--secondary);
        }
        
        @media (max-width: 480px) {
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            .detail-value {
                text-align: left;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="verification-card">
        <?php if ($certificate): ?>
            <!-- VALID CERTIFICATE -->
            <div class="header">
                <div class="logo">
                     <img src="public/logos/chmsu-logo.png" alt="Logo" onerror="this.src='https://via.placeholder.com/60?text=CHMSU'">
                </div>
                <h2>Certificate Verification</h2>
                <div class="status-badge status-valid">
                    <i class="fas fa-check-circle"></i> VERIFIED VALID
                </div>
            </div>

            <div class="content">
                <div class="cert-title">
                    "<?php echo htmlspecialchars($certificate['title']); ?>"
                </div>

                <div class="detail-row">
                    <span class="detail-label">Certificate Number</span>
                    <span class="detail-value"><?php echo htmlspecialchars($certificate['certificate_number']); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Reference Number</span>
                    <span class="detail-value"><?php echo htmlspecialchars($certificate['reference_number']); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Inventor / Owner</span>
                    <span class="detail-value"><?php echo htmlspecialchars($certificate['inventor_name'] ?: $certificate['full_name']); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">IP Classification</span>
                    <span class="detail-value"><?php echo htmlspecialchars($certificate['ip_type']); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Date Issued</span>
                    <span class="detail-value"><?php echo date('F d, Y', strtotime($certificate['issued_at'])); ?></span>
                </div>
                
                 <?php if($certificate['department']): ?>
                <div class="detail-row">
                    <span class="detail-label">Department</span>
                    <span class="detail-value"><?php echo htmlspecialchars($certificate['department']); ?></span>
                </div>
                <?php endif; ?>

                <a href="index.php" class="btn-home">Back to Home</a>
            </div>

        <?php elseif ($error): ?>
            <!-- INVALID CERTIFICATE -->
            <div class="header" style="background-color: var(--danger);">
                <div class="logo">
                    <i class="fas fa-times" style="font-size: 30px; color: var(--danger);"></i>
                </div>
                <h2>Verification Failed</h2>
                <div class="status-badge status-invalid">
                    <i class="fas fa-times-circle"></i> NOT FOUND
                </div>
            </div>

            <div class="content">
                <p style="color: var(--text-muted); margin-bottom: 20px;">
                    We could not find a valid certificate with the provided number.
                </p>
                <div style="background: #fff3f3; padding: 15px; border-radius: 8px; margin-bottom: 20px; color: var(--danger); font-family: monospace;">
                    <?php echo htmlspecialchars($cert_number); ?>
                </div>
                <p style="font-size: 13px; color: var(--text-muted);">
                    Please check the certificate number and try again. If you believe this is an error, please contact the IP Office.
                </p>
                <a href="index.php" class="btn-home" style="background-color: var(--text-muted);">Back to Home</a>
            </div>

        <?php else: ?>
             <!-- NO CERTIFICATE PROVIDED -->
             <div class="header" style="background-color: var(--secondary);">
                 <div class="logo">
                    <i class="fas fa-search" style="font-size: 24px; color: var(--secondary);"></i>
                 </div>
                 <h2>Certificate Verification</h2>
             </div>
             
             <div class="content">
                <p>Please provide a certificate number to verify.</p>
                <form action="" method="GET" style="margin-top: 20px;">
                    <div style="margin-bottom: 15px;">
                        <input type="text" name="cert" placeholder="Enter Certificate Number" required
                            style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; box-sizing: border-box;">
                    </div>
                    <button type="submit" class="btn-home" style="width: 100%; border: none; cursor: pointer;">Verify</button>
                </form>
             </div>
        <?php endif; ?>

        <div class="footer">
            &copy; <?php echo date('Y'); ?> <?php echo UNIVERSITY_NAME; ?>. All Rights Reserved.
        </div>
    </div>
</div>

</body>
</html>
