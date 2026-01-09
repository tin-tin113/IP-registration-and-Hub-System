<?php
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'config/session.php';

requireLogin();

$user_role = getUserRole();
$user_name = $_SESSION['full_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Help & Guide - CHMSU IP System</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="public/logo-styles.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: linear-gradient(135deg, #F8FAFC 0%, #E2E8F0 100%);
      min-height: 100vh;
    }
    
    .navbar {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
      padding: 16px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 100;
    }
    
    .logo {
      font-size: 18px;
      font-weight: 700;
      color: white;
      display: flex;
      align-items: center;
      gap: 12px;
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
    
    .btn-back {
      background: rgba(255,255,255,0.15);
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.2s;
      backdrop-filter: blur(10px);
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-back:hover {
      background: rgba(255,255,255,0.25);
      transform: translateY(-1px);
    }
    
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 40px 24px;
    }
    
    .page-header {
      background: white;
      border-radius: 20px;
      padding: 40px;
      margin-bottom: 32px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.08);
      text-align: center;
    }
    
    .page-header h1 {
      font-size: 42px;
      font-weight: 700;
      color: #0A4D2E;
      margin-bottom: 12px;
      letter-spacing: -1px;
    }
    
    .page-header p {
      font-size: 18px;
      color: #64748B;
      font-weight: 500;
    }
    
    .tabs {
      display: flex;
      gap: 12px;
      margin-bottom: 32px;
      background: white;
      padding: 8px;
      border-radius: 16px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    }
    
    .tab-btn {
      flex: 1;
      padding: 16px 24px;
      border: none;
      background: transparent;
      color: #64748B;
      font-size: 15px;
      font-weight: 600;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }
    
    .tab-btn:hover {
      background: #F8FAFC;
      color: #0A4D2E;
    }
    
    .tab-btn.active {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      box-shadow: 0 4px 16px rgba(10, 77, 46, 0.3);
    }
    
    .tab-content {
      display: none;
    }
    
    .tab-content.active {
      display: block;
      animation: fadeIn 0.4s ease;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .help-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
      gap: 24px;
      margin-bottom: 32px;
    }
    
    .help-card {
      background: white;
      border-radius: 16px;
      padding: 32px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.06);
      transition: all 0.3s;
      border: 1px solid rgba(0,0,0,0.05);
    }
    
    .help-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 32px rgba(0,0,0,0.12);
    }
    
    .help-icon {
      width: 64px;
      height: 64px;
      border-radius: 16px;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      color: #DAA520;
      margin-bottom: 20px;
      box-shadow: 0 8px 20px rgba(10, 77, 46, 0.2);
    }
    
    .help-card h3 {
      font-size: 20px;
      font-weight: 700;
      color: #1E293B;
      margin-bottom: 12px;
    }
    
    .help-card p {
      font-size: 15px;
      color: #64748B;
      line-height: 1.6;
      margin-bottom: 20px;
    }
    
    .help-card ul {
      list-style: none;
      padding: 0;
    }
    
    .help-card li {
      padding: 12px 0;
      color: #475569;
      font-size: 14px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      border-bottom: 1px solid #F1F5F9;
    }
    
    .help-card li:last-child {
      border-bottom: none;
    }
    
    .help-card li i {
      color: #0A4D2E;
      font-size: 16px;
      margin-top: 2px;
    }
    
    .process-flow {
      background: white;
      border-radius: 16px;
      padding: 40px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.06);
      margin-bottom: 32px;
    }
    
    .process-flow h2 {
      font-size: 28px;
      font-weight: 700;
      color: #0A4D2E;
      margin-bottom: 32px;
      text-align: center;
    }
    
    .flow-steps {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }
    
    .flow-step {
      display: flex;
      gap: 24px;
      align-items: flex-start;
    }
    
    .step-number {
      min-width: 50px;
      height: 50px;
      border-radius: 50%;
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      font-weight: 700;
      box-shadow: 0 4px 16px rgba(10, 77, 46, 0.3);
    }
    
    .step-content {
      flex: 1;
      background: #F8FAFC;
      padding: 24px;
      border-radius: 12px;
    }
    
    .step-content h4 {
      font-size: 18px;
      font-weight: 700;
      color: #1E293B;
      margin-bottom: 8px;
    }
    
    .step-content p {
      font-size: 14px;
      color: #64748B;
      line-height: 1.6;
    }
    
    .faq-section {
      background: white;
      border-radius: 16px;
      padding: 40px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    }
    
    .faq-section h2 {
      font-size: 28px;
      font-weight: 700;
      color: #0A4D2E;
      margin-bottom: 32px;
      text-align: center;
    }
    
    .faq-item {
      margin-bottom: 20px;
      border: 1px solid #E2E8F0;
      border-radius: 12px;
      overflow: hidden;
    }
    
    .faq-question {
      background: #F8FAFC;
      padding: 20px 24px;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: all 0.3s;
    }
    
    .faq-question:hover {
      background: #F1F5F9;
    }
    
    .faq-question h4 {
      font-size: 16px;
      font-weight: 600;
      color: #1E293B;
    }
    
    .faq-question i {
      color: #0A4D2E;
      transition: transform 0.3s;
    }
    
    .faq-item.active .faq-question i {
      transform: rotate(180deg);
    }
    
    .faq-answer {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease;
    }
    
    .faq-item.active .faq-answer {
      max-height: 500px;
    }
    
    .faq-answer p {
      padding: 20px 24px;
      font-size: 14px;
      color: #64748B;
      line-height: 1.7;
    }
    
    .contact-box {
      background: linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%);
      border-radius: 16px;
      padding: 40px;
      text-align: center;
      color: white;
      margin-top: 32px;
    }
    
    .contact-box h3 {
      font-size: 24px;
      margin-bottom: 12px;
    }
    
    .contact-box p {
      font-size: 16px;
      opacity: 0.9;
      margin-bottom: 24px;
    }
    
    .contact-btn {
      display: inline-block;
      background: white;
      color: #0A4D2E;
      padding: 14px 32px;
      border-radius: 10px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s;
      box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    }
    
    .contact-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 24px rgba(0,0,0,0.3);
    }
    
    @media (max-width: 768px) {
      .help-grid {
        grid-template-columns: 1fr;
      }
      
      .page-header h1 {
        font-size: 32px;
      }
      
      .tabs {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
  <div class="navbar">
    <div class="logo">
      <img src="public/logos/chmsu-logo.png" alt="CHMSU" class="logo-img" onerror="this.src='public/logos/chmsu-logo.jpg'; this.onerror=null;">
      <span>Help & Guide</span>
    </div>
    <a href="dashboard.php" class="btn-back">
      <i class="fas fa-arrow-left"></i>
      Back to Dashboard
    </a>
  </div>
  
  <div class="container">
    <div class="page-header">
      <h1>How Can We Help You?</h1>
      <p>Everything you need to know about the CHMSU IP System</p>
    </div>
    
    <div class="tabs">
      <button class="tab-btn active" onclick="showTab('getting-started')">
        <i class="fas fa-rocket"></i>
        Getting Started
      </button>
      <button class="tab-btn" onclick="showTab('application-process')">
        <i class="fas fa-clipboard-list"></i>
        Application Process
      </button>
      <button class="tab-btn" onclick="showTab('payment')">
        <i class="fas fa-credit-card"></i>
        Payment Guide
      </button>
      <button class="tab-btn" onclick="showTab('faq')">
        <i class="fas fa-circle-question"></i>
        FAQ
      </button>
    </div>
    
    <!-- Getting Started Tab -->
    <div id="getting-started" class="tab-content active">
      <div class="help-grid">
        <div class="help-card">
          <div class="help-icon">
            <i class="fas fa-user-plus"></i>
          </div>
          <h3>Create Your Account</h3>
          <p>Start your IP registration journey by creating an account in the CHMSU IP System.</p>
          <ul>
            <li><i class="fas fa-check-circle"></i> <span>Register with your CHMSU email</span></li>
            <li><i class="fas fa-check-circle"></i> <span>Verify your email address</span></li>
            <li><i class="fas fa-check-circle"></i> <span>Complete your profile information</span></li>
            <li><i class="fas fa-check-circle"></i> <span>Start submitting IP applications</span></li>
          </ul>
        </div>
        
        <div class="help-card">
          <div class="help-icon">
            <i class="fas fa-lightbulb"></i>
          </div>
          <h3>What is Intellectual Property?</h3>
          <p>Intellectual property refers to creations of the mind protected by law.</p>
          <ul>
            <li><i class="fas fa-copyright"></i> <span><strong>Copyright:</strong> Original creative works</span></li>
            <li><i class="fas fa-flask"></i> <span><strong>Patent:</strong> Inventions and innovations</span></li>
            <li><i class="fas fa-trademark"></i> <span><strong>Trademark:</strong> Brand identifiers</span></li>
          </ul>
        </div>
        
        <div class="help-card">
          <div class="help-icon">
            <i class="fas fa-folder-open"></i>
          </div>
          <h3>Prepare Your Documents</h3>
          <p>Make sure you have all necessary documents ready before submitting.</p>
          <ul>
            <li><i class="fas fa-file-pdf"></i> <span>Supporting documentation (PDF, DOC, JPG, PNG)</span></li>
            <li><i class="fas fa-database"></i> <span>Maximum 50MB total file size</span></li>
            <li><i class="fas fa-shield-alt"></i> <span>Proof of ownership or authorship</span></li>
            <li><i class="fas fa-list-check"></i> <span>Detailed description of your work</span></li>
          </ul>
        </div>
        
        <div class="help-card">
          <div class="help-icon">
            <i class="fas fa-chart-line"></i>
          </div>
          <h3>Track Your Applications</h3>
          <p>Monitor your application status in real-time through your dashboard.</p>
          <ul>
            <li><i class="fas fa-hourglass-start"></i> <span>View current application status</span></li>
            <li><i class="fas fa-bell"></i> <span>Receive notifications for updates</span></li>
            <li><i class="fas fa-download"></i> <span>Download certificates when approved</span></li>
            <li><i class="fas fa-history"></i> <span>Review application history</span></li>
          </ul>
        </div>
      </div>
    </div>
    
    <!-- Application Process Tab -->
    <div id="application-process" class="tab-content">
      <div class="process-flow">
        <h2>Complete Application Workflow</h2>
        <div class="flow-steps">
          <div class="flow-step">
            <div class="step-number">1</div>
            <div class="step-content">
              <h4>Submit Your Application</h4>
              <p>Fill out the application form with your IP details, including title, type, description, and upload supporting documents (up to 50MB). Once submitted, your application enters the review queue.</p>
            </div>
          </div>
          
          <div class="flow-step">
            <div class="step-number">2</div>
            <div class="step-content">
              <h4>Clerk Reviews Application</h4>
              <p>A CHMSU clerk reviews your application for completeness and validity. They check if all required information is provided and documents are properly uploaded. Status: "Submitted"</p>
            </div>
          </div>
          
          <div class="flow-step">
            <div class="step-number">3</div>
            <div class="step-content">
              <h4>Approval for Payment</h4>
              <p>If the clerk approves your application, you'll receive a notification to proceed with payment. Visit the CHMSU cashier to make the required payment. Status: "Payment Pending"</p>
            </div>
          </div>
          
          <div class="flow-step">
            <div class="step-number">4</div>
            <div class="step-content">
              <h4>Upload Payment Receipt</h4>
              <p>After making payment at the cashier, upload your official payment receipt through the system. Make sure the receipt is clear and shows the payment amount and date.</p>
            </div>
          </div>
          
          <div class="flow-step">
            <div class="step-number">5</div>
            <div class="step-content">
              <h4>Clerk Verifies Payment</h4>
              <p>The clerk reviews your payment receipt to confirm the transaction. They verify the payment amount and authenticity. Status: "Payment Verified"</p>
            </div>
          </div>
          
          <div class="flow-step">
            <div class="step-number">6</div>
            <div class="step-content">
              <h4>Director Evaluates & Approves</h4>
              <p>The IP Director conducts a final evaluation of your intellectual property work. They review the merit, originality, and compliance with CHMSU standards before making the final decision.</p>
            </div>
          </div>
          
          <div class="flow-step">
            <div class="step-number">7</div>
            <div class="step-content">
              <h4>Certificate Issued</h4>
              <p>Once approved, your official IP certificate is automatically generated with a unique certificate number, QR code for verification, and the CHMSU seal. Download it from your dashboard. Status: "Approved"</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Payment Tab -->
    <div id="payment" class="tab-content">
      <div class="help-grid">
        <div class="help-card">
          <div class="help-icon">
            <i class="fas fa-money-bill-wave"></i>
          </div>
          <h3>Making Payment</h3>
          <p>After clerk approval, follow these steps to complete your payment:</p>
          <ul>
            <li><i class="fas fa-check"></i> <span>Wait for "Payment Pending" notification</span></li>
            <li><i class="fas fa-building-columns"></i> <span>Visit CHMSU cashier office</span></li>
            <li><i class="fas fa-receipt"></i> <span>Make payment and get official receipt</span></li>
            <li><i class="fas fa-camera"></i> <span>Take clear photo or scan of receipt</span></li>
          </ul>
        </div>
        
        <div class="help-card">
          <div class="help-icon">
            <i class="fas fa-cloud-arrow-up"></i>
          </div>
          <h3>Uploading Receipt</h3>
          <p>Upload your payment receipt through the system for verification:</p>
          <ul>
            <li><i class="fas fa-image"></i> <span>Supported formats: JPG, PNG, PDF</span></li>
            <li><i class="fas fa-expand"></i> <span>Maximum file size: 50MB</span></li>
            <li><i class="fas fa-eye"></i> <span>Ensure receipt is clear and readable</span></li>
            <li><i class="fas fa-info-circle"></i> <span>Include payment date and amount</span></li>
          </ul>
        </div>
        
        <div class="help-card">
          <div class="help-icon">
            <i class="fas fa-clipboard-check"></i>
          </div>
          <h3>Payment Verification</h3>
          <p>What happens after you upload your receipt:</p>
          <ul>
            <li><i class="fas fa-hourglass-half"></i> <span>Clerk reviews receipt within 1-2 days</span></li>
            <li><i class="fas fa-check-double"></i> <span>Verification of payment authenticity</span></li>
            <li><i class="fas fa-forward"></i> <span>Forwarded to director for approval</span></li>
            <li><i class="fas fa-bell"></i> <span>Email notification on verification</span></li>
          </ul>
        </div>
        
        <div class="help-card">
          <div class="help-icon">
            <i class="fas fa-triangle-exclamation"></i>
          </div>
          <h3>Payment Issues</h3>
          <p>If your payment receipt is rejected or has issues:</p>
          <ul>
            <li><i class="fas fa-redo"></i> <span>Resubmit with corrected information</span></li>
            <li><i class="fas fa-envelope"></i> <span>Contact clerk for specific feedback</span></li>
            <li><i class="fas fa-file-circle-xmark"></i> <span>Ensure receipt shows official stamps</span></li>
            <li><i class="fas fa-headset"></i> <span>Get help from support if needed</span></li>
          </ul>
        </div>
      </div>
    </div>
    
    <!-- FAQ Tab -->
    <div id="faq" class="tab-content">
      <div class="faq-section">
        <h2>Frequently Asked Questions</h2>
        
        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <h4>How long does the approval process take?</h4>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="faq-answer">
            <p>The typical approval timeline is 3-7 business days from submission to final approval, assuming all documents are complete and payment is processed promptly. Clerk review takes 1-2 days, payment verification 1-2 days, and director evaluation 1-3 days.</p>
          </div>
        </div>
        
        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <h4>Can I edit my application after submission?</h4>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="faq-answer">
            <p>Applications can only be edited while in "Draft" status. Once submitted, you cannot edit the application directly. If changes are needed after submission, contact the clerk through the system, and they may allow revisions before proceeding to payment.</p>
          </div>
        </div>
        
        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <h4>What file formats are accepted for uploads?</h4>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="faq-answer">
            <p>The system accepts PDF, DOC, DOCX, JPG, JPEG, and PNG files. You can upload multiple files as long as the total size doesn't exceed 50MB. Make sure your documents are clear and legible for review.</p>
          </div>
        </div>
        
        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <h4>What if my application is rejected?</h4>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="faq-answer">
            <p>If your application is rejected, you'll receive feedback explaining the reasons. You can address the issues and submit a new application. Common rejection reasons include incomplete documentation, unclear descriptions, or IP that doesn't meet CHMSU criteria.</p>
          </div>
        </div>
        
        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <h4>Is my certificate legally binding?</h4>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="faq-answer">
            <p>The CHMSU IP certificate is an official university record of your intellectual property registration. While it provides institutional recognition, for full legal IP protection under Philippine law, you should also register with the Intellectual Property Office of the Philippines (IPOPHL).</p>
          </div>
        </div>
        
        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <h4>Can I submit multiple IP applications?</h4>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="faq-answer">
            <p>Yes! There's no limit to the number of IP applications you can submit. Each piece of intellectual property should be submitted as a separate application. This allows each work to be evaluated independently and receive its own certificate.</p>
          </div>
        </div>
        
        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <h4>Who can access my submitted IP documents?</h4>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="faq-answer">
            <p>Only authorized personnel (clerks and directors) can access your submitted documents during the review process. Once approved, basic information (title, type, description) may be published in the IP Hub, but supporting documents remain confidential unless you choose to share them.</p>
          </div>
        </div>
        
        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <h4>How do I earn badges and innovation points?</h4>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="faq-answer">
            <p>Badges are earned automatically when your approved IP works gain views in the IP Hub. Each application can earn different badges based on its IP type:</p>
            <ul style="margin: 10px 0 10px 20px; line-height: 1.8;">
              <li><strong>Copyright:</strong> Can earn Bronze (10+ views), Silver (50+), and Gold (100+) badges</li>
              <li><strong>Patent:</strong> Can earn Bronze, Silver, Gold, and Platinum (250+ views) badges</li>
              <li><strong>Trademark:</strong> Can earn all badges including Diamond (500+ views)</li>
            </ul>
            <p>Innovation points match badge thresholds automatically. When you earn all available badges, you'll receive an Achievement Certificate recognizing your excellence in IP contributions!</p>
          </div>
        </div>
      </div>
      
      <div class="contact-box">
        <h3>Still Have Questions?</h3>
        <p>Our support team is here to help you with any questions or concerns</p>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
          <div style="text-align: center; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 8px;">
            <i class="fas fa-envelope" style="font-size: 24px; margin-bottom: 8px; color: #DAA520;"></i>
            <div style="font-weight: 600; margin-bottom: 5px;">Email</div>
            <a href="mailto:<?php echo IP_OFFICE_EMAIL; ?>" style="color: white; text-decoration: none; font-size: 13px;">
              <?php echo IP_OFFICE_EMAIL; ?>
            </a>
          </div>
          <div style="text-align: center; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 8px;">
            <i class="fas fa-phone" style="font-size: 24px; margin-bottom: 8px; color: #DAA520;"></i>
            <div style="font-weight: 600; margin-bottom: 5px;">Phone</div>
            <a href="tel:<?php echo IP_OFFICE_PHONE; ?>" style="color: white; text-decoration: none; font-size: 13px;">
              <?php echo IP_OFFICE_PHONE; ?>
            </a>
          </div>
          <div style="text-align: center; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 8px;">
            <i class="fab fa-facebook" style="font-size: 24px; margin-bottom: 8px; color: #DAA520;"></i>
            <div style="font-weight: 600; margin-bottom: 5px;">Facebook</div>
            <a href="<?php echo IP_OFFICE_FACEBOOK; ?>" target="_blank" style="color: white; text-decoration: none; font-size: 13px;">
              Visit Our Page
            </a>
          </div>
        </div>
        <a href="mailto:<?php echo IP_OFFICE_EMAIL; ?>" class="contact-btn" style="margin-top: 20px;">
          <i class="fas fa-envelope"></i> Contact Support
        </a>
      </div>
    </div>
  </div>
  
  <script>
    function showTab(tabId) {
      // Hide all tabs
      document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
      });
      
      // Remove active from all buttons
      document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
      });
      
      // Show selected tab
      document.getElementById(tabId).classList.add('active');
      
      // Add active to clicked button
      event.target.closest('.tab-btn').classList.add('active');
    }
    
    function toggleFaq(element) {
      const faqItem = element.closest('.faq-item');
      faqItem.classList.toggle('active');
    }
  </script>
</body>
</html>
