# CHMSU Intellectual Property Registration and Hub System
## Technical Reference Document for Final Defense

**Document Version:** 1.0  
**Date:** January 12, 2026  
**Prepared For:** Capstone Final Defense Presentation  
**Institution:** Carlos Hilado Memorial State University

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Overview](#2-system-overview)
3. [Technology Stack](#3-technology-stack)
4. [System Architecture](#4-system-architecture)
5. [Database Schema](#5-database-schema)
6. [User Roles and Access Control](#6-user-roles-and-access-control)
7. [Application Workflow](#7-application-workflow)
8. [File Structure and Component Interaction](#8-file-structure-and-component-interaction)
9. [Key Features and Functionalities](#9-key-features-and-functionalities)
10. [Security Implementation](#10-security-implementation)
11. [Deployment Requirements](#11-deployment-requirements)

---

## 1. Executive Summary

The **CHMSU Intellectual Property Registration and Hub System** is a comprehensive web-based application designed to digitize and streamline the complete lifecycle of intellectual property management at Carlos Hilado Memorial State University. The system transforms the traditionally paper-based IP registration process into an efficient, trackable, and transparent digital workflow.

### Problem Statement
The university's existing manual IP registration process suffers from:
- Time-consuming paper-based submissions
- Lack of real-time status tracking for applicants
- Difficulty in maintaining audit trails
- No centralized repository for approved IP works

### Solution
This system provides:
- Online submission and tracking of Copyright, Patent, and Trademark applications
- Multi-stage verification and approval workflow
- Automated certificate generation with QR verification
- Public IP Hub for showcasing approved works
- Gamification features to encourage innovation

---

## 2. System Overview

### 2.1 System Purpose

| Aspect | Description |
|--------|-------------|
| **Primary Function** | Digital management of IP registration, verification, approval, and publication |
| **Target Users** | University faculty, staff, students, IP Office clerks, and directors |
| **Deployment Model** | Web-based application accessible via local network or internet |
| **Data Management** | MySQL relational database with structured data storage |

### 2.2 System Modules

| Module | Description | Primary Users |
|--------|-------------|---------------|
| **IP Registration Portal** | Online submission of Copyright, Patent, and Trademark applications | Users (Applicants) |
| **Verification Dashboard** | Document and payment verification interface | Clerks, Directors |
| **Approval System** | Final approval workflow with certificate generation | Directors |
| **IP Hub Repository** | Public showcase of approved IP works | Public Viewers |
| **Analytics Dashboard** | System-wide statistics and reporting | Directors |
| **Administration Panel** | User management, form builder, badge configuration | Directors |

---

## 3. Technology Stack

### 3.1 Core Technologies

| Component | Technology | Version | Purpose |
|-----------|------------|---------|---------|
| **Backend Language** | PHP | 7.4+ | Server-side logic and database interaction |
| **Database** | MySQL / MariaDB | 5.7+ / 10.3+ | Relational data storage |
| **Web Server** | Apache | 2.4+ | HTTP request handling |
| **Frontend** | HTML5, CSS3, JavaScript | ES6+ | User interface and interactivity |

### 3.2 Frontend Libraries and Resources

| Library | Version | Purpose |
|---------|---------|---------|
| **Font Awesome** | 6.4.0 | Icon library for UI elements |
| **Inter Font** | Google Fonts | Modern typography |
| **Chart.js** | 4.x (Local) | Data visualization charts |
| **Custom CSS** | - | Modern gradients, glassmorphism, animations |

### 3.3 Development Environment

| Tool | Purpose |
|------|---------|
| **XAMPP** | Local development server (Apache + MySQL + PHP) |
| **phpMyAdmin** | Database administration interface |
| **Git** | Version control system |

---

## 4. System Architecture

### 4.1 Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         CLIENT LAYER                                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐              │
│  │   Browser    │  │   Browser    │  │   Browser    │              │
│  │   (User)     │  │   (Clerk)    │  │  (Director)  │              │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘              │
└─────────┼─────────────────┼─────────────────┼───────────────────────┘
          │                 │                 │
          └─────────────────┼─────────────────┘
                            │ HTTP/HTTPS
┌───────────────────────────┴─────────────────────────────────────────┐
│                      PRESENTATION LAYER                             │
│  ┌────────────────────────────────────────────────────────────────┐ │
│  │                     Apache Web Server                          │ │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐            │ │
│  │  │  index.php  │  │ dashboard   │  │   admin/    │            │ │
│  │  │  (Landing)  │  │   (User)    │  │  (Admin)    │            │ │
│  │  └─────────────┘  └─────────────┘  └─────────────┘            │ │
│  └────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────┘
                            │
┌───────────────────────────┴─────────────────────────────────────────┐
│                      APPLICATION LAYER                              │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │                    PHP Application Logic                      │   │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────────────────┐  │   │
│  │  │  config/   │  │  includes/ │  │  Business Logic Files  │  │   │
│  │  │ (Settings) │  │  (Sidebar) │  │  (app/, admin/, auth/) │  │   │
│  │  └────────────┘  └────────────┘  └────────────────────────┘  │   │
│  └──────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
                            │
┌───────────────────────────┴─────────────────────────────────────────┐
│                        DATA LAYER                                   │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │                    MySQL Database                             │   │
│  │  ┌─────────┐ ┌─────────────────┐ ┌────────────────────────┐  │   │
│  │  │  users  │ │  ip_applications│ │  certificates, badges  │  │   │
│  │  └─────────┘ └─────────────────┘ └────────────────────────┘  │   │
│  └──────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

### 4.2 Request Flow

1. **User Request** → Browser sends HTTP request to Apache server
2. **Routing** → Apache serves the requested PHP file
3. **Authentication** → `session.php` verifies user login and role
4. **Processing** → PHP file executes business logic
5. **Database** → `db.php` provides MySQL connection for data operations
6. **Response** → HTML/JSON response sent back to browser

---

## 5. Database Schema

### 5.1 Entity Relationship Overview

The system uses **10 interconnected tables** to manage all data:

```
┌─────────────────┐       ┌─────────────────────┐       ┌──────────────┐
│     users       │───────│   user_profiles     │       │ form_fields  │
│  (Authentication│       │  (Verification Info)│       │  (Form Config)│
└────────┬────────┘       └─────────────────────┘       └──────────────┘
         │
         │ 1:N
         ▼
┌─────────────────────┐       ┌─────────────────┐       ┌──────────────┐
│   ip_applications   │───────│   certificates  │       │  audit_log   │
│   (Main Records)    │       │   (Generated)   │       │  (Tracking)  │
└─────────┬───────────┘       └─────────────────┘       └──────────────┘
          │
          │ 1:N
          ▼
┌─────────────────┐       ┌─────────────────────┐
│     badges      │       │   view_tracking     │
│  (Achievements) │       │   (Hub Analytics)   │
└─────────────────┘       └─────────────────────┘
                                    │
                                    ▼
                          ┌─────────────────────┐
                          │  badge_thresholds   │
                          │  (Badge Rules)      │
                          └─────────────────────┘
```

### 5.2 Table Descriptions

| Table Name | Records | Purpose | Key Relationships |
|------------|---------|---------|-------------------|
| `users` | User accounts | Stores authentication credentials and role information | Parent of user_profiles, ip_applications |
| `user_profiles` | Verification data | Extended profile information for applicants | Belongs to users |
| `ip_applications` | IP submissions | Core application data (title, type, status, files) | Belongs to users, has certificates |
| `certificates` | Generated certs | Certificate metadata with unique numbers | Belongs to ip_applications |
| `badges` | User achievements | Earned badges based on view thresholds | Belongs to users |
| `badge_thresholds` | Badge rules | Configuration for badge levels (Bronze→Diamond) | Referenced by badge awarding logic |
| `view_tracking` | Page views | Tracks views on IP Hub for analytics | Belongs to ip_applications |
| `form_fields` | Form configuration | Dynamic form field definitions | Used by form builder |
| `audit_log` | Activity history | Security audit trail of system actions | References users |
| `certificate_template_settings` | Template config | Customizable certificate appearance | Used by certificate generator |

### 5.3 Key Table: `ip_applications`

This is the central table of the system:

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Unique application identifier |
| `user_id` | INT (FK) | Reference to applicant's user account |
| `title` | VARCHAR(255) | Title of the IP work |
| `inventor_name` | VARCHAR(500) | Name(s) to appear on certificate |
| `ip_type` | ENUM | Copyright, Patent, or Trademark |
| `abstract` | LONGTEXT | Detailed description of the work |
| `status` | ENUM | Current workflow status |
| `document_file` | TEXT | JSON array of uploaded document paths |
| `payment_receipt` | VARCHAR(255) | Path to payment receipt image |
| `certificate_id` | VARCHAR(50) | Unique certificate identifier |
| `approved_at` | DATETIME | Timestamp of final approval |

### 5.4 Application Status Flow

| Status | Description | Next Status |
|--------|-------------|-------------|
| `draft` | Application saved but not submitted | `submitted` |
| `submitted` | Awaiting clerk verification | `office_visit` or `rejected` |
| `office_visit` | Clerk has verified, waiting for payment | `payment_pending` |
| `payment_pending` | User uploaded payment receipt | `payment_verified` or (re-upload) |
| `payment_verified` | Clerk verified payment, awaiting director | `approved` or `rejected` |
| `approved` | Director approved, certificate generated | Final state |
| `rejected` | Application was rejected | Final state |

---

## 6. User Roles and Access Control

### 6.1 Role Definitions

| Role | Code | Description | Population |
|------|------|-------------|------------|
| **User** | `user` | Faculty, staff, students who submit applications | Majority of users |
| **Clerk** | `clerk` | IP Office staff who verify documents and payments | Limited (1-2) |
| **Director** | `director` | IP Office head with final approval authority | Limited (1) |

### 6.2 Access Control Matrix

| Feature | User | Clerk | Director |
|---------|:----:|:-----:|:--------:|
| Submit IP Application | ✅ | ❌ | ❌ |
| View Own Applications | ✅ | ✅ | ✅ |
| Upload Payment Receipt | ✅ | ❌ | ❌ |
| View All Applications | ❌ | ✅ | ✅ |
| Verify Documents | ❌ | ✅ | ✅ |
| Verify Payments | ❌ | ✅ | ✅ |
| Approve Applications | ❌ | ❌ | ✅ |
| View Analytics | ❌ | ❌ | ✅ |
| Manage Users | ❌ | ❌ | ✅ |
| Form Builder | ❌ | ❌ | ✅ |
| Audit Log Access | ❌ | ❌ | ✅ |
| Browse IP Hub | ✅ | ✅ | ✅ |

### 6.3 Session Management

Authentication is handled through PHP sessions:

```php
// Session functions in config/session.php
requireLogin()       // Ensures user is logged in
requireRole($role)   // Ensures user has specific role(s)
getCurrentUserId()   // Returns logged-in user's ID
getUserRole()        // Returns user's role string
```

---

## 7. Application Workflow

### 7.1 Complete Application Lifecycle

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        IP APPLICATION WORKFLOW                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   USER                    CLERK                      DIRECTOR               │
│   ─────                   ─────                      ────────               │
│                                                                             │
│   ┌─────────────┐                                                           │
│   │  Register   │                                                           │
│   │  & Login    │                                                           │
│   └──────┬──────┘                                                           │
│          │                                                                  │
│          ▼                                                                  │
│   ┌─────────────┐                                                           │
│   │ Complete    │                                                           │
│   │ Profile     │                                                           │
│   └──────┬──────┘                                                           │
│          │                                                                  │
│          ▼                                                                  │
│   ┌─────────────┐                                                           │
│   │ Submit IP   │                                                           │
│   │ Application │───────────────────┐                                       │
│   └─────────────┘                   │                                       │
│                                     ▼                                       │
│                              ┌─────────────┐                                │
│                              │   Review    │                                │
│                              │  Documents  │                                │
│                              └──────┬──────┘                                │
│                                     │                                       │
│                              ┌──────┴──────┐                                │
│                              │  Approved?  │                                │
│                              └──────┬──────┘                                │
│                                Yes  │  No → Reject                          │
│                                     ▼                                       │
│   ┌─────────────┐            ┌─────────────┐                                │
│   │  Upload     │            │ Notify User │                                │
│   │  Payment    │◄───────────│ (Office     │                                │
│   │  Receipt    │            │  Visit)     │                                │
│   └──────┬──────┘            └─────────────┘                                │
│          │                                                                  │
│          ▼                                                                  │
│                              ┌─────────────┐                                │
│                              │   Verify    │                                │
│                              │   Payment   │                                │
│                              └──────┬──────┘                                │
│                                     │                                       │
│                                     │         ┌─────────────────────┐       │
│                                     └────────►│   Final Approval    │       │
│                                               │   (Review & Sign)   │       │
│                                               └──────────┬──────────┘       │
│                                                          │                  │
│                                               ┌──────────┴──────────┐       │
│                                               │ Generate Certificate│       │
│                                               └──────────┬──────────┘       │
│                                                          │                  │
│   ┌─────────────┐                                        │                  │
│   │  Download   │◄───────────────────────────────────────┘                  │
│   │ Certificate │                                                           │
│   └─────────────┘                                                           │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 7.2 Workflow Stages Detailed

| Stage | Actor | Actions | System Response |
|-------|-------|---------|-----------------|
| **Registration** | User | Creates account, verifies email | Account created, can login |
| **Profile Setup** | User | Fills personal/employment info | Profile marked as complete |
| **Application** | User | Submits IP details and documents | Status = `submitted` |
| **Document Review** | Clerk | Reviews uploaded files | Status = `office_visit` |
| **Payment** | User | Uploads payment receipt | Status = `payment_pending` |
| **Payment Verify** | Clerk | Verifies receipt matches amount | Status = `payment_verified` |
| **Approval** | Director | Reviews and approves/rejects | Status = `approved` |
| **Certificate** | System | Auto-generates certificate | Certificate available |
| **Publication** | User | Grants permission to publish | Visible in IP Hub |

---

## 8. File Structure and Component Interaction

### 8.1 Directory Structure

```
chmsu-IP-system/
├── admin/                      # Administrative dashboard and functions
│   ├── analytics.php          # Charts and statistics dashboard
│   ├── analytics-data.php     # API for fetching chart data
│   ├── approve-applications.php # Director approval interface
│   ├── audit-log.php          # Activity tracking viewer
│   ├── dashboard.php          # Admin home page
│   ├── manage-badges.php      # Badge threshold configuration
│   ├── manage-certificate-template.php # Certificate customization
│   ├── manage-form-fields.php # Dynamic form builder
│   ├── manage-users.php       # User management interface
│   ├── verify-applications.php # Document verification
│   └── verify-payments.php    # Payment receipt verification
│
├── app/                        # User application functions
│   ├── apply.php              # IP submission form
│   ├── my-applications.php    # Application list and tracking
│   ├── permission-request.php # Publish permission form
│   ├── upload-payment.php     # Payment receipt upload
│   ├── view-application.php   # Application detail view
│   ├── view-badge.php         # Badge display page
│   └── view-certificate.php   # Certificate viewer
│
├── auth/                       # Authentication system
│   ├── forgot-password.php    # Password recovery
│   ├── login.php              # User login
│   └── register.php           # New user registration
│
├── certificate/                # Certificate generation
│   └── generate.php           # Creates certificate image/PDF
│
├── config/                     # System configuration
│   ├── badge-auto-award.php   # Automatic badge awarding logic
│   ├── config.php             # Global settings (DB, URL)
│   ├── db.php                 # Database connection handler
│   ├── form_fields_helper.php # Form field utility functions
│   └── session.php            # Session and auth functions
│
├── database/                   # Database files
│   └── complete_chmsu_ip_system.sql # Complete DB schema
│
├── hub/                        # Public IP Hub
│   ├── browse.php             # Browse approved IPs
│   └── view.php               # View single IP details
│
├── includes/                   # Reusable components
│   ├── footer.php             # Page footer
│   ├── header.php             # Page header/navbar
│   └── sidebar.php            # Navigation sidebar
│
├── uploads/                    # User uploaded files
│   ├── copyright/             # Copyright documents
│   ├── patent/                # Patent documents
│   ├── trademark/             # Trademark documents
│   └── receipts/              # Payment receipts
│
├── dashboard.php               # User dashboard
├── index.php                   # Public landing page
├── help.php                    # Help/FAQ page
└── logout.php                  # Session termination
```

### 8.2 Component Interaction Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         COMPONENT INTERACTION                               │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                         CONFIGURATION LAYER                           │  │
│  │  ┌───────────┐    ┌───────────┐    ┌───────────┐    ┌─────────────┐  │  │
│  │  │ config.php│───►│  db.php   │    │session.php│    │form_fields  │  │  │
│  │  │(Settings) │    │(Database) │    │  (Auth)   │    │_helper.php  │  │  │
│  │  └───────────┘    └─────┬─────┘    └─────┬─────┘    └──────┬──────┘  │  │
│  └────────────────────────│──────────────────│─────────────────│────────┘  │
│                           │                  │                 │            │
│                           └──────────┬───────┴─────────────────┘            │
│                                      │                                      │
│                                      ▼                                      │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                         PRESENTATION LAYER                            │  │
│  │  ┌───────────┐    ┌───────────┐    ┌───────────┐                     │  │
│  │  │ sidebar   │    │  header   │    │  footer   │                     │  │
│  │  │   .php    │    │   .php    │    │   .php    │                     │  │
│  │  └─────┬─────┘    └─────┬─────┘    └─────┬─────┘                     │  │
│  └────────│────────────────│────────────────│───────────────────────────┘  │
│           └────────────────┼────────────────┘                               │
│                            ▼                                                │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                         PAGE LAYER (Views)                            │  │
│  │                                                                       │  │
│  │  ┌─────────────────────────┐    ┌─────────────────────────┐          │  │
│  │  │      USER PAGES         │    │      ADMIN PAGES        │          │  │
│  │  │  • dashboard.php        │    │  • admin/dashboard.php  │          │  │
│  │  │  • app/apply.php        │    │  • admin/verify-*.php   │          │  │
│  │  │  • app/my-applications  │    │  • admin/approve-*      │          │  │
│  │  │  • hub/browse.php       │    │  • admin/analytics.php  │          │  │
│  │  └─────────────────────────┘    └─────────────────────────┘          │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 8.3 Critical Files and Their Importance

| File | Importance | Description | Dependencies |
|------|:----------:|-------------|--------------|
| `config/config.php` | 🔴 Critical | Database credentials, base URL, global constants | Required by all files |
| `config/db.php` | 🔴 Critical | Creates MySQL connection (`$conn`) | Requires config.php |
| `config/session.php` | 🔴 Critical | Authentication and access control functions | Used by all protected pages |
| `app/apply.php` | 🔴 Critical | Core IP submission functionality | Core system purpose |
| `admin/approve-applications.php` | 🔴 Critical | Final approval and certificate trigger | Director workflow |
| `admin/verify-applications.php` | 🔴 Critical | Document verification interface | Clerk workflow |
| `includes/sidebar.php` | 🟡 High | Dynamic navigation based on role | Used by all pages |
| `certificate/generate.php` | 🟡 High | Certificate image generation | Called on approval |

---

## 9. Key Features and Functionalities

### 9.1 For Users (Applicants)

| Feature | Description | File(s) |
|---------|-------------|---------|
| **Application Submission** | Submit Copyright, Patent, Trademark with document uploads | `app/apply.php` |
| **Draft Saving** | Save incomplete applications for later | `app/apply.php` |
| **Application Tracking** | Real-time status updates | `app/my-applications.php` |
| **Payment Upload** | Upload payment receipts | `app/upload-payment.php` |
| **Certificate Download** | Download approved certificates | `app/view-certificate.php` |
| **Badge Collection** | View earned badges | `app/view-badge.php` |

### 9.2 For Clerks

| Feature | Description | File(s) |
|---------|-------------|---------|
| **Document Verification** | Review uploaded IP documents | `admin/verify-applications.php` |
| **Payment Verification** | Verify payment receipts | `admin/verify-payments.php` |
| **Status Updates** | Mark applications as office_visit, etc. | Various admin files |

### 9.3 For Directors

| Feature | Description | File(s) |
|---------|-------------|---------|
| **Final Approval** | Approve or reject verified applications | `admin/approve-applications.php` |
| **Analytics Dashboard** | View charts and statistics | `admin/analytics.php` |
| **User Management** | Manage user accounts and roles | `admin/manage-users.php` |
| **Form Builder** | Customize application form fields | `admin/manage-form-fields.php` |
| **Audit Log** | Review system activity history | `admin/audit-log.php` |
| **Badge Management** | Configure badge thresholds | `admin/manage-badges.php` |

### 9.4 Gamification System

| Badge Level | Views Required | Points Awarded |
|-------------|:--------------:|:--------------:|
| Bronze 🥉 | 10+ views | 50 points |
| Silver 🥈 | 50+ views | 150 points |
| Gold 🥇 | 100+ views | 300 points |
| Platinum 💎 | 250+ views | 500 points |
| Diamond 💠 | 500+ views | 1,000 points |

---

## 10. Security Implementation

### 10.1 Authentication Security

| Measure | Implementation |
|---------|----------------|
| **Password Hashing** | PHP `password_hash()` with bcrypt algorithm |
| **Session Management** | PHP native sessions with regeneration on login |
| **Role-Based Access** | `requireRole()` checks on every protected page |
| **SQL Injection Prevention** | Prepared statements with bound parameters |

### 10.2 Data Protection

| Measure | Implementation |
|---------|----------------|
| **Input Sanitization** | `htmlspecialchars()` on all output |
| **File Upload Validation** | File type and size restrictions |
| **CSRF Protection** | Form tokens (recommended for production) |
| **XSS Prevention** | Output encoding on dynamic content |

### 10.3 Audit Trail

All significant actions are logged in the `audit_log` table:

| Logged Actions |
|----------------|
| User login/logout |
| Application submission |
| Status changes |
| Approval/rejection |
| User role changes |

---

## 11. Deployment Requirements

### 11.1 Server Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| **PHP Version** | 7.4 | 8.0+ |
| **MySQL Version** | 5.7 | 8.0+ |
| **Apache Version** | 2.4 | 2.4+ |
| **Memory** | 512 MB | 2 GB |
| **Storage** | 500 MB | 5 GB+ |

### 11.2 Installation Steps

1. **Copy files** to web server directory
2. **Import database** from `database/complete_chmsu_ip_system.sql`
3. **Configure** `config/config.php` with database credentials
4. **Set permissions** on `uploads/` folder (writable)
5. **Access** via browser at configured BASE_URL

### 11.3 Default Accounts

| Role | Email | Password |
|------|-------|----------|
| Director | director@chmsu.edu.ph | password |
| Clerk | clerk@chmsu.edu.ph | password |
| User | student@chmsu.edu.ph | password |

> ⚠️ **Important:** Change all default passwords immediately after deployment.

---

## Document Information

| Property | Value |
|----------|-------|
| **Document Title** | CHMSU IP System Technical Reference |
| **Version** | 1.0 |
| **Last Updated** | January 12, 2026 |
| **Author** | System Development Team |
| **Purpose** | Final Defense Reference Document |

---

**Carlos Hilado Memorial State University**  
*College of Engineering and Technology*  
*Intellectual Property Registration and Hub System*

---
