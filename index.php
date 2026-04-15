<?php
declare(strict_types=1);
session_start();

if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: /hospital-system/modules/admin/dashboard.php');
            break;
        default:
            header('Location: /hospital-system/login.php');
            break;
    }
    exit();
}

header('Location: /hospital-system/login.php');
exit();