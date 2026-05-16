<?php
declare(strict_types=1);
require_once '../../includes/session_check.php';
require_once '../../config/db_config.php';

if (($_SESSION['role'] ?? '') !== 'patient') {
    header('Location: /Web/Hospital-Management-System/login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

// Get patient_id
$stmtPat = $pdo->prepare("SELECT patient_id, full_name FROM patients p JOIN users u ON u.user_id=p.user_id WHERE p.user_id=:uid LIMIT 1");
$stmtPat->execute([':uid' => $userId]);
$patRow    = $stmtPat->fetch();
$patientId = (int)($patRow['patient_id'] ?? 0);
$patName   = $patRow['full_name'] ?? 'Patient';

if ($patientId === 0) die('<p style="padding:40px;color:red;">Patient profile not found.</p>');

// Handle cancel / reschedule actions
$flashMsg  = '';
$flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apptId = (int)($_POST['appointment_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'cancel' && $apptId > 0) {
        // Only cancel own pending/confirmed appointments
        $upd = $pdo->prepare(
            "UPDATE appointments SET status='cancelled'
             WHERE appointment_id=:id AND patient_id=:pid AND status IN ('pending','confirmed')"
        );
        $upd->execute([':id' => $apptId, ':pid' => $patientId]);
        $flashMsg  = 'Appointment cancelled successfully.';
        $flashType = 'warning';
    }

    if ($action === 'reschedule' && $apptId > 0) {
        $newDate = trim($_POST['new_date'] ?? '');
        $newTime = trim($_POST['new_time'] ?? '');
        $newDoc  = (int)($_POST['new_doctor_id'] ?? 0);

        if ($newDate && $newTime && $newDoc) {
            // Check new slot not taken
            $chk = $pdo->prepare(
                "SELECT appointment_id FROM appointments
                 WHERE doctor_id=:did AND appt_date=:dt AND appt_time=:tm
                   AND status NOT IN ('cancelled') AND appointment_id != :aid LIMIT 1"
            );
            $chk->execute([':did'=>$newDoc,':dt'=>$newDate,':tm'=>$newTime,':aid'=>$apptId]);
            if ($chk->fetch()) {
                $flashMsg  = 'That slot is already taken. Please choose another.';
                $flashType = 'error';
            } else {
                $upd = $pdo->prepare(
                    "UPDATE appointments SET doctor_id=:did, appt_date=:dt, appt_time=:tm, status='pending'
                     WHERE appointment_id=:id AND patient_id=:pid AND status IN ('pending','confirmed')"
                );
                $upd->execute([':did'=>$newDoc,':dt'=>$newDate,':tm'=>$newTime,':id'=>$apptId,':pid'=>$patientId]);
                $flashMsg  = 'Appointment rescheduled successfully.';
                $flashType = 'success';
            }
        }
    }
}

// Filter
$filterStatus = trim($_GET['status'] ?? '');
$filterTab    = trim($_GET['tab']    ?? 'upcoming');

$where  = "a.patient_id = :pid";
$params = [':pid' => $patientId];

if ($filterStatus !== '') {
    $where .= " AND a.status = :st";
    $params[':st'] = $filterStatus;
}
if ($filterTab === 'upcoming') {
    $where .= " AND a.appt_date >= CURDATE() AND a.status NOT IN ('cancelled','completed')";
} elseif ($filterTab === 'past') {
    $where .= " AND (a.appt_date < CURDATE() OR a.status IN ('completed','cancelled'))";
}

$stmt = $pdo->prepare(
    "SELECT a.appointment_id, a.ref_number, a.appt_date, a.appt_time,
            a.status, a.source, a.notes, a.doctor_id,
            ud.full_name AS doctor_name, d.specialization, d.consultation_fee
     FROM appointments a
     JOIN doctors d ON d.doctor_id = a.doctor_id
     JOIN users ud ON ud.user_id = d.user_id
     WHERE $where
     ORDER BY a.appt_date DESC, a.appt_time DESC"
);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Stats
$statsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
        SUM(status='pending')   AS pending,
        SUM(status='confirmed') AS confirmed,
        SUM(status='completed') AS completed,
        SUM(status='cancelled') AS cancelled
     FROM appointments WHERE patient_id=:pid"
);
$statsStmt->execute([':pid' => $patientId]);
$stats = $statsStmt->fetch();

// All doctors (for reschedule)
$allDocs = $pdo->query(
    "SELECT d.doctor_id, u.full_name, d.specialization FROM doctors d
     JOIN users u ON u.user_id=d.user_id WHERE u.status='active' ORDER BY d.specialization,u.full_name"
)->fetchAll();

$statusConfig = [
    'pending'   => ['bg'=>'var(--warning-light)', 'color'=>'var(--warning)', 'icon'=>'⏳'],
    'confirmed' => ['bg'=>'var(--success-light)', 'color'=>'var(--success)', 'icon'=>'✅'],
    'completed' => ['bg'=>'#e0e7ff',              'color'=>'#4f46e5',       'icon'=>'🏁'],
    'cancelled' => ['bg'=>'#f1f5f9',              'color'=>'#94a3b8',       'icon'=>'❌'],
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
        <p>Welcome, <?= htmlspecialchars($patName) ?> &mdash; Manage your bookings here.</p>
    </div>
    <a href="book.php" class="btn btn-primary">Book New Appointment</a>
</div>

<!-- Flash -->
<?php if ($flashMsg !== ''): ?>
<div style="margin-bottom:20px;padding:13px 18px;border-radius:8px;
    background:<?= $flashType==='success'?'var(--success-light)':($flashType==='warning'?'var(--warning-light)':'var(--danger-light)') ?>;
    color:<?= $flashType==='success'?'var(--success)':($flashType==='warning'?'var(--warning)':'var(--danger)') ?>;
    border:1px solid <?= $flashType==='success'?'#a7f3d0':($flashType==='warning'?'#fcd34d':'#fca5a5') ?>;">
    <?= $flashType==='success'?'✅':($flashType==='warning'?'⚠️':'❌') ?> <?= htmlspecialchars($flashMsg) ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
    <?php foreach ([
        ['📅','Total',     (int)$stats['total'],     'blue'],
        ['⏳','Pending',   (int)$stats['pending'],   'yellow'],
        ['✅','Confirmed', (int)$stats['confirmed'], 'green'],
        ['🏁','Completed', (int)$stats['completed'], 'blue'],
    ] as [$icon,$label,$val,$cls]): ?>
    <div class="stat-card">
        <div class="stat-icon <?= $cls ?>"><?= $icon ?></div>
        <div><span class="stat-label"><?= $label ?></span><span class="stat-value"><?= $val ?></span></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Tabs -->
<div style="display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid var(--border-light);">
    <?php foreach (['upcoming'=>'Upcoming','past'=>'History','all'=>'All'] as $tab=>$label): ?>
    <a href="?tab=<?= $tab ?>"
       style="padding:10px 20px;font-size:13px;font-weight:600;color:<?= $filterTab===$tab?'var(--accent)':'var(--muted)' ?>;
              border-bottom:<?= $filterTab===$tab?'2px solid var(--accent)':'2px solid transparent' ?>;
              margin-bottom:-2px;text-decoration:none;transition:color .15s;">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Table -->
<div class="card" style="padding:0;overflow:hidden;">
<div style="padding:18px 24px;border-bottom:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;"><?= $filterTab==='upcoming'?'Upcoming Appointments':($filterTab==='past'?'Appointment History':'All Appointments') ?></h3>
    <span style="font-size:13px;color:var(--muted);"><?= count($appointments) ?> record(s)</span>
</div>

<?php if (empty($appointments)): ?>
<div style="padding:60px;text-align:center;color:var(--muted);">
    <div style="font-size:48px;margin-bottom:12px;"></div>
    <p style="margin-bottom:16px;">No appointments found.</p>
    <a href="book.php" class="btn btn-primary">Book Your First Appointment</a>
</div>
<?php else: ?>
<div style="overflow-x:auto;">
<table style="width:100%;border-collapse:collapse;font-size:13px;">
<thead>
<tr style="background:var(--bg);">
    <?php $th = 'padding:11px 16px;text-align:left;font-weight:600;color:var(--muted);font-size:11px;letter-spacing:.5px;text-transform:uppercase;border-bottom:1px solid var(--border-light);'; ?>
    <th style="<?= $th ?>">Ref #</th>
    <th style="<?= $th ?>">Doctor</th>
    <th style="<?= $th ?>">Date &amp; Time</th>
    <th style="<?= $th ?>">Source</th>
    <th style="<?= $th ?>">Status</th>
    <th style="<?= $th ?>">Fee</th>
    <th style="<?= $th ?>">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($appointments as $a):
    $sc  = $statusConfig[$a['status']] ?? ['bg'=>'#f1f5f9','color'=>'#94a3b8','icon'=>'?'];
    $td  = 'padding:13px 16px;border-bottom:1px solid var(--border-light);';
    $canAct = in_array($a['status'], ['pending','confirmed']) && $a['appt_date'] >= date('Y-m-d');
?>
<tr onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
    <td style="<?= $td ?> font-weight:700;color:var(--accent);"><?= htmlspecialchars($a['ref_number']) ?></td>
    <td style="<?= $td ?>">
        <div style="font-weight:600;">Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
        <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($a['specialization']) ?></div>
    </td>
    <td style="<?= $td ?>">
        <div style="font-weight:600;"><?= date('d M Y', strtotime($a['appt_date'])) ?></div>
        <div style="font-size:13px;color:var(--accent);font-weight:700;"><?= substr($a['appt_time'],0,5) ?></div>
    </td>
    <td style="<?= $td ?>">
        <?php if ($a['source']==='online'): ?>
        <span style="background:#e0f2fe;color:#0284c7;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">🌐 Online</span>
        <?php else: ?>
        <span style="background:#fef3c7;color:#d97706;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">🚶 OPD</span>
        <?php endif; ?>
    </td>
    <td style="<?= $td ?>">
        <span style="padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;
              background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;">
            <?= $sc['icon'] ?> <?= ucfirst($a['status']) ?>
        </span>
    </td>
    <td style="<?= $td ?>">
        <span style="font-weight:600;color:var(--text);">Rs. <?= number_format((float)$a['consultation_fee'],2) ?></span>
    </td>
    <td style="<?= $td ?>">
        <?php if ($canAct): ?>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <!-- Cancel -->
            <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this appointment?')">
                <input type="hidden" name="appointment_id" value="<?= $a['appointment_id'] ?>">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($filterTab) ?>">
                <button type="submit" style="padding:5px 10px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;font-family:var(--font-body);background:var(--danger-light);color:var(--danger);border:1px solid #fca5a5;"> Cancel</button>
            </form>
            <!-- Reschedule toggle -->
            <button onclick="toggleReschedule(<?= $a['appointment_id'] ?>)"
                    style="padding:5px 10px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;font-family:var(--font-body);background:var(--warning-light);color:var(--warning);border:1px solid #fcd34d;">
                Reschedule
            </button>
        </div>
        <!-- Reschedule form (hidden) -->
        <div id="reschedule-<?= $a['appointment_id'] ?>" style="display:none;margin-top:12px;padding:14px;background:var(--bg);border-radius:8px;border:1px solid var(--border-light);">
            <form method="POST">
                <input type="hidden" name="appointment_id" value="<?= $a['appointment_id'] ?>">
                <input type="hidden" name="action" value="reschedule">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($filterTab) ?>">
                <div style="margin-bottom:10px;">
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--muted);margin-bottom:4px;">Doctor</label>
                    <select name="new_doctor_id" id="rdoc-<?= $a['appointment_id'] ?>"
                            style="width:100%;padding:7px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:var(--font-body);"
                            onchange="rsLoadSlots(<?= $a['appointment_id'] ?>)">
                        <?php foreach ($allDocs as $d): ?>
                        <option value="<?= $d['doctor_id'] ?>" <?= $d['doctor_id']==$a['doctor_id']?'selected':'' ?>>
                            Dr. <?= htmlspecialchars($d['full_name']) ?> — <?= htmlspecialchars($d['specialization']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom:10px;">
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--muted);margin-bottom:4px;">New Date</label>
                    <input type="date" name="new_date" id="rdate-<?= $a['appointment_id'] ?>"
                           min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($a['appt_date']) ?>"
                           style="width:100%;padding:7px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:var(--font-body);"
                           onchange="rsLoadSlots(<?= $a['appointment_id'] ?>)">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--muted);margin-bottom:4px;">New Time Slot</label>
                    <input type="hidden" name="new_time" id="rtime-<?= $a['appointment_id'] ?>">
                    <div id="rslots-<?= $a['appointment_id'] ?>" style="font-size:12px;color:var(--muted);">Select date to load slots.</div>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="submit" style="padding:6px 14px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;font-family:var(--font-body);background:var(--accent);color:#fff;border:none;">
                        Confirm Reschedule
                    </button>
                    <button type="button" onclick="toggleReschedule(<?= $a['appointment_id'] ?>)"
                            style="padding:6px 12px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;font-family:var(--font-body);background:#f1f5f9;color:var(--muted);border:1px solid var(--border);">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <span style="font-size:12px;color:var(--muted);">—</span>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>

</main>

<script>
function toggleReschedule(id) {
    const el = document.getElementById('reschedule-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
    if (el.style.display === 'block') rsLoadSlots(id);
}

function rsLoadSlots(apptId) {
    const did  = document.getElementById('rdoc-'   + apptId).value;
    const date = document.getElementById('rdate-'  + apptId).value;
    const box  = document.getElementById('rslots-' + apptId);
    const hiddenTime = document.getElementById('rtime-' + apptId);

    hiddenTime.value = '';
    if (!did || !date) { box.innerHTML = 'Select doctor & date.'; return; }

    box.innerHTML = 'Loading…';
    fetch(`get_slots.php?doctor_id=${did}&date=${date}`)
        .then(r => r.json())
        .then(data => {
            if (data.error || !data.slots || !data.slots.length) {
                box.innerHTML = '<span style="color:var(--muted)">No slots for this day.</span>';
                return;
            }
            let html = '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
            data.slots.forEach(s => {
                if (s.booked) {
                    html += `<div style="padding:5px 10px;border-radius:6px;background:#f1f5f9;color:#94a3b8;font-size:11px;font-weight:600;text-decoration:line-through;">${s.time}</div>`;
                } else {
                    html += `<div onclick="rsSelectSlot(${apptId},'${s.time}',this)"
                               style="padding:5px 10px;border-radius:6px;background:var(--accent-light);color:var(--accent-dark);font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--accent);"
                               onmouseover="this.style.background='var(--accent)';this.style.color='#fff'"
                               onmouseout="if(!this.classList.contains('rs-selected')){this.style.background='var(--accent-light)';this.style.color='var(--accent-dark)'}"
                             >${s.time}</div>`;
                }
            });
            html += '</div>';
            box.innerHTML = html;
        });
}

function rsSelectSlot(apptId, time, el) {
    const box = document.getElementById('rslots-' + apptId);
    box.querySelectorAll('.rs-selected').forEach(b => {
        b.classList.remove('rs-selected');
        b.style.background = 'var(--accent-light)';
        b.style.color = 'var(--accent-dark)';
    });
    el.classList.add('rs-selected');
    el.style.background = 'var(--accent)';
    el.style.color = '#fff';
    document.getElementById('rtime-' + apptId).value = time;
}
</script>

<?php include '../../includes/footer.php'; ?>
