<?php
declare(strict_types=1);
// modules/ward/reception_ward.php
// Reception: Create admission requests + assign rooms to approved patients

session_start();
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole(['reception']);

$pageTitle  = 'Ward & Admissions';
$useSidebar = true;
$pageCss    = '/Web/Hospital-Management-System/modules/ward/ward.css';

$msg   = '';
$error = '';
$tab   = $_GET['tab'] ?? 'requests';  // requests | assign | inpatients | discharge

// ──────────────────────────────────────────────
// POST HANDLERS
// ──────────────────────────────────────────────

// 1) Create new admission request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_request') {
    $patientId  = (int)($_POST['patient_id'] ?? 0);
    $doctorId   = (int)($_POST['doctor_id'] ?? 0);
    $reason     = trim($_POST['admission_reason'] ?? '');
    $notes      = trim($_POST['request_notes'] ?? '');

    if ($patientId && $doctorId && $reason) {
        $stmt = $pdo->prepare("
            INSERT INTO admission_requests
                (patient_id, requested_by, assigned_doctor_id, admission_reason, request_notes, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$patientId, $_SESSION['user_id'], $doctorId, $reason, $notes]);
        $msg = '✅ Admission request submitted. Waiting for doctor approval.';
    } else {
        $error = '❌ Please fill all required fields.';
    }
}

// 2) Assign room to an approved request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_room') {
    $requestId  = (int)($_POST['request_id'] ?? 0);
    $roomId     = (int)($_POST['room_id'] ?? 0);
    $bedId      = ($_POST['bed_id'] ?? '') ? (int)$_POST['bed_id'] : null;
    $advancePaid= (float)($_POST['advance_paid'] ?? 0);

    if ($requestId && $roomId) {
        $pdo->beginTransaction();
        try {
            // Get request info
            $req = $pdo->prepare("SELECT * FROM admission_requests WHERE request_id=? AND status='approved'");
            $req->execute([$requestId]);
            $reqData = $req->fetch();

            if ($reqData) {
                // Create admission record
                $ins = $pdo->prepare("
                    INSERT INTO admissions
                        (patient_id, room_id, bed_id, doctor_id, admitted_by,
                         admission_date, dietary_notes, meal_required, meal_type,
                         priority_level, advance_paid, request_id, status)
                    VALUES (?,?,?,?,?, CURDATE(), ?,?,?, ?, ?, ?, 'admitted')
                ");
                $ins->execute([
                    $reqData['patient_id'], $roomId, $bedId, $reqData['assigned_doctor_id'],
                    $_SESSION['user_id'],
                    $reqData['meal_notes'], $reqData['meal_required'], $reqData['meal_type'],
                    $reqData['priority_level'], $advancePaid, $requestId
                ]);
                $admissionId = $pdo->lastInsertId();

                // Update room availability
                $pdo->prepare("UPDATE rooms SET is_available=0 WHERE room_id=?")->execute([$roomId]);
                if ($bedId) {
                    $pdo->prepare("UPDATE beds SET status='occupied' WHERE bed_id=?")->execute([$bedId]);
                }

                // Update request status
                $pdo->prepare("UPDATE admission_requests SET status='admitted', room_id=?, bed_id=?, admission_id=? WHERE request_id=?")
                    ->execute([$roomId, $bedId, $admissionId, $requestId]);

                $pdo->commit();
                $msg = '✅ Patient admitted and room assigned successfully.';
                $tab = 'inpatients';
            } else {
                $pdo->rollBack();
                $error = '❌ Request not found or not yet approved.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = '❌ Error: ' . $e->getMessage();
        }
    }
}

// 3) Discharge patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'discharge') {
    $admissionId = (int)($_POST['admission_id'] ?? 0);
    if ($admissionId) {
        $pdo->beginTransaction();
        try {
            // Get admission
            $adm = $pdo->prepare("SELECT * FROM admissions WHERE admission_id=? AND status='admitted'");
            $adm->execute([$admissionId]);
            $admData = $adm->fetch();

            if ($admData) {
                // Calculate stay + bill
                $admDate    = new DateTime($admData['admission_date']);
                $today      = new DateTime();
                $days       = max(1, (int)$admDate->diff($today)->days);

                $roomRate   = $pdo->prepare("SELECT daily_rate FROM rooms WHERE room_id=?");
                $roomRate->execute([$admData['room_id']]);
                $rate       = (float)($roomRate->fetchColumn() ?? 0);
                $roomCharge = $days * $rate;

                // Update admission
                $pdo->prepare("UPDATE admissions SET status='discharged', discharge_date=CURDATE() WHERE admission_id=?")
                    ->execute([$admissionId]);

                // Free room/bed
                $pdo->prepare("UPDATE rooms SET is_available=1 WHERE room_id=?")->execute([$admData['room_id']]);
                if ($admData['bed_id']) {
                    $pdo->prepare("UPDATE beds SET status='available' WHERE bed_id=?")->execute([$admData['bed_id']]);
                }

                // Create invoice
                $invStmt = $pdo->prepare("INSERT INTO billing_invoices (patient_id, total_amount, paid_amount, status) VALUES (?,?,?,'open')");
                $invStmt->execute([$admData['patient_id'], $roomCharge, $admData['advance_paid']]);
                $invoiceId = $pdo->lastInsertId();

                // Add room charge line item
                $pdo->prepare("INSERT INTO billing_items (invoice_id, description, category, unit_price, quantity) VALUES (?,?,?,?,?)")
                    ->execute([$invoiceId, "Room Charge ({$days} days)", 'room', $rate, $days]);

                $pdo->prepare("UPDATE admissions SET invoice_id=? WHERE admission_id=?")->execute([$invoiceId, $admissionId]);

                $pdo->commit();
                $balance = $roomCharge - $admData['advance_paid'];
                $msg = "✅ Patient discharged. Room charge: LKR " . number_format($roomCharge, 2) . ". Balance due: LKR " . number_format($balance, 2) . ".";
                $tab = 'discharge';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = '❌ Error: ' . $e->getMessage();
        }
    }
}

// ──────────────────────────────────────────────
// FETCH DATA
// ──────────────────────────────────────────────

// All patients for dropdown
$allPatients = $pdo->query("
    SELECT p.patient_id, u.full_name, p.nic
    FROM patients p JOIN users u ON p.user_id=u.user_id
    ORDER BY u.full_name
")->fetchAll();

// All doctors for dropdown
$allDoctors = $pdo->query("
    SELECT d.doctor_id, u.full_name, d.specialization
    FROM doctors d JOIN users u ON d.user_id=u.user_id
    ORDER BY u.full_name
")->fetchAll();

// Pending requests (my submissions)
$pendingRequests = $pdo->query("
    SELECT ar.*, u_p.full_name AS patient_name, p.nic,
           u_d.full_name AS doctor_name, d.specialization
    FROM admission_requests ar
    JOIN patients p     ON ar.patient_id = p.patient_id
    JOIN users u_p      ON p.user_id = u_p.user_id
    JOIN doctors d      ON ar.assigned_doctor_id = d.doctor_id
    JOIN users u_d      ON d.user_id = u_d.user_id
    WHERE ar.status IN ('pending','approved')
    ORDER BY ar.priority_level DESC, ar.created_at DESC
")->fetchAll();

// Available rooms
$availableRooms = $pdo->query("
    SELECT * FROM rooms WHERE is_available=1 ORDER BY room_type, room_number
")->fetchAll();

// Available beds (for semi-private/general)
$availableBeds = $pdo->query("
    SELECT b.*, r.room_number, r.room_type
    FROM beds b JOIN rooms r ON b.room_id=r.room_id
    WHERE b.status='available'
    ORDER BY r.room_number, b.bed_number
")->fetchAll();

// Current inpatients
$inpatients = $pdo->query("
    SELECT a.*, u_p.full_name AS patient_name, p.nic,
           u_d.full_name AS doctor_name,
           r.room_number, r.room_type, r.daily_rate,
           b.bed_number,
           DATEDIFF(CURDATE(), a.admission_date) AS days_stayed,
           ROUND(DATEDIFF(CURDATE(), a.admission_date) * r.daily_rate, 2) AS accrued_charge
    FROM admissions a
    JOIN patients p     ON a.patient_id = p.patient_id
    JOIN users u_p      ON p.user_id = u_p.user_id
    JOIN doctors d      ON a.doctor_id = d.doctor_id
    JOIN users u_d      ON d.user_id = u_d.user_id
    JOIN rooms r        ON a.room_id = r.room_id
    LEFT JOIN beds b    ON a.bed_id = b.bed_id
    WHERE a.status = 'admitted'
    ORDER BY a.admission_date DESC
")->fetchAll();

// Recent discharges
$recentDischarges = $pdo->query("
    SELECT a.*, u_p.full_name AS patient_name,
           r.room_number, r.room_type, bi.total_amount, bi.paid_amount, bi.balance
    FROM admissions a
    JOIN patients p     ON a.patient_id = p.patient_id
    JOIN users u_p      ON p.user_id = u_p.user_id
    JOIN rooms r        ON a.room_id = r.room_id
    LEFT JOIN billing_invoices bi ON a.invoice_id = bi.invoice_id
    WHERE a.status = 'discharged'
    ORDER BY a.discharge_date DESC
    LIMIT 20
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <div class="page-header-title">
            <h2>🏥 Ward &amp; Admissions</h2>
            <p>Manage patient admissions, room assignments and discharges</p>
        </div>
        <button class="btn btn-primary" onclick="showTab('requests'); document.getElementById('newRequestModal').style.display='flex'">
            + New Admission Request
        </button>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- TABS -->
    <div class="ward-tabs">
        <button class="ward-tab <?= $tab==='requests'?'active':'' ?>" onclick="showTab('requests')">
            📋 Requests
            <span class="badge"><?= count(array_filter($pendingRequests, fn($r)=>$r['status']==='pending')) ?></span>
        </button>
        <button class="ward-tab <?= $tab==='assign'?'active':'' ?>" onclick="showTab('assign')">
            🛏️ Assign Room
            <span class="badge badge-green"><?= count(array_filter($pendingRequests, fn($r)=>$r['status']==='approved')) ?></span>
        </button>
        <button class="ward-tab <?= $tab==='inpatients'?'active':'' ?>" onclick="showTab('inpatients')">
            🏥 Inpatients
            <span class="badge badge-blue"><?= count($inpatients) ?></span>
        </button>
        <button class="ward-tab <?= $tab==='discharge'?'active':'' ?>" onclick="showTab('discharge')">
            🚪 Discharge Log
        </button>
    </div>

    <!-- TAB: REQUESTS -->
    <div id="tab-requests" class="tab-content <?= $tab==='requests'?'active':'' ?>">
        <h3 class="section-title">Admission Requests</h3>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th><th>Patient</th><th>NIC</th><th>Doctor</th>
                        <th>Reason</th><th>Priority</th><th>Status</th><th>Room Type</th><th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingRequests as $r): ?>
                    <tr>
                        <td><?= $r['request_id'] ?></td>
                        <td><?= htmlspecialchars($r['patient_name']) ?></td>
                        <td><?= htmlspecialchars($r['nic']) ?></td>
                        <td><?= htmlspecialchars($r['doctor_name']) ?><br><small><?= htmlspecialchars($r['specialization']) ?></small></td>
                        <td><?= htmlspecialchars(substr($r['admission_reason'],0,60)) ?>…</td>
                        <td><span class="priority-badge <?= $r['priority_level'] ?>"><?= strtoupper($r['priority_level']) ?></span></td>
                        <td><span class="status-badge <?= $r['status'] ?>"><?= strtoupper($r['status']) ?></span></td>
                        <td><?= $r['approved_room_type'] ? str_replace('_',' ', strtoupper($r['approved_room_type'])) : '—' ?></td>
                        <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$pendingRequests): ?>
                        <tr><td colspan="9" class="empty-state">No pending admission requests.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: ASSIGN ROOM -->
    <div id="tab-assign" class="tab-content <?= $tab==='assign'?'active':'' ?>">
        <h3 class="section-title">Assign Room to Approved Patients</h3>
        <?php
        $approved = array_filter($pendingRequests, fn($r) => $r['status'] === 'approved');
        ?>
        <?php if ($approved): ?>
            <?php foreach ($approved as $r): ?>
            <div class="approval-card">
                <div class="approval-info">
                    <div class="approval-name"><?= htmlspecialchars($r['patient_name']) ?></div>
                    <div class="approval-meta">
                        Doctor: <strong><?= htmlspecialchars($r['doctor_name']) ?></strong> |
                        Requested Room: <strong><?= str_replace('_',' ',strtoupper($r['approved_room_type']??'Not specified')) ?></strong> |
                        Priority: <span class="priority-badge <?= $r['priority_level'] ?>"><?= strtoupper($r['priority_level']) ?></span>
                    </div>
                    <?php if ($r['doctor_notes']): ?>
                        <div class="doctor-note">📝 Doctor note: <?= htmlspecialchars($r['doctor_notes']) ?></div>
                    <?php endif; ?>
                    <div class="approval-meta">
                        Meal: <?= $r['meal_type'] ? str_replace('_',' ',strtoupper($r['meal_type'])) : '—' ?>
                        <?php if ($r['meal_notes']): ?> — <?= htmlspecialchars($r['meal_notes']) ?><?php endif; ?>
                    </div>
                </div>
                <form method="POST" class="assign-form">
                    <input type="hidden" name="action" value="assign_room">
                    <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Select Room *</label>
                            <select name="room_id" required onchange="loadBeds(this)">
                                <option value="">-- Choose Room --</option>
                                <?php foreach ($availableRooms as $room): ?>
                                    <option value="<?= $room['room_id'] ?>"
                                        data-type="<?= $room['room_type'] ?>"
                                        data-rate="<?= $room['daily_rate'] ?>">
                                        <?= htmlspecialchars($room['room_number']) ?> |
                                        <?= str_replace('_',' ',strtoupper($room['room_type'])) ?> |
                                        LKR <?= number_format($room['daily_rate'],0) ?>/day
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Select Bed (if shared room)</label>
                            <select name="bed_id">
                                <option value="">-- Whole Room / N/A --</option>
                                <?php foreach ($availableBeds as $bed): ?>
                                    <option value="<?= $bed['bed_id'] ?>" data-room="<?= $bed['room_id'] ?>">
                                        <?= htmlspecialchars($bed['room_number']) ?> — Bed <?= htmlspecialchars($bed['bed_number']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Advance Payment (LKR)</label>
                            <input type="number" name="advance_paid" min="0" step="0.01" value="0" class="form-control">
                        </div>
                    </div>
                    <div class="rate-preview" id="rate-preview-<?= $r['request_id'] ?>"></div>
                    <button type="submit" class="btn btn-success">✅ Admit &amp; Assign Room</button>
                </form>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state-box">No approved requests waiting for room assignment.</div>
        <?php endif; ?>
    </div>

    <!-- TAB: INPATIENTS -->
    <div id="tab-inpatients" class="tab-content <?= $tab==='inpatients'?'active':'' ?>">
        <h3 class="section-title">Current Inpatients (<?= count($inpatients) ?>)</h3>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Patient</th><th>Room / Bed</th><th>Type</th><th>Doctor</th>
                        <th>Admitted</th><th>Days</th><th>Accrued (LKR)</th><th>Meal</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inpatients as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['patient_name']) ?><br><small><?= htmlspecialchars($p['nic']) ?></small></td>
                        <td><?= htmlspecialchars($p['room_number']) ?><?= $p['bed_number'] ? ' — Bed '.$p['bed_number'] : '' ?></td>
                        <td><span class="room-type-badge"><?= str_replace('_',' ',strtoupper($p['room_type'])) ?></span></td>
                        <td><?= htmlspecialchars($p['doctor_name']) ?></td>
                        <td><?= date('d M Y', strtotime($p['admission_date'])) ?></td>
                        <td><?= (int)$p['days_stayed'] ?></td>
                        <td class="amount"><?= number_format((float)$p['accrued_charge'], 2) ?></td>
                        <td><?= $p['meal_type'] ? str_replace('_',' ',ucfirst($p['meal_type'])) : '—' ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Discharge this patient?')">
                                <input type="hidden" name="action" value="discharge">
                                <input type="hidden" name="admission_id" value="<?= $p['admission_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Discharge</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$inpatients): ?>
                        <tr><td colspan="9" class="empty-state">No current inpatients.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: DISCHARGE LOG -->
    <div id="tab-discharge" class="tab-content <?= $tab==='discharge'?'active':'' ?>">
        <h3 class="section-title">Recent Discharges</h3>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Patient</th><th>Room</th><th>Admitted</th><th>Discharged</th>
                        <th>Total Bill (LKR)</th><th>Paid (LKR)</th><th>Balance (LKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentDischarges as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['patient_name']) ?></td>
                        <td><?= htmlspecialchars($d['room_number']) ?> (<?= str_replace('_',' ',ucfirst($d['room_type'])) ?>)</td>
                        <td><?= date('d M Y', strtotime($d['admission_date'])) ?></td>
                        <td><?= date('d M Y', strtotime($d['discharge_date'])) ?></td>
                        <td class="amount"><?= number_format((float)$d['total_amount'], 2) ?></td>
                        <td class="amount"><?= number_format((float)$d['paid_amount'], 2) ?></td>
                        <td class="amount <?= ($d['balance']>0)?'text-danger':'' ?>"><?= number_format((float)$d['balance'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentDischarges): ?>
                        <tr><td colspan="7" class="empty-state">No discharge records yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- NEW REQUEST MODAL -->
<div id="newRequestModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-box">
        <div class="modal-header">
            <h3>📋 New Admission Request</h3>
            <button class="modal-close" onclick="document.getElementById('newRequestModal').style.display='none'">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_request">
            <div class="form-group">
                <label>Patient *</label>
                <select name="patient_id" required class="form-control">
                    <option value="">-- Search Patient --</option>
                    <?php foreach ($allPatients as $p): ?>
                        <option value="<?= $p['patient_id'] ?>"><?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['nic']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Assigned Doctor *</label>
                <select name="doctor_id" required class="form-control">
                    <option value="">-- Select Doctor --</option>
                    <?php foreach ($allDoctors as $d): ?>
                        <option value="<?= $d['doctor_id'] ?>"><?= htmlspecialchars($d['full_name']) ?> — <?= htmlspecialchars($d['specialization']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Admission Reason *</label>
                <textarea name="admission_reason" rows="3" required class="form-control" placeholder="Why does the patient need admission?"></textarea>
            </div>
            <div class="form-group">
                <label>Additional Notes</label>
                <textarea name="request_notes" rows="2" class="form-control" placeholder="Any extra notes for the doctor..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('newRequestModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.ward-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.target.classList.add('active');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
