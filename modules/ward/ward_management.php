<?php
declare(strict_types=1);
$requiredRoles = ['admin','reception'];
require_once __DIR__ . '/../../includes/role_check.php';
require_once __DIR__ . '/../../config/db_config.php';
$pageTitle  = 'Ward Management';
$useSidebar = true;
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="main-content">
    <div class="page-header">
        <div class="page-header-title">
            <h2>Ward Management</h2>
            <p>This module is assigned to <strong>TG2086</strong> — implementation coming soon.</p>
        </div>
    </div>
    <div class="card" style="text-align:center;padding:60px 40px">
        <div style="font-size:48px;margin-bottom:16px">🚧</div>
        <h3 style="margin-bottom:8px">Module Under Development</h3>
        <p style="color:var(--muted)">The <strong>Ward Management</strong> module will be implemented here.<br>Assigned to: <strong>TG2086</strong></p>
    </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
