# CHMSU Intellectual Property Registration and Hub System

![CHMSU Logo](public/logos/chmsu-logo.png)

A comprehensive, centralized web-based platform for digitizing the management, verification, approval, and showcasing of intellectual property at Carlos Hilado Memorial State University.

---

## 🚀 System Overview

The **CHMSU IP System** serves as the digital backbone for the university's Intellectual Property Management Office (IPMO). It replaces manual paper-based workflows with a secure, efficient, and transparent online process.

### Core Objectives
1.  **Digitize Submission**: Allow faculty and students to submit IP applications online 24/7.
2.  **Streamline Workflow**: Automate the routing of applications through verification, payment, and approval stages.
3.  **Ensure Compliance**: Enforce strict verification of documents and payments before approval.
4.  **Showcase Innovation**: Publicly display approved IP works to promote the university's research output.
5.  **Incentivize creation**: Gamification system with badges and innovation points.

---

## 🔑 Key Features

### 🏛️ For the IP Office (Administration)

*   **Director Dashboard**:
    *   **Analytics & Reporting**: Real-time charts showing application trends, acceptance rates, and revenue.
    *   **Final Approval Authority**: One-click approval that automatically generates and seals certificates.
    *   **User Management**: Control access, assign roles, and manage staff accounts.
    *   **Custom Form Builder**: Modify application forms dynamically without writing code.
    *   **Audit Trails**: Complete history of every action taken within the system for accountability.

*   **Clerk Workspace**:
    *   **Document Verification**: Dedicated interface to review uploaded requirements (PDFs/Images).
    *   **Payment Processing**: Verify proof of payment receipts before processing applications.
    *   **Profile Validation**: Ensure all registered users are legitimate university members.

### 👨‍🔬 For Researchers (Faculty/Students)

*   **Online Application Portal**:
    *   Support for **Copyright**, **Patent**, and **Trademark** registrations.
    *   **Draft Mode**: Save work and continue later.
    *   **Progress Tracking**: Live status updates (e.g., "Under Review", "For Payment").
*   **Digital Certificates**:
    *   Automated generation of secure, printable certificates upon approval.
    *   QR Code integration for authenticity verification.
*   **Gamification**:
    *   **Badges**: Earn digital badges (Bronze to Diamond) based on the impact/views of your work.
    *   **Innovation Points**: Track your contribution score.
*   **Public Profile**: Showcase your portfolio of approved intellectual properties.

### 🌐 Public IP Hub

*   **Open Access Repository**: A public-facing gallery of all approved IPs.
*   **Search & Discovery**: Filter works by department, type, or year.
*   **View Tracking**: Monitor how many people are viewing specific research works.

---

## 🛠️ System Architecture

The system is built on a robust **3-Tier Architecture**:

1.  **Presentation Tier (Frontend)**:
    *   **HTML5/CSS3**: Modern, responsive design using glassmorphism effects.
    *   **JavaScript**: Dynamic interactions, async data loading, and local charting.
    *   **FontAwesome**: Validated visual iconography.

2.  **Logic Tier (Backend)**:
    *   **PHP 7.4+**: Secure server-side processing, session management, and business logic.
    *   **Apache**: Web server handling HTTP requests and routing.

3.  **Data Tier (Database)**:
    *   **MySQL**: Relational database storing 10+ interconnected tables (users, IPs, logs, etc.).

---

## 📦 Installation & Setup

### Requirements
*   **Server**: XAMPP (Apache + MySQL)
*   **Browser**: Chrome, Edge, or Firefox

### Quick Start Guide

1.  **Clone Files**: Place the project folder in `C:/xampp/htdocs/chmsu-IP-system`.
2.  **Import Database**:
    *   Open **phpMyAdmin**.
    *   Create a database named `chmsu-IP-system`.
    *   Import `database/complete_chmsu_ip_system.sql`.
3.  **Configure Config**:
    *   Check `config/config.php` to ensure DB credentials match your local setup.
4.  **Launch**:
    *   Open browser and go to `http://localhost/chmsu-IP-system`.

---

## 👤 Default Login Credentials

**Note: The default password for ALL test accounts is `password`.**

| Role | Email | Capabilities |
| :--- | :--- | :--- |
| **Director** (Admin) | `director@chmsu.edu.ph` | Full Access, Approvals, Analytics, User Management |
| **Clerk** | `clerk@chmsu.edu.ph` | Verification (Docs/Payment), Profile Approvals |
| **User** (Student) | `student@chmsu.edu.ph` | Apply for IP, View Own Certificates, Browse Hub |

> 🛡️ **Security Note**: In a production environment, all default passwords must be changed immediately via the Profile settings.

---

## 📂 Project Structure Overview

*   `admin/` - Management interfaces for Director and Clerk.
*   `app/` - Applicant-facing logic (Forms, Status checks).
*   `auth/` - Login, Register, and Password Reset.
*   `certificate/` - Certificate generation engine.
*   `config/` - Database connections and global settings.
*   `hub/` - Public-facing IP repository.
*   `uploads/` - Secure storage for user documents (Not directly accessible).

---

**Carlos Hilado Memorial State University**  
*College of Engineering and Technology*
