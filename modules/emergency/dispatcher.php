<?php
// dispatcher.php — place in project root
session_start();

require_once '../../config/db_config.php';

// ── AJAX handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {

        case 'add_driver':
            $pass = trim($_POST['password']);
            if (!$pass) { echo json_encode(['ok'=>false,'msg'=>'Password required']); exit; }
            $stmt = $pdo->prepare("INSERT INTO drivers
                (full_name, nic, phone, license_no, ambulance_no, status, password)
                VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([
                trim($_POST['full_name']),
                trim($_POST['nic']),
                trim($_POST['phone']),
                trim($_POST['license_no']),
                trim($_POST['ambulance_no']),
                $_POST['status'],
                password_hash($pass, PASSWORD_DEFAULT)
            ]);
            echo json_encode(['ok' => true]);
            break;

        case 'edit_driver':
            $fields = [
                trim($_POST['full_name']), trim($_POST['nic']),
                trim($_POST['phone']),     trim($_POST['license_no']),
                trim($_POST['ambulance_no']), $_POST['status'],
                (int)$_POST['driver_id']
            ];
            $pdo->prepare("UPDATE drivers SET full_name=?,nic=?,phone=?,license_no=?,ambulance_no=?,status=? WHERE driver_id=?")
                ->execute($fields);
            // Update password only if provided
            if (!empty(trim($_POST['password']))) {
                $pdo->prepare("UPDATE drivers SET password=? WHERE driver_id=?")
                    ->execute([password_hash(trim($_POST['password']), PASSWORD_DEFAULT), (int)$_POST['driver_id']]);
            }
            echo json_encode(['ok' => true]);
            break;

        case 'delete_driver':
            $pdo->prepare("DELETE FROM drivers WHERE driver_id=?")->execute([(int)$_POST['driver_id']]);
            echo json_encode(['ok' => true]);
            break;

        case 'assign_driver':
            $rid = (int)$_POST['request_id'];
            $did = (int)$_POST['driver_id'];
            $pdo->prepare("UPDATE emergency_requests SET driver_id=?, status='dispatched', dispatched_at=NOW() WHERE emergency_id=?")
                ->execute([$did, $rid]);
            $pdo->prepare("UPDATE drivers SET status='on_duty' WHERE driver_id=?")
                ->execute([$did]);
            echo json_encode(['ok' => true]);
            break;

        case 'cancel_request':
            $rid = (int)$_POST['id'];
            $row = $pdo->prepare("SELECT driver_id FROM emergency_requests WHERE emergency_id=?");
            $row->execute([$rid]);
            $r = $row->fetch();
            if (!empty($r['driver_id'])) {
                $pdo->prepare("UPDATE drivers SET status='available' WHERE driver_id=?")->execute([$r['driver_id']]);
            }
            $pdo->prepare("UPDATE emergency_requests SET status='cancelled', driver_id=NULL WHERE emergency_id=?")
                ->execute([$rid]);
            echo json_encode(['ok' => true]);
            break;
    }
    exit;
}

// ── Fetch data ────────────────────────────────────────────────
$drivers   = $pdo->query("SELECT * FROM drivers ORDER BY created_at DESC")->fetchAll();
$available = array_filter($drivers, fn($d) => $d['status'] === 'available');

$requests = $pdo->query("
    SELECT er.*, d.full_name AS driver_name, d.ambulance_no AS driver_ambulance
    FROM emergency_requests er
    LEFT JOIN drivers d ON er.driver_id = d.driver_id
    ORDER BY er.submitted_at DESC
")->fetchAll();

$pending  = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
$onDuty   = count(array_filter($drivers,  fn($d) => $d['status'] === 'on_duty'));
$avail    = count(array_filter($drivers,  fn($d) => $d['status'] === 'available'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatcher Dashboard — MediCare</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'DM Sans',sans-serif;background:#f0f2f8;color:#1a1a2e;min-height:100vh}

        /* ── Topbar ── */
        .topbar{background:linear-gradient(135deg,#0f1b3d,#1a3a7a);color:#fff;padding:0 32px;height:64px;
            display:flex;align-items:center;justify-content:space-between;
            position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,0.3)}
        .tb-left{display:flex;align-items:center;gap:14px}
        .tb-logo{width:38px;height:38px;border-radius:10px;background:#1a6dff;
            display:flex;align-items:center;justify-content:center;font-weight:800;font-size:20px}
        .tb-title{font-size:16px;font-weight:700}
        .tb-sub{font-size:11px;opacity:.6}
        .tb-badge{background:#ff2d2d;color:#fff;font-size:12px;font-weight:700;padding:4px 14px;border-radius:20px}
        .tb-link{color:rgba(255,255,255,.75);font-size:13px;text-decoration:none;padding:8px 16px;
            border:1px solid rgba(255,255,255,.3);border-radius:8px;transition:.2s}
        .tb-link:hover{background:rgba(255,255,255,.1);color:#fff}

        /* ── Page ── */
        .page{padding:28px 32px;max-width:1440px;margin:0 auto}

        /* ── Stats ── */
        .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
        .stat{background:#fff;border-radius:14px;padding:20px 24px;
            box-shadow:0 2px 10px rgba(0,0,0,.06);border-left:4px solid var(--c)}
        .stat-label{font-size:12px;color:#888;font-weight:500;margin-bottom:6px}
        .stat-val{font-size:30px;font-weight:700;color:var(--c)}

        /* ── Card ── */
        .card{background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.06);
            overflow:hidden;margin-bottom:28px}
        .card-head{padding:18px 24px;border-bottom:1px solid #eef0f6;
            display:flex;align-items:center;justify-content:space-between}
        .card-title{font-size:15px;font-weight:700}

        /* ── Table ── */
        table{width:100%;border-collapse:collapse}
        th{background:#f7f8fc;text-align:left;padding:11px 16px;font-size:11px;
            font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap}
        td{padding:12px 16px;font-size:13px;border-top:1px solid #f0f2f8;vertical-align:middle}
        tr:hover td{background:#fafbff}
        .empty{text-align:center;padding:48px;color:#bbb;font-size:14px}

        /* ── Badges ── */
        .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap}
        .badge-available {background:#e8f9ee;color:#1a9e4a}
        .badge-on_duty   {background:#fff3cd;color:#b07800}
        .badge-off_duty  {background:#f0f2f8;color:#888}
        .badge-pending   {background:#fff3cd;color:#b07800}
        .badge-dispatched{background:#e0eaff;color:#1a6dff}
        .badge-en_route  {background:#fff0e0;color:#e07000}
        .badge-arrived   {background:#e0f0ff;color:#0070c0}
        .badge-resolved  {background:#e8f9ee;color:#1a9e4a}
        .badge-cancelled {background:#ffeaea;color:#cc0000}

        /* ── Buttons ── */
        .btn{padding:7px 14px;border-radius:8px;border:none;font-family:'DM Sans',sans-serif;
            font-size:12px;font-weight:600;cursor:pointer;transition:.15s}
        .btn:hover{transform:translateY(-1px);opacity:.88}
        .btn-blue   {background:#1a6dff;color:#fff}
        .btn-red    {background:#e02020;color:#fff}
        .btn-green  {background:#1a9e4a;color:#fff}
        .btn-outline{background:#fff;color:#1a6dff;border:1.5px solid #1a6dff}
        .btn-sm{padding:5px 10px;font-size:11px}
        .btn-add{background:#1a6dff;color:#fff;padding:9px 18px;border-radius:9px;border:none;
            font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;cursor:pointer;
            display:flex;align-items:center;gap:6px;transition:.2s}
        .btn-add:hover{background:#0e52c1}

        /* ── Assign row ── */
        .assign-wrap{display:flex;gap:6px;align-items:center}
        .assign-select{padding:5px 10px;border:1.5px solid #d0d5e8;border-radius:7px;
            font-family:'DM Sans',sans-serif;font-size:12px;outline:none;cursor:pointer;max-width:200px}

        /* ── Modal ── */
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;
            align-items:center;justify-content:center}
        .overlay.open{display:flex}
        .modal{background:#fff;border-radius:16px;width:100%;max-width:500px;
            padding:28px 32px;position:relative;animation:up .28s ease;max-height:90vh;overflow-y:auto}
        @keyframes up{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        .modal-title{font-size:17px;font-weight:700;margin-bottom:20px}
        .modal-x{position:absolute;top:14px;right:16px;background:none;border:none;
            font-size:20px;color:#888;cursor:pointer}
        .fg{margin-bottom:14px}
        .fl{display:block;font-size:13px;font-weight:600;color:#1a1a2e;margin-bottom:6px}
        .fi,.fs{width:100%;padding:10px 14px;border:1.5px solid #d0d5e8;border-radius:9px;
            font-family:'DM Sans',sans-serif;font-size:13px;outline:none;box-sizing:border-box;transition:.2s}
        .fi:focus,.fs:focus{border-color:#1a6dff}
        .fg-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .modal-foot{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}
    </style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
    <div class="tb-left">
        <div class="tb-logo">M</div>
        <div>
            <div class="tb-title">Dispatcher Dashboard</div>
            <div class="tb-sub">MediCare General Hospital — Emergency Management</div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:12px">
        <?php if($pending > 0): ?>
        <span class="tb-badge">🔴 <?= $pending ?> Pending</span>
        <?php endif; ?>

    </div>
</div>

<div class="page">

    <!-- Stats -->
    <div class="stats">
        <div class="stat" style="--c:#1a6dff">
            <div class="stat-label">Total Drivers</div>
            <div class="stat-val"><?= count($drivers) ?></div>
        </div>
        <div class="stat" style="--c:#1a9e4a">
            <div class="stat-label">Available</div>
            <div class="stat-val"><?= $avail ?></div>
        </div>
        <div class="stat" style="--c:#e07000">
            <div class="stat-label">On Duty</div>
            <div class="stat-val"><?= $onDuty ?></div>
        </div>
        <div class="stat" style="--c:#ff2d2d">
            <div class="stat-label">Pending Requests</div>
            <div class="stat-val"><?= $pending ?></div>
        </div>
    </div>

    <!-- Drivers Table -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">🚑 Drivers</div>
            <button class="btn-add" onclick="openModal()">+ Add Driver</button>
        </div>
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Name</th><th>NIC</th><th>Phone</th>
                    <th>License</th><th>Ambulance</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($drivers)): ?>
                <tr><td colspan="8" class="empty">No drivers yet. Click "Add Driver" to get started.</td></tr>
            <?php else: foreach ($drivers as $i => $d): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($d['full_name']) ?></strong></td>
                    <td><?= htmlspecialchars($d['nic']) ?></td>
                    <td><?= htmlspecialchars($d['phone']) ?></td>
                    <td><?= htmlspecialchars($d['license_no']) ?></td>
                    <td><?= htmlspecialchars($d['ambulance_no']) ?></td>
                    <td><span class="badge badge-<?= $d['status'] ?>"><?= ucfirst(str_replace('_', ' ', $d['status'])) ?></span></td>
                    <td style="display:flex;gap:6px">
                        <button class="btn btn-outline btn-sm" onclick='editDriver(<?= json_encode($d) ?>)'>Edit</button>
                        <button class="btn btn-red btn-sm"     onclick="delDriver(<?= $d['id'] ?>,'<?= htmlspecialchars($d['full_name']) ?>')">Delete</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Emergency Requests Table -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">🆘 Emergency Requests</div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Patient</th><th>Address</th><th>Contact</th>
                    <th>Emergency</th><th>Conscious</th><th>Assistance</th>
                    <th>Status</th><th>Assigned Driver</th><th>Time</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($requests)): ?>
                <tr><td colspan="11" class="empty">No emergency requests yet.</td></tr>
            <?php else: foreach ($requests as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($r['patient_name'] ?? '—') ?></strong></td>
                    <td style="max-width:160px"><?= htmlspecialchars($r['patient_address'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($r['contact_number']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($r['emergency_type'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars(ucfirst($r['is_conscious'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars(ucfirst($r['assistance_on_site'] ?? '—')) ?></td>
                    <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst(str_replace('_', ' ', $r['status'])) ?></span></td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                            <div class="assign-wrap">
                                <select class="assign-select" id="sel-<?= $r['emergency_id'] ?>">
                                    <option value="">-- Select --</option>
                                    <?php foreach ($available as $av): ?>
                                        <option value="<?= $av['id'] ?>">
                                            <?= htmlspecialchars($av['full_name']) ?> (<?= $av['ambulance_no'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-green btn-sm" onclick="assignDriver(<?= $r['emergency_id'] ?>)">Assign</button>
                            </div>
                        <?php elseif (!empty($r['driver_name'])): ?>
                            <span>👤 <strong><?= htmlspecialchars($r['driver_name']) ?></strong><br>
                            <small style="color:#888"><?= htmlspecialchars($r['driver_ambulance'] ?? '') ?></small></span>
                        <?php else: ?>
                            <span style="color:#aaa">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;font-size:11px;color:#888">
                        <?= date('d M Y', strtotime($r['submitted_at'])) ?><br>
                        <?= date('H:i', strtotime($r['submitted_at'])) ?>
                    </td>
                    <td>
                        <?php if (!in_array($r['status'], ['resolved', 'cancelled'])): ?>
                            <button class="btn btn-red btn-sm" onclick="cancelReq(<?= $r['emergency_id'] ?>)">Cancel</button>
                        <?php else: ?>
                            <span style="color:#aaa;font-size:11px">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</div><!-- /.page -->

<!-- Add / Edit Driver Modal -->
<div class="overlay" id="driverOverlay">
    <div class="modal">
        <button class="modal-x" onclick="closeModal()">✕</button>
        <div class="modal-title" id="modalTitle">Add Driver</div>
        <input type="hidden" id="dId">

        <div class="fg-grid">
            <div class="fg"><label class="fl">Full Name</label><input class="fi" id="dName"    placeholder="e.g. Kamal Perera"></div>
            <div class="fg"><label class="fl">NIC</label>      <input class="fi" id="dNic"     placeholder="e.g. 901234567V"></div>
            <div class="fg"><label class="fl">Phone</label>    <input class="fi" id="dPhone"   placeholder="+94 77 ..."></div>
            <div class="fg"><label class="fl">License No.</label><input class="fi" id="dLicense" placeholder="e.g. B1234567"></div>
            <div class="fg"><label class="fl">Ambulance No.</label><input class="fi" id="dAmb"  placeholder="e.g. AMB-001"></div>
            <div class="fg">
                <label class="fl">Status</label>
                <select class="fs" id="dStatus">
                    <option value="available">Available</option>
                    <option value="on_duty">On Duty</option>
                    <option value="off_duty">Off Duty</option>
                </select>
            </div>
        </div>
        <div class="fg">
            <label class="fl">Password <span style="color:#aaa;font-size:11px" id="passNote">(required)</span></label>
            <input class="fi" id="dPass" type="password" placeholder="Set login password">
        </div>

        <div class="modal-foot">
            <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
            <button class="btn btn-blue"    onclick="saveDriver()">Save Driver</button>
        </div>
    </div>
</div>

<script>
// ── Modal ──────────────────────────────────────────────────────
function openModal(d = null) {
    document.getElementById('modalTitle').textContent = d ? 'Edit Driver' : 'Add Driver';
    document.getElementById('passNote').textContent   = d ? '(leave blank to keep existing)' : '(required)';
    document.getElementById('dId').value      = d ? d.driver_id : '';
    document.getElementById('dName').value    = d ? d.full_name : '';
    document.getElementById('dNic').value     = d ? d.nic       : '';
    document.getElementById('dPhone').value   = d ? d.phone     : '';
    document.getElementById('dLicense').value = d ? d.license_no  : '';
    document.getElementById('dAmb').value     = d ? d.ambulance_no: '';
    document.getElementById('dStatus').value  = d ? d.status    : 'available';
    document.getElementById('dPass').value    = '';
    document.getElementById('driverOverlay').classList.add('open');
}
function closeModal() { document.getElementById('driverOverlay').classList.remove('open'); }
function editDriver(d) { openModal(d); }

async function saveDriver() {
    const id   = document.getElementById('dId').value;
    const body = new FormData();
    body.append('action',       id ? 'edit_driver' : 'add_driver');
    body.append('driver_id',    id);
    body.append('full_name',    document.getElementById('dName').value.trim());
    body.append('nic',          document.getElementById('dNic').value.trim());
    body.append('phone',        document.getElementById('dPhone').value.trim());
    body.append('license_no',   document.getElementById('dLicense').value.trim());
    body.append('ambulance_no', document.getElementById('dAmb').value.trim());
    body.append('status',       document.getElementById('dStatus').value);
    body.append('password',     document.getElementById('dPass').value);
    const r = await fetch('dispatcher.php', { method: 'POST', body });
    const j = await r.json();
    if (j.ok) { closeModal(); location.reload(); }
    else alert(j.msg || 'Something went wrong.');
}

async function delDriver(id, name) {
    if (!confirm(`Delete driver "${name}"? This cannot be undone.`)) return;
    const body = new FormData();
    body.append('action', 'delete_driver');
    body.append('driver_id', id);
    const r = await fetch('dispatcher.php', { method: 'POST', body });
    const j = await r.json();
    if (j.ok) location.reload();
}

// ── Assign ─────────────────────────────────────────────────────
async function assignDriver(reqId) {
    const driverId = document.getElementById('sel-' + reqId).value;
    if (!driverId) { alert('Please select a driver first.'); return; }
    const body = new FormData();
    body.append('action',     'assign_driver');
    body.append('request_id', reqId);
    body.append('driver_id',  driverId);
    const r = await fetch('dispatcher.php', { method: 'POST', body });
    const j = await r.json();
    if (j.ok) location.reload();
}

// ── Cancel ─────────────────────────────────────────────────────
async function cancelReq(id) {
    if (!confirm('Cancel this emergency request?')) return;
    const body = new FormData();
    body.append('action', 'cancel_request');
    body.append('driver_id', id);
    const r = await fetch('dispatcher.php', { method: 'POST', body });
    const j = await r.json();
    if (j.ok) location.reload();
}

// Close on backdrop click
document.getElementById('driverOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>