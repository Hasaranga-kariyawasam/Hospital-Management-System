<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
$requiredRoles = ['doctor'];
require_once __DIR__ . '/../../includes/role_check.php';

$userId = (int)$_SESSION['user_id'];

// Get doctor_id
$doctorRow = $pdo->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
$doctorRow->execute([$userId]);
$doctorId = (int)($doctorRow->fetchColumn() ?? 0);

$success = '';
$error   = '';

// ── Handle approve/reject ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId  = (int)($_POST['request_id'] ?? 0);
    $action     = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $roomType    = $_POST['approved_room_type'] ?? '';
        $mealRequired = isset($_POST['meal_required']) ? 1 : 0;
        $mealType    = $_POST['meal_type'] ?? null;
        $mealNotes   = trim($_POST['meal_notes'] ?? '');
        $doctorNotes = trim($_POST['doctor_notes'] ?? '');
        $priority    = $_POST['priority_level'] ?? 'normal';

        if (!$roomType) {
            $error = 'Please select a room type.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE admission_requests SET
                    status             = 'approved',
                    approved_room_type = ?,
                    meal_required      = ?,
                    meal_type          = ?,
                    meal_notes         = ?,
                    doctor_notes       = ?,
                    priority_level     = ?,
                    reviewed_at        = NOW()
                WHERE request_id = ? AND assigned_doctor_id = ?
            ");
            $stmt->execute([$roomType, $mealRequired, $mealType ?: null, $mealNotes, $doctorNotes, $priority, $requestId, $doctorId]);
            $success = 'Request approved. Reception has been notified to assign a room.';
        }
    } elseif ($action === 'reject') {
        $doctorNotes = trim($_POST['doctor_notes'] ?? '');
        $stmt = $pdo->prepare("
            UPDATE admission_requests SET
                status       = 'rejected',
                doctor_notes = ?,
                reviewed_at  = NOW()
            WHERE request_id = ? AND assigned_doctor_id = ?
        ");
        $stmt->execute([$doctorNotes, $requestId, $doctorId]);
        $success = 'Request rejected. Reception has been notified.';
    }
}

// ── Pending requests for this doctor ─────────────────────────────────────
$pendingStmt = $pdo->prepare("
    SELECT
        ar.*,
        pu.full_name  AS patient_name,
        p.dob, p.gender, p.blood_type, p.phone,
        ru.full_name  AS reception_name
    FROM admission_requests ar
    JOIN patients p   ON p.patient_id = ar.patient_id
    JOIN users pu     ON pu.user_id   = p.user_id
    JOIN users ru     ON ru.user_id   = ar.requested_by
    WHERE ar.assigned_doctor_id = ? AND ar.status = 'pending'
    ORDER BY
        FIELD(ar.priority_level,'critical','urgent','normal'),
        ar.created_at ASC
");
$pendingStmt->execute([$doctorId]);
$pendingRequests = $pendingStmt->fetchAll();

// ── Reviewed requests ─────────────────────────────────────────────────────
$reviewedStmt = $pdo->prepare("
    SELECT
        ar.request_id, ar.status, ar.priority_level, ar.admission_reason,
        ar.approved_room_type, ar.doctor_notes, ar.reviewed_at,
        pu.full_name AS patient_name
    FROM admission_requests ar
    JOIN patients p ON p.patient_id = ar.patient_id
    JOIN users pu   ON pu.user_id   = p.user_id
    WHERE ar.assigned_doctor_id = ? AND ar.status IN ('approved','rejected','admitted')
    ORDER BY ar.reviewed_at DESC
    LIMIT 15
");
$reviewedStmt->execute([$doctorId]);
$reviewedRequests = $reviewedStmt->fetchAll();

$pageTitle  = 'Admission Requests';
$useSidebar = true;
include __DIR__ . '/../../includes/header.php';
?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="main-content">

    <div class="page-header">
        <div class="page-header-title">
            <h2>Admission Requests</h2>
            <p>Review and approve patient admission requests from reception</p>
        </div>
        <div>
            <span class="badge badge-warning"><?= count($pendingRequests) ?> Pending</span>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom:20px"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:20px"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Pending Requests -->
    <h3 style="margin-bottom:16px;font-size:16px">Pending Review (<?= count($pendingRequests) ?>)</h3>

    <?php if (empty($pendingRequests)): ?>
        <div class="card" style="padding:40px;text-align:center;color:var(--muted);margin-bottom:24px">
            No pending admission requests for you right now.
        </div>
    <?php endif; ?>

    <?php foreach ($pendingRequests as $r):
        $age = floor((time() - strtotime($r['dob'])) / (365.25 * 24 * 3600));
        $priorityColors = ['normal'=>['bg'=>'var(--success-light)','c'=>'var(--success)'],'urgent'=>['bg'=>'var(--warning-light)','c'=>'var(--warning)'],'critical'=>['bg'=>'var(--danger-light)','c'=>'var(--danger)']];
        $pc = $priorityColors[$r['priority_level']] ?? ['bg'=>'var(--border-light)','c'=>'var(--muted)'];
    ?>
    <div class="card" style="margin-bottom:20px;border-left:4px solid <?= $pc['c'] ?>">
        <div class="card-header">
            <div>
                <span style="font-weight:700;font-size:16px"><?= htmlspecialchars($r['patient_name']) ?></span>
                <span style="color:var(--muted);font-size:13px;margin-left:10px">
                    Age <?= $age ?> · <?= ucfirst($r['gender']) ?>
                    <?php if ($r['blood_type']): ?> · Blood <?= $r['blood_type'] ?><?php endif; ?>
                </span>
            </div>
            <div style="display:flex;gap:10px;align-items:center">
                <span style="background:<?= $pc['bg'] ?>;color:<?= $pc['c'] ?>;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:700;text-transform:uppercase"><?= $r['priority_level'] ?></span>
                <span style="color:var(--muted);font-size:12px"><?= date('M j, Y g:ia', strtotime($r['created_at'])) ?></span>
            </div>
        </div>
        <div style="padding:20px 24px">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                <div>
                    <div style="font-size:12px;color:var(--muted);font-weight:600;margin-bottom:4px">ADMISSION REASON</div>
                    <div style="font-size:14px"><?= nl2br(htmlspecialchars($r['admission_reason'])) ?></div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--muted);font-weight:600;margin-bottom:4px">REQUESTED BY</div>
                    <div style="font-size:14px"><?= htmlspecialchars($r['reception_name']) ?> (Reception)</div>
                    <?php if ($r['request_notes']): ?>
                    <div style="margin-top:6px;font-size:13px;color:var(--muted)"><?= htmlspecialchars($r['request_notes']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Approval Form -->
            <form method="POST" style="border-top:1px solid var(--border-light);padding-top:20px">
                <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px">

                    <div class="form-group" style="margin:0">
                        <label class="form-label">Room Type *</label>
                        <select name="approved_room_type" class="form-control" required>
                            <option value="">— Select —</option>
                            <option value="general_ward">General Ward</option>
                            <option value="semi_private">Semi-Private</option>
                            <option value="private">Private Room</option>
                            <option value="children">Children's Room</option>
                            <option value="icu">ICU</option>
                            <option value="maternity">Maternity</option>
                            <option value="recovery">Recovery Room</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin:0">
                        <label class="form-label">Priority</label>
                        <select name="priority_level" class="form-control">
                            <option value="normal"   <?= $r['priority_level']==='normal'   ? 'selected' : '' ?>>Normal</option>
                            <option value="urgent"   <?= $r['priority_level']==='urgent'   ? 'selected' : '' ?>>Urgent</option>
                            <option value="critical" <?= $r['priority_level']==='critical' ? 'selected' : '' ?>>Critical</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin:0">
                        <label class="form-label">Meal Type</label>
                        <select name="meal_type" class="form-control">
                            <option value="">— No Meal Required —</option>
                            <option value="hospital_standard">Hospital Standard</option>
                            <option value="doctor_prescribed">Doctor Prescribed</option>
                            <option value="liquid">Liquid Diet</option>
                            <option value="diabetic">Diabetic Diet</option>
                            <option value="low_salt">Low Salt Diet</option>
                            <option value="outside_food">Outside Food Allowed</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Meal / Dietary Notes</label>
                        <textarea name="meal_notes" class="form-control" rows="2" placeholder="e.g., low sugar, no dairy..."></textarea>
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Doctor Notes (visible to reception)</label>
                        <textarea name="doctor_notes" class="form-control" rows="2" placeholder="Notes or reason for rejection..."></textarea>
                    </div>
                </div>

                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <button type="submit" name="action" value="approve" class="btn btn-primary">Approve Admission</button>
                    <button type="submit" name="action" value="reject"  class="btn" style="background:var(--danger-light);color:var(--danger);border-color:var(--danger)"
                        onclick="return confirm('Are you sure you want to reject this request?')">Reject</button>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Reviewed -->
    <h3 style="margin:24px 0 16px;font-size:16px">Recently Reviewed</h3>
    <div class="card">
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Reason</th>
                        <th>Room Type</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Reviewed</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reviewedRequests as $r):
                    $statusColors = [
                        'approved' => ['bg'=>'var(--success-light)','c'=>'var(--success)'],
                        'rejected' => ['bg'=>'var(--danger-light)', 'c'=>'var(--danger)'],
                        'admitted' => ['bg'=>'#e0f2fe',             'c'=>'var(--accent)'],
                    ];
                    $sc = $statusColors[$r['status']] ?? ['bg'=>'var(--border-light)','c'=>'var(--muted)'];
                    $typeLabels = ['general_ward'=>'General Ward','semi_private'=>'Semi-Private','private'=>'Private','children'=>'Children\'s','icu'=>'ICU','maternity'=>'Maternity','recovery'=>'Recovery'];
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['patient_name']) ?></strong></td>
                    <td style="max-width:200px;color:var(--muted);font-size:13px"><?= htmlspecialchars(mb_strimwidth($r['admission_reason'], 0, 60, '…')) ?></td>
                    <td><?= $r['approved_room_type'] ? htmlspecialchars($typeLabels[$r['approved_room_type']] ?? $r['approved_room_type']) : '—' ?></td>
                    <td style="text-transform:capitalize;color:<?= ['normal'=>'var(--success)','urgent'=>'var(--warning)','critical'=>'var(--danger)'][$r['priority_level']] ?? 'var(--muted)' ?>;font-weight:600"><?= $r['priority_level'] ?></td>
                    <td><span style="background:<?= $sc['bg'] ?>;color:<?= $sc['c'] ?>;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;text-transform:capitalize"><?= $r['status'] ?></span></td>
                    <td style="color:var(--muted);font-size:13px"><?= $r['reviewed_at'] ? date('M j, Y', strtotime($r['reviewed_at'])) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($reviewedRequests)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--muted)">No reviewed requests yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<style>
.page-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.page-header-title h2 { font-size:1.5rem; font-weight:700; margin-bottom:4px; }
.page-header-title p { color:var(--muted); font-size:14px; }
.card { background:var(--surface); border-radius:var(--radius); box-shadow:var(--shadow-sm); overflow:hidden; }
.card-header { display:flex; justify-content:space-between; align-items:center; padding:16px 24px; border-bottom:1px solid var(--border-light); }
.form-group { margin-bottom:18px; }
.form-label { display:block; font-size:13px; font-weight:600; color:var(--text-mid); margin-bottom:6px; }
.form-control { width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:var(--radius-sm); font-size:14px; font-family:var(--font-body); color:var(--text); background:var(--surface); }
.form-control:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(14,165,233,.12); }
.btn { display:inline-flex; align-items:center; gap:6px; padding:9px 18px; border-radius:var(--radius-sm); font-size:14px; font-weight:500; cursor:pointer; border:1px solid transparent; text-decoration:none; transition:all var(--transition); }
.btn-primary { background:var(--accent); color:#fff; border-color:var(--accent); }
.btn-primary:hover { background:var(--accent-dark); color:#fff; }
.table { width:100%; border-collapse:collapse; }
.table th { padding:10px 16px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:var(--muted); background:var(--bg); border-bottom:1px solid var(--border-light); }
.table td { padding:12px 16px; border-bottom:1px solid var(--border-light); font-size:14px; }
.table tr:last-child td { border-bottom:none; }
.alert { padding:12px 16px; border-radius:var(--radius-sm); font-size:14px; }
.alert-success { background:var(--success-light); color:var(--success); }
.alert-danger  { background:var(--danger-light);  color:var(--danger); }
.badge { display:inline-block; padding:4px 12px; border-radius:999px; font-size:12px; font-weight:700; }
.badge-warning { background:var(--warning-light); color:var(--warning); }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>