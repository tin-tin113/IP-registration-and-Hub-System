<?php
require_once 'config/db.php';
require_once 'config/session.php';

if (is_logged_in()) {
    log_audit('LOGOUT', 'user', $_SESSION['user_id']);
}

session_unset();
session_destroy();
header('Location: auth/login.php?msg=You have been logged out');
exit();
?>
