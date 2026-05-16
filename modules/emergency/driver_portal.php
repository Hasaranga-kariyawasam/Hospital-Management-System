<?php
// driver_portal.php — place in project root
session_start();
require_once  '../../config/db_config.php';



$driverId = (int)$_SESSION['driver_id'];

// ── AJAX status update 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'update_status') {
        $newStatus = $_POST['status'];
        $reqId     = (int)$_POST['request_id'];
        $allowed   = ['dispatched', 'en_route', 'arrived', 'resolved'];

        if (!in_array($newStatus, $allowed)) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid status']); exit;
        }

        $pdo->prepare("UPDATE emergency_requests SET status=? WHERE emergency_id=? AND driver_id=?")
            ->execute([$newStatus, $reqId, $driverId]);

        if ($newStatus === 'resolved') {
            $pdo->prepare("UPDATE emergency_requests SET completed_at=NOW() WHERE emergency_id=?")
                ->execute([$reqId]);
            $pdo->prepare("UPDATE drivers SET status='available' WHERE driver_id=?")
                ->execute([$driverId]);
        }

        echo json_encode(['ok' => true]);
    }
    exit;
}

// ── Fetch driver ──────────────────────────────────────────────
$dStmt = $pdo->prepare("SELECT * FROM drivers WHERE driver_id=?");
$dStmt->execute([$driverId]);
$driver = $dStmt->fetch();
if (!$driver) { session_destroy(); header('Location: driver_login.php'); exit; }

// ── Active request (dispatched / en_route / arrived) ─────────
$aStmt = $pdo->prepare("
    SELECT * FROM emergency_requests
    WHERE driver_id = ? AND status IN ('dispatched','en_route','arrived')
    ORDER BY dispatched_at DESC LIMIT 1
");
$aStmt->execute([$driverId]);
$req = $aStmt->fetch();

// ── Completed history ─────────────────────────────────────────
$hStmt = $pdo->prepare("
    SELECT * FROM emergency_requests
    WHERE driver_id = ? AND status = 'resolved'
    ORDER BY completed_at DESC LIMIT 10
");
$hStmt->execute([$driverId]);
$history = $hStmt->fetchAll();

// Status flow
$flow = ['dispatched','en_route','arrived','resolved'];
$nextLabel = [
    'dispatched' => ['status'=>'en_route',  'label'=>'🚦 Mark En Route',    'color'=>'#e07000'],
    'en_route'   => ['status'=>'arrived',   'label'=>'📍 Mark Arrived',     'color'=>'#1a6dff'],
    'arrived'    => ['status'=>'resolved',  'label'=>'✅ Mark as Resolved',  'color'=>'#1a9e4a'],
];
$stepIcons  = ['📋','🚦','📍','✅'];
$stepLabels = ['Dispatched','En Route','Arrived','Resolved'];

// Human-readable maps
$typeMap = [
    'cardiac'=>'Cardiac Arrest / Chest Pain','accident'=>'Accident / Trauma',
    'breathing'=>'Breathing Difficulty','stroke'=>'Stroke / Sudden Paralysis',
    'burn'=>'Severe Burns','poisoning'=>'Poisoning / Overdose',
    'fracture'=>'Fracture / Bone Injury','other'=>'Other Critical Condition',
];
$consciousMap = ['yes'=>'Fully Conscious','semi'=>'Semi-Conscious','no'=>'Unconscious'];
$assistMap    = ['yes'=>'Someone is helping','no'=>'Person is alone','medical'=>'Medical professional present'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Portal — MediCare</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'DM Sans',sans-serif;background:#f0f2f8;color:#1a1a2e;min-height:100vh}

        /* ── Topbar ── */
        .topbar{background:linear-gradient(135deg,#0f1b3d,#1a3a7a);color:#fff;padding:0 28px;height:64px;
            display:flex;align-items:center;justify-content:space-between;
            position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,.3)}
        .tb-left{display:flex;align-items:center;gap:12px}
        .tb-icon{font-size:28px}
        .tb-name{font-size:16px;font-weight:700}
        .tb-sub{font-size:11px;opacity:.6}
        .tb-right{display:flex;align-items:center;gap:12px}
        .status-pill{padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700}
        .pill-available{background:#e8f9ee;color:#1a9e4a}
        .pill-on_duty  {background:#fff3cd;color:#b07800}
        .pill-off_duty {background:#f0f2f8;color:#888}
        .btn-logout{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);
            padding:8px 16px;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;cursor:pointer;transition:.2s}
        .btn-logout:hover{background:rgba(255,255,255,.25)}

        /* ── Page ── */
        .page{padding:28px;max-width:900px;margin:0 auto}

        /* ── Info row ── */
        .info-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px}
        .info-card{background:#fff;border-radius:14px;padding:18px 22px;
            box-shadow:0 2px 10px rgba(0,0,0,.06);border-left:4px solid var(--c)}
        .info-label{font-size:11px;color:#888;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
        .info-val{font-size:20px;font-weight:700;color:var(--c)}

        /* ── Active request card ── */
        .req-card{background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.08);
            overflow:hidden;margin-bottom:24px}
        .req-head{background:linear-gradient(135deg,#e02020,#a50000);color:#fff;
            padding:18px 26px;display:flex;align-items:center;justify-content:space-between}
        .req-head-title{font-size:16px;font-weight:700}
        .req-head-time{font-size:12px;opacity:.8}
        .req-body{padding:24px 26px}

        /* ── Progress bar ── */
        .progress{display:flex;align-items:flex-start;margin-bottom:26px}
        .p-step{flex:1;text-align:center;position:relative}
        .p-step::after{content:'';position:absolute;top:16px;left:50%;right:-50%;
            height:3px;background:#e0e4ef;z-index:0}
        .p-step:last-child::after{display:none}
        .p-step.done::after{background:#1a9e4a}
        .p-dot{width:34px;height:34px;border-radius:50%;
            background:#e0e4ef;color:#aaa;
            display:flex;align-items:center;justify-content:center;
            font-size:14px;font-weight:700;margin:0 auto 6px;position:relative;z-index:1;transition:.3s}
        .p-step.done   .p-dot{background:#1a9e4a;color:#fff}
        .p-step.active .p-dot{background:#1a6dff;color:#fff;box-shadow:0 0 0 5px rgba(26,109,255,.2)}
        .p-label{font-size:11px;color:#888;font-weight:500}
        .p-step.done   .p-label{color:#1a9e4a;font-weight:600}
        .p-step.active .p-label{color:#1a6dff;font-weight:700}

        /* ── Detail grid ── */
        .detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px 32px;margin-bottom:24px}
        .d-label{font-size:11px;color:#888;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px}
        .d-val{font-size:14px;font-weight:600;color:#1a1a2e}

        /* ── Critical badge ── */
        .critical-banner{background:#fff0f0;border:2px solid #ff2d2d;border-radius:10px;
            padding:10px 16px;font-size:13px;font-weight:600;color:#cc0000;
            margin-bottom:18px;display:flex;align-items:center;gap:8px}

        /* ── Action buttons ── */
        .action-row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
        .btn-action{padding:13px 26px;border-radius:10px;border:none;
            font-family:'DM Sans',sans-serif;font-size:14px;font-weight:700;
            color:#fff;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.2);transition:.2s}
        .btn-action:hover{opacity:.88;transform:translateY(-1px)}
        .btn-call{padding:13px 22px;border-radius:10px;background:#0f1b3d;color:#fff;border:none;
            font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;cursor:pointer;
            text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:.2s}
        .btn-call:hover{background:#1a3a7a}

        /* ── No request ── */
        .no-req{background:#fff;border-radius:16px;padding:60px 40px;text-align:center;
            box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:24px}
        .no-req-icon{font-size:56px;margin-bottom:16px}
        .no-req-title{font-size:20px;font-weight:700;color:#1a1a2e;margin-bottom:8px}
        .no-req-sub{font-size:14px;color:#888}

        /* ── History ── */
        .hist-card{background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:hidden}
        .hist-head{padding:16px 22px;border-bottom:1px solid #eef0f6;font-size:15px;font-weight:700}
        table{width:100%;border-collapse:collapse}
        th{background:#f7f8fc;text-align:left;padding:10px 16px;font-size:11px;font-weight:600;color:#888;text-transform:uppercase}
        td{padding:12px 16px;font-size:13px;border-top:1px solid #f0f2f8}
        .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600}
        .badge-resolved{background:#e8f9ee;color:#1a9e4a}

        /* ── Toast ── */
        .toast{position:fixed;bottom:28px;right:28px;background:#1a1a2e;color:#fff;
            padding:13px 22px;border-radius:10px;font-size:14px;font-weight:500;
            box-shadow:0 8px 24px rgba(0,0,0,.3);display:none;z-index:999;
            animation:fadeIn .3s ease}
        @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    </style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
    <div class="tb-left">
        <div class="tb-icon">🚑</div>
        <div>
            <div class="tb-name"><?= htmlspecialchars($driver['full_name']) ?></div>
            <div class="tb-sub"><?= htmlspecialchars($driver['ambulance_no']) ?> · License: <?= htmlspecialchars($driver['license_no']) ?></div>
        </div>
    </div>
    <div class="tb-right">
        <span class="status-pill pill-<?= $driver['status'] ?>"><?= ucfirst(str_replace('_',' ',$driver['status'])) ?></span>
        <form method="POST" action="driver_logout.php" style="margin:0">
            <button type="submit" class="btn-logout">Logout</button>
        </form>
    </div>
</div>

<div class="page">

    <!-- Info row -->
    <div class="info-row">
        <div class="info-card" style="--c:#1a6dff">
            <div class="info-label">Ambulance</div>
            <div class="info-val"><?= htmlspecialchars($driver['ambulance_no']) ?></div>
        </div>
        <div class="info-card" style="--c:<?= $driver['status']==='available'?'#1a9e4a':'#e07000' ?>">
            <div class="info-label">My Status</div>
            <div class="info-val"><?= ucfirst(str_replace('_',' ',$driver['status'])) ?></div>
        </div>
        <div class="info-card" style="--c:#888">
            <div class="info-label">Trips Resolved</div>
            <div class="info-val"><?= count($history) ?></div>
        </div>
    </div>

    <?php if ($req): ?>

    <!-- Active Request -->
    <?php
        $curIdx = array_search($req['status'], $flow);
        $isUnconscious = ($req['is_conscious'] === 'no');
    ?>
    <div class="req-card">
        <div class="req-head">
            <div>
                <div class="req-head-title">🆘 Active Emergency — <?= htmlspecialchars($typeMap[$req['emergency_type']] ?? ucfirst($req['emergency_type'])) ?></div>
                <div class="req-head-time">Dispatched: <?= date('d M Y, H:i', strtotime($req['dispatched_at'])) ?></div>
            </div>
            <span style="background:rgba(255,255,255,.2);color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700">
                <?= ucfirst(str_replace('_',' ',$req['status'])) ?>
            </span>
        </div>

        <div class="req-body">

            <!-- Critical warning -->
            <?php if ($isUnconscious): ?>
            <div class="critical-banner">🚨 CRITICAL — Patient is UNCONSCIOUS. Respond immediately.</div>
            <?php endif; ?>

            <!-- Progress -->
            <div class="progress">
                <?php foreach ($flow as $i => $s): ?>
                <div class="p-step <?= $i < $curIdx ? 'done' : ($i === $curIdx ? 'active' : '') ?>">
                    <div class="p-dot"><?= $i < $curIdx ? '✓' : $stepIcons[$i] ?></div>
                    <div class="p-label"><?= $stepLabels[$i] ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Patient details -->
            <div class="detail-grid">
                <div>
                    <div class="d-label">👤 Patient Name</div>
                    <div class="d-val"><?= htmlspecialchars($req['patient_name'] ?? '—') ?></div>
                </div>
                <div>
                    <div class="d-label">🆘 Emergency Type</div>
                    <div class="d-val"><?= htmlspecialchars($typeMap[$req['emergency_type']] ?? ucfirst($req['emergency_type'])) ?></div>
                </div>
                <div>
                    <div class="d-label">📍 Address</div>
                    <div class="d-val"><?= htmlspecialchars($req['patient_address'] ?? '—') ?></div>
                </div>
                <div>
                    <div class="d-label">🧠 Consciousness</div>
                    <div class="d-val"><?= htmlspecialchars($consciousMap[$req['is_conscious']] ?? ucfirst($req['is_conscious'])) ?></div>
                </div>
                <div>
                    <div class="d-label">📞 Contact Number</div>
                    <div class="d-val"><?= htmlspecialchars($req['contact_number']) ?></div>
                </div>
                <div>
                    <div class="d-label">👥 Assistance on Site</div>
                    <div class="d-val"><?= htmlspecialchars($assistMap[$req['assistance_on_site']] ?? ucfirst($req['assistance_on_site'])) ?></div>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="action-row">
                <?php if (isset($nextLabel[$req['status']])): $next = $nextLabel[$req['status']]; ?>
                    <button class="btn-action" style="background:<?= $next['color'] ?>"
                        onclick="updateStatus('<?= $next['status'] ?>', <?= $req['emergency_id'] ?>)">
                        <?= $next['label'] ?>
                    </button>
                <?php endif; ?>
                <a href="tel:<?= preg_replace('/\s+/','',$req['contact_number']) ?>" class="btn-call">
                    📞 Call Patient
                </a>
            </div>

        </div>
    </div>

    <?php else: ?>

    <!-- No active request -->
    <div class="no-req">
        <div class="no-req-icon">🟢</div>
        <div class="no-req-title">No Active Assignment</div>
        <div class="no-req-sub">You are marked as available. The dispatcher will assign you when an emergency comes in.</div>
    </div>

    <?php endif; ?>

    <!-- Trip History -->
    <?php if (!empty($history)): ?>
    <div class="hist-card">
        <div class="hist-head">📋 Resolved Trips</div>
        <table>
            <thead>
                <tr><th>Patient</th><th>Emergency</th><th>Address</th><th>Resolved At</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($h['patient_name'] ?? '—') ?></strong></td>
                    <td><?= htmlspecialchars($typeMap[$h['emergency_type']] ?? ucfirst($h['emergency_type'])) ?></td>
                    <td><?= htmlspecialchars($h['patient_address'] ?? '—') ?></td>
                    <td style="white-space:nowrap;color:#888;font-size:12px">
                        <?= $h['completed_at'] ? date('d M Y, H:i', strtotime($h['completed_at'])) : '—' ?>
                    </td>
                    <td><span class="badge badge-resolved">Resolved</span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div><!-- /.page -->

<div class="toast" id="toast"></div>

<script>
async function updateStatus(newStatus, requestId) {
    const body = new FormData();
    body.append('action',     'update_status');
    body.append('status',     newStatus);
    body.append('request_id', requestId);
    const r = await fetch('driver_portal.php', { method: 'POST', body });
    const j = await r.json();
    if (j.ok) {
        showToast('Status updated → ' + newStatus.replace('_',' ').toUpperCase());
        setTimeout(() => location.reload(), 1200);
    } else {
        alert('Failed to update. Please try again.');
    }
}
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg; t.style.display = 'block';
    setTimeout(() => t.style.display = 'none', 3000);
}
</script>
</body>
</html>
