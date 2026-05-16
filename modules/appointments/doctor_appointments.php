<?php
declare(strict_types=1);
require_once '../../includes/session_check.php';
require_once '../../config/db_config.php';

if (($_SESSION['role'] ?? '') !== 'doctor') {
    header('Location: /Web/Hospital-Management-System/login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

$stmtDoc = $pdo->prepare(
    "SELECT d.doctor_id, u.full_name, d.specialization
     FROM doctors d JOIN users u ON u.user_id = d.user_id
     WHERE d.user_id = :uid LIMIT 1"
);
$stmtDoc->execute([':uid' => $userId]);
$doc = $stmtDoc->fetch();
$doctorId   = (int)($doc['doctor_id'] ?? 0);
$doctorName = $doc['full_name'] ?? 'Doctor';
$spec       = $doc['specialization'] ?? '';

if ($doctorId === 0) die('<p style="padding:40px;color:red;">Doctor profile not found.</p>');

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['appointment_id'])) {
    $apptId = (int)$_POST['appointment_id'];
    $action = $_POST['action'];
    if (in_array($action, ['confirmed','completed','cancelled'])) {
        $upd = $pdo->prepare(
            "UPDATE appointments SET status=:st WHERE appointment_id=:id AND doctor_id=:did"
        );
        $upd->execute([':st' => $action, ':id' => $apptId, ':did' => $doctorId]);
    }
    $qs = http_build_query(array_filter(['date' => $_GET['date'] ?? '', 'status' => $_GET['status'] ?? '']));
    header('Location: doctor_appointments.php' . ($qs ? "?$qs" : ''));
    exit();
}

$filterDate   = trim($_GET['date']   ?? date('Y-m-d'));
$filterStatus = trim($_GET['status'] ?? '');

$where  = "a.doctor_id = :did";
$params = [':did' => $doctorId];
if ($filterDate !== '')   { $where .= " AND a.appt_date = :dt";  $params[':dt'] = $filterDate; }
if ($filterStatus !== '') { $where .= " AND a.status = :st";     $params[':st'] = $filterStatus; }

$stmt = $pdo->prepare(
    "SELECT a.appointment_id, a.ref_number, a.appt_date, a.appt_time,
            a.status, a.source, a.notes,
            u.full_name AS patient_name,
            p.gender, p.dob, p.phone
     FROM appointments a
     JOIN patients p ON p.patient_id = a.patient_id
     JOIN users u ON u.user_id = p.user_id
     WHERE $where
     ORDER BY a.appt_date ASC, a.appt_time ASC"
);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$statsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
        SUM(status='pending') AS pending,
        SUM(status='confirmed') AS confirmed,
        SUM(status='completed') AS completed
     FROM appointments WHERE doctor_id=:did AND appt_date=CURDATE()"
);
$statsStmt->execute([':did' => $doctorId]);
$stats = $statsStmt->fetch();

$statusConfig = [
    'pending'   => ['bg'=>'var(--warning-light)', 'color'=>'var(--warning)', 'label'=>'⏳ Pending'],
    'confirmed' => ['bg'=>'var(--success-light)', 'color'=>'var(--success)', 'label'=>'✅ Confirmed'],
    'completed' => ['bg'=>'#e0e7ff',              'color'=>'#4f46e5',       'label'=>'🏁 Completed'],
    'cancelled' => ['bg'=>'#f1f5f9',              'color'=>'#94a3b8',       'label'=>'❌ Cancelled'],
];

$pageTitle  = 'My Appointments';
$useSidebar = true;
$isPublic   = false;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">

<div class="page-header">
    <div class="page-header-title">
        <h2>My Appointments</h2>
        <p>Dr. <?= htmlspecialchars($doctorName) ?> &bull; <?= htmlspecialchars($spec) ?> &mdash; <?= date('l, d F Y') ?></p>
    </div>
    <a href="appointments.php" class="btn btn-secondary">Set My Schedule</a>
</div>

<!-- Today Stats -->
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
    <?php
    $statItems = [
        ['icon'=>'','label'=>'Today Total', 'val'=>(int)$stats['total'],     'cls'=>'blue'],
        ['icon'=>'','label'=>'Pending',      'val'=>(int)$stats['pending'],   'cls'=>'yellow'],
        ['icon'=>'','label'=>'Confirmed',    'val'=>(int)$stats['confirmed'], 'cls'=>'blue'],
        ['icon'=>'','label'=>'Completed',    'val'=>(int)$stats['completed'], 'cls'=>'green'],
    ];
    foreach ($statItems as $si):
    ?>
    <div class="stat-card">
        <div class="stat-icon <?= $si['cls'] ?>"><?= $si['icon'] ?></div>
        <div><span class="stat-label"><?= $si['label'] ?></span><span class="stat-value"><?= $si['val'] ?></span></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card" style="padding:18px 24px;margin-bottom:20px;">
<form method="GET" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;">
    <div>
        <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px;">Date</label>
        <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>"
               style="padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:var(--font-body);">
    </div>
    <div>
        <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px;">Status</label>
        <select name="status" style="padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:var(--font-body);">
            <option value="">— All —</option>
            <?php foreach (['pending','confirmed','completed','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary" style="padding:9px 20px;">Filter</button>
    <a href="doctor_appointments.php" class="btn btn-secondary" style="padding:9px 20px;">Reset</a>
    <a href="doctor_appointments.php?date=<?= date('Y-m-d') ?>" class="btn btn-secondary" style="padding:9px 16px;">Today</a>
</form>
</div>

<!-- Table -->
<div class="card" style="padding:0;overflow:hidden;">
<div style="padding:18px 24px;border-bottom:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;">Appointments
        <?php if ($filterDate): ?>
        <span style="font-size:13px;font-weight:400;color:var(--muted);margin-left:8px;"><?= date('d M Y', strtotime($filterDate)) ?></span>
        <?php endif; ?>
    </h3>
    <span style="font-size:13px;color:var(--muted);"><?= count($appointments) ?> record(s)</span>
</div>

<?php if (empty($appointments)): ?>
<div style="padding:60px;text-align:center;color:var(--muted);">
    <div style="font-size:48px;margin-bottom:12px;"></div>
    <p>No appointments found.</p>
    <a href="doctor_appointments.php" style="color:var(--accent);">Show all →</a>
</div>
<?php else: ?>
<div style="overflow-x:auto;">
<table style="width:100%;border-collapse:collapse;font-size:13px;">
<thead>
<tr style="background:var(--bg);">
    <?php $th = 'padding:11px 16px;text-align:left;font-weight:600;color:var(--muted);font-size:11px;letter-spacing:.5px;text-transform:uppercase;border-bottom:1px solid var(--border-light);'; ?>
    <th style="<?= $th ?>">Ref #</th>
    <th style="<?= $th ?>">Patient</th>
    <th style="<?= $th ?>">Date &amp; Time</th>
    <th style="<?= $th ?>">Source</th>
    <th style="<?= $th ?>">Status</th>
    <th style="<?= $th ?>">Notes</th>
    <th style="<?= $th ?>">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($appointments as $a):
    $sc  = $statusConfig[$a['status']] ?? ['bg'=>'#f1f5f9','color'=>'#94a3b8','label'=>ucfirst($a['status'])];
    $age = $a['dob'] ? (int)((time()-strtotime($a['dob']))/31536000).' yrs' : '—';
    $td  = 'padding:13px 16px;border-bottom:1px solid var(--border-light);';
?>
<tr onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
    <td style="<?= $td ?> font-weight:700;color:var(--accent);"><?= htmlspecialchars($a['ref_number']) ?></td>
    <td style="<?= $td ?>">
        <div style="font-weight:600;color:var(--text);"><?= htmlspecialchars($a['patient_name']) ?></div>
        <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars(ucfirst($a['gender']??'')) ?> · <?= $age ?> · <?= htmlspecialchars($a['phone']??'') ?></div>
    </td>
    <td style="<?= $td ?>">
        <div style="font-weight:600;"><?= date('d M Y', strtotime($a['appt_date'])) ?></div>
        <div style="font-size:13px;color:var(--accent);font-weight:700;"><?= substr($a['appt_time'],0,5) ?></div>
    </td>
    <td style="<?= $td ?>">
        <?php if ($a['source']==='online'): ?>
        <span style="background:#e0f2fe;color:#0284c7;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">Online</span>
        <?php else: ?>
        <span style="background:#fef3c7;color:#d97706;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">OPD</span>
        <?php endif; ?>
    </td>
    <td style="<?= $td ?>">
        <span style="padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;
              background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;">
            <?= $sc['label'] ?>
        </span>
    </td>
    <td style="<?= $td ?> max-width:160px;">
        <span style="font-size:12px;color:var(--muted);">
            <?= $a['notes'] ? htmlspecialchars(substr($a['notes'],0,55)).(strlen($a['notes'])>55?'…':'') : '—' ?>
        </span>
    </td>
    <td style="<?= $td ?>">
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <?php $bsm = 'padding:5px 10px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;font-family:var(--font-body);'; ?>
        <?php if ($a['status']==='pending'): ?>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="appointment_id" value="<?= $a['appointment_id'] ?>">
                <input type="hidden" name="action" value="confirmed">
                <?php if ($filterDate) echo '<input type="hidden" name="date" value="'.htmlspecialchars($filterDate).'">'; ?>
                <button type="submit" style="<?= $bsm ?> background:var(--success-light);color:var(--success);border:1px solid #a7f3d0;">Confirm</button>
            </form>
        <?php endif; ?>
        <?php if (in_array($a['status'],['pending','confirmed'])): ?>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="appointment_id" value="<?= $a['appointment_id'] ?>">
                <input type="hidden" name="action" value="completed">
                <?php if ($filterDate) echo '<input type="hidden" name="date" value="'.htmlspecialchars($filterDate).'">'; ?>
                <button type="submit" style="<?= $bsm ?> background:#e0e7ff;color:#4f46e5;border:1px solid #c7d2fe;">Complete</button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this appointment?')">
                <input type="hidden" name="appointment_id" value="<?= $a['appointment_id'] ?>">
                <input type="hidden" name="action" value="cancelled">
                <?php if ($filterDate) echo '<input type="hidden" name="date" value="'.htmlspecialchars($filterDate).'">'; ?>
                <button type="submit" style="<?= $bsm ?> background:var(--danger-light);color:var(--danger);border:1px solid #fca5a5;">Cancel</button>
            </form>
        <?php else: ?>
            <span style="font-size:12px;color:var(--muted);">—</span>
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

</main>
<?php include '../../includes/footer.php'; ?>
