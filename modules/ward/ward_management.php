<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
$requiredRoles = ['admin', 'reception'];
require_once __DIR__ . '/../../includes/role_check.php';

$role = $_SESSION['role'];

// ── Stats ──────────────────────────────────────────────────────────────────
$roomStats = $pdo->query("
    SELECT
        COUNT(*)                                  AS total_rooms,
        SUM(is_available = 1)                     AS available_rooms,
        SUM(is_available = 0)                     AS occupied_rooms,
        SUM(room_type = 'icu')                    AS icu_total,
        SUM(room_type = 'icu' AND is_available=1) AS icu_available
    FROM rooms
")->fetch();




// ── Room availability by type ──────────────────────────────────────────────
$roomsByType = $pdo->query("
    SELECT
        room_type,
        COUNT(*) AS total,
        SUM(is_available = 1) AS available,
        SUM(is_available = 0) AS occupied
    FROM rooms
    GROUP BY room_type
    ORDER BY room_type
")->fetchAll();



$pageTitle  = 'Ward & Room Management';
$useSidebar = true;
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>


<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-title">
            <h2>Ward &amp; Room Management</h2>
            <p>Overview of all rooms, admissions, and patient stays</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
           
               
                <a href="room_management.php" class="btn btn-primary">Manage Rooms</a>
          
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon blue"></div>
            <div>
                <div class="stat-label">Total Rooms</div>
                <div class="stat-value"><?= $roomStats['total_rooms'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"></div>
            <div>
                <div class="stat-label">Available</div>
                <div class="stat-value"><?= $roomStats['available_rooms'] ?? 0 ?></div>
            </div>
        </div>
        
       
    </div>

   

    <!-- Room Availability by Type -->
    <div class="card" style="margin-bottom:24px">
        <div class="card-header">
            <h3 class="card-title">Room Availability by Type</h3>
        </div>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Room Type</th>
                        <th>Total</th>
                        <th>Available</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                
                foreach ($roomsByType as $rt):
                    $pct = $rt['total'] > 0 ? round(($rt['occupied'] / $rt['total']) * 100) : 0;
                    
                    $barColor = $pct >= 90 ? 'var(--danger)' : ($pct >= 60 ? 'var(--warning)' : 'var(--success)');
                    $info = $typeLabels[$rt['room_type']] ?? ['label' => ucfirst($rt['room_type']), 'icon' => ''];
                ?>
                <tr>
                    <td><strong><?= $info['icon'] ?> <?= htmlspecialchars($info['label']) ?></strong></td>
                    <td><?= $rt['total'] ?></td>
                    <td><span style="color:var(--success);font-weight:600"><?= $rt['available'] ?></span></td>
                  
                   
                  
                </tr>
                <?php endforeach; ?>
                <?php if (empty($roomsByType)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--muted)">No rooms configured yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    
</main>

<style>
.stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:16px; margin-bottom:24px; }
.stat-card { background:var(--surface); border-radius:var(--radius); padding:20px; display:flex; align-items:center; gap:14px; box-shadow:var(--shadow-sm); }
.stat-icon { font-size:26px; width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; }
.stat-icon.blue { background:var(--accent-light); }
.stat-icon.green { background:var(--success-light); }
.stat-icon.yellow { background:var(--warning-light); }
.stat-icon.red { background:var(--danger-light); }
.stat-label { font-size:12px; color:var(--muted); font-weight:500; }
.stat-value { font-size:26px; font-weight:700; color:var(--text); }
.card { background:var(--surface); border-radius:var(--radius); box-shadow:var(--shadow-sm); overflow:hidden; }
.card-header { display:flex; justify-content:space-between; align-items:center; padding:18px 24px; border-bottom:1px solid var(--border-light); }
.card-title { font-size:15px; font-weight:700; margin:0; }
.table { width:100%; border-collapse:collapse; }
.table th { padding:10px 16px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:var(--muted); background:var(--bg); border-bottom:1px solid var(--border-light); }
.table td { padding:12px 16px; border-bottom:1px solid var(--border-light); font-size:14px; }
.table tr:last-child td { border-bottom:none; }
.table tr:hover td { background:#f8fafc; }
.btn { display:inline-flex; align-items:center; gap:6px; padding:9px 18px; border-radius:var(--radius-sm); font-size:14px; font-weight:500; cursor:pointer; border:1px solid transparent; text-decoration:none; transition:all var(--transition); }
.btn-primary { background:var(--accent); color:#fff; border-color:var(--accent); }
.btn-primary:hover { background:var(--accent-dark); color:#fff; }
.btn-secondary { background:var(--surface); color:var(--text-mid); border-color:var(--border); }
.btn-secondary:hover { background:var(--bg); color:var(--text); }
.badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; }
.page-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.page-header-title h2 { font-size:1.5rem; font-weight:700; margin-bottom:4px; }
.page-header-title p { color:var(--muted); font-size:14px; }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>