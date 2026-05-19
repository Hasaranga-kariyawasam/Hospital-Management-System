<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';

// ── Auth guard — uses same session vars as login.php ──────────
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: /Web/Hospital-Management-System/login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

// ── AJAX status update ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'finish_duty') {
        // Driver is on_duty but emergency link is missing — just reset driver to available
        try {
            $pdo->beginTransaction();
            // Resolve any lingering emergency assigned to this driver
            $pdo->prepare("
                UPDATE emergency_requests SET status = 'resolved'
                WHERE assigned_driver_id = ? AND status IN ('dispatched','en_route','arrived','pending')
            ")->execute([$userId]);
            $pdo->prepare("
                UPDATE drivers SET status = 'available', assigned_emergency_id = NULL
                WHERE user_id = ?
            ")->execute([$userId]);
            $pdo->commit();
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'update_status') {
        $newStatus = $_POST['status'];
        $reqId     = (int)$_POST['request_id'];
        $allowed   = ['dispatched', 'en_route', 'arrived', 'resolved'];

        if (!in_array($newStatus, $allowed, true)) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid status']); exit;
        }

        try {
            $pdo->beginTransaction();

            $pdo->prepare("
                UPDATE emergency_requests
                SET status = ?
                WHERE emergency_id = ? AND assigned_driver_id = ?
            ")->execute([$newStatus, $reqId, $userId]);

            if ($newStatus === 'resolved') {
                $pdo->prepare("
                    UPDATE drivers SET status = 'available', assigned_emergency_id = NULL
                    WHERE user_id = ?
                ")->execute([$userId]);
            }

            $pdo->commit();
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
    }
    exit;
}

// ── Fetch driver (joined with users table like driver.php) ────
$dStmt = $pdo->prepare("
    SELECT d.*, u.full_name, u.email
    FROM drivers d
    JOIN users u ON d.user_id = u.user_id
    WHERE d.user_id = ?
");
$dStmt->execute([$userId]);
$driver = $dStmt->fetch();

if (!$driver) {
    // drivers table row missing — auto-insert then retry ONCE
    try {
        $pdo->prepare("INSERT IGNORE INTO drivers (user_id, license_number, status) VALUES (?, 'PENDING', 'available')")
            ->execute([$userId]);
        $dStmt->execute([$userId]);
        $driver = $dStmt->fetch();
    } catch (Throwable $e) { $driver = null; }

    if (!$driver) {
        // Still missing — show diagnostic, do NOT destroy session or loop
        http_response_code(200);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <style>body{font-family:sans-serif;background:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
        .card{background:#fff;border-radius:12px;padding:36px;max-width:460px;width:100%;box-shadow:0 4px 20px rgba(0,0,0,.1);text-align:center}
        h2{color:#0f172a;margin-bottom:10px}p{color:#64748b;font-size:13px;line-height:1.7}
        .info{background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:14px;font-size:12px;color:#92400e;text-align:left;margin:16px 0}
        .btn{display:inline-block;padding:9px 22px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:#0C447C;color:#fff;margin:4px}
        .btn-gray{background:#e2e8f0;color:#475569}</style></head>
        <body><div class="card">
        <h2>Driver Profile Not Found</h2>
        <p>You are logged in as a driver but no driver profile record exists in the database.</p>
        <div class="info">
          <strong>User ID:</strong> ' . $userId . '<br>
          <strong>Fix:</strong> Run <code>emergency_setup.sql</code> in phpMyAdmin to create the drivers table, then log in again.
        </div>
        <a href="/Web/Hospital-Management-System/logout.php" class="btn">Logout</a>
        <a href="/Web/Hospital-Management-System/login.php" class="btn btn-gray">Back to Login</a>
        </div></body></html>';
        exit();
    }
}


// ── Active request ────────────────────────────────────────────
// Try matching by assigned_driver_id (user_id) first
$aStmt = $pdo->prepare("
    SELECT * FROM emergency_requests
    WHERE assigned_driver_id = ? AND status IN ('dispatched','en_route','arrived')
    ORDER BY dispatch_time DESC LIMIT 1
");
$aStmt->execute([$userId]);
$req = $aStmt->fetch();

// Fallback: if driver is on_duty but no request found via user_id,
// try finding via the driver's assigned_emergency_id (handles driver_id vs user_id mismatch)
if (!$req && $driver['status'] === 'on_duty' && !empty($driver['assigned_emergency_id'])) {
    $aStmt2 = $pdo->prepare("
        SELECT * FROM emergency_requests
        WHERE emergency_id = ? AND status IN ('dispatched','en_route','arrived','pending')
    ");
    $aStmt2->execute([$driver['assigned_emergency_id']]);
    $req = $aStmt2->fetch();
}

// ── Completed history ─────────────────────────────────────────
$hStmt = $pdo->prepare("
    SELECT * FROM emergency_requests
    WHERE assigned_driver_id = ? AND status = 'resolved'
    ORDER BY dispatch_time DESC LIMIT 10
");
$hStmt->execute([$userId]);
$history = $hStmt->fetchAll();

// ── Total resolved count ──────────────────────────────────────
$cStmt = $pdo->prepare("
    SELECT COUNT(*) FROM emergency_requests
    WHERE assigned_driver_id = ? AND status = 'resolved'
");
$cStmt->execute([$userId]);
$totalResolved = (int)$cStmt->fetchColumn();

// ── Status flow ───────────────────────────────────────────────
$flow       = ['dispatched','en_route','arrived','resolved'];
$nextLabel  = [
    'dispatched' => ['status'=>'en_route', 'label'=>'Mark En Route',   'color'=>'#e07000'],
    'en_route'   => ['status'=>'arrived',  'label'=>'Mark Arrived',    'color'=>'#1a6dff'],
    'arrived'    => ['status'=>'resolved', 'label'=>'Mark as Resolved', 'color'=>'#1a9e4a'],
];
$stepIcons  = ['','','',''];
$stepLabels = ['Dispatched','En Route','Arrived','Resolved'];

// ── Human-readable maps ───────────────────────────────────────
$typeMap = [
    'cardiac'   => 'Cardiac Arrest / Chest Pain',
    'accident'  => 'Accident / Trauma',
    'breathing' => 'Breathing Difficulty',
    'stroke'    => 'Stroke / Sudden Paralysis',
    'burn'      => 'Severe Burns',
    'poisoning' => 'Poisoning / Overdose',
    'fracture'  => 'Fracture / Bone Injury',
    'other'     => 'Other Critical Condition',
];
$consciousMap = ['yes'=>'Fully Conscious','semi'=>'Semi-Conscious','no'=>'Unconscious'];
$assistMap    = ['yes'=>'Someone is helping','no'=>'Person is alone','medical'=>'Medical professional present'];

$pageTitle  = 'Driver Dashboard';
$useSidebar = false;
$isPublic   = false;
include __DIR__ . '/../../includes/header.php';
?>

<style>
    body { background: #f0f2f8; }
    .page { padding: 28px; max-width: 960px; margin: 0 auto; }

    /* Info row */
    .info-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; margin-bottom: 24px; }
    .info-card { background: #fff; border-radius: 14px; padding: 18px 22px;
        box-shadow: 0 2px 10px rgba(0,0,0,.06); border-left: 4px solid var(--c); }
    .info-label { font-size: 11px; color: #888; font-weight: 600; text-transform: uppercase;
        letter-spacing: .05em; margin-bottom: 4px; }
    .info-val { font-size: 22px; font-weight: 700; color: var(--c); }

    /* Active request card */
    .req-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.08);
        overflow: hidden; margin-bottom: 24px; }
    .req-head { background: linear-gradient(135deg,#e02020,#a50000); color: #fff;
        padding: 18px 26px; display: flex; align-items: center; justify-content: space-between; }
    .req-head-title { font-size: 16px; font-weight: 700; }
    .req-head-time  { font-size: 12px; opacity: .8; }
    .req-body { padding: 24px 26px; }

    /* Progress */
    .progress { display: flex; align-items: flex-start; margin-bottom: 26px; }
    .p-step { flex: 1; text-align: center; position: relative; }
    .p-step::after { content: ''; position: absolute; top: 16px; left: 50%; right: -50%;
        height: 3px; background: #e0e4ef; z-index: 0; }
    .p-step:last-child::after { display: none; }
    .p-step.done::after { background: #1a9e4a; }
    .p-dot { width: 34px; height: 34px; border-radius: 50%; background: #e0e4ef; color: #aaa;
        display: flex; align-items: center; justify-content: center;
        font-size: 14px; font-weight: 700; margin: 0 auto 6px; position: relative; z-index: 1; transition: .3s; }
    .p-step.done   .p-dot { background: #1a9e4a; color: #fff; }
    .p-step.active .p-dot { background: #1a6dff; color: #fff; box-shadow: 0 0 0 5px rgba(26,109,255,.2); }
    .p-label { font-size: 11px; color: #888; font-weight: 500; }
    .p-step.done   .p-label { color: #1a9e4a; font-weight: 600; }
    .p-step.active .p-label { color: #1a6dff; font-weight: 700; }

    /* Detail grid */
    .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 32px; margin-bottom: 24px; }
    .d-label { font-size: 11px; color: #888; font-weight: 600; text-transform: uppercase;
        letter-spacing: .05em; margin-bottom: 3px; }
    .d-val { font-size: 14px; font-weight: 600; color: #1a1a2e; }

    /* Critical banner */
    .critical-banner { background: #fff0f0; border: 2px solid #ff2d2d; border-radius: 10px;
        padding: 10px 16px; font-size: 13px; font-weight: 600; color: #cc0000;
        margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }

    /* Action buttons */
    .action-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
    .btn-action { padding: 13px 26px; border-radius: 10px; border: none;
        font-family: 'DM Sans',sans-serif; font-size: 14px; font-weight: 700;
        color: #fff; cursor: pointer; box-shadow: 0 4px 14px rgba(0,0,0,.2); transition: .2s; }
    .btn-action:hover { opacity: .88; transform: translateY(-1px); }
    .btn-call { padding: 13px 22px; border-radius: 10px; background: #0f1b3d; color: #fff; border: none;
        font-family: 'DM Sans',sans-serif; font-size: 14px; font-weight: 600; cursor: pointer;
        text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: .2s; }
    .btn-call:hover { background: #1a3a7a; }

    /* No request */
    .no-req { background: #fff; border-radius: 16px; padding: 60px 40px; text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,.06); margin-bottom: 24px; }
    .no-req-icon  { font-size: 56px; margin-bottom: 16px; }
    .no-req-title { font-size: 20px; font-weight: 700; color: #1a1a2e; margin-bottom: 8px; }
    .no-req-sub   { font-size: 14px; color: #888; }

    /* History table */
    .hist-card { background: #fff; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,.06); overflow: hidden; }
    .hist-head { padding: 16px 22px; border-bottom: 1px solid #eef0f6; font-size: 15px; font-weight: 700; }
    .hist-card table { width: 100%; border-collapse: collapse; }
    .hist-card th { background: #f7f8fc; text-align: left; padding: 10px 16px;
        font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; }
    .hist-card td { padding: 12px 16px; font-size: 13px; border-top: 1px solid #f0f2f8; }
    .badge-resolved { display: inline-block; padding: 3px 10px; border-radius: 20px;
        font-size: 11px; font-weight: 600; background: #e8f9ee; color: #1a9e4a; }

    /* Toast */
    .toast { position: fixed; bottom: 28px; right: 28px; background: #1a1a2e; color: #fff;
        padding: 13px 22px; border-radius: 10px; font-size: 14px; font-weight: 500;
        box-shadow: 0 8px 24px rgba(0,0,0,.3); display: none; z-index: 999; }

    @media (max-width: 600px) {
        .info-row { grid-template-columns: 1fr; }
        .detail-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="page">

    <!-- Page heading -->
    <div style="margin-bottom:24px">
        <h1 style="font-size:24px;font-weight:700;color:#0f1b3d">🚑 Driver Dashboard</h1>
        <p style="color:#888;font-size:14px;margin-top:4px">
            Welcome, <?= htmlspecialchars($driver['full_name']) ?> &nbsp;·&nbsp;
            <?= htmlspecialchars($driver['ambulance_number'] ?? 'N/A') ?> &nbsp;·&nbsp;
            License: <?= htmlspecialchars($driver['license_number'] ?? 'N/A') ?>
        </p>
    </div>

    <!-- Info row -->
    <div class="info-row">
        <div class="info-card" style="--c:#1a6dff">
            <div class="info-label">Ambulance</div>
            <div class="info-val"><?= htmlspecialchars($driver['ambulance_number'] ?? 'N/A') ?></div>
        </div>
        <div class="info-card" style="--c:<?= $driver['status']==='available'?'#1a9e4a':'#e07000' ?>">
            <div class="info-label">My Status</div>
            <div class="info-val"><?= ucfirst(str_replace('_',' ',$driver['status'])) ?></div>
        </div>
        <div class="info-card" style="--c:#888">
            <div class="info-label">Trips Resolved</div>
            <div class="info-val"><?= $totalResolved ?></div>
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
                <div class="req-head-title">
                    Active Emergency — <?= htmlspecialchars($typeMap[$req['emergency_type']] ?? ucfirst($req['emergency_type'])) ?>
                </div>
                <div class="req-head-time">
                    Dispatched: <?= $req['dispatch_time'] ? date('d M Y, H:i', strtotime($req['dispatch_time'])) : '—' ?>
                </div>
            </div>
            <span style="background:rgba(255,255,255,.2);color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700">
                <?= ucfirst(str_replace('_',' ',$req['status'])) ?>
            </span>
        </div>

        <div class="req-body">
            <?php if ($isUnconscious): ?>
            <div class="critical-banner">CRITICAL — Patient is UNCONSCIOUS. Respond immediately.</div>
            <?php endif; ?>

            <!-- Progress steps -->
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
                    <div class="d-label">Patient Name</div>
                    <div class="d-val"><?= htmlspecialchars($req['patient_name'] ?? '—') ?></div>
                </div>
                <div>
                    <div class="d-label">Emergency Type</div>
                    <div class="d-val"><?= htmlspecialchars($typeMap[$req['emergency_type']] ?? ucfirst($req['emergency_type'])) ?></div>
                </div>
                <div>
                    <div class="d-label">Address</div>
                    <div class="d-val"><?= htmlspecialchars($req['patient_address'] ?? '—') ?></div>
                </div>
                <div>
                    <div class="d-label">Consciousness</div>
                    <div class="d-val"><?= htmlspecialchars($consciousMap[$req['is_conscious']] ?? '—') ?></div>
                </div>
                <div>
                    <div class="d-label">Contact Number</div>
                    <div class="d-val"><?= htmlspecialchars($req['contact_number'] ?? '—') ?></div>
                </div>
                <div>
                    <div class="d-label">Assistance on Site</div>
                    <div class="d-val"><?= htmlspecialchars($assistMap[$req['assistance_on_site'] ?? ''] ?? '—') ?></div>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="action-row">
                <button class="btn-action" style="background:#1a9e4a;font-size:16px;padding:16px 36px;"
                    onclick="confirmFinish(<?= (int)$req['emergency_id'] ?>)">
                  Finish
                </button>
                <a href="tel:<?= preg_replace('/[^\d+]/','',$req['contact_number'] ?? '') ?>" class="btn-call">
                Call Patient
                </a>
                <?php if (!empty($req['patient_address'])): ?>
                <a href="https://maps.google.com/?q=<?= urlencode($req['patient_address']) ?>"
                   target="_blank" class="btn-call" style="background:#1a6dff">
                    🗺 Navigate
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sticky Finish Bar (always visible at bottom when active emergency) -->
    <div style="position:fixed;bottom:0;left:0;right:0;background:#0f1b3d;padding:14px 24px;
                display:flex;align-items:center;justify-content:space-between;z-index:100;
                box-shadow:0 -4px 20px rgba(0,0,0,.3);">
        <div style="color:#fff;font-size:13px;font-weight:600;">
            Active Emergency in Progress
        </div>
        <button class="btn-action" style="background:#1a9e4a;font-size:15px;padding:12px 32px;"
            onclick="confirmFinish(<?= (int)$req['emergency_id'] ?>)">
            Finish Task
        </button>
    </div>
    <div style="height:68px;"></div><!-- spacer -->

    <?php else: ?>
    <?php if ($driver['status'] === 'on_duty'): ?>
    <!-- On duty but request not linked — show manual finish option -->
    <div class="no-req" style="border:2px solid #f59e0b;">
       
        <div class="no-req-title" style="color:#b07800;">You are currently On Duty</div>
        <div class="no-req-sub">Your assignment details could not be loaded. If you have completed your task, click Finish below.</div>
        <div style="margin-top:24px;">
            <button class="btn-action" style="background:#1a9e4a;font-size:16px;padding:14px 40px;"
                onclick="confirmFinishNoId()">
                Finish Task
            </button>
        </div>
    </div>
    <!-- Sticky bar for on_duty with no req -->
    <div style="position:fixed;bottom:0;left:0;right:0;background:#0f1b3d;padding:14px 24px;
                display:flex;align-items:center;justify-content:space-between;z-index:100;
                box-shadow:0 -4px 20px rgba(0,0,0,.3);">
        <div style="color:#fff;font-size:13px;font-weight:600;">You are On Duty</div>
        <button class="btn-action" style="background:#1a9e4a;font-size:15px;padding:12px 32px;"
            onclick="confirmFinishNoId()">
            Finish Task
        </button>
    </div>
    <div style="height:68px;"></div>
    <?php else: ?>
    <!-- No active request, available -->
    <div class="no-req">
      
        <div class="no-req-title">No Active Assignment</div>
        <div class="no-req-sub">You are marked as available. The dispatcher will assign you when an emergency comes in.</div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Trip History -->
    <?php if (!empty($history)): ?>
    <div class="hist-card">
        <div class="hist-head">📋 Resolved Trips</div>
        <table>
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Emergency</th>
                    <th>Address</th>
                    <th>Resolved At</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($h['patient_name'] ?? '—') ?></strong></td>
                    <td><?= htmlspecialchars($typeMap[$h['emergency_type']] ?? ucfirst($h['emergency_type'])) ?></td>
                    <td><?= htmlspecialchars($h['patient_address'] ?? '—') ?></td>
                    <td style="white-space:nowrap;color:#888;font-size:12px">
                        <?= !empty($h['dispatch_time']) ? date('d M Y, H:i', strtotime($h['dispatch_time'])) : '—' ?>
                    </td>
                    <td><span class="badge-resolved">Resolved</span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<div class="toast" id="toast"></div>

<!-- Finish Confirmation Modal -->
<div id="finishOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:300;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:400px;padding:32px 28px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.2);">
        <div style="font-size:48px;margin-bottom:12px;"></div>
        <h2 style="font-size:18px;font-weight:700;color:#0f1b3d;margin-bottom:8px;">Finish Task?</h2>
        <p style="font-size:14px;color:#6b7280;margin-bottom:24px;">Are you sure you have completed this emergency response? This will mark the task as resolved and set you as available.</p>
        <div style="display:flex;gap:12px;justify-content:center;">
            <button onclick="closeFinishModal()" style="padding:11px 28px;border-radius:9px;border:1.5px solid #d0d5e8;background:#fff;font-family:inherit;font-size:14px;font-weight:600;color:#555;cursor:pointer;">Cancel</button>
            <button id="finishConfirmBtn" onclick="doFinish()" style="padding:11px 28px;border-radius:9px;border:none;background:#1a9e4a;color:#fff;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(26,158,74,.3);">Yes, Finish</button>
        </div>
    </div>
</div>

<!-- Auto-refresh every 30 seconds when no active request -->
<?php if (!$req): ?>
<script>setTimeout(() => location.reload(), 30000);</script>
<?php endif; ?>

<script>
let _finishReqId = null;

function confirmFinish(requestId) {
    _finishReqId = requestId;
    const overlay = document.getElementById('finishOverlay');
    overlay.style.display = 'flex';
}
function closeFinishModal() {
    document.getElementById('finishOverlay').style.display = 'none';
    _finishReqId = null;
}

// Close modal on overlay click
document.getElementById('finishOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeFinishModal();
});

function confirmFinishNoId() {
    _finishReqId = 0; // 0 signals finish_duty action
    const overlay = document.getElementById('finishOverlay');
    overlay.style.display = 'flex';
}

async function doFinish() {
    const overlay_btn = document.getElementById('finishConfirmBtn');
    overlay_btn.textContent = 'Finishing…';
    overlay_btn.disabled = true;

    let body = new FormData();
    if (_finishReqId && _finishReqId > 0) {
        body.append('action',     'update_status');
        body.append('status',     'resolved');
        body.append('request_id', _finishReqId);
    } else {
        body.append('action', 'finish_duty');
    }

    const r = await fetch('driver_portal.php', { method: 'POST', body });
    const j = await r.json();
    if (j.ok) {
        closeFinishModal();
        showToast('Task finished — you are now available.');
        setTimeout(() => location.reload(), 1400);
    } else {
        overlay_btn.textContent = 'Yes, Finish';
        overlay_btn.disabled = false;
        alert('Failed to finish: ' + (j.msg || 'Please try again.'));
    }
}

function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.display = 'block';
    setTimeout(() => t.style.display = 'none', 3000);
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>