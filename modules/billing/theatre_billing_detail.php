<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';

$requiredRoles = ['admin', 'reception'];
require_once __DIR__ . '/../../includes/role_check.php';

$operation_id = (int)($_GET['op'] ?? 0);
if ($operation_id < 1) {
    header('Location: theatre_billing.php');
    exit;
}

// Load full detail
$stmt = $pdo->prepare(
    "SELECT
        o.operation_id, o.operation_type, o.theatre_number,
        o.scheduled_date, o.scheduled_time, o.status AS op_status,
        o.pre_op_notes, o.post_op_notes, o.recovery_instructions,
        p.patient_id,
        pu.full_name   AS patient_name,
        pat.phone      AS patient_phone,
        pat.nic,
        su.full_name   AS surgeon_name,
        au.full_name   AS anaesthetist_name,
        tb.theatre_billing_id,
        tb.operation_charge,
        tb.anaesthesia_charge,
        tb.consumables_charge,
        tb.total_charge,
        tb.billing_status,
        tb.invoice_id,
        tb.notes        AS billing_notes,
        tb.created_at   AS billing_created,
        bi.status       AS invoice_status,
        bi.total_amount AS invoice_total,
        bi.paid_amount  AS invoice_paid
     FROM theatre_operations o
     JOIN patients pat  ON pat.patient_id = o.patient_id
     JOIN patients p    ON p.patient_id   = o.patient_id
     JOIN users pu      ON pu.user_id     = p.user_id
     JOIN users su      ON su.user_id     = o.surgeon_id
     LEFT JOIN users au ON au.user_id     = o.anaesthetist_id
     LEFT JOIN theatre_billing tb ON tb.operation_id = o.operation_id
     LEFT JOIN billing_invoices bi ON bi.invoice_id  = tb.invoice_id
     WHERE o.operation_id = ?
     LIMIT 1"
);
$stmt->execute([$operation_id]);
$op = $stmt->fetch();

if (!$op) {
    header('Location: theatre_billing.php');
    exit;
}

$pageTitle  = 'Operation Billing — #' . $operation_id;
$useSidebar = true;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';

$theatreLabels = [1=>'Theatre 1 – General',2=>'Theatre 2 – Emergency',3=>'Theatre 3 – Labour',4=>'Theatre 4 – Minor'];
$opBadge       = ['scheduled'=>'badge-info','confirmed'=>'badge-success','in_progress'=>'badge-warning','completed'=>'badge-success','cancelled'=>'badge-danger'];
$bilBadge      = ['pending'=>'badge-warning','invoiced'=>'badge-info','paid'=>'badge-success','waived'=>'badge-neutral'];
?>

<main class="main-content">

<div class="page-header">
    <div class="page-header-title">
        <h2>Operation Billing Detail</h2>
        <p><?= htmlspecialchars($op['operation_type']) ?> — #<?= $operation_id ?></p>
    </div>
    <div style="display:flex;gap:10px">
        <a href="theatre_billing.php" class="btn btn-secondary">← Back to Billing List</a>
        <a href="../theatre/operation_details.php?id=<?= $operation_id ?>" class="btn btn-secondary">Op Details</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

    <!-- Operation Info -->
    <div class="card">
        <div class="card-header"><h3>Operation Info</h3></div>
        <div style="padding:16px 20px">
            <table class="detail-tbl">
                <tr><td class="dtl-lbl">Operation ID</td><td><strong>#<?= $op['operation_id'] ?></strong></td></tr>
                <tr><td class="dtl-lbl">Type</td><td><?= htmlspecialchars($op['operation_type']) ?></td></tr>
                <tr><td class="dtl-lbl">Theatre</td><td><?= $theatreLabels[$op['theatre_number']] ?? 'Theatre '.$op['theatre_number'] ?></td></tr>
                <tr><td class="dtl-lbl">Date</td><td><?= date('d M Y', strtotime($op['scheduled_date'])) ?></td></tr>
                <tr><td class="dtl-lbl">Time</td><td><?= date('h:i A', strtotime($op['scheduled_time'])) ?></td></tr>
                <tr><td class="dtl-lbl">Status</td><td>
                    <span class="badge <?= $opBadge[$op['op_status']] ?? 'badge-neutral' ?>">
                        <?= ucfirst(str_replace('_',' ',$op['op_status'])) ?>
                    </span>
                </td></tr>
                <tr><td class="dtl-lbl">Surgeon</td><td><?= htmlspecialchars($op['surgeon_name']) ?></td></tr>
                <tr><td class="dtl-lbl">Anaesthetist</td><td><?= htmlspecialchars($op['anaesthetist_name'] ?? '—') ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Patient Info -->
    <div class="card">
        <div class="card-header"><h3>Patient Info</h3></div>
        <div style="padding:16px 20px">
            <table class="detail-tbl">
                <tr><td class="dtl-lbl">Name</td><td><strong><?= htmlspecialchars($op['patient_name']) ?></strong></td></tr>
                <tr><td class="dtl-lbl">Patient ID</td><td>#<?= $op['patient_id'] ?></td></tr>
                <tr><td class="dtl-lbl">NIC</td><td><?= htmlspecialchars($op['nic'] ?? '—') ?></td></tr>
                <tr><td class="dtl-lbl">Phone</td><td><?= htmlspecialchars($op['patient_phone'] ?? '—') ?></td></tr>
            </table>
        </div>
    </div>
</div>

<!-- Billing Detail -->
<?php if ($op['theatre_billing_id']): ?>
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <h3>Billing Breakdown</h3>
        <span class="badge <?= $bilBadge[$op['billing_status']] ?? 'badge-neutral' ?>">
            <?= ucfirst($op['billing_status']) ?>
        </span>
    </div>
    <div style="padding:20px">
        <div class="charge-grid">
            <div class="charge-item">
                <div class="charge-label">Operation Charge</div>
                <div class="charge-val">Rs. <?= number_format((float)$op['operation_charge'],2) ?></div>
            </div>
            <div class="charge-item">
                <div class="charge-label">Anaesthesia Charge</div>
                <div class="charge-val">Rs. <?= number_format((float)$op['anaesthesia_charge'],2) ?></div>
            </div>
            <div class="charge-item">
                <div class="charge-label">Consumables</div>
                <div class="charge-val">Rs. <?= number_format((float)$op['consumables_charge'],2) ?></div>
            </div>
            <div class="charge-item total">
                <div class="charge-label">Total Charge</div>
                <div class="charge-val" style="color:var(--success);font-size:1.3rem">
                    Rs. <?= number_format((float)$op['total_charge'],2) ?>
                </div>
            </div>
        </div>

        <?php if ($op['billing_notes']): ?>
            <div style="margin-top:14px;padding:10px 14px;background:var(--bg);border-radius:var(--radius);font-size:13px;color:var(--muted)">
                <?= htmlspecialchars($op['billing_notes']) ?>
            </div>
        <?php endif; ?>

        <?php if ($op['invoice_id']): ?>
            <div style="margin-top:16px;padding:14px;background:#eff6ff;border-radius:var(--radius);border:1px solid #bfdbfe">
                <strong>Linked Invoice #<?= $op['invoice_id'] ?></strong>
                <span style="float:right">
                    Total: Rs. <?= number_format((float)$op['invoice_total'],2) ?> |
                    Paid: Rs. <?= number_format((float)$op['invoice_paid'],2) ?> |
                    Balance: Rs. <?= number_format(max(0,(float)$op['invoice_total']-(float)$op['invoice_paid']),2) ?>
                </span>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:20px;text-align:center;padding:32px">
    <div style="font-size:40px;margin-bottom:10px"></div>
    <p style="color:var(--muted)">No billing record yet for this operation.</p>
    <a href="theatre_billing.php" class="btn btn-primary" style="margin-top:12px">Add Billing Record</a>
</div>
<?php endif; ?>

<!-- Clinical Notes -->
<?php if ($op['pre_op_notes'] || $op['post_op_notes']): ?>
<div class="card">
    <div class="card-header"><h3>Clinical Notes</h3></div>
    <div style="padding:16px 20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
            <div class="notes-lbl">Pre-Op Notes</div>
            <div class="notes-body"><?= nl2br(htmlspecialchars($op['pre_op_notes'] ?? '—')) ?></div>
        </div>
        <div>
            <div class="notes-lbl">Post-Op Notes</div>
            <div class="notes-body"><?= nl2br(htmlspecialchars($op['post_op_notes'] ?? '—')) ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<style>
.detail-tbl { width:100%; border-collapse:collapse; font-size:13.5px; }
.detail-tbl tr td { padding:8px 0; border-bottom:1px solid var(--border-light); }
.detail-tbl tr:last-child td { border-bottom:none; }
.dtl-lbl { color:var(--muted); font-weight:500; font-size:12px; width:40%; text-transform:uppercase; letter-spacing:.3px; }

.charge-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; }
.charge-item { background:var(--bg); border-radius:var(--radius); padding:14px 16px; }
.charge-item.total { background:var(--success-light); }
.charge-label { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:6px; font-weight:600; }
.charge-val { font-size:1.1rem; font-weight:700; color:var(--text); }

.notes-lbl  { font-size:12px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:8px; }
.notes-body { font-size:13.5px; color:var(--text); line-height:1.6; }
</style>
