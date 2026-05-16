<?php
declare(strict_types=1);
// modules/ward/doctor_admissions.php
// Doctor: Review pending admission requests, approve/reject, set room type + meal

session_start();
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole(['doctor']);

$pageTitle  = 'Admission Requests';
$useSidebar = true;
$pageCss    = '/Web/Hospital-Management-System/modules/ward/ward.css';

$msg   = '';
$error = '';

// ──────────────────────────────────────────────
// POST HANDLER — Approve or Reject
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']     ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);

    // Get doctor_id from session
    $myDoctor = $pdo->prepare("SELECT doctor_id FROM doctors WHERE user_id=?");
    $myDoctor->execute([$_SESSION['user_id']]);
    $doctorId = $myDoctor->fetchColumn();

    if ($action === 'approve' && $requestId) {
        $roomType    = $_POST['approved_room_type'] ?? '';
        $mealReq     = (int)($_POST['meal_required'] ?? 1);
        $mealType    = $_POST['meal_type'] ?? null;
        $mealNotes   = trim($_POST['meal_notes'] ?? '');
        $priority    = $_POST['priority_level'] ?? 'normal';
        $doctorNotes = trim($_POST['doctor_notes'] ?? '');

        if ($roomType) {
            $stmt = $pdo->prepare("
                UPDATE admission_requests SET
                    status = 'approved',
                    approved_room_type = ?,
                    meal_required = ?,
                    meal_type = ?,
                    meal_notes = ?,
                    priority_level = ?,
                    doctor_notes = ?,
                    reviewed_at = NOW()
                WHERE request_id = ? AND assigned_doctor_id = ?
            ");
            $stmt->execute([$roomType, $mealReq, $mealType ?: null, $mealNotes, $priority, $doctorNotes, $requestId, $doctorId]);
            $msg = '✅ Admission request approved. Reception will be notified to assign a room.';
        } else {
            $error = '❌ Please select a room type before approving.';
        }
    }

    if ($action === 'reject' && $requestId) {
        $doctorNotes = trim($_POST['reject_reason'] ?? 'Not required');
        $pdo->prepare("
            UPDATE admission_requests SET status='rejected', doctor_notes=?, reviewed_at=NOW()
            WHERE request_id=? AND assigned_doctor_id=?
        ")->execute([$doctorNotes, $requestId, $doctorId]);
        $msg = '✅ Request marked as not required. Patient does not need admission.';
    }
}

// ──────────────────────────────────────────────
// FETCH — This doctor's pending requests
// ──────────────────────────────────────────────
$myDoctorStmt = $pdo->prepare("SELECT doctor_id FROM doctors WHERE user_id=?");
$myDoctorStmt->execute([$_SESSION['user_id']]);
$myDoctorId = $myDoctorStmt->fetchColumn();

$pendingRequests = [];
$approvedList    = [];
$rejectedList    = [];

if ($myDoctorId) {
    $pendingRequests = $pdo->prepare("
        SELECT ar.*, u_p.full_name AS patient_name, p.nic, p.dob,
               p.gender, p.blood_type, p.phone
        FROM admission_requests ar
        JOIN patients p  ON ar.patient_id = p.patient_id
        JOIN users u_p   ON p.user_id = u_p.user_id
        WHERE ar.assigned_doctor_id = ? AND ar.status = 'pending'
        ORDER BY ar.created_at DESC
    ");
    $pendingRequests->execute([$myDoctorId]);
    $pendingRequests = $pendingRequests->fetchAll();

    $approvedList = $pdo->prepare("
        SELECT ar.*, u_p.full_name AS patient_name, r.room_number, r.room_type
        FROM admission_requests ar
        JOIN patients p   ON ar.patient_id = p.patient_id
        JOIN users u_p    ON p.user_id = u_p.user_id
        LEFT JOIN rooms r ON ar.room_id = r.room_id
        WHERE ar.assigned_doctor_id = ? AND ar.status IN ('approved','admitted')
        ORDER BY ar.reviewed_at DESC LIMIT 20
    ");
    $approvedList->execute([$myDoctorId]);
    $approvedList = $approvedList->fetchAll();

    $rejectedList = $pdo->prepare("
        SELECT ar.*, u_p.full_name AS patient_name
        FROM admission_requests ar
        JOIN patients p ON ar.patient_id = p.patient_id
        JOIN users u_p  ON p.user_id = u_p.user_id
        WHERE ar.assigned_doctor_id = ? AND ar.status = 'rejected'
        ORDER BY ar.reviewed_at DESC LIMIT 10
    ");
    $rejectedList->execute([$myDoctorId]);
    $rejectedList = $rejectedList->fetchAll();
}

$tab = $_GET['tab'] ?? 'pending';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <div class="page-header-title">
            <h2>🏥 Patient Admission Requests</h2>
            <p>Review and approve patient admission requests from Reception</p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="ward-tabs">
        <button class="ward-tab <?= $tab==='pending'?'active':'' ?>" onclick="showTab('pending')">
            ⏳ Pending Review
            <span class="badge"><?= count($pendingRequests) ?></span>
        </button>
        <button class="ward-tab <?= $tab==='approved'?'active':'' ?>" onclick="showTab('approved')">
            ✅ Approved / Admitted
        </button>
        <button class="ward-tab <?= $tab==='rejected'?'active':'' ?>" onclick="showTab('rejected')">
            ❌ Rejected
        </button>
    </div>

    <!-- TAB: PENDING -->
    <div id="tab-pending" class="tab-content <?= $tab==='pending'?'active':'' ?>">
        <?php if ($pendingRequests): ?>
            <?php foreach ($pendingRequests as $req): ?>
            <div class="patient-request-card">
                <div class="request-patient-info">
                    <div class="request-patient-name"><?= htmlspecialchars($req['patient_name']) ?></div>
                    <div class="request-patient-meta">
                        NIC: <?= htmlspecialchars($req['nic']) ?> |
                        Gender: <?= ucfirst($req['gender']) ?> |
                        Blood Type: <?= $req['blood_type'] ?? 'Unknown' ?> |
                        Phone: <?= htmlspecialchars($req['phone']) ?>
                    </div>
                    <div class="request-reason">
                        <strong>Admission Reason:</strong> <?= htmlspecialchars($req['admission_reason']) ?>
                    </div>
                    <?php if ($req['request_notes']): ?>
                        <div class="request-notes">
                            <strong>Reception Note:</strong> <?= htmlspecialchars($req['request_notes']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="request-time">Requested: <?= date('d M Y, g:i A', strtotime($req['created_at'])) ?></div>
                </div>

                <!-- APPROVE FORM -->
                <form method="POST" class="decision-form">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">

                    <div class="decision-grid">
                        <div class="form-group">
                            <label>Room Type Required *</label>
                            <select name="approved_room_type" required class="form-control">
                                <option value="">-- Select --</option>
                                <option value="general_ward">General Ward</option>
                                <option value="semi_private">Semi-Private Room</option>
                                <option value="private">Private Room</option>
                                <option value="children">Children's Room</option>
                                <option value="icu">ICU</option>
                                <option value="maternity">Maternity Room</option>
                                <option value="recovery">Post-Op Recovery</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Priority Level</label>
                            <select name="priority_level" class="form-control">
                                <option value="normal">Normal</option>
                                <option value="urgent">Urgent</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Hospital Meal Required?</label>
                            <select name="meal_required" class="form-control">
                                <option value="1">Yes — Provide Hospital Meal</option>
                                <option value="0">No — Patient arranges own food</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Meal / Diet Type</label>
                            <select name="meal_type" class="form-control">
                                <option value="">-- Select Meal Type --</option>
                                <option value="hospital_standard">Hospital Standard Meal</option>
                                <option value="doctor_prescribed">Doctor-Prescribed Diet</option>
                                <option value="liquid">Liquid Diet</option>
                                <option value="diabetic">Diabetic Diet</option>
                                <option value="low_salt">Low Salt Diet</option>
                                <option value="no_meal">No Meal Required</option>
                                <option value="outside_food">Outside Food Allowed</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Diet / Meal Notes</label>
                        <input type="text" name="meal_notes" class="form-control" placeholder="e.g. No pork, extra fruit, lactose-free...">
                    </div>
                    <div class="form-group">
                        <label>Doctor's Notes for Reception</label>
                        <textarea name="doctor_notes" rows="2" class="form-control" placeholder="Any special instructions for reception or nursing staff..."></textarea>
                    </div>
                    <div class="decision-buttons">
                        <button type="submit" class="btn btn-success">✅ Approve Admission</button>
                    </div>
                </form>

                <!-- REJECT FORM -->
                <form method="POST" class="reject-form">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                    <div class="reject-inline">
                        <input type="text" name="reject_reason" class="form-control" placeholder="Reason for rejection (e.g. outpatient treatment sufficient)">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Reject this admission request?')">❌ Not Required</button>
                    </div>
                </form>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state-box">🎉 No pending admission requests for you right now.</div>
        <?php endif; ?>
    </div>

    <!-- TAB: APPROVED -->
    <div id="tab-approved" class="tab-content <?= $tab==='approved'?'active':'' ?>">
        <h3 class="section-title">Approved / Admitted Patients</h3>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>Patient</th><th>Status</th><th>Room Type</th><th>Meal</th><th>Priority</th><th>Notes</th><th>Reviewed</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($approvedList as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['patient_name']) ?></td>
                        <td><span class="status-badge <?= $a['status'] ?>"><?= strtoupper($a['status']) ?></span></td>
                        <td><?= str_replace('_',' ',strtoupper($a['approved_room_type']??'')) ?></td>
                        <td><?= $a['meal_type'] ? str_replace('_',' ',ucfirst($a['meal_type'])) : '—' ?></td>
                        <td><span class="priority-badge <?= $a['priority_level'] ?>"><?= strtoupper($a['priority_level']) ?></span></td>
                        <td><?= htmlspecialchars(substr($a['doctor_notes']??'',0,60)) ?></td>
                        <td><?= $a['reviewed_at'] ? date('d M Y', strtotime($a['reviewed_at'])) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$approvedList): ?><tr><td colspan="7" class="empty-state">No records.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: REJECTED -->
    <div id="tab-rejected" class="tab-content <?= $tab==='rejected'?'active':'' ?>">
        <h3 class="section-title">Rejected Requests</h3>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>Patient</th><th>Admission Reason</th><th>Rejection Reason</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rejectedList as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['patient_name']) ?></td>
                        <td><?= htmlspecialchars(substr($r['admission_reason'],0,60)) ?>…</td>
                        <td><?= htmlspecialchars($r['doctor_notes'] ?? '—') ?></td>
                        <td><?= $r['reviewed_at'] ? date('d M Y', strtotime($r['reviewed_at'])) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$rejectedList): ?><tr><td colspan="4" class="empty-state">No rejected requests.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.ward-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.target.classList.add('active');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
