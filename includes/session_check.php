<?php
// includes/session_check.php
// Include at top of any page that requires login.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /Web/Hospital-Management-System/login.php');
    exit();
}
