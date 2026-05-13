<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../includes/auth_guard.php';

requireRole(['admin', 'doctor']);

$pageTitle  = 'Operation Details';
$useSidebar = true;

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: theatre_schedule.php'); exit(); }

// ── Fetch operation ───────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        o.*,
        u.full_name    AS patient_name,
        p.nic, p.dob, p.gender, p.blood_type, p.phone,
        ls.full_name   AS surgeon_name,
        ls.department  AS surgeon_dept,
        an.full_name   AS anaesthetist_name,
        ast.full_name  AS assistant_name,
        cb.full_name   AS created_by_name
    FROM theatre_operations o
    JOIN patients  p   ON o.patient_id          = p.patient_id
    JOIN users     u   ON p.user_id             = u.user_id
    JOIN users     ls  ON o.lead_surgeon_id     = ls.user_id
    JOIN users     an  ON o.anaesthetist_id     = an.user_id
    LEFT JOIN users ast ON o.assistant_doctor_id = ast.user_id
    LEFT JOIN users cb  ON o.created_by          = cb.user_id
    WHERE o.operation_id = ?
");
$stmt->execute([$id]);
$op = $stmt->fetch();
if (!$op) { header('Location: theatre_schedule.php'); exit(); }

// ── Fetch billing items ───────────────────────────────────────────
$billItems = $pdo->prepare("SELECT * FROM theatre_billing_items WHERE operation_id = ?");
$billItems->execute([$id]);
$bills = $billItems->fetchAll();

// ── Status update (quick action) ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_status'])) {
    $newStatus = $_POST['quick_status'];
    $allowed   = ['Scheduled','Confirmed','In Progress','Completed','Cancelled'];
    if (in_array($newStatus, $allowed, true)) {
        $pdo->prepare("UPDATE theatre_operations SET status = ? WHERE operation_id = ?")
            ->execute([$newStatus, $id]);

        // If completed, redirect to post-op page
        if ($newStatus === 'Completed') {
            header('Location: post_operation.php?id=' . $id);
            exit();
        }
    }
    header('Location: operation_details.php?id=' . $id);
    exit();
}

$statusColors = [
    'Scheduled'   => '#2563eb',
    'Confirmed'   => '#059669',
    'In Progress' => '#d97706',
    'Completed'   => '#065f46',
    'Cancelled'   => '#dc2626',
    'Transferred' => '#7c3aed',
];
$statusColor = $statusColors[$op['status']] ?? '#6b7280';

include __DIR__ . '/../../includes/header.php';
?>

<!-- Notifications -->
<?php if (isset($_GET['created'])): ?>
    <div class="alert alert-success">✅ Operation scheduled successfully!</div>
<?php elseif (isset($_GET['saved'])): ?>
    <div class="alert alert-success">✅ Operation updated successfully!</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">🔬 Operation #<?= $id ?></h1>
        <p class="page-sub">
            <?= htmlspecialchars($op['operation_type']) ?> —
            <?= date('d M Y', strtotime($op['scheduled_date'])) ?>,
            <?= date('h:i A', strtotime($op['scheduled_time'])) ?>
        </p>
    </div>
    <div style="display:flex;gap:10px">
        <?php if (!in_array($op['status'], ['Completed','Cancelled','Transferred'])): ?>
            <a href="create_operation.php?edit=<?= $id ?>" class="btn btn-outline">✏️ Edit</a>
        <?php endif; ?>
        <a href="theatre_schedule.php?date=<?= urlencode($op['scheduled_date']) ?>"
           class="btn btn-secondary">← Back to Schedule</a>
    </div>
</div>

<!-- Status Banner -->
<div style="background:<?= $statusColor ?>1a;border:1px solid <?= $statusColor ?>40;border-radius:10px;padding:14px 20px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between">
    <div>
        <strong style="color:<?= $statusColor ?>;font-size:1rem"><?= htmlspecialchars($op['status']) ?></strong>
        <?php if ($op['is_maternity']): ?>
            &nbsp;<span class="badge badge-pink">🤱 Maternity Case</span>
        <?php endif; ?>
    </div>
    <!-- Quick Status Change -->
    <?php if (!in_array($op['status'], ['Completed','Cancelled','Transferred'])): ?>
    <form method="POST" style="display:flex;gap:8px;align-items:center">
        <select name="quick_status" class="form-control" style="width:auto;font-size:13px">
            <?php foreach (['Scheduled','Confirmed','In Progress','Completed','Cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $op['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-primary">Update Status</button>
    </form>
    <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

    <!-- Patient Info -->
    <div class="card">
        <div class="card-header"><h3>👤 Patient Information</h3></div>
        <div style="padding:20px">
            <table class="detail-table">
                <tr><th>Name</th><td><?= htmlspecialchars($op['patient_name']) ?></td></tr>
                <tr><th>NIC</th><td><?= htmlspecialchars($op['nic']) ?></td></tr>
                <tr><th>Date of Birth</th><td><?= $op['dob'] ? date('d M Y', strtotime($op['dob'])) : '—' ?></td></tr>
                <tr><th>Gender</th><td><?= htmlspecialchars(ucfirst($op['gender'] ?? '')) ?></td></tr>
                <tr><th>Blood Type</th><td><?= htmlspecialchars($op['blood_type'] ?? '—') ?></td></tr>
                <tr><th>Phone</th><td><?= htmlspecialchars($op['phone'] ?? '—') ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Operation Info -->
    <div class="card">
        <div class="card-header"><h3>🏨 Theatre & Schedule</h3></div>
        <div style="padding:20px">
            <table class="detail-table">
                <tr><th>Theatre</th><td><?= htmlspecialchars($op['theatre_number']) ?></td></tr>
                <tr><th>Date</th><td><?= date('d M Y', strtotime($op['scheduled_date'])) ?></td></tr>
                <tr><th>Time</th><td><?= date('h:i A', strtotime($op['scheduled_time'])) ?></td></tr>
                <tr><th>Duration</th><td><?= $op['duration_minutes'] ?> minutes</td></tr>
                <tr><th>Operation Type</th><td><?= htmlspecialchars($op['operation_type']) ?></td></tr>
                <tr><th>Scheduled By</th><td><?= htmlspecialchars($op['created_by_name'] ?? '—') ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Medical Team -->
    <div class="card">
        <div class="card-header"><h3>👨‍⚕️ Medical Team</h3></div>
        <div style="padding:20px">
            <table class="detail-table">
                <tr><th>Lead Surgeon</th><td><?= htmlspecialchars($op['surgeon_name']) ?>
                    <?php if ($op['surgeon_dept']): ?>
                        <small style="color:var(--muted)">(<?= htmlspecialchars($op['surgeon_dept']) ?>)</small>
                    <?php endif; ?>
                </td></tr>
                <tr><th>Anaesthesiologist</th><td><?= htmlspecialchars($op['anaesthetist_name']) ?></td></tr>
                <tr><th>Assisting Doctor</th><td><?= htmlspecialchars($op['assistant_name'] ?? '—') ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Pre-Op Notes -->
    <div class="card">
        <div class="card-header"><h3>📋 Pre-Op Notes</h3></div>
        <div style="padding:20px;color:<?= $op['pre_op_notes'] ? 'inherit' : 'var(--muted)' ?>">
            <?= $op['pre_op_notes'] ? nl2br(htmlspecialchars($op['pre_op_notes'])) : 'No pre-op notes recorded.' ?>
        </div>
    </div>

    <?php if ($op['status'] === 'Completed' || $op['post_op_notes']): ?>
    <!-- Post-Op Notes -->
    <div class="card">
        <div class="card-header"><h3>🏥 Post-Op Details</h3></div>
        <div style="padding:20px">
            <table class="detail-table">
                <?php if ($op['post_op_room_type']): ?>
                    <tr><th>Transfer To</th><td><?= htmlspecialchars($op['post_op_room_type']) ?></td></tr>
                <?php endif; ?>
            </table>
            <?php if ($op['post_op_notes']): ?>
                <p style="margin-top:10px"><strong>Post-Op Notes:</strong><br>
                    <?= nl2br(htmlspecialchars($op['post_op_notes'])) ?></p>
            <?php endif; ?>
            <?php if ($op['recovery_instructions']): ?>
                <p style="margin-top:10px"><strong>Recovery Instructions:</strong><br>
                    <?= nl2br(htmlspecialchars($op['recovery_instructions'])) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Billing -->
    <div class="card" style="<?= ($op['status'] === 'Completed' || $op['post_op_notes']) ? '' : 'grid-column:1/3' ?>">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <h3>💳 Theatre Billing</h3>
            <?php if ($op['billing_triggered']): ?>
                <span class="badge badge-success">✅ Added to Invoice</span>
            <?php endif; ?>
        </div>
        <div style="padding:20px">
            <?php if (empty($bills)): ?>
                <p style="color:var(--muted)">Billing items will be auto-generated when the operation is marked Completed.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Item</th><th>Description</th><th style="text-align:right">Amount (Rs.)</th></tr></thead>
                    <tbody>
                        <?php $total = 0; foreach ($bills as $b): $total += $b['amount']; ?>
                        <tr>
                            <td><?= htmlspecialchars($b['item_type']) ?></td>
                            <td><?= htmlspecialchars($b['description'] ?? '') ?></td>
                            <td style="text-align:right"><?= number_format($b['amount'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight:600;background:var(--bg-soft)">
                            <td colspan="2">Total</td>
                            <td style="text-align:right">Rs. <?= number_format($total, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div style="margin-top:24px;display:flex;gap:12px;justify-content:flex-end">
    <?php if ($op['status'] === 'Completed' && !$op['billing_triggered']): ?>
        <a href="post_operation.php?id=<?= $id ?>" class="btn btn-primary btn-lg">📋 Complete Post-Op Record</a>
    <?php endif; ?>
    <?php if ($op['is_maternity'] && $op['status'] === 'Completed'): ?>
        <a href="../newborn/newborn_record.php?from_op=<?= $id ?>&patient=<?= $op['patient_id'] ?>"
           class="btn btn-secondary">🍼 Create Newborn Record</a>
    <?php endif; ?>
</div>

<style>
.detail-table { width:100%; border-collapse:collapse; }
.detail-table th { width:40%; padding:8px 4px; color:var(--muted); font-weight:500; text-align:left; vertical-align:top; }
.detail-table td { padding:8px 4px; }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
