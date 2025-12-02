# CHMSU Intellectual Property Registration and Hub System

A comprehensive web-based system for managing intellectual property registrations at Carlos Hilado Memorial State University.

## Features

- **IP Application Submission** - Users can submit copyright, patent, and trademark applications
- **Document Management** - Support for multiple file uploads (20MB total limit)
- **Complete Workflow** - Clerk verification → Payment → Director approval
- **Payment Integration** - Receipt upload and verification system
- **Certificate Generation** - Automatic certificate creation with QR codes
- **IP Hub** - Browse and discover approved intellectual property works
- **Analytics Dashboard** - Comprehensive statistics and reports for directors
- **Audit Trail** - Complete logging of all system activities
- **Badge System** - Recognition system for active contributors

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Frontend**: HTML5, CSS3, JavaScript
- **Icons**: Font Awesome 6.4.0
- **Fonts**: Inter, Google Fonts

## Installation

Please refer to `INSTALLATION_GUIDE.txt` for complete setup instructions.

Quick start:
1. Import `database/complete_chmsu_ip_system.sql` into MySQL
2. Copy files to web server directory (htdocs/chmsu-IP-system/)
3. Configure `config/config.php` with your database credentials
4. Access via `http://localhost/chmsu-IP-system/`

## Default Accounts

- **Clerk**: clerk@chmsu.edu.ph / password
- **Director**: director@chmsu.edu.ph / password  
- **Student**: student@chmsu.edu.ph / password

**⚠️ Change these passwords immediately in production!**

## Workflow

1. User submits IP application with supporting documents
2. Clerk reviews and validates submission
3. User makes payment at CHMSU Cashier Office
4. User uploads payment receipt
5. Clerk verifies payment
6. Director evaluates and makes final decision
7. Upon approval, certificate is generated automatically

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx with mod_rewrite enabled
- 20MB file upload limit configured
- Writable uploads directory

## File Structure

\`\`\`
chmsu-IP-system/
├── admin/                 # Admin panel pages
├── app/                   # User application pages
├── auth/                  # Authentication pages
├── config/                # Configuration files
├── database/              # Database setup scripts
├── hub/                   # IP Hub browsing
├── lib/                   # Helper libraries
├── public/                # Public assets (logos, images)
├── uploads/               # User uploaded files
├── dashboard.php          # Main dashboard
└── index.php             # Entry point
\`\`\`

## Security Features

- Password hashing with bcrypt
- SQL injection prevention with prepared statements
- Session management with timeout
- Role-based access control
- Audit logging for all actions
- File type validation for uploads

## Browser Support

- Chrome (recommended)
- Firefox
- Safari
- Edge
- Opera

## License

Copyright © 2025 Carlos Hilado Memorial State University. All rights reserved.

## Support

For technical support:
- Email: ipoffice@chmsu.edu.ph
- Location: Reaserch Innovation and Extension House - Intellectual Property Management Office 1st floor
- 
- Hours: Monday-Friday, 8:00 AM - 5:00 PM
