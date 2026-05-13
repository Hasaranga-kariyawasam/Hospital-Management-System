<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
$requiredRoles = ['admin', 'doctor'];
require_once __DIR__ . '/../../includes/role_check.php';





$pageTitle  = 'Schedule Operation';
$useSidebar = true;

$error   = '';
$success = '';

// ── Load patients ─────────────────────────────────────────────
$patients = $pdo->query("
    SELECT p.patient_id, u.full_name, p.nic
    FROM patients p JOIN users u ON u.user_id = p.user_id
    ORDER BY u.full_name
")->fetchAll();

// ── Load doctors ──────────────────────────────────────────────
$doctors = $pdo->query("
    SELECT d.doctor_id, u.full_name, d.specialization
    FROM doctors d JOIN users u ON u.user_id = d.user_id
    ORDER BY u.full_name
")->fetchAll();

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId       = (int)($_POST['patient_id']       ?? 0);
    $operationType   = trim($_POST['operation_type']    ?? '');
    $surgeonId       = (int)($_POST['surgeon_id']       ?? 0);
    $assistantId     = (int)($_POST['assistant_id']     ?? 0) ?: null;
    $anaesthetistId  = (int)($_POST['anaesthetist_id']  ?? 0) ?: null;
    $theatreNumber   = (int)($_POST['theatre_number']   ?? 0);
    $scheduledDate   = trim($_POST['scheduled_date']    ?? '');
    $scheduledTime   = trim($_POST['scheduled_time']    ?? '');
    $preOpNotes      = trim($_POST['pre_op_notes']      ?? '');

    // ── Validation ────────────────────────────────────────────
    if (!$patientId || !$operationType || !$surgeonId || !$theatreNumber || !$scheduledDate || !$scheduledTime) {
        $error = 'Please fill in all required fields.';
    } elseif (strtotime($scheduledDate) < strtotime(date('Y-m-d'))) {
        $error = 'Scheduled date cannot be in the past.';
    } else {
        // ── Double-booking check ──────────────────────────────
        $conflict = $pdo->prepare("
            SELECT COUNT(*) FROM theatre_operations
            WHERE theatre_number = ?
              AND scheduled_date  = ?
              AND scheduled_time  = ?
              AND status NOT IN ('cancelled')
        ");
        $conflict->execute([$theatreNumber, $scheduledDate, $scheduledTime]);

        if ($conflict->fetchColumn() > 0) {
            $error = '⚠️ Theatre ' . $theatreNumber . ' is already booked on ' . date('d M Y', strtotime($scheduledDate)) . ' at ' . date('h:i A', strtotime($scheduledTime)) . '. Please choose a different time or theatre.';
        } else {
            // ── Insert operation ──────────────────────────────
            $stmt = $pdo->prepare("
                INSERT INTO theatre_operations
                    (patient_id, surgeon_id, anaesthetist_id, assistant_doctor_id,
                     operation_type, theatre_number, scheduled_date, scheduled_time,
                     pre_op_notes, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
            ");
            $stmt->execute([
                $patientId, $surgeonId, $anaesthetistId, $assistantId,
                $operationType, $theatreNumber, $scheduledDate, $scheduledTime,
                $preOpNotes, $_SESSION['user_id']
            ]);
            $newId = $pdo->lastInsertId();
            header("Location: operation_details.php?id=$newId&created=1");
            exit();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="main-content">

    <div class="page-header">
        <div class="page-header-title">
            <h2>➕ Schedule New Operation</h2>
            <p>Book a theatre slot and assign surgical team</p>
        </div>
        <a href="theatre.php" class="btn btn-secondary">← Back to Schedule</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Availability Checker Banner -->
    <div class="alert alert-info" style="margin-bottom:24px">
        ℹ️ The system will automatically check for <strong>double-booking</strong> before saving. If a conflict is found, you will be notified.
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start">

        <!-- Main Form -->
        <div class="card">
            <div class="card-header">
                <h3>🗓️ Operation Details</h3>
            </div>

            <form method="POST" id="opForm">

                <!-- Patient + Operation Type -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Patient <span style="color:var(--danger)">*</span></label>
                        <select name="patient_id" class="form-control" required id="patientSelect">
                            <option value="">— Select Patient —</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['patient_id'] ?>"
                                    <?= (isset($_POST['patient_id']) && $_POST['patient_id'] == $p['patient_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['full_name']) ?> (NIC: <?= htmlspecialchars($p['nic']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Operation Type <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="operation_type" class="form-control"
                               placeholder="e.g. Appendectomy, Caesarean Section"
                               value="<?= htmlspecialchars($_POST['operation_type'] ?? '') ?>" required>
                        <div class="form-hint">Enter the surgical procedure name</div>
                    </div>
                </div>

                <!-- Theatre + Date + Time -->
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Theatre <span style="color:var(--danger)">*</span></label>
                        <select name="theatre_number" class="form-control" required id="theatreSelect">
                            <option value="">— Select —</option>
                            <option value="1" <?= (isset($_POST['theatre_number']) && $_POST['theatre_number'] == 1) ? 'selected' : '' ?>>🏥 Theatre 1 – General</option>
                            <option value="2" <?= (isset($_POST['theatre_number']) && $_POST['theatre_number'] == 2) ? 'selected' : '' ?>>🚨 Theatre 2 – Emergency</option>
                            <option value="3" <?= (isset($_POST['theatre_number']) && $_POST['theatre_number'] == 3) ? 'selected' : '' ?>>👶 Theatre 3 – Labour</option>
                            <option value="4" <?= (isset($_POST['theatre_number']) && $_POST['theatre_number'] == 4) ? 'selected' : '' ?>>🔧 Theatre 4 – Minor</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date <span style="color:var(--danger)">*</span></label>
                        <input type="date" name="scheduled_date" class="form-control"
                               min="<?= date('Y-m-d') ?>"
                               value="<?= htmlspecialchars($_POST['scheduled_date'] ?? '') ?>"
                               required id="dateInput">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time <span style="color:var(--danger)">*</span></label>
                        <input type="time" name="scheduled_time" class="form-control"
                               value="<?= htmlspecialchars($_POST['scheduled_time'] ?? '') ?>"
                               required id="timeInput">
                    </div>
                </div>

                <!-- Availability Check Result -->
                <div id="availResult" style="display:none;margin-bottom:16px"></div>

                <!-- Surgical Team -->
                <div style="padding:16px;background:var(--bg);border-radius:var(--radius-sm);margin-bottom:20px">
                    <div style="font-size:13px;font-weight:700;color:var(--text-mid);margin-bottom:14px;text-transform:uppercase;letter-spacing:0.5px">👨‍⚕️ Surgical Team</div>
                    <div class="form-row">
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label">Lead Surgeon <span style="color:var(--danger)">*</span></label>
                            <select name="surgeon_id" class="form-control" required>
                                <option value="">— Select Surgeon —</option>
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?= $d['doctor_id'] ?>"
                                        <?= (isset($_POST['surgeon_id']) && $_POST['surgeon_id'] == $d['doctor_id']) ? 'selected' : '' ?>>
                                        Dr. <?= htmlspecialchars($d['full_name']) ?> – <?= htmlspecialchars($d['specialization']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label">Anaesthesiologist</label>
                            <select name="anaesthetist_id" class="form-control">
                                <option value="">— Not Assigned —</option>
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?= $d['doctor_id'] ?>"
                                        <?= (isset($_POST['anaesthetist_id']) && $_POST['anaesthetist_id'] == $d['doctor_id']) ? 'selected' : '' ?>>
                                        Dr. <?= htmlspecialchars($d['full_name']) ?> – <?= htmlspecialchars($d['specialization']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:14px;margin-bottom:0">
                        <label class="form-label">Assisting Doctor</label>
                        <select name="assistant_id" class="form-control">
                            <option value="">— Not Assigned —</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?= $d['doctor_id'] ?>"
                                    <?= (isset($_POST['assistant_id']) && $_POST['assistant_id'] == $d['doctor_id']) ? 'selected' : '' ?>>
                                    Dr. <?= htmlspecialchars($d['full_name']) ?> – <?= htmlspecialchars($d['specialization']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Pre-op Notes -->
                <div class="form-group">
                    <label class="form-label">Pre-Op Notes</label>
                    <textarea name="pre_op_notes" class="form-control" rows="4"
                              placeholder="Patient condition, allergies, special instructions..."><?= htmlspecialchars($_POST['pre_op_notes'] ?? '') ?></textarea>
                </div>

                <div style="display:flex;gap:12px;margin-top:8px">
                    <button type="submit" class="btn btn-primary btn-lg">🔬 Schedule Operation</button>
                    <a href="theatre.php" class="btn btn-secondary btn-lg">Cancel</a>
                </div>

            </form>
        </div>

        <!-- Side Info Panel -->
        <div style="display:flex;flex-direction:column;gap:16px">

            <!-- Theatre Guide -->
            <div class="card">
                <div class="card-header" style="margin-bottom:14px;padding-bottom:12px">
                    <h3>🏥 Theatre Guide</h3>
                </div>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <?php
                    $theatreInfo = [
                        ['num'=>1,'icon'=>'🏥','name'=>'Theatre 1','desc'=>'General surgery, orthopaedics, urology'],
                        ['num'=>2,'icon'=>'🚨','name'=>'Theatre 2','desc'=>'Emergency and trauma operations'],
                        ['num'=>3,'icon'=>'👶','name'=>'Theatre 3','desc'=>'Labour, maternity and C-sections'],
                        ['num'=>4,'icon'=>'🔧','name'=>'Theatre 4','desc'=>'Minor procedures and day surgery'],
                    ];
                    foreach ($theatreInfo as $t): ?>
                    <div style="display:flex;align-items:flex-start;gap:10px;padding:10px;background:var(--bg);border-radius:var(--radius-sm)">
                        <span style="font-size:20px"><?= $t['icon'] ?></span>
                        <div>
                            <div style="font-weight:600;font-size:13px"><?= $t['name'] ?></div>
                            <div style="font-size:12px;color:var(--muted)"><?= $t['desc'] ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Status Flow -->
            <div class="card">
                <div class="card-header" style="margin-bottom:14px;padding-bottom:12px">
                    <h3>📋 Status Flow</h3>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;font-size:13px">
                    <?php
                    $statuses = [
                        ['Scheduled','badge-info','Operation booked'],
                        ['Confirmed','badge-success','Ready and approved'],
                        ['In Progress','badge-warning','Surgery running'],
                        ['Completed','badge-success','Surgery finished'],
                        ['Transferred','badge-neutral','Patient moved to ward'],
                    ];
                    foreach ($statuses as [$s, $cls, $desc]): ?>
                    <div style="display:flex;align-items:center;gap:10px">
                        <span class="badge <?= $cls ?>" style="width:90px;justify-content:center"><?= $s ?></span>
                        <span style="color:var(--muted)"><?= $desc ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div><!-- end grid -->

</main>

<script>
// Live availability check
const theatreEl = document.getElementById('theatreSelect');
const dateEl    = document.getElementById('dateInput');
const timeEl    = document.getElementById('timeInput');
const resultEl  = document.getElementById('availResult');

function checkAvailability() {
    const t = theatreEl.value;
    const d = dateEl.value;
    const tm = timeEl.value;
    if (!t || !d || !tm) { resultEl.style.display='none'; return; }

    fetch(`check_availability.php?theatre=${t}&date=${d}&time=${tm}`)
        .then(r => r.json())
        .then(data => {
            resultEl.style.display = 'block';
            if (data.available) {
                resultEl.innerHTML = `<div class="alert alert-success">✅ Theatre ${t} is available on ${d} at ${tm}.</div>`;
            } else {
                resultEl.innerHTML = `<div class="alert alert-error">⚠️ Theatre ${t} is already booked at this time. Please choose another slot.</div>`;
            }
        })
        .catch(() => { resultEl.style.display='none'; });
}

theatreEl.addEventListener('change', checkAvailability);
dateEl.addEventListener('change', checkAvailability);
timeEl.addEventListener('change', checkAvailability);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>