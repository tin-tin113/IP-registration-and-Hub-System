# CHMSU IP System - File Dictionary & Importance Guide

This document provides a detailed breakdown of every key file in the system, explaining **what it does** and **why it is important**.

## 📂 Root Directory (Main Entry Points)

| File | Description | Importance |
| :--- | :--- | :--- |
| `index.php` | The publicly accessible homepage. It welcomes visitors and links to Login/Register/Hub. | **High**. It's the "front door" of the website. |
| `dashboard.php` | The main control panel for **Regular Users (Applicants)**. Shows status summaries and recent applications. | **Critical**. This is where applicants start their work. |
| `help.php` | A user guide/FAQ page. | Low. Useful for support but not critical for logic. |
| `logout.php` | Destroys the user session and redirects to login. | **High**. Essential for security (properly closing access). |

## 📂 admin/ (Administrative Functions)
*Only accessible by users with 'director' or 'clerk' roles.*

| File | Description | Importance |
| :--- | :--- | :--- |
| `analytics.php` | Displays visual charts (graphs) of application data. **Now works Offline.** | **High**. Provides insights for decision-makers. |
| `analytics-data.php` | A backend "API" that fetches raw numbers from the database for `analytics.php`. | **High**. The visuals in analytics are empty without this data source. |
| `approve-applications.php` | **Director Only**. Allows final approval of applications after they pass verification. | **Critical**. This controls the final stage of the application workflow. |
| `verify-applications.php` | **Clerk/Director**. Interface for checking uploaded documents. | **Critical**. The primary workspace for Clerks. |
| `verify-payments.php` | **Clerk/Director**. Interface for checking payment receipts. | **Critical**. Ensures the university receives payment before processing IPs. |
| `dashboard.php` | Admin-specific dashboard showing system-wide stats and pending tasks. | **Critical**. The homepage for Admins. |
| `manage-users.php` | List of all registered users. Admins can view/edit roles here. | **High**. User management control center. |
| `manage-form-fields.php` | Allows Admins to customize dropdown options (e.g., adding a new Department). | **Medium**. Makes the system flexible without coding. |
| `manage-certificate-template.php` | Upload/Edit the background image for certificates. | **Medium**. Aesthetic control for generated certificates. |
| `manage-badges.php` | Define rules for awarding badges (e.g., "First Filing"). | **Low**. Gamification features. |
| `audit-log.php` | Shows a history of who did what (e.g., "Director X approved App Y"). | **High**. Security and accountability trail. |

## 📂 app/ (Applicant Functions)
*The core logic for submitting and managing applications.*

| File | Description | Importance |
| :--- | :--- | :--- |
| `apply.php` | The "New Application" form. Collects details and file uploads. | **Critical**. The core purpose of the system (collecting IPs). |
| `my-applications.php` | A list of all applications submitted by the current user. | **High**. Allows users to track their progress. |
| `view-application.php` | Detailed view of a single application (timeline, comments, files). | **High**. Communication hub between Admin and User. |
| `upload-payment.php` | Form to upload the payment receipt for a specific application. | **High**. Specific workflow step. |
| `permission-request.php` | Form to ask for permission to publish an IP externally. | **Medium**. Additional workflow feature. |
| `view-certificate.php` | Displays the final IP certificate if approved. | **High**. The "reward" for the user. |

## 📂 auth/ (Security & Access)

| File | Description | Importance |
| :--- | :--- | :--- |
| `login.php` | Handles User & Admin sign-in (Password verification). | **Critical**. Controls entry; protects the system. |
| `register.php` | Account creation form (Name, Email, Password). | **Critical**. How new users get into the system. |
| `forgot-password.php` | Reset mechanism for lost passwords. | **Medium**. User support feature. |

## 📂 config/ (System Core)
*The engine room. Do not touch unless you know what you are doing.*

| File | Description | Importance |
| :--- | :--- | :--- |
| `db.php` | Creates the Database Connection (`$conn`). | **Critical**. The system dies without this. |
| `config.php` | Global settings (Database credentials, Base URL path). | **Critical**. "One place to change them all." |
| `session.php` | Functions to check if a user is logged in (`requireLogin()`). | **High**. Enforces security on every page. |
| `form_fields_helper.php` | Functions to load dropdown options from the database. | **Medium**. Keeps forms dynamic. |
| `badge-auto-award.php` | Logic that runs silently to give badges when criteria are met. | **Low**. Gamification logic. |

## 📂 hub/ (Public View)

| File | Description | Importance |
| :--- | :--- | :--- |
| `browse.php` | A public library of all Approved and Published IPs. | **High**. Showcases university innovation to the world. |
| `view.php` | Public details page for a specific published IP. | **High**. Public visibility. |

## 📂 includes/ (Reusable Components)

| File | Description | Importance |
| :--- | :--- | :--- |
| `header.php` | The top bar (Logo, User Name, Logout button). | **Medium**. UI Consistency. |
| `sidebar.php` | The left navigation menu. Checks user role to show correct links. | **Medium**. Navigation consistency. |
| `footer.php` | Bottom of the page (Copyright, Links). | **Low**. UI Consistency. |

## 📂 assets/js/ (Static Logic)

| File | Description | Importance |
| :--- | :--- | :--- |
| `chart.js` | The library code for drawing graphs. | **High (for Analytics)**. Enables offline charts. |

## 📂 certificate/ (Generation Logic)

| File | Description | Importance |
| :--- | :--- | :--- |
| `generate.php` | Code that draws text onto an image to create the PDF/Image certificate. | **High**. Automates certificate creation. |

---

### Key Takeaways for Maintenance
1.  **If the site is "Down" (White screen/Error)**: Check `config/db.php` and `config/config.php`.
2.  **Authentication Issues**: Check `auth/login.php` and `config/session.php`.
3.  **Styling/Layout**: Check `includes/sidebar.php` and CSS files in `header.php`.
4.  **Offline Analytics**: Ensure `assets/js/chart.js` is unmodified.
