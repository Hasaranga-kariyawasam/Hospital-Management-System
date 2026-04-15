<?php
declare(strict_types=1);

function requireRole(array $allowedRoles): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['role'])) {
        header('Location: /hospital-system/login.php');
        exit();
    }

    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        http_response_code(403);
        die('Access denied.');
    }
}