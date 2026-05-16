<?php
declare(strict_types=1);
require_once '../../includes/session_check.php';
require_once '../../config/db_config.php';

// Only patients can book online
if (($_SESSION['role'] ?? '') !== 'patient') {
    header('Location: /Web/Hospital-Management-System/login.php');
    exit();
}

$userId    = (int)$_SESSION['user_id'];
$message   = '';
$msgType   = '';

// Resolve patient_id
$stmtPat = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = :uid LIMIT 1");
$stmtPat->execute([':uid' => $userId]);
$patRow    = $stmtPat->fetch();
$patientId = (int)($patRow['patient_id'] ?? 0);

if ($patientId === 0) {
    die('<p style="padding:40px;color:red;">Patient profile not found. Please complete registration.</p>');
}

// Handle POST booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    $apptDate = trim($_POST['appt_date'] ?? '');
    $apptTime = trim($_POST['appt_time'] ?? '');
    $notes    = trim($_POST['notes'] ?? '');

    $errors = [];
    if ($doctorId === 0)            $errors[] = 'Please select a doctor.';
    if ($apptDate === '')           $errors[] = 'Please select a date.';
    if ($apptTime === '')           $errors[] = 'Please select a time slot.';
    if ($apptDate < date('Y-m-d'))  $errors[] = 'Cannot book a past date.';

    if (empty($errors)) {
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
        $refNo = 'APT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $ins = $pdo->prepare(
            "INSERT INTO appointments
               (patient_id, doctor_id, appt_date, appt_time, source, status, ref_number, notes, booked_by)
             VALUES (:pid, :did, :dt, :tm, 'online', 'pending', :ref, :notes, :by)"
        );
        $ins->execute([
            ':pid' => $patientId, ':did' => $doctorId,
            ':dt'  => $apptDate,  ':tm'  => $apptTime,
            ':ref' => $refNo,     ':notes' => $notes ?: null,
            ':by'  => $userId,
        ]);
        $message = "Appointment booked! Reference: <strong>$refNo</strong>";
        $msgType = 'success';
    } else {
        $message = implode('<br>', $errors);
        $msgType = 'error';
    }
}

// Load doctors
$docStmt = $pdo->query(
    "SELECT d.doctor_id, u.full_name, d.specialization, d.consultation_fee
     FROM doctors d JOIN users u ON u.user_id = d.user_id
     WHERE u.status = 'active' ORDER BY d.specialization, u.full_name"
);
$doctors = $docStmt->fetchAll();
$specializations = array_unique(array_column($doctors, 'specialization'));
sort($specializations);

$pageTitle  = 'Book Appointment';
$useSidebar = true;
$isPublic   = false;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">

<div class="page-header">
    <div class="page-header-title">
        <h2>Book an Appointment</h2>
        <p>Select a doctor, date and available time slot to confirm your booking.</p>
    </div>
    <a href="my_appointments.php" class="btn btn-secondary">My Appointments</a>
</div>

<?php if ($message !== ''): ?>
<div style="margin-bottom:20px;padding:14px 20px;border-radius:8px;
    background:<?= $msgType==='success'?'var(--success-light)':'var(--danger-light)'?>;
    color:<?= $msgType==='success'?'var(--success)':'var(--danger)'?>;
    border:1px solid <?= $msgType==='success'?'#a7f3d0':'#fca5a5'?>;">
    <?= $msgType==='success'?'':'' ?> <?= $message ?>
    <?php if ($msgType==='success'): ?>
        &nbsp;<a href="my_appointments.php" style="color:var(--success);text-decoration:underline;">View My Appointments →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start;">

<!-- Booking Form -->
<div class="card" style="padding:32px;">
<h3 style="margin-bottom:24px;">Appointment Details</h3>
<form method="POST" id="bookingForm">

    <div class="fg">
        <label class="fl">Filter by Specialization</label>
        <select id="specFilter" class="fc" onchange="filterDoctors()">
            <option value="">— All Specializations —</option>
            <?php foreach ($specializations as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="fg">
        <label class="fl">Select Doctor <span style="color:red">*</span></label>
        <select name="doctor_id" id="doctorSelect" class="fc" required onchange="onDoctorChange()">
            <option value="">— Choose a Doctor —</option>
            <?php foreach ($doctors as $doc): ?>
            <option value="<?= $doc['doctor_id'] ?>"
                    data-spec="<?= htmlspecialchars($doc['specialization']) ?>"
                    data-fee="<?= number_format((float)$doc['consultation_fee'],2) ?>"
                    data-name="Dr. <?= htmlspecialchars($doc['full_name']) ?>">
                Dr. <?= htmlspecialchars($doc['full_name']) ?> — <?= htmlspecialchars($doc['specialization']) ?> — Rs. <?= number_format((float)$doc['consultation_fee'],2) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="fg">
        <label class="fl">Appointment Date <span style="color:red">*</span></label>
        <input type="date" name="appt_date" id="apptDate" class="fc"
               min="<?= date('Y-m-d') ?>" required onchange="loadSlots()">
    </div>

    <div class="fg">
        <label class="fl">Available Time Slots <span style="color:red">*</span></label>
        <input type="hidden" name="appt_time" id="apptTimeHidden">
        <div id="slotsContainer" style="margin-top:8px;">
            <p style="color:var(--muted);font-size:13px;">⬆ Select a doctor and date to see available slots.</p>
        </div>
    </div>

    <div class="fg">
        <label class="fl">Reason for Visit (Optional)</label>
        <textarea name="notes" class="fc" rows="3" placeholder="Briefly describe your symptoms…"></textarea>
    </div>

    <button type="submit" class="btn btn-primary" id="submitBtn" disabled
            style="width:100%;padding:14px;font-size:15px;margin-top:8px;">
        Confirm Booking
    </button>
</form>
</div>

<!-- Right Panel -->
<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="card" style="padding:22px;">
        <p style="font-size:11px;font-weight:700;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase;margin:0 0 10px;">Selected Doctor</p>
        <div id="doctorInfo" style="font-size:13px;color:var(--muted);">No doctor selected yet.</div>
    </div>
    <div class="card" style="padding:22px;">
        <p style="font-size:11px;font-weight:700;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase;margin:0 0 10px;">Booking Summary</p>
        <div id="bookingSummary" style="font-size:13px;color:var(--muted);">Fill the form to see summary.</div>
    </div>
    <div style="background:linear-gradient(135deg,var(--accent),var(--accent-dark));border-radius:var(--radius);padding:20px;color:#fff;">
        <h4 style="margin-bottom:8px;">Tips</h4>
        <ul style="font-size:13px;color:rgba(255,255,255,0.9);line-height:2;padding-left:16px;margin:0;">
            <li>Arrive 15 minutes early</li>
            <li>Bring NIC &amp; medical records</li>
            <li>Note your reference number</li>
        </ul>
    </div>
</div>

</div><!-- /grid -->
</main>

<style>
.fg{margin-bottom:18px;}
.fl{display:block;font-size:13px;font-weight:600;color:var(--text-mid);margin-bottom:6px;}
.fc{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:14px;font-family:var(--font-body);background:#fff;color:var(--text);transition:border-color .2s;}
.fc:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(14,165,233,.15);}
.slot-btn{display:inline-block;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:2px solid transparent;transition:all .15s;user-select:none;margin:0;}
.slot-btn.free{background:var(--accent-light);color:var(--accent-dark);border-color:var(--accent);}
.slot-btn.free:hover{background:var(--accent);color:#fff;}
.slot-btn.selected{background:var(--accent);color:#fff;border-color:var(--accent-dark);box-shadow:0 2px 8px rgba(14,165,233,.4);}
.slot-btn.taken{background:#f1f5f9;color:#94a3b8;border-color:#e2e8f0;cursor:not-allowed;text-decoration:line-through;}
</style>

<script>
const doctorMeta = {};
document.querySelectorAll('#doctorSelect option[data-spec]').forEach(o => {
    doctorMeta[o.value] = {name: o.dataset.name, spec: o.dataset.spec, fee: o.dataset.fee};
});

function filterDoctors() {
    const spec = document.getElementById('specFilter').value;
    document.querySelectorAll('#doctorSelect option[data-spec]').forEach(o => {
        o.hidden = spec ? o.dataset.spec !== spec : false;
    });
    document.getElementById('doctorSelect').value = '';
    clearSlots();
}

function onDoctorChange() {
    updateDoctorInfo();
    const date = document.getElementById('apptDate').value;
    if (date) loadSlots(); else clearSlots();
}

function clearSlots() {
    document.getElementById('slotsContainer').innerHTML =
        '<p style="color:var(--muted);font-size:13px;">⬆ Select a date to see available slots.</p>';
    document.getElementById('apptTimeHidden').value = '';
    document.getElementById('submitBtn').disabled = true;
    updateSummary();
}

function updateDoctorInfo() {
    const did = document.getElementById('doctorSelect').value;
    const box = document.getElementById('doctorInfo');
    if (did && doctorMeta[did]) {
        const d = doctorMeta[did];
        box.innerHTML = `<div style="font-weight:700;font-size:15px;color:var(--text);margin-bottom:4px;">${d.name}</div>
            <div style="color:var(--muted);font-size:12px;margin-bottom:4px;">🏥 ${d.spec}</div>
            <div style="font-weight:600;color:var(--accent);">Fee: Rs. ${d.fee}</div>`;
    } else {
        box.innerHTML = '<span style="color:var(--muted);">No doctor selected.</span>';
    }
}

function updateSummary() {
    const did  = document.getElementById('doctorSelect').value;
    const date = document.getElementById('apptDate').value;
    const time = document.getElementById('apptTimeHidden').value;
    const box  = document.getElementById('bookingSummary');
    const dname = did && doctorMeta[did] ? doctorMeta[did].name : '—';
    box.innerHTML = `<div style="line-height:2;">
        <div><strong>${dname}</strong></div>
        <div><strong>${date || '—'}</strong></div>
        <div><strong>${time || '(select slot)'}</strong></div>
    </div>`;
}

function loadSlots() {
    const did  = document.getElementById('doctorSelect').value;
    const date = document.getElementById('apptDate').value;
    updateSummary();
    if (!did || !date) {
        document.getElementById('slotsContainer').innerHTML =
            '<p style="color:var(--muted);font-size:13px;">⬆ Select a doctor and date first.</p>';
        return;
    }
    document.getElementById('slotsContainer').innerHTML =
        '<p style="color:var(--muted);font-size:13px;">⏳ Loading slots…</p>';

    fetch(`get_slots.php?doctor_id=${did}&date=${date}`)
        .then(r => r.json())
        .then(data => renderSlots(data))
        .catch(() => {
            document.getElementById('slotsContainer').innerHTML =
                '<p style="color:red;font-size:13px;">Failed to load slots.</p>';
        });
}

function renderSlots(data) {
    const container = document.getElementById('slotsContainer');
    document.getElementById('apptTimeHidden').value = '';
    document.getElementById('submitBtn').disabled = true;

    if (data.error) {
        container.innerHTML = `<p style="color:var(--muted);font-size:13px;">${data.error}</p>`;
        return;
    }
    if (!data.slots || data.slots.length === 0) {
        container.innerHTML = '<p style="color:var(--muted);font-size:13px;">No schedule for this day. Try another date.</p>';
        return;
    }

    let html = '<div style="display:flex;flex-wrap:wrap;gap:8px;">';
    data.slots.forEach(s => {
        if (s.booked) {
            html += `<div class="slot-btn taken" title="Already booked">${s.time}</div>`;
        } else {
            html += `<div class="slot-btn free" onclick="selectSlot('${s.time}',this)">${s.time}</div>`;
        }
    });
    html += '</div>';
    const free = data.slots.filter(s => !s.booked).length;
    html += `<p style="font-size:12px;color:var(--muted);margin-top:8px;">
        <span style="color:var(--success);font-weight:600;">${free} free</span> · 
        <span style="color:var(--muted);">${data.slots.length - free} booked</span>
    </p>`;
    container.innerHTML = html;
}

function selectSlot(time, el) {
    document.querySelectorAll('.slot-btn.selected').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('apptTimeHidden').value = time;
    document.getElementById('submitBtn').disabled = false;
    updateSummary();
}
</script>

<?php include '../../includes/footer.php'; ?>