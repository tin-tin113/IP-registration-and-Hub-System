# CHMSU Intellectual Property Registration and Hub System

![CHMSU Logo](public/logos/chmsu-logo.png)

A comprehensive web-based application for managing intellectual property registrations, approvals, and showcasing at Carlos Hilado Memorial State University.

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Default Accounts](#default-accounts)
- [Project Structure](#project-structure)
- [Technologies Used](#technologies-used)
- [Usage Guide](#usage-guide)
- [Documentation](#documentation)
- [License](#license)

## 🎯 Overview

The **CHMSU IP Registration and Hub System** is a full-featured platform designed to digitize and streamline the complete lifecycle of intellectual property management. The system provides:

1. **IP Registration Portal** - Online submission and tracking of Copyright, Patent, and Trademark applications
2. **Administrative Workflow** - Multi-stage verification and approval process with payment tracking
3. **IP Hub Repository** - Public showcase of approved IP works with search and gamification features

## ✨ Features

### For Users (Faculty/Staff/Students)
- 📝 **Application Submission** - Submit Copyright, Patent, and Trademark applications
- 💾 **Draft Saving** - Save application drafts and complete them later
- 📊 **Application Tracking** - Real-time status tracking of all applications
- 💳 **Payment Upload** - Upload payment receipts and track verification
- 🏆 **Badges & Achievements** - Earn badges and innovation points for approved IP works
- 📜 **Certificate Generation** - Download digital certificates with QR verification
- 🔍 **IP Hub Browsing** - Explore and view all approved IP works

### For Clerks
- ✅ **Profile Verification** - Verify user profiles and documents
- 📋 **Application Review** - Review IP applications for completeness
- 💰 **Payment Verification** - Verify payment receipts and process payments

### For Directors
- ✔️ **Final Approval** - Approve or reject IP applications
- 💵 **Award Management** - Set award amounts and incentives
- 📈 **Analytics Dashboard** - View system-wide statistics and trends

### For Administrators
- 👥 **User Management** - Manage users and assign roles
- 📋 **Form Builder** - Customize application form fields dynamically
- 🏅 **Badge Management** - Configure badges and achievement thresholds
- 📜 **Certificate Templates** - Customize certificate appearance
- 📊 **Analytics & Reports** - Comprehensive system analytics
- 🔍 **Audit Logging** - Track all system activities and changes

## 🖥️ System Requirements

- **PHP**: 7.4 or higher
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Web Server**: Apache (XAMPP recommended)
- **Browser**: Modern browser (Chrome, Firefox, Edge, Safari)

## 📦 Installation

### Step 1: Clone or Download the Repository

```bash
git clone https://github.com/yourusername/chmsu-IP-system.git
cd chmsu-IP-system
```

Or download and extract the ZIP file to your web server directory:
- **XAMPP**: `C:/xampp/htdocs/chmsu-IP-system/`
- **WAMP**: `C:/wamp64/www/chmsu-IP-system/`
- **Linux**: `/var/www/html/chmsu-IP-system/`

### Step 2: Database Setup

1. Open phpMyAdmin (`http://localhost/phpmyadmin`)
2. Click **Import** in the top menu
3. Choose file: `database/complete_chmsu_ip_system.sql`
4. Click **Go** to import

**OR** use MySQL command line:
```bash
mysql -u root -p < database/complete_chmsu_ip_system.sql
```

This will create the database `chmsu-IP-system` with all required tables and default accounts.

### Step 3: Configure the System

1. Open `config/config.php`
2. Verify/update database settings:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', ''); // Your MySQL password
   define('DB_NAME', 'chmsu-IP-system');
   ```

3. Update the base URL if needed:
   ```php
   define('BASE_URL', 'http://localhost/chmsu-IP-system/');
   ```

### Step 4: Set Permissions

Ensure the `uploads/` folder is writable:

**Windows:**
- Right-click `uploads` folder → Properties → Security
- Add write permissions for IIS_IUSRS / IUSR

**Linux/Mac:**
```bash
chmod -R 755 uploads/
```

### Step 5: Access the System

Open your browser and navigate to:
```
http://localhost/chmsu-IP-system/
```

## 👤 Default Accounts

After installation, you can log in with these default accounts:

| Role | Email | Password | Access Level |
|------|-------|----------|--------------|
| **Administrator** | admin@chmsu.edu.ph | admin123 | Full system access |
| **Director** | director@chmsu.edu.ph | director123 | Approval workflow |
| **Clerk** | clerk@chmsu.edu.ph | clerk123 | Verification workflow |
| **User** | user@chmsu.edu.ph | user123 | Application submission |

⚠️ **Important**: Change all default passwords after first login for security!

## 📁 Project Structure

```
chmsu-IP-system/
├── admin/                      # Admin dashboard and management pages
│   ├── analytics.php          # System analytics and reports
│   ├── approve-applications.php # Director approval page
│   ├── audit-log.php          # System audit logs
│   ├── manage-badges.php      # Badge configuration
│   ├── manage-certificate-template.php
│   ├── manage-form-fields.php # Dynamic form builder
│   ├── manage-users.php       # User management
│   ├── verify-applications.php # Clerk verification
│   └── verify-payments.php    # Payment verification
├── app/                        # User application pages
│   ├── apply.php              # IP application form
│   ├── my-applications.php    # Application tracking
│   ├── upload-payment.php     # Payment upload
│   ├── view-application.php   # Application details
│   ├── view-badge.php         # Badge viewer
│   └── view-certificate.php   # Certificate viewer
├── auth/                       # Authentication pages
│   ├── login.php
│   ├── register.php
│   └── forgot-password.php
├── certificate/                # Certificate generation
│   └── generate.php
├── config/                     # Configuration files
│   ├── config.php             # Main configuration
│   ├── db.php                 # Database connection
│   ├── session.php            # Session management
│   └── form_fields_helper.php
├── database/                   # Database files
│   └── complete_chmsu_ip_system.sql
├── docs/                       # Documentation
│   └── CHMSU_IP_System_Documentation.md
├── hub/                        # Public IP Hub
│   ├── browse.php             # Browse IP works
│   └── view.php               # View IP details
├── includes/                   # Reusable components
│   ├── header.php
│   ├── footer.php
│   └── sidebar.php
├── lib/                        # Libraries
│   └── qr-generator.php
├── profile/                    # User profile pages
│   └── badges-certificates.php
├── public/                     # Public assets
│   ├── logo-styles.css
│   └── logos/
├── uploads/                    # User uploads
│   ├── copyright/
│   ├── patent/
│   ├── trademark/
│   └── receipts/
├── dashboard.php               # User dashboard
├── index.php                   # Landing page
├── logout.php
├── help.php                    # Help documentation
├── INSTALLATION_GUIDE.txt
├── DATABASE_SETUP.txt
├── CERTIFICATE_BADGE_GUIDE.md
└── README.md
```

## 🛠️ Technologies Used

| Component | Technology |
|-----------|------------|
| **Backend** | PHP 7.4+ |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ |
| **Web Server** | Apache (XAMPP) |
| **Frontend** | HTML5, CSS3, JavaScript |
| **Styling** | Custom CSS with gradient designs |
| **Icons** | Font Awesome 6.4 |
| **Font** | Inter Font Family |
| **PDF Generation** | Browser-based rendering |
| **QR Codes** | QR Code API integration |

## 📖 Usage Guide

### For Users

1. **Register an Account**
   - Go to Registration page
   - Fill in your details (use CHMSU email if applicable)
   - Complete profile verification

2. **Submit an Application**
   - Navigate to "Apply for IP"
   - Choose application type (Copyright/Patent/Trademark)
   - Fill in required fields
   - Upload supporting documents
   - Submit or save as draft

3. **Track Your Applications**
   - View "My Applications" to see status
   - Upload payment receipt when required
   - Download certificate once approved

4. **Earn Badges**
   - Badges are automatically awarded based on achievements
   - View your badges in Profile → Badges & Certificates

### For Clerks

1. **Verify Profiles**
   - Review user profiles pending verification
   - Check ID and supporting documents
   - Approve or reject with comments

2. **Verify Applications**
   - Review submitted applications
   - Check document completeness
   - Mark as verified or request corrections

3. **Verify Payments**
   - Review uploaded payment receipts
   - Match with application details
   - Approve or reject payment

### For Directors

1. **Approve Applications**
   - Review clerk-verified applications
   - Set award amounts and incentives
   - Approve for certificate generation or reject

2. **View Analytics**
   - Monitor system statistics
   - Track approval rates and trends

### For Administrators

1. **Manage Users**
   - Add/edit/delete users
   - Assign roles and permissions
   - Reset passwords if needed

2. **Customize Forms**
   - Add/remove form fields
   - Configure field types and validations
   - Set field order and visibility

3. **Configure Badges**
   - Create custom badges
   - Set achievement thresholds
   - Upload badge images

4. **Review Audit Logs**
   - Monitor system activities
   - Track user actions
   - Generate compliance reports

## 📚 Documentation

For detailed documentation, see:
- **[Full System Documentation](docs/CHMSU_IP_System_Documentation.md)** - Complete technical documentation
- **[Installation Guide](INSTALLATION_GUIDE.txt)** - Detailed installation steps
- **[Certificate & Badge Guide](CERTIFICATE_BADGE_GUIDE.md)** - Badge and certificate system guide
- **[Database Setup](DATABASE_SETUP.txt)** - Database configuration details

## 🔒 Security Notes

- Change all default passwords after installation
- Ensure `uploads/` directory is NOT directly accessible via browser
- Keep PHP and MySQL updated to latest stable versions
- Use HTTPS in production environments
- Regularly backup your database

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is developed for Carlos Hilado Memorial State University.

## 👥 Authors

Carlos Hilado Memorial State University - College of Engineering and Technology

## 📞 Support

For questions or support:
- **Email**: ipmoffice@chmsu.edu.ph
- **Phone**: (034) 495-4996
- **Location**: Research Innovation and Extension House - Intellectual Property Management Office, 1st floor
- **Hours**: Monday-Friday, 8:00 AM - 5:00 PM

---

**Carlos Hilado Memorial State University**  
*Innovation Through Intellectual Property Management*
