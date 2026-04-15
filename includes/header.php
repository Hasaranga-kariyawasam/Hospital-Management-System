<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'Hospital Management System';
$pageCss = $pageCss ?? '';
$useSidebar = $useSidebar ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <link rel="stylesheet" href="/hospital-system/includes/layout.css">

    <?php if ($pageCss !== ''): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($pageCss); ?>">
    <?php endif; ?>
</head>
<body>

<header class="topbar">
    <div class="topbar-left">
        <h1 class="site-title">Hospital Management System</h1>
        <p class="site-subtitle">Appointment, Admission, Billing & Admin Control</p>
    </div>

    <div class="topbar-right">
        <?php if (isset($_SESSION['full_name'])): ?>
            <span class="user-chip">
                <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </span>
        <?php endif; ?>
    </div>
</header>

<?php if ($useSidebar): ?>
<div class="app-layout">
<?php endif; ?>