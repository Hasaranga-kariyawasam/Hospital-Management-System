<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../includes/auth_guard.php';

requireRole(['admin', 'doctor']);

$pageTitle  = 'Schedule Operation';
$useSidebar = true;

$errors  = [];
$success = false;
$editId  = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$isEdit  = $editId > 0;

$theatres = ['Theatre 1', 'Theatre 2', 'Labour Theatre', 'Emergency Theatre'];
$opTypes  = [
    'Appendectomy', 'Cholecystectomy', 'Hernia Repair', 'C-Section',
    'Normal Delivery', 'Knee Replacement', 'Hip Replacement',
    'Coronary Bypass', 'Cataract Surgery', 'Spinal Surgery',
    'Tonsillectomy', 'Hysterectomy', 'Prostatectomy', 'Thyroidectomy',
    'Craniotomy', 'Emergency Surgery', 'Other',
];

// ── Fetch doctors ─────────────────────────────────────────────────
$doctors = $pdo->query("
    SELECT u.user_id, u.full_name, u.department
    FROM users u
    WHERE u.role = 'doctor' AND u.status = 'active'
    ORDER BY u.full_name
")->fetchAll();

// ── Fetch active patients ─────────────────────────────────────────
$patients = $pdo->query("
    SELECT p.patient_id, u.full_name, p.nic
    FROM patients p
    JOIN users u ON p.user_id = u.user_id
    WHERE u.status = 'active'
    ORDER BY u.full_name
")->fetchAll();

// ── Load existing record for edit ─────────────────────────────────
$op = null;
if ($isEdit) {
    $s = $pdo->prepare("SELECT * FROM theatre_operations WHERE operation_id = ?");
    $s->execute([$editId]);
    $op = $s->fetch();
    if (!$op) { header('Location: theatre_schedule.php'); exit(); }
    if (in_array($op['status'], ['Completed', 'Cancelled', 'Transferred'])) {
        header('Location: operation_details.php?id=' . $editId);
        exit();
    }
}

// ── POST handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId    = (int)($_POST['patient_id']          ?? 0);
    $surgeonId    = (int)($_POST['lead_surgeon_id']     ?? 0);
    $anaestId     = (int)($_POST['anaesthetist_id']     ?? 0);
    $assistId     = (int)($_POST['assistant_doctor_id'] ?? 0) ?: null;
    $opType       = trim($_POST['operation_type']       ?? '');
    $opTypeCustom = trim($_POST['operation_type_custom']?? '');
    $theatre      = trim($_POST['theatre_number']       ?? '');
    $date         = trim($_POST['scheduled_date']       ?? '');
    $time         = trim($_POST['scheduled_time']       ?? '');
    $duration     = (int)($_POST['duration_minutes']    ?? 60);
    $preNotes     = trim($_POST['pre_op_notes']         ?? '');
    $isMaternity  = isset($_POST['is_maternity']) ? 1 : 0;

    // Final operation type
    if ($opType === 'Other') $opType = $opTypeCustom;

    // Validation
    if (!$patientId)                        $errors[] = 'Please select a patient.';
    if (!$surgeonId)                        $errors[] = 'Please select a lead surgeon.';
    if (!$anaestId)                         $errors[] = 'Please select an anaesthesiologist.';
    if ($surgeonId && $surgeonId === $anaestId) $errors[] = 'Lead surgeon and anaesthesiologist must be different.';
    if ($opType === '')                     $errors[] = 'Operation type is required.';
    if (!in_array($theatre, $theatres))    $errors[] = 'Please select a valid theatre.';
    if ($date === '')                       $errors[] = 'Scheduled date is required.';
    if ($time === '')                       $errors[] = 'Scheduled time is required.';
    if ($date < date('Y-m-d'))             $errors[] = 'Scheduled date cannot be in the past.';

    // ── Double-booking check ──────────────────────────────────────
    if (empty($errors)) {
        $slotEnd = date('H:i:s', strtotime($time) + ($duration * 60));
        $sql = "
            SELECT operation_id FROM theatre_operations
            WHERE theatre_number  = :theatre
              AND scheduled_date  = :date
              AND status NOT IN ('Cancelled')
              AND (
                  (scheduled_time <= :time AND ADDTIME(scheduled_time, SEC_TO_TIME(duration_minutes*60)) > :time)
                  OR
                  (scheduled_time >= :time AND scheduled_time < :slot_end)
              )
        ";
        $params = [
            ':theatre'  => $theatre,
            ':date'     => $date,
            ':time'     => $time,
            ':slot_end' => $slotEnd,
        ];
        if ($isEdit) {
            $sql .= " AND operation_id != :edit_id";
            $params[':edit_id'] = $editId;
        }
        $conflict = $pdo->prepare($sql);
        $conflict->execute($params);
        if ($conflict->fetch()) {
            $errors[] = "⚠️ Double-booking detected! {$theatre} is already reserved at that time. Please choose a different theatre or time.";
        }
    }

    // ── Save ─────────────────────────────────────────────────────
    if (empty($errors)) {
        $fields = [
            'patient_id'          => $patientId,
            'lead_surgeon_id'     => $surgeonId,
            'anaesthetist_id'     => $anaestId,
            'assistant_doctor_id' => $assistId,
            'operation_type'      => $opType,
            'theatre_number'      => $theatre,
            'scheduled_date'      => $date,
            'scheduled_time'      => $time,
            'duration_minutes'    => $duration,
            'pre_op_notes'        => $preNotes,
            'is_maternity'        => $isMaternity,
        ];

        if ($isEdit) {
            $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($fields)));
            $stmt = $pdo->prepare("UPDATE theatre_operations SET $sets WHERE operation_id = :operation_id");
            $fields['operation_id'] = $editId;
            $stmt->execute($fields);
            header('Location: operation_details.php?id=' . $editId . '&saved=1');
        } else {
            $fields['status']     = 'Scheduled';
            $fields['created_by'] = $_SESSION['user_id'];
            $cols = implode(', ', array_keys($fields));
            $vals = implode(', ', array_map(fn($k) => ":$k", array_keys($fields)));
            $stmt = $pdo->prepare("INSERT INTO theatre_operations ($cols) VALUES ($vals)");
            $stmt->execute($fields);
            $newId = (int)$pdo->lastInsertId();
            header('Location: operation_details.php?id=' . $newId . '&created=1');
        }
        exit();
    }
}

// Populate form values (from POST or existing record)
$fv = fn(string $key, mixed $default = '') =>
    htmlspecialchars((string)($_POST[$key] ?? $op[$key] ?? $default));

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= $isEdit ? '✏️ Edit Operation' : '➕ Schedule Operation' ?></h1>
        <p class="page-sub">
            <?= $isEdit ? 'Update the operation details below.' : 'Fill in all required fields to schedule a surgery.' ?>
        </p>
    </div>
    <a href="theatre_schedule.php" class="btn btn-secondary">← Back to Schedule</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" style="margin-bottom:20px">
        <?php foreach ($errors as $e): ?>
            <div>⚠️ <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST" id="opForm" novalidate>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

        <!-- LEFT COLUMN -->
        <div>
            <!-- Patient & Operation -->
            <div class="card" style="margin-bottom:20px">
                <div class="card-header"><h3>👤 Patient & Operation</h3></div>
                <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

                    <div class="form-group">
                        <label class="form-label">Patient <span style="color:var(--danger)">*</span></label>
                        <select name="patient_id" class="form-control" required>
                            <option value="">— Select Patient —</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['patient_id'] ?>"
                                    <?= (string)($op['patient_id'] ?? $_POST['patient_id'] ?? '') === (string)$p['patient_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['full_name']) ?> (NIC: <?= htmlspecialchars($p['nic']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Operation Type <span style="color:var(--danger)">*</span></label>
                        <select name="operation_type" class="form-control" id="opTypeSelect" required>
                            <option value="">— Select Type —</option>
                            <?php
                            $currentType = $op['operation_type'] ?? $_POST['operation_type'] ?? '';
                            foreach ($opTypes as $t):
                                $sel = $currentType === $t ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= $sel ?>><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                            <?php if ($currentType && !in_array($currentType, $opTypes)): ?>
                                <option value="Other" selected>Other</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group" id="customTypeGroup"
                         style="display:<?= (!in_array($currentType, $opTypes) && $currentType) ? 'block' : 'none' ?>">
                        <label class="form-label">Specify Operation Type <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="operation_type_custom" class="form-control"
                               placeholder="Enter custom operation type"
                               value="<?= !in_array($currentType, $opTypes) ? htmlspecialchars($currentType) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer">
                            <input type="checkbox" name="is_maternity" value="1"
                                   <?= ($op['is_maternity'] ?? $_POST['is_maternity'] ?? 0) ? 'checked' : '' ?>>
                            🤱 This is a Maternity / Labour case
                        </label>
                        <small style="color:var(--muted)">Check if this operation is linked to childbirth.</small>
                    </div>
                </div>
            </div>

            <!-- Pre-Op Notes -->
            <div class="card">
                <div class="card-header"><h3>📋 Pre-Op Notes</h3></div>
                <div style="padding:20px">
                    <textarea name="pre_op_notes" class="form-control" rows="5"
                              placeholder="Patient pre-operative condition, allergies, medications, special instructions..."><?= $fv('pre_op_notes') ?></textarea>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div>
            <!-- Theatre & Time -->
            <div class="card" style="margin-bottom:20px">
                <div class="card-header"><h3>🏨 Theatre & Schedule</h3></div>
                <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

                    <div class="form-group">
                        <label class="form-label">Theatre <span style="color:var(--danger)">*</span></label>
                        <select name="theatre_number" class="form-control" required>
                            <option value="">— Select Theatre —</option>
                            <?php foreach ($theatres as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>"
                                    <?= $fv('theatre_number') === $t ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Date <span style="color:var(--danger)">*</span></label>
                            <input type="date" name="scheduled_date" class="form-control"
                                   min="<?= date('Y-m-d') ?>"
                                   value="<?= $fv('scheduled_date') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Time <span style="color:var(--danger)">*</span></label>
                            <input type="time" name="scheduled_time" class="form-control"
                                   value="<?= $fv('scheduled_time') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Estimated Duration (minutes)</label>
                        <input type="number" name="duration_minutes" class="form-control"
                               min="15" max="600" step="15"
                               value="<?= $fv('duration_minutes', '60') ?>">
                    </div>

                    <!-- Live availability checker -->
                    <div id="availabilityResult" style="display:none;padding:10px;border-radius:6px;font-size:13px"></div>
                </div>
            </div>

            <!-- Medical Team -->
            <div class="card">
                <div class="card-header"><h3>👨‍⚕️ Medical Team</h3></div>
                <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

                    <div class="form-group">
                        <label class="form-label">Lead Surgeon <span style="color:var(--danger)">*</span></label>
                        <select name="lead_surgeon_id" class="form-control" required>
                            <option value="">— Select Surgeon —</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?= $d['user_id'] ?>"
                                    <?= $fv('lead_surgeon_id') === (string)$d['user_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['full_name']) ?>
                                    <?= $d['department'] ? '(' . htmlspecialchars($d['department']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Anaesthesiologist <span style="color:var(--danger)">*</span></label>
                        <select name="anaesthetist_id" class="form-control" required>
                            <option value="">— Select Anaesthesiologist —</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?= $d['user_id'] ?>"
                                    <?= $fv('anaesthetist_id') === (string)$d['user_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Assisting Doctor <span style="color:var(--muted)">Optional</span></label>
                        <select name="assistant_doctor_id" class="form-control">
                            <option value="">— None —</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?= $d['user_id'] ?>"
                                    <?= $fv('assistant_doctor_id') === (string)$d['user_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit -->
    <div style="display:flex;gap:12px;margin-top:24px;justify-content:flex-end">
        <a href="theatre_schedule.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary btn-lg">
            <?= $isEdit ? '💾 Save Changes' : '✅ Schedule Operation' ?>
        </button>
    </div>
</form>

<script>
// Show/hide custom operation type input
document.getElementById('opTypeSelect').addEventListener('change', function() {
    const g = document.getElementById('customTypeGroup');
    g.style.display = this.value === 'Other' ? 'block' : 'none';
});

// Live theatre availability check
const theatreEl = document.querySelector('[name=theatre_number]');
const dateEl    = document.querySelector('[name=scheduled_date]');
const timeEl    = document.querySelector('[name=scheduled_time]');
const resultDiv = document.getElementById('availabilityResult');

async function checkAvailability() {
    const theatre = theatreEl.value;
    const date    = dateEl.value;
    const time    = timeEl.value;
    const dur     = document.querySelector('[name=duration_minutes]').value || 60;
    if (!theatre || !date || !time) return;

    const params = new URLSearchParams({ theatre, date, time, duration: dur, edit: '<?= $editId ?>' });
    try {
        const resp = await fetch('check_availability.php?' + params);
        const data = await resp.json();
        resultDiv.style.display = 'block';
        if (data.available) {
            resultDiv.style.background = '#d1fae5';
            resultDiv.style.color = '#065f46';
            resultDiv.innerHTML = '✅ ' + theatre + ' is available at the selected time.';
        } else {
            resultDiv.style.background = '#fee2e2';
            resultDiv.style.color = '#991b1b';
            resultDiv.innerHTML = '❌ Conflict detected! This theatre is already booked at this time.';
        }
    } catch(e) {}
}

[theatreEl, dateEl, timeEl].forEach(el => el?.addEventListener('change', checkAvailability));
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
