<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
$requiredRoles = ['reception'];
require_once __DIR__ . '/../../includes/role_check.php';

$userId = (int)$_SESSION['user_id'];
$errors   = [];
$success  = '';

// ── Handle form submission ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId   = (int)($_POST['patient_id']   ?? 0);
    $doctorId    = (int)($_POST['doctor_id']     ?? 0);
    $reason      = trim($_POST['admission_reason'] ?? '');
    $notes       = trim($_POST['request_notes']    ?? '');
    $priority    = $_POST['priority_level'] ?? 'normal';

    if (!$patientId) $errors[] = 'Please select a patient.';
    if (!$doctorId)  $errors[] = 'Please select an assigned doctor.';
    if (!$reason)    $errors[] = 'Admission reason is required.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO admission_requests
                (patient_id, requested_by, assigned_doctor_id, admission_reason, request_notes, priority_level, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$patientId, $userId, $doctorId, $reason, $notes, $priority]);
        $success = 'Admission request created and sent to the doctor for review.';
    }
}

// ── Load patients ──────────────────────────────────────────────────────────
$patients = $pdo->query("
    SELECT p.patient_id, u.full_name, p.nic
    FROM patients p
    JOIN users u ON u.user_id = p.user_id
    WHERE u.status = 'active'
    ORDER BY u.full_name
")->fetchAll();

// ── Load doctors ──────────────────────────────────────────────────────────
$doctors = $pdo->query("
    SELECT d.doctor_id, u.full_name, d.specialization
    FROM doctors d
    JOIN users u ON u.user_id = d.user_id
    WHERE u.status = 'active'
    ORDER BY u.full_name
")->fetchAll();

// ── My recent requests ─────────────────────────────────────────────────────
$myRequests = $pdo->prepare("
    SELECT
        ar.request_id, ar.admission_reason, ar.priority_level, ar.status,
        ar.created_at, ar.doctor_notes,
        pu.full_name AS patient_name,
        du.full_name AS doctor_name
    FROM admission_requests ar
    JOIN patients p  ON p.patient_id = ar.patient_id
    JOIN users pu    ON pu.user_id   = p.user_id
    JOIN doctors d   ON d.doctor_id  = ar.assigned_doctor_id
    JOIN users du    ON du.user_id   = d.user_id
    WHERE ar.requested_by = ?
    ORDER BY ar.created_at DESC
    LIMIT 10
");
$myRequests->execute([$userId]);
$myRequests = $myRequests->fetchAll();

$pageTitle  = 'New Admission Request';
$useSidebar = true;
include __DIR__ . '/../../includes/header.php';
?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="main-content">

    <div class="page-header">
        <div class="page-header-title">
            <h2>New Admission Request</h2>
            <p>Create a request for a doctor to review and approve patient admission</p>
        </div>
        <a href="ward_management.php" class="btn btn-secondary">← Back</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

        <!-- Form -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">Request Details</h3></div>
            <div style="padding:24px">

                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom:20px"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger" style="margin-bottom:20px">
                        <?php foreach ($errors as $e): ?><div>• <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Patient *</label>
                        <select name="patient_id" class="form-control" required>
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
                        <label class="form-label">Assigned Doctor *</label>
                        <select name="doctor_id" class="form-control" required>
                            <option value="">— Select Doctor —</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?= $d['doctor_id'] ?>"
                                    <?= (isset($_POST['doctor_id']) && $_POST['doctor_id'] == $d['doctor_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['full_name']) ?> — <?= htmlspecialchars($d['specialization']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Priority Level *</label>
                        <select name="priority_level" class="form-control">
                            <option value="normal"   <?= (isset($_POST['priority_level']) && $_POST['priority_level']==='normal')   ? 'selected' : '' ?>>Normal</option>
                            <option value="urgent"   <?= (isset($_POST['priority_level']) && $_POST['priority_level']==='urgent')   ? 'selected' : '' ?>>Urgent</option>
                            <option value="critical" <?= (isset($_POST['priority_level']) && $_POST['priority_level']==='critical') ? 'selected' : '' ?>>Critical</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Admission Reason *</label>
                        <textarea name="admission_reason" class="form-control" rows="3" required
                            placeholder="Why does this patient need to be admitted?"><?= htmlspecialchars($_POST['admission_reason'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Additional Notes (optional)</label>
                        <textarea name="request_notes" class="form-control" rows="2"
                            placeholder="Any extra notes for the doctor..."><?= htmlspecialchars($_POST['request_notes'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%">Send to Doctor for Review</button>
                </form>
            </div>
        </div>

        <!-- Recent Requests -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">My Recent Requests</h3></div>
            <div style="overflow-x:auto">
                <?php foreach ($myRequests as $r):
                    $statusColors = [
                        'pending'  => ['bg'=>'var(--warning-light)',  'c'=>'var(--warning)'],
                        'approved' => ['bg'=>'var(--success-light)',  'c'=>'var(--success)'],
                        'rejected' => ['bg'=>'var(--danger-light)',   'c'=>'var(--danger)'],
                        'admitted' => ['bg'=>'#e0f2fe',               'c'=>'var(--accent)'],
                        'cancelled'=> ['bg'=>'var(--border-light)',   'c'=>'var(--muted)'],
                    ];
                    $sc = $statusColors[$r['status']] ?? ['bg'=>'var(--border-light)','c'=>'var(--muted)'];
                    $priorityColors = ['normal'=>'var(--success)','urgent'=>'var(--warning)','critical'=>'var(--danger)'];
                    $pc = $priorityColors[$r['priority_level']] ?? 'var(--muted)';
                ?>
                <div style="padding:14px 20px;border-bottom:1px solid var(--border-light)">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px">
                        <div>
                            <strong><?= htmlspecialchars($r['patient_name']) ?></strong>
                            <span style="color:var(--muted);font-size:12px;margin-left:8px">→ Dr. <?= htmlspecialchars($r['doctor_name']) ?></span>
                        </div>
                        <span style="background:<?= $sc['bg'] ?>;color:<?= $sc['c'] ?>;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;text-transform:capitalize"><?= $r['status'] ?></span>
                    </div>
                    <div style="font-size:13px;color:var(--muted);margin-bottom:4px"><?= htmlspecialchars(mb_strimwidth($r['admission_reason'], 0, 80, '…')) ?></div>
                    <div style="display:flex;gap:10px;font-size:11px">
                        <span style="color:<?= $pc ?>;font-weight:600;text-transform:uppercase"><?= $r['priority_level'] ?></span>
                        <span style="color:var(--muted)"><?= date('M j, Y g:ia', strtotime($r['created_at'])) ?></span>
                    </div>
                    <?php if ($r['doctor_notes']): ?>
                        <div style="margin-top:6px;padding:6px 10px;background:var(--accent-light);border-radius:6px;font-size:12px;color:var(--accent-dark)">
                            Doctor note: <?= htmlspecialchars($r['doctor_notes']) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (empty($myRequests)): ?>
                <div style="padding:30px;text-align:center;color:var(--muted)">No requests created yet.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</main>

<style>
.page-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.page-header-title h2 { font-size:1.5rem; font-weight:700; margin-bottom:4px; }
.page-header-title p { color:var(--muted); font-size:14px; }
.card { background:var(--surface); border-radius:var(--radius); box-shadow:var(--shadow-sm); overflow:hidden; }
.card-header { display:flex; justify-content:space-between; align-items:center; padding:18px 24px; border-bottom:1px solid var(--border-light); }
.card-title { font-size:15px; font-weight:700; margin:0; }
.form-group { margin-bottom:18px; }
.form-label { display:block; font-size:13px; font-weight:600; color:var(--text-mid); margin-bottom:6px; }
.form-control { width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:var(--radius-sm); font-size:14px; font-family:var(--font-body); color:var(--text); background:var(--surface); transition:border-color var(--transition); }
.form-control:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(14,165,233,.12); }
.btn { display:inline-flex; align-items:center; gap:6px; padding:9px 18px; border-radius:var(--radius-sm); font-size:14px; font-weight:500; cursor:pointer; border:1px solid transparent; text-decoration:none; transition:all var(--transition); }
.btn-primary { background:var(--accent); color:#fff; border-color:var(--accent); }
.btn-primary:hover { background:var(--accent-dark); color:#fff; }
.btn-secondary { background:var(--surface); color:var(--text-mid); border-color:var(--border); }
.btn-secondary:hover { background:var(--bg); color:var(--text); }
.alert { padding:12px 16px; border-radius:var(--radius-sm); font-size:14px; }
.alert-success { background:var(--success-light); color:var(--success); }
.alert-danger  { background:var(--danger-light);  color:var(--danger); }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<?php
