<?php
declare(strict_types=1);
require_once '../../includes/session_check.php';
require_once '../../config/db_config.php';

// Only reception can access OPD walk-in
$role = $_SESSION['role'] ?? '';
if ($role !== 'reception' && $role !== 'admin') {
    header('Location: /Web/Hospital-Management-System/login.php');
    exit();
}

$userId  = (int)$_SESSION['user_id'];
$message = '';
$msgType = '';

// Handle POST — create OPD walk-in appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $doctorId  = (int)($_POST['doctor_id']  ?? 0);
    $apptDate  = trim($_POST['appt_date']   ?? '');
    $apptTime  = trim($_POST['appt_time']   ?? '');
    $notes     = trim($_POST['notes']       ?? '');

    $errors = [];
    if ($patientId === 0) $errors[] = 'Please select a patient.';
    if ($doctorId  === 0) $errors[] = 'Please select a doctor.';
    if ($apptDate  === '') $errors[] = 'Please select a date.';
    if ($apptTime  === '') $errors[] = 'Please select a time slot.';

    if (empty($errors)) {
        // Double-booking check
        $chk = $pdo->prepare(
            "SELECT appointment_id FROM appointments
             WHERE doctor_id=:did AND appt_date=:dt AND appt_time=:tm
               AND status NOT IN ('cancelled') LIMIT 1"
        );
        $chk->execute([':did' => $doctorId, ':dt' => $apptDate, ':tm' => $apptTime]);
        if ($chk->fetch()) {
            $errors[] = 'This slot is already booked. Please choose another time.';
        }
    }

    if (empty($errors)) {
        $refNo = 'OPD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $ins = $pdo->prepare(
            "INSERT INTO appointments
               (patient_id, doctor_id, appt_date, appt_time, source, status, ref_number, notes, booked_by)
             VALUES (:pid, :did, :dt, :tm, 'opd', 'confirmed', :ref, :notes, :by)"
        );
        $ins->execute([
            ':pid'   => $patientId,
            ':did'   => $doctorId,
            ':dt'    => $apptDate,
            ':tm'    => $apptTime,
            ':ref'   => $refNo,
            ':notes' => $notes ?: null,
            ':by'    => $userId,
        ]);
        $message = "OPD appointment created! Reference: <strong>$refNo</strong> — Status: Confirmed";
        $msgType = 'success';
    } else {
        $message = implode('<br>', $errors);
        $msgType = 'error';
    }
}

// Load patients (for patient search dropdown)
$patStmt = $pdo->query(
    "SELECT p.patient_id, u.full_name, p.nic, p.phone
     FROM patients p JOIN users u ON u.user_id = p.user_id
     WHERE u.status = 'active'
     ORDER BY u.full_name"
);
$patients = $patStmt->fetchAll();

// Load doctors
$docStmt = $pdo->query(
    "SELECT d.doctor_id, u.full_name, d.specialization, d.consultation_fee
     FROM doctors d JOIN users u ON u.user_id = d.user_id
     WHERE u.status = 'active'
     ORDER BY d.specialization, u.full_name"
);
$doctors = $docStmt->fetchAll();
$specializations = array_unique(array_column($doctors, 'specialization'));
sort($specializations);

// Today's OPD appointments for this receptionist's view
$todayStmt = $pdo->prepare(
    "SELECT a.ref_number, a.appt_time, a.status,
            up.full_name AS patient_name, ud.full_name AS doctor_name,
            d.specialization
     FROM appointments a
     JOIN patients pt ON pt.patient_id = a.patient_id
     JOIN users up ON up.user_id = pt.user_id
     JOIN doctors d ON d.doctor_id = a.doctor_id
     JOIN users ud ON ud.user_id = d.user_id
     WHERE a.appt_date = CURDATE() AND a.source = 'opd'
     ORDER BY a.appt_time ASC"
);
$todayStmt->execute();
$todayOpd = $todayStmt->fetchAll();

$pageTitle  = 'OPD Walk-in Booking';
$useSidebar = true;
$isPublic   = false;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">

<div class="page-header">
    <div class="page-header-title">
        <h2>OPD Walk-in Appointment</h2>
        <p>Create a walk-in appointment for a patient at the reception counter.</p>
    </div>
    <span style="font-size:13px;color:var(--muted);background:var(--accent-light);padding:6px 14px;border-radius:20px;font-weight:600;">
        Today: <?= date('d M Y') ?>
    </span>
</div>

<?php if ($message !== ''): ?>
<div style="margin-bottom:20px;padding:14px 20px;border-radius:8px;
    background:<?= $msgType==='success'?'var(--success-light)':'var(--danger-light)'?>;
    color:<?= $msgType==='success'?'var(--success)':'var(--danger)'?>;
    border:1px solid <?= $msgType==='success'?'#a7f3d0':'#fca5a5'?>;">
    <?= $msgType==='success'?'':'' ?> <?= $message ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

<!-- OPD Booking Form -->
<div class="card" style="padding:28px;">
<h3 style="margin-bottom:22px;">Create OPD Appointment</h3>
<form method="POST" id="opdForm">

    <!-- Patient Search -->
    <div class="fg">
        <label class="fl">Search &amp; Select Patient <span style="color:red">*</span></label>
        <input type="text" id="patientSearch" class="fc" placeholder="Type patient name or NIC…"
               oninput="filterPatients()" autocomplete="off">
        <div id="patientDropdown" style="display:none;position:absolute;z-index:200;
             background:#fff;border:1px solid var(--border);border-radius:8px;
             box-shadow:var(--shadow);max-height:200px;overflow-y:auto;width:100%;"></div>
        <input type="hidden" name="patient_id" id="patientIdHidden">
        <div id="selectedPatient" style="margin-top:8px;font-size:12px;color:var(--muted);"></div>
    </div>

    <!-- Specialization Filter -->
    <div class="fg">
        <label class="fl">Filter by Specialization</label>
        <select id="opdSpecFilter" class="fc" onchange="opdFilterDoctors()">
            <option value="">— All Specializations —</option>
            <?php foreach ($specializations as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Doctor -->
    <div class="fg">
        <label class="fl">Select Doctor <span style="color:red">*</span></label>
        <select name="doctor_id" id="opdDoctorSelect" class="fc" required onchange="opdOnDoctorChange()">
            <option value="">— Choose a Doctor —</option>
            <?php foreach ($doctors as $doc): ?>
            <option value="<?= $doc['doctor_id'] ?>"
                    data-spec="<?= htmlspecialchars($doc['specialization']) ?>"
                    data-fee="<?= number_format((float)$doc['consultation_fee'],2) ?>">
                Dr. <?= htmlspecialchars($doc['full_name']) ?> — <?= htmlspecialchars($doc['specialization']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Date -->
    <div class="fg">
        <label class="fl">Appointment Date <span style="color:red">*</span></label>
        <input type="date" name="appt_date" id="opdDate" class="fc"
               min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>"
               required onchange="opdLoadSlots()">
    </div>

    <!-- Slots -->
    <div class="fg">
        <label class="fl">Available Time Slots <span style="color:red">*</span></label>
        <input type="hidden" name="appt_time" id="opdTimeHidden">
        <div id="opdSlotsContainer" style="margin-top:8px;">
            <p style="color:var(--muted);font-size:13px;">⬆ Select a doctor to see available slots.</p>
        </div>
    </div>

    <!-- Notes -->
    <div class="fg">
        <label class="fl">Notes (Optional)</label>
        <textarea name="notes" class="fc" rows="2" placeholder="Walk-in notes…"></textarea>
    </div>

    <div style="background:var(--warning-light);border:1px solid #fcd34d;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:var(--warning);">
        OPD walk-in appointments are auto-set to <strong>Confirmed</strong> status.
    </div>

    <button type="submit" class="btn btn-primary" id="opdSubmitBtn" disabled
            style="width:100%;padding:14px;font-size:15px;">
        Create OPD Appointment
    </button>
</form>
</div>

<!-- Today's OPD Appointments -->
<div class="card" style="padding:28px;">
<h3 style="margin-bottom:18px;">Today's OPD Appointments
    <span style="font-size:13px;font-weight:400;color:var(--muted);"><?= date('d M Y') ?></span>
</h3>

<?php if (empty($todayOpd)): ?>
<p style="color:var(--muted);font-size:13px;text-align:center;padding:30px 0;">No OPD appointments created today.</p>
<?php else: ?>
<div style="overflow-x:auto;">
<table style="width:100%;border-collapse:collapse;font-size:13px;">
<thead>
<tr style="background:var(--bg);">
    <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--muted);border-bottom:1px solid var(--border-light);">Ref#</th>
    <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--muted);border-bottom:1px solid var(--border-light);">Patient</th>
    <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--muted);border-bottom:1px solid var(--border-light);">Doctor</th>
    <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--muted);border-bottom:1px solid var(--border-light);">Time</th>
    <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--muted);border-bottom:1px solid var(--border-light);">Status</th>
</tr>
</thead>
<tbody>
<?php foreach ($todayOpd as $appt): ?>
<?php
    $statusColors = [
        'pending'   => ['var(--warning-light)', 'var(--warning)'],
        'confirmed' => ['var(--success-light)', 'var(--success)'],
        'completed' => ['#e0e7ff', '#4f46e5'],
        'cancelled' => ['#f1f5f9', '#94a3b8'],
    ];
    $sc = $statusColors[$appt['status']] ?? ['#f1f5f9', '#64748b'];
?>
<tr style="border-bottom:1px solid var(--border-light);">
    <td style="padding:10px 12px;font-weight:600;color:var(--accent);"><?= htmlspecialchars($appt['ref_number']) ?></td>
    <td style="padding:10px 12px;"><?= htmlspecialchars($appt['patient_name']) ?></td>
    <td style="padding:10px 12px;">
        Dr. <?= htmlspecialchars($appt['doctor_name']) ?><br>
        <span style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($appt['specialization']) ?></span>
    </td>
    <td style="padding:10px 12px;font-weight:600;"><?= substr($appt['appt_time'],0,5) ?></td>
    <td style="padding:10px 12px;">
        <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;
              background:<?= $sc[0] ?>;color:<?= $sc[1] ?>;">
            <?= ucfirst($appt['status']) ?>
        </span>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<p style="font-size:12px;color:var(--muted);margin-top:12px;"><?= count($todayOpd) ?> OPD appointment(s) today</p>
<?php endif; ?>
</div>

</div><!-- /grid -->
</main>

<!-- Patient Data for JS -->
<script>
const allPatients = <?= json_encode(array_map(function($p) {
    return ['id' => $p['patient_id'], 'name' => $p['full_name'], 'nic' => $p['nic'], 'phone' => $p['phone']];
}, $patients)) ?>;

function filterPatients() {
    const q = document.getElementById('patientSearch').value.toLowerCase().trim();
    const dd = document.getElementById('patientDropdown');
    if (!q) { dd.style.display = 'none'; return; }

    const matches = allPatients.filter(p =>
        p.name.toLowerCase().includes(q) || p.nic.toLowerCase().includes(q)
    ).slice(0, 10);

    if (matches.length === 0) { dd.innerHTML = '<div style="padding:12px;color:var(--muted);font-size:13px;">No patients found.</div>'; }
    else {
        dd.innerHTML = matches.map(p =>
            `<div onclick="selectPatient(${p.id},'${p.name.replace(/'/g,"\\'")}','${p.nic}')"
                  style="padding:10px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--border-light);"
                  onmouseover="this.style.background='var(--accent-light)'"
                  onmouseout="this.style.background=''"
             ><strong>${p.name}</strong> <span style="color:var(--muted);">NIC: ${p.nic}</span></div>`
        ).join('');
    }
    dd.style.display = 'block';
    // position relative to input
    const inp = document.getElementById('patientSearch');
    dd.style.width = inp.offsetWidth + 'px';
}

function selectPatient(id, name, nic) {
    document.getElementById('patientSearch').value = name;
    document.getElementById('patientIdHidden').value = id;
    document.getElementById('patientDropdown').style.display = 'none';
    document.getElementById('selectedPatient').innerHTML =
        `Selected: <strong>${name}</strong> — NIC: ${nic}`;
}

document.addEventListener('click', e => {
    if (!e.target.closest('#patientSearch') && !e.target.closest('#patientDropdown')) {
        document.getElementById('patientDropdown').style.display = 'none';
    }
});

// Doctor handling
function opdFilterDoctors() {
    const spec = document.getElementById('opdSpecFilter').value;
    document.querySelectorAll('#opdDoctorSelect option[data-spec]').forEach(o => {
        o.hidden = spec ? o.dataset.spec !== spec : false;
    });
    document.getElementById('opdDoctorSelect').value = '';
    opdClearSlots();
}

function opdOnDoctorChange() {
    const date = document.getElementById('opdDate').value;
    if (date) opdLoadSlots(); else opdClearSlots();
}

function opdClearSlots() {
    document.getElementById('opdSlotsContainer').innerHTML =
        '<p style="color:var(--muted);font-size:13px;">⬆ Select a date to see slots.</p>';
    document.getElementById('opdTimeHidden').value = '';
    document.getElementById('opdSubmitBtn').disabled = true;
}

function opdLoadSlots() {
    const did  = document.getElementById('opdDoctorSelect').value;
    const date = document.getElementById('opdDate').value;
    if (!did || !date) {
        document.getElementById('opdSlotsContainer').innerHTML =
            '<p style="color:var(--muted);font-size:13px;">⬆ Select a doctor and date first.</p>';
        return;
    }
    document.getElementById('opdSlotsContainer').innerHTML =
        '<p style="color:var(--muted);font-size:13px;">⏳ Loading slots…</p>';

    fetch(`get_slots.php?doctor_id=${did}&date=${date}`)
        .then(r => r.json())
        .then(data => opdRenderSlots(data))
        .catch(() => {
            document.getElementById('opdSlotsContainer').innerHTML =
                '<p style="color:red;font-size:13px;">Failed to load slots.</p>';
        });
}

function opdRenderSlots(data) {
    const container = document.getElementById('opdSlotsContainer');
    document.getElementById('opdTimeHidden').value = '';
    document.getElementById('opdSubmitBtn').disabled = true;

    if (data.error) {
        container.innerHTML = `<p style="color:var(--muted);font-size:13px;">${data.error}</p>`;
        return;
    }
    if (!data.slots || data.slots.length === 0) {
        container.innerHTML = '<p style="color:var(--muted);font-size:13px;">😔 No schedule for this day.</p>';
        return;
    }

    let html = '<div style="display:flex;flex-wrap:wrap;gap:8px;">';
    data.slots.forEach(s => {
        if (s.booked) {
            html += `<div class="slot-btn taken" title="Already booked">${s.time}</div>`;
        } else {
            html += `<div class="slot-btn free" onclick="opdSelectSlot('${s.time}',this)">${s.time}</div>`;
        }
    });
    html += '</div>';
    const free = data.slots.filter(s => !s.booked).length;
    html += `<p style="font-size:12px;color:var(--muted);margin-top:8px;">
        <span style="color:var(--success);font-weight:600;">${free} free</span> · 
        ${data.slots.length - free} booked
    </p>`;
    container.innerHTML = html;
}

function opdSelectSlot(time, el) {
    document.querySelectorAll('.slot-btn.selected').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('opdTimeHidden').value = time;
    document.getElementById('opdSubmitBtn').disabled = false;
}

// Load slots for today on page load if doctor is pre-selected
window.addEventListener('load', () => {
    const did = document.getElementById('opdDoctorSelect').value;
    if (did) opdLoadSlots();
});
</script>

<style>
.fg{margin-bottom:16px;position:relative;}
.fl{display:block;font-size:13px;font-weight:600;color:var(--text-mid);margin-bottom:6px;}
.fc{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:14px;font-family:var(--font-body);background:#fff;color:var(--text);transition:border-color .2s;}
.fc:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(14,165,233,.15);}
.slot-btn{display:inline-block;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:2px solid transparent;transition:all .15s;user-select:none;}
.slot-btn.free{background:var(--accent-light);color:var(--accent-dark);border-color:var(--accent);}
.slot-btn.free:hover{background:var(--accent);color:#fff;}
.slot-btn.selected{background:var(--accent);color:#fff;border-color:var(--accent-dark);box-shadow:0 2px 8px rgba(14,165,233,.4);}
.slot-btn.taken{background:#f1f5f9;color:#94a3b8;border-color:#e2e8f0;cursor:not-allowed;text-decoration:line-through;}
</style>

<?php include '../../includes/footer.php'; ?>