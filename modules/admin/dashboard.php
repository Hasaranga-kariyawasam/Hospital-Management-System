<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/session_check.php';
require_once __DIR__ . '/../../includes/role_check.php';

requireRole(['admin']);

$pageTitle = 'Admin Dashboard';
$pageCss = '/hospital-system/modules/admin/dashboard.css';
$useSidebar = true;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<h2 class="page-title">Admin Dashboard</h2>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <h4>Today's Appointments</h4>
        <div class="value">24</div>
        <p>Current daily total</p>
    </div>

    <div class="dashboard-card">
        <h4>Admitted Patients</h4>
        <div class="value">11</div>
        <p>Currently inside hospital</p>
    </div>

    <div class="dashboard-card">
        <h4>Pending Bills</h4>
        <div class="value">6</div>
        <p>Need payment follow-up</p>
    </div>

    <div class="dashboard-card">
        <h4>Active Emergencies</h4>
        <div class="value">2</div>
        <p>Live emergency requests</p>
    </div>
</div>

<div class="card">
    <h3 class="card-title">Quick Overview</h3>
    <p>Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>.</p>
    <p>This is the clean starter version of the admin dashboard.</p>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>