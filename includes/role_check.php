<?php
// includes/role_check.php
// Usage: $requiredRoles = ['admin', 'reception']; include 'role_check.php';
declare(strict_types=1);

require_once __DIR__ . '/session_check.php';

if (!isset($requiredRoles) || !is_array($requiredRoles)) {
    $requiredRoles = [];
}

if (!empty($requiredRoles) && !in_array($_SESSION['role'] ?? '', $requiredRoles, true)) {
    http_response_code(403);
    die('<p style="font-family:sans-serif;padding:40px;color:#dc2626">
        <strong>Access Denied</strong> — You do not have permission to view this page.</p>');
}
