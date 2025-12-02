# Header and Footer Components

This directory contains reusable header and footer components for the CHMSU IP System.

## Files

### `header.php`
Reusable header component that includes:
- Navigation bar with CHMSU logo
- User authentication status detection
- Dynamic logo path based on current directory
- User info display (when logged in)
- Login/Signup buttons (when not logged in)
- Logout button (when logged in)

**Location:** `includes/header.php`

**Usage:**
```php
<?php
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'config/session.php';

// Optional: Set page title
$page_title = 'My Page Title';
$header_title = 'CHMSU IP System'; // Optional: Custom header title

// Optional: Add additional CSS files
$additional_css = ['path/to/custom.css'];

require_once 'includes/header.php';
?>
```

### `footer.php`
Reusable footer component that includes:
- About section
- Quick links navigation
- Contact information
- Copyright notice
- Responsive grid layout

**Location:** `includes/footer.php`

**Usage:**
```php
<?php
// Your page content here
?>

<?php require_once 'includes/footer.php'; ?>
```

## Features

### Automatic Path Detection
Both header and footer automatically detect the current directory and adjust asset paths accordingly:
- Root directory: `public/logos/chmsu-logo.png`
- Subdirectories (admin/, app/, auth/, etc.): `../public/logos/chmsu-logo.png`

### Dynamic Content
- Header shows different content based on login status
- Footer shows different links based on authentication
- Logo path adjusts automatically

## Integration Example

```php
<?php
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'config/session.php';

requireLogin(); // or requireRole() if needed

$page_title = 'Dashboard';
require_once 'includes/header.php';
?>

<!-- Your page content here -->

<?php require_once 'includes/footer.php'; ?>
```

## Notes

- Header includes the opening `<html>`, `<head>`, and `<body>` tags
- Footer includes the closing `</body>` and `</html>` tags
- Both components handle path detection automatically
- Logo uses the modern round styling with hover effects

