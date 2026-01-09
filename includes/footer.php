<?php
// Footer component for CHMSU IP System
// Usage: require_once 'includes/footer.php';

// Determine base path for assets
$base_path = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/app/') !== false || strpos($_SERVER['PHP_SELF'], '/auth/') !== false || strpos($_SERVER['PHP_SELF'], '/hub/') !== false || strpos($_SERVER['PHP_SELF'], '/profile/') !== false || strpos($_SERVER['PHP_SELF'], '/certificate/') !== false) ? '../' : '';
?>
  <!-- Footer -->
  <footer class="footer" style="background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%); color: white; padding: 40px 20px; margin-top: 60px; text-align: center;">
    <div style="max-width: 1200px; margin: 0 auto;">
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 40px; margin-bottom: 30px; text-align: left;">
        <!-- About Section -->
        <div>
          <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 15px; color: #DAA520;">About CHMSU IP Office</h3>
          <p style="font-size: 14px; line-height: 1.6; opacity: 0.9;">
            The official Intellectual Property Registration and Management System of Carlos Hilado Memorial State University. Protecting and promoting innovation within the CHMSU community.
          </p>
        </div>
        
        <!-- Quick Links -->
        <div>
          <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 15px; color: #DAA520;">Quick Links</h3>
          <ul style="list-style: none; padding: 0;">
            <li style="margin-bottom: 10px;">
              <a href="<?php echo $base_path; ?>hub/browse.php" style="color: white; text-decoration: none; font-size: 14px; opacity: 0.9; transition: opacity 0.2s;">
                <i class="fas fa-search" style="margin-right: 8px;"></i> Browse IP Hub
              </a>
            </li>
            <li style="margin-bottom: 10px;">
              <a href="<?php echo $base_path; ?>help.php" style="color: white; text-decoration: none; font-size: 14px; opacity: 0.9; transition: opacity 0.2s;">
                <i class="fas fa-question-circle" style="margin-right: 8px;"></i> Help & Guide
              </a>
            </li>
            <?php if (isLoggedIn()): ?>
              <li style="margin-bottom: 10px;">
                <a href="<?php echo $base_path; ?>dashboard.php" style="color: white; text-decoration: none; font-size: 14px; opacity: 0.9; transition: opacity 0.2s;">
                  <i class="fas fa-gauge" style="margin-right: 8px;"></i> Dashboard
                </a>
              </li>
            <?php else: ?>
              <li style="margin-bottom: 10px;">
                <a href="<?php echo $base_path; ?>auth/login.php" style="color: white; text-decoration: none; font-size: 14px; opacity: 0.9; transition: opacity 0.2s;">
                  <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i> Sign In
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </div>
        
        <!-- Contact Info -->
        <div>
          <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 15px; color: #DAA520;">Contact Information</h3>
          <ul style="list-style: none; padding: 0; font-size: 14px; line-height: 1.8;">
            <li style="margin-bottom: 10px; opacity: 0.9;">
              <i class="fas fa-map-marker-alt" style="margin-right: 8px; color: #DAA520;"></i>
              <?php echo IP_OFFICE_LOCATION; ?>
            </li>
            <li style="margin-bottom: 10px; opacity: 0.9;">
              <i class="fas fa-envelope" style="margin-right: 8px; color: #DAA520;"></i>
              <?php echo IP_OFFICE_EMAIL; ?>
            </li>
            <li style="margin-bottom: 10px; opacity: 0.9;">
              <i class="fas fa-clock" style="margin-right: 8px; color: #DAA520;"></i>
              <?php echo IP_OFFICE_HOURS; ?>
            </li>
          </ul>
        </div>
      </div>
      
      <!-- Copyright -->
      <div style="border-top: 1px solid rgba(255,255,255,0.2); padding-top: 20px; margin-top: 20px;">
        <p style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">
          &copy; <?php echo date('Y'); ?> Carlos Hilado Memorial State University. All rights reserved.
        </p>
        <p style="font-size: 12px; opacity: 0.8;">
          Intellectual Property Office • Negros Occidental, Philippines
        </p>
      </div>
    </div>
  </footer>
</body>
</html>
