<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle  = $pageTitle  ?? 'MediCare HMS';
$pageCss    = $pageCss    ?? '';
$useSidebar = $useSidebar ?? false;
$isPublic   = $isPublic   ?? false;   // true = public website, hides app topbar
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> — MediCare HMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/Web/Hospital-Management-System/includes/layout.css">

    <?php if ($pageCss !== ''): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($pageCss); ?>">
    <?php endif; ?>
</head>
<body>

<?php if (!$isPublic): ?>
<!-- ── Authenticated App Topbar ── -->
<header class="topbar">
    <div class="topbar-brand">
        <div class="topbar-logo">M</div>
        <div>
            <div class="topbar-title">MediCare HMS</div>
            <div class="topbar-subtitle">Hospital Management System</div>
        </div>
    </div>
    <div class="topbar-right">
        <?php if (isset($_SESSION['full_name'])): ?>
            <div class="user-chip">
                <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                <span class="user-chip-role"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></span>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/Web/Hospital-Management-System/logout.php" class="logout-btn">Logout</a>
        <?php endif; ?>
    </div>
</header>
<?php endif; ?>

<?php if ($useSidebar): ?>
<div class="app-layout">
<?php endif; ?>
