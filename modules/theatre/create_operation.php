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

$patients = $pdo->query("
    SELECT p.patient_id, u.full_name, p.nic
    FROM patients p JOIN users u ON u.user_id = p.user_id
    ORDER BY u.full_name
")->fetchAll();


$doctors = $pdo->query("
    SELECT u.user_id, u.full_name, d.specialization
    FROM doctors d JOIN users u ON u.user_id = d.user_id
    WHERE u.status = 'active'
    ORDER BY u.full_name
")->fetchAll();


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
            $error = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Theatre ' . $theatreNumber . ' is already booked on ' . date('d M Y', strtotime($scheduledDate)) . ' at ' . date('h:i A', strtotime($scheduledTime)) . '. Please choose a different time or theatre.';
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
            <h2><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Schedule New Operation</h2>
            <p>Book a theatre slot and assign surgical team</p>
        </div>
        <a href="theatre.php" class="btn btn-secondary">← Back to Schedule</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Availability Checker Banner -->
    <div class="alert alert-info" style="margin-bottom:24px">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> The system will automatically check for <strong>double-booking</strong> before saving. If a conflict is found, you will be notified.
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start">

        <!-- Main Form -->
        <div class="card">
            <div class="card-header">
                <h3><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="14" x2="8" y2="14"/><line x1="12" y1="14" x2="12" y2="14"/></svg> Operation Details</h3>
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
                            <option value="1" <?= (isset($_POST['theatre_number']) && $_POST['theatre_number'] == 1) ? 'selected' : '' ?>>Theatre 1 – General</option>
                            <option value="2" <?= (isset($_POST['theatre_number']) && $_POST['theatre_number'] == 2) ? 'selected' : '' ?>>Theatre 2 – Emergency</option>
                            <option value="3" <?= (isset($_POST['theatre_number']) && $_POST['theatre_number'] == 3) ? 'selected' : '' ?>>Theatre 3 – Labour</option>
                            <option value="4" <?= (isset($_POST['theatre_number']) && $_POST['theatre_number'] == 4) ? 'selected' : '' ?>>Theatre 4 – Minor</option>
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
                    <div style="font-size:13px;font-weight:700;color:var(--text-mid);margin-bottom:14px;text-transform:uppercase;letter-spacing:0.5px"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="12" y1="11" x2="12" y2="15"/><line x1="10" y1="13" x2="14" y2="13"/></svg> Surgical Team</div>
                    <div class="form-row">
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label">Lead Surgeon <span style="color:var(--danger)">*</span></label>
                            <select name="surgeon_id" class="form-control" required>
                                <option value="">— Select Surgeon —</option>
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?= $d['user_id'] ?>"
                                        <?= (isset($_POST['surgeon_id']) && $_POST['surgeon_id'] == $d['user_id']) ? 'selected' : '' ?>>
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
                                    <option value="<?= $d['user_id'] ?>"
                                        <?= (isset($_POST['anaesthetist_id']) && $_POST['anaesthetist_id'] == $d['user_id']) ? 'selected' : '' ?>>
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
                                <option value="<?= $d['user_id'] ?>"
                                    <?= (isset($_POST['assistant_id']) && $_POST['assistant_id'] == $d['user_id']) ? 'selected' : '' ?>>
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
                    <button type="submit" class="btn btn-primary btn-lg"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><circle cx="12" cy="8" r="3"/><path d="M12 11v10M8 21h8M6 15h12M17 5l2-2M15 3l4 4"/></svg> Schedule Operation</button>
                    <a href="theatre.php" class="btn btn-secondary btn-lg">Cancel</a>
                </div>

            </form>
        </div>

        <!-- Side Info Panel -->
        <div style="display:flex;flex-direction:column;gap:16px">

            <!-- Theatre Guide -->
            <div class="card">
                <div class="card-header" style="margin-bottom:14px;padding-bottom:12px">
                    <h3><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><line x1="12" y1="12" x2="12" y2="18"/><line x1="9" y1="15" x2="15" y2="15"/></svg> Theatre Guide</h3>
                </div>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <?php
                    $theatreInfo = [
                        ['num'=>1,'icon'=>'<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><line x1="12" y1="12" x2="12" y2="18"/><line x1="9" y1="15" x2="15" y2="15"/></svg>','name'=>'Theatre 1','desc'=>'General surgery, orthopaedics, urology'],
                        ['num'=>2,'icon'=>'<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>','name'=>'Theatre 2','desc'=>'Emergency and trauma operations'],
                        ['num'=>3,'icon'=>'<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M9 12h.01M15 12h.01M10 16c.5.3 1.1.5 2 .5s1.5-.2 2-.5"/><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z"/></svg>','name'=>'Theatre 3','desc'=>'Labour, maternity and C-sections'],
                        ['num'=>4,'icon'=>'<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>','name'=>'Theatre 4','desc'=>'Minor procedures and day surgery'],
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
                resultEl.innerHTML = `<div class="alert alert-success"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><polyline points="20 6 9 17 4 12"/></svg> Theatre ${t} is available on ${d} at ${tm}.</div>`;
            } else {
                resultEl.innerHTML = `<div class="alert alert-error"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Theatre ${t} is already booked at this time. Please choose another slot.</div>`;
            }
        })
        .catch(() => { resultEl.style.display='none'; });
}

theatreEl.addEventListener('change', checkAvailability);
dateEl.addEventListener('change', checkAvailability);
timeEl.addEventListener('change', checkAvailability);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>