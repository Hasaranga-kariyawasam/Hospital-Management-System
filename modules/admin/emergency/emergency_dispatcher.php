<?php


session_start();
require_once '../../config/db_config.php';


$dispatcher_id = 1;

// ── Handle AJAX Actions ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action     = $_POST['action'];
    $request_id = (int)($_POST['request_id'] ?? 0);

    // Dispatch ambulance
    if ($action === 'dispatch') {
        $ambulance_id = (int)($_POST['ambulance_id'] ?? 0);
        if (!$ambulance_id || !$request_id) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid data.']);
            exit;
        }
        try {
            // Update request status to dispatched
            $pdo->prepare("
                UPDATE emergency_requests
                SET status='dispatched', ambulance_id=:amb, dispatcher_id=:disp, dispatched_at=NOW()
                WHERE request_id=:id AND status='pending'
            ")->execute([':amb' => $ambulance_id, ':disp' => $dispatcher_id, ':id' => $request_id]);

            // Mark ambulance as dispatched
            $pdo->prepare("
                UPDATE ambulances SET status='dispatched' WHERE ambulance_id=:id
            ")->execute([':id' => $ambulance_id]);

            echo json_encode(['ok' => true]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    // Update status (en_route / arrived / closed)
    if ($action === 'update_status') {
        $new_status = $_POST['new_status'] ?? '';
        $allowed    = ['en_route', 'arrived', 'closed'];
        if (!in_array($new_status, $allowed)) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid status.']);
            exit;
        }
        try {
            $pdo->prepare("
                UPDATE emergency_requests SET status=:s WHERE request_id=:id
            ")->execute([':s' => $new_status, ':id' => $request_id]);

            // If closed, free the ambulance
            if ($new_status === 'closed') {
                $pdo->prepare("
                    UPDATE ambulances a
                    JOIN emergency_requests r ON r.ambulance_id = a.ambulance_id
                    SET a.status = 'available'
                    WHERE r.request_id = :id
                ")->execute([':id' => $request_id]);
            }
            echo json_encode(['ok' => true]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Unknown action.']);
    exit;
}

// ── Fetch Data ─────────────────────────────────────────────
// Filter
$filter_status = $_GET['status'] ?? 'all';

$where = $filter_status !== 'all'
    ? "WHERE er.status = " . $pdo->quote($filter_status)
    : "";

$requests = $pdo->query("
    SELECT er.*, a.vehicle_no, a.driver_name, a.driver_phone
    FROM emergency_requests er
    LEFT JOIN ambulances a ON a.ambulance_id = er.ambulance_id
    $where
    ORDER BY
        FIELD(er.status,'pending','dispatched','en_route','arrived','closed'),
        er.created_at DESC
")->fetchAll();

// Counts
$counts = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status='pending')    AS pending,
        SUM(status='dispatched' OR status='en_route') AS active,
        SUM(status='arrived' OR status='closed')      AS resolved
    FROM emergency_requests
")->fetch();

// Available ambulances
$ambulances = $pdo->query("
    SELECT * FROM ambulances WHERE status='available' ORDER BY vehicle_no
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Dispatcher Panel | Hospital Management System</title>
    <link rel="stylesheet" href="../../includes/emergency.css">
    <style>
        /* Auto-refresh indicator */
        .refresh-bar {
            background: var(--gray-100);
            padding: 7px 28px;
            font-size: 12px;
            color: var(--gray-400);
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid var(--gray-200);
        }
        .refresh-dot {
            width: 8px; height: 8px;
            background: var(--green);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%,100% { opacity:1; } 50% { opacity:.3; }
        }
        .action-group { display:flex; gap:6px; flex-wrap:wrap; }
        .detail-row { font-size:12px; color:var(--gray-400); margin-top:3px; }
    </style>
</head>
<body>

<!-- ── Page Header ─────────────────────────────────────────── -->
<div class="page-header">
    <span class="icon">📡</span>
    <div>
        <h1>Emergency Dispatcher Panel</h1>
        <p>Real-time view of all incoming ambulance requests</p>
    </div>
</div>

<!-- ── Auto-refresh indicator ─────────────────────────────── -->
<div class="refresh-bar">
    <span class="refresh-dot"></span>
    Live — page auto-refreshes every 30 seconds &nbsp;|&nbsp;
    Last updated: <strong id="lastUpdated"><?= date('H:i:s') ?></strong>
    &nbsp;|&nbsp;
    <a href="emergency_dispatcher.php" style="color:var(--blue);text-decoration:none;">🔄 Refresh Now</a>
</div>

<!-- ── Main Content ─────────────────────────────────────────── -->
<div class="container wide">

    <!-- Stats -->
    <div class="stats-bar">
        <div class="stat-card red">
            <div class="num"><?= $counts['pending'] ?></div>
            <div class="lbl">Pending Requests</div>
        </div>
        <div class="stat-card amber">
            <div class="num"><?= $counts['active'] ?></div>
            <div class="lbl">Active / En Route</div>
        </div>
        <div class="stat-card green">
            <div class="num"><?= $counts['resolved'] ?></div>
            <div class="lbl">Resolved Today</div>
        </div>
        <div class="stat-card blue">
            <div class="num"><?= count($ambulances) ?></div>
            <div class="lbl">Available Ambulances</div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="tabs">
        <?php
        $tabs = [
            'all'        => '📋 All',
            'pending'    => '🔴 Pending',
            'dispatched' => '🔵 Dispatched',
            'en_route'   => '🟣 En Route',
            'arrived'    => '🟢 Arrived',
            'closed'     => '⚫ Closed',
        ];
        foreach ($tabs as $val => $label):
            $active = $filter_status === $val ? 'active' : '';
        ?>
        <a href="?status=<?= $val ?>" class="tab-btn <?= $active ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Requests Table -->
    <div class="card" style="padding:0;overflow:hidden;">
        <?php if (empty($requests)): ?>
            <div style="text-align:center;padding:50px;color:var(--gray-400);">
                <div style="font-size:48px;margin-bottom:12px;">✅</div>
                <p>No emergency requests found for this filter.</p>
            </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Requester</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Ambulance</th>
                        <th>Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $r): ?>
                <tr id="row-<?= $r['request_id'] ?>">
                    <!-- Ticket -->
                    <td><strong><?= htmlspecialchars($r['ticket_no']) ?></strong></td>

                    <!-- Requester -->
                    <td>
                        <?= htmlspecialchars($r['requester_name']) ?>
                        <?php if ($r['patient_id']): ?>
                            <div class="detail-row">Patient #<?= $r['patient_id'] ?></div>
                        <?php endif; ?>
                    </td>

                    <!-- Phone — clickable -->
                    <td>
                        <a href="tel:<?= htmlspecialchars($r['phone']) ?>" style="color:var(--blue);font-weight:600;">
                            <?= htmlspecialchars($r['phone']) ?>
                        </a>
                    </td>

                    <!-- GPS Location -->
                    <td>
                        <?php if ($r['gps_lat'] && $r['gps_lng']): ?>
                            <a class="map-link"
                               href="https://www.google.com/maps/search/?api=1&query=<?= $r['gps_lat'] ?>,<?= $r['gps_lng'] ?>"
                               target="_blank">
                                📍 View on Map
                            </a>
                            <div class="detail-row"><?= $r['gps_lat'] ?>, <?= $r['gps_lng'] ?></div>
                        <?php else: ?>
                            <span class="text-muted">No GPS</span>
                        <?php endif; ?>
                    </td>

                    <!-- Description -->
                    <td style="max-width:180px;white-space:normal;">
                        <?= htmlspecialchars(substr($r['description'] ?? '—', 0, 100)) ?>
                        <?= strlen($r['description'] ?? '') > 100 ? '…' : '' ?>
                    </td>

                    <!-- Status Badge -->
                    <td>
                        <span class="badge badge-<?= $r['status'] ?>">
                            <?= str_replace('_', ' ', strtoupper($r['status'])) ?>
                        </span>
                        <?php if ($r['dispatched_at']): ?>
                            <div class="detail-row">Dispatched: <?= date('H:i', strtotime($r['dispatched_at'])) ?></div>
                        <?php endif; ?>
                    </td>

                    <!-- Ambulance -->
                    <td>
                        <?php if ($r['vehicle_no']): ?>
                            <strong><?= htmlspecialchars($r['vehicle_no']) ?></strong>
                            <div class="detail-row"><?= htmlspecialchars($r['driver_name']) ?></div>
                            <div class="detail-row">📞 <?= htmlspecialchars($r['driver_phone']) ?></div>
                        <?php else: ?>
                            <span class="text-muted">Not assigned</span>
                        <?php endif; ?>
                    </td>

                    <!-- Time -->
                    <td>
                        <div><?= date('H:i', strtotime($r['created_at'])) ?></div>
                        <div class="detail-row"><?= date('d M', strtotime($r['created_at'])) ?></div>
                    </td>

                    <!-- Actions -->
                    <td>
                        <div class="action-group">
                        <?php if ($r['status'] === 'pending'): ?>
                            <button class="btn btn-danger btn-sm"
                                    onclick="openDispatchModal(<?= $r['request_id'] ?>, '<?= htmlspecialchars($r['requester_name']) ?>')">
                                🚑 Dispatch
                            </button>

                        <?php elseif ($r['status'] === 'dispatched'): ?>
                            <button class="btn btn-sm" style="background:#7c3aed;color:#fff;"
                                    onclick="updateStatus(<?= $r['request_id'] ?>, 'en_route')">
                                ▶ En Route
                            </button>

                        <?php elseif ($r['status'] === 'en_route'): ?>
                            <button class="btn btn-success btn-sm"
                                    onclick="updateStatus(<?= $r['request_id'] ?>, 'arrived')">
                                ✅ Arrived
                            </button>

                        <?php elseif ($r['status'] === 'arrived'): ?>
                            <button class="btn btn-sm" style="background:var(--gray-400);color:#fff;"
                                    onclick="updateStatus(<?= $r['request_id'] ?>, 'closed')">
                                🔒 Close
                            </button>

                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /container -->

<!-- ── Dispatch Modal ─────────────────────────────────────── -->
<div class="modal-overlay" id="dispatchModal">
    <div class="modal">
        <h3>🚑 Dispatch Ambulance</h3>
        <p style="font-size:14px;color:var(--gray-600);margin-bottom:16px;">
            Assigning to request: <strong id="modalPatientName"></strong>
        </p>
        <input type="hidden" id="modalRequestId">

        <?php if (empty($ambulances)): ?>
            <div class="alert alert-warning">
                <span>⚠️</span> No ambulances are currently available. Mark an ambulance as available first.
            </div>
        <?php else: ?>
        <div class="form-group">
            <label for="selectAmbulance">Select Available Ambulance</label>
            <select id="selectAmbulance">
                <?php foreach ($ambulances as $amb): ?>
                <option value="<?= $amb['ambulance_id'] ?>">
                    <?= htmlspecialchars($amb['vehicle_no']) ?> — Driver: <?= htmlspecialchars($amb['driver_name']) ?>
                    (<?= htmlspecialchars($amb['driver_phone']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="modal-actions">
            <button class="btn btn-sm" style="background:var(--gray-100);color:var(--gray-800);"
                    onclick="closeModal()">Cancel</button>
            <?php if (!empty($ambulances)): ?>
            <button class="btn btn-danger btn-sm" onclick="confirmDispatch()">
                🚑 Confirm Dispatch
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── JavaScript ─────────────────────────────────────────── -->
<script>
// ── Modal control ──────────────────────────────────────────
function openDispatchModal(requestId, patientName) {
    document.getElementById('modalRequestId').value   = requestId;
    document.getElementById('modalPatientName').textContent = patientName;
    document.getElementById('dispatchModal').classList.add('open');
}
function closeModal() {
    document.getElementById('dispatchModal').classList.remove('open');
}
// Close on overlay click
document.getElementById('dispatchModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// ── Confirm dispatch ───────────────────────────────────────
function confirmDispatch() {
    const requestId   = document.getElementById('modalRequestId').value;
    const ambulanceId = document.getElementById('selectAmbulance')?.value;
    if (!ambulanceId) return;

    const fd = new FormData();
    fd.append('action',       'dispatch');
    fd.append('request_id',   requestId);
    fd.append('ambulance_id', ambulanceId);

    fetch('emergency_dispatcher.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                closeModal();
                showToast('Ambulance dispatched! Emergency ward notified. ✅', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Error: ' + (data.msg || 'Could not dispatch.'), 'danger');
            }
        })
        .catch(() => showToast('Network error. Please try again.', 'danger'));
}

// ── Update status (en_route / arrived / closed) ────────────
function updateStatus(requestId, newStatus) {
    const labels = {
        en_route: 'Mark as En Route?',
        arrived:  'Mark as Arrived?',
        closed:   'Close this request? The ambulance will be marked as available again.',
    };
    if (!confirm(labels[newStatus] || 'Update status?')) return;

    const fd = new FormData();
    fd.append('action',     'update_status');
    fd.append('request_id', requestId);
    fd.append('new_status', newStatus);

    fetch('emergency_dispatcher.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                showToast('Status updated to: ' + newStatus.replace('_',' ').toUpperCase(), 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast('Error: ' + (data.msg || 'Could not update.'), 'danger');
            }
        })
        .catch(() => showToast('Network error.', 'danger'));
}

// ── Toast notification ─────────────────────────────────────
function showToast(msg, type) {
    const t = document.createElement('div');
    t.textContent = msg;
    Object.assign(t.style, {
        position:     'fixed',
        bottom:       '24px',
        right:        '24px',
        padding:      '14px 22px',
        borderRadius: '8px',
        background:   type === 'success' ? '#16a34a' : '#dc2626',
        color:        '#fff',
        fontSize:     '14px',
        fontWeight:   '600',
        boxShadow:    '0 4px 12px rgba(0,0,0,.2)',
        zIndex:       9999,
        transition:   'opacity .4s',
    });
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 3000);
}

// ── Auto-refresh every 30 seconds ─────────────────────────
let countdown = 30;
setInterval(() => {
    countdown--;
    if (countdown <= 0) location.reload();
}, 1000);
</script>

</body>
</html>