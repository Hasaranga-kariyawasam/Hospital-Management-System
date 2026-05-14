<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';

$requiredRoles = ['admin', 'reception'];
require_once __DIR__ . '/../../includes/role_check.php';

$pageTitle  = 'Theatre Billing';
$useSidebar = true;

$success = '';
$error   = '';

// ════════════════════════════════════════════════════
// POST — create / update theatre billing record
// ════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action       = $_POST['action']       ?? '';
    $operation_id = (int)($_POST['operation_id'] ?? 0);

    // ── Create billing record ────────────────────────
    if ($action === 'create_billing' && $operation_id > 0) {
        $op_charge   = (float)($_POST['operation_charge']   ?? 0);
        $an_charge   = (float)($_POST['anaesthesia_charge'] ?? 0);
        $con_charge  = (float)($_POST['consumables_charge'] ?? 0);
        $notes       = trim($_POST['notes'] ?? '');
        $patient_id  = (int)($_POST['patient_id'] ?? 0);
        $staff_id    = (int)($_SESSION['user_id'] ?? 0);

        if ($op_charge < 0 || $an_charge < 0 || $con_charge < 0) {
            $error = 'Charges cannot be negative.';
        } elseif ($patient_id < 1) {
            $error = 'Invalid patient.';
        } else {
            // Check if billing already exists
            $chk = $pdo->prepare("SELECT theatre_billing_id FROM theatre_billing WHERE operation_id = ?");
            $chk->execute([$operation_id]);
            if ($chk->fetch()) {
                $error = 'Billing record already exists for this operation.';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO theatre_billing
                         (operation_id, patient_id, operation_charge, anaesthesia_charge,
                          consumables_charge, billing_status, notes, created_by)
                     VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)"
                );
                $stmt->execute([$operation_id, $patient_id, $op_charge, $an_charge, $con_charge, $notes, $staff_id]);
                $success = 'Theatre billing record created successfully.';
            }
        }
    }

    // ── Update billing status ────────────────────────
    if ($action === 'update_status' && $operation_id > 0) {
        $new_status = $_POST['billing_status'] ?? '';
        $allowed    = ['pending', 'invoiced', 'paid', 'waived'];
        if (in_array($new_status, $allowed)) {
            $pdo->prepare("UPDATE theatre_billing SET billing_status=? WHERE operation_id=?")
                ->execute([$new_status, $operation_id]);

            // If marking paid → also add billing_item to billing_invoices if invoice exists
            if ($new_status === 'paid') {
                $tb = $pdo->prepare(
                    "SELECT tb.*, o.operation_type FROM theatre_billing tb
                     JOIN theatre_operations o ON o.operation_id = tb.operation_id
                     WHERE tb.operation_id = ?"
                )->execute([$operation_id]);
                $tb = $pdo->prepare(
                    "SELECT tb.total_charge, tb.invoice_id, o.operation_type
                     FROM theatre_billing tb
                     JOIN theatre_operations o ON o.operation_id = tb.operation_id
                     WHERE tb.operation_id = ?"
                );
                $tb->execute([$operation_id]);
                $row = $tb->fetch();
                if ($row && $row['invoice_id']) {
                    // Update billing_invoices paid_amount
                    $pdo->prepare(
                        "UPDATE billing_invoices SET paid_amount = paid_amount + ? WHERE invoice_id = ?"
                    )->execute([$row['total_charge'], $row['invoice_id']]);
                }
            }
            $success = 'Billing status updated.';
        }
    }

    // ── Link to invoice ──────────────────────────────
    if ($action === 'link_invoice' && $operation_id > 0) {
        $invoice_id = (int)($_POST['invoice_id'] ?? 0);
        if ($invoice_id > 0) {
            $pdo->prepare("UPDATE theatre_billing SET invoice_id=?, billing_status='invoiced' WHERE operation_id=?")
                ->execute([$invoice_id, $operation_id]);
            // Add as billing_item
            $tb = $pdo->prepare(
                "SELECT tb.total_charge, o.operation_type
                 FROM theatre_billing tb
                 JOIN theatre_operations o ON o.operation_id = tb.operation_id
                 WHERE tb.operation_id = ?"
            );
            $tb->execute([$operation_id]);
            $row = $tb->fetch();
            if ($row) {
                $pdo->prepare(
                    "INSERT IGNORE INTO billing_items (invoice_id, description, category, unit_price, quantity)
                     VALUES (?, ?, 'theatre', ?, 1)"
                )->execute([$invoice_id, 'Theatre: ' . $row['operation_type'], $row['total_charge']]);
                // Update invoice total
                $pdo->prepare(
                    "UPDATE billing_invoices SET total_amount = total_amount + ? WHERE invoice_id = ?"
                )->execute([$row['total_charge'], $invoice_id]);
            }
            $success = 'Theatre charge linked to invoice #' . $invoice_id . '.';
        }
    }
}

// ════════════════════════════════════════════════════
// Load data
// ════════════════════════════════════════════════════
$filter_status = $_GET['status'] ?? 'all';
$filter_date   = $_GET['date']   ?? '';

$where  = ['1=1'];
$params = [];

if ($filter_status !== 'all' && in_array($filter_status, ['pending','invoiced','paid','waived'])) {
    $where[]  = 'tb.billing_status = ?';
    $params[] = $filter_status;
}
if ($filter_date !== '') {
    $where[]  = 'DATE(o.scheduled_date) = ?';
    $params[] = $filter_date;
}

// Main list — LEFT JOIN so ops without billing row also show
$stmt = $pdo->prepare(
    "SELECT
        o.operation_id,
        o.operation_type,
        o.theatre_number,
        o.scheduled_date,
        o.scheduled_time,
        o.status              AS op_status,
        o.theatre_charge      AS default_charge,
        p.patient_id,
        pu.full_name          AS patient_name,
        su.full_name          AS surgeon_name,
        tb.theatre_billing_id,
        tb.operation_charge,
        tb.anaesthesia_charge,
        tb.consumables_charge,
        tb.total_charge,
        tb.billing_status,
        tb.invoice_id,
        tb.notes              AS billing_notes
     FROM theatre_operations o
     JOIN patients p    ON p.patient_id = o.patient_id
     JOIN users pu      ON pu.user_id   = p.user_id
     JOIN users su      ON su.user_id   = o.surgeon_id
     LEFT JOIN theatre_billing tb ON tb.operation_id = o.operation_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY o.scheduled_date DESC, o.scheduled_time DESC"
);
$stmt->execute($params);
$operations = $stmt->fetchAll();

// Stats
$stats = $pdo->query(
    "SELECT
        COUNT(*)                                        AS total_ops,
        SUM(tb.billing_status = 'pending')              AS pending,
        SUM(tb.billing_status = 'invoiced')             AS invoiced,
        SUM(tb.billing_status = 'paid')                 AS paid,
        COALESCE(SUM(tb.total_charge), 0)               AS total_value,
        COALESCE(SUM(CASE WHEN tb.billing_status='paid' THEN tb.total_charge ELSE 0 END), 0) AS collected
     FROM theatre_operations o
     LEFT JOIN theatre_billing tb ON tb.operation_id = o.operation_id"
)->fetch();

// Load invoices for the link-to-invoice modal
$invoices = $pdo->query(
    "SELECT bi.invoice_id, u.full_name AS patient_name, bi.total_amount, bi.paid_amount, bi.status
     FROM billing_invoices bi
     JOIN patients p ON p.patient_id = bi.patient_id
     JOIN users u    ON u.user_id    = p.user_id
     WHERE bi.status = 'open'
     ORDER BY bi.created_at DESC
     LIMIT 100"
)->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">

<div class="page-header">
    <div class="page-header-title">
        <h2>🏥 Theatre Billing</h2>
        <p>Manage operation charges and link to patient invoices — <?= date('l, d F Y') ?></p>
    </div>
    <div style="display:flex;gap:10px">
        <a href="../theatre/theatre.php" class="btn btn-secondary">🔬 Theatre Schedule</a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert-msg success">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert-msg danger">❌ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- ── Stat Cards ── -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue">🔬</div>
        <div>
            <div class="stat-label">Total Operations</div>
            <div class="stat-value"><?= (int)$stats['total_ops'] ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">⏳</div>
        <div>
            <div class="stat-label">Pending Billing</div>
            <div class="stat-value"><?= (int)$stats['pending'] ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">📄</div>
        <div>
            <div class="stat-label">Invoiced</div>
            <div class="stat-value"><?= (int)$stats['invoiced'] ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">💰</div>
        <div>
            <div class="stat-label">Collected</div>
            <div class="stat-value">Rs. <?= number_format((float)$stats['collected'], 0) ?></div>
            <div class="stat-change">of Rs. <?= number_format((float)$stats['total_value'], 0) ?> total</div>
        </div>
    </div>
</div>

<!-- ── Filter ── -->
<div class="card" style="margin-bottom:24px">
    <form method="GET" style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">
        <div class="form-group" style="margin:0;flex:1;min-width:160px">
            <label class="form-label">Billing Status</label>
            <select name="status" class="form-control" onchange="this.form.submit()">
                <option value="all"      <?= $filter_status==='all'      ?'selected':'' ?>>All Statuses</option>
                <option value="pending"  <?= $filter_status==='pending'  ?'selected':'' ?>>⏳ Pending</option>
                <option value="invoiced" <?= $filter_status==='invoiced' ?'selected':'' ?>>📄 Invoiced</option>
                <option value="paid"     <?= $filter_status==='paid'     ?'selected':'' ?>>✅ Paid</option>
                <option value="waived"   <?= $filter_status==='waived'   ?'selected':'' ?>>🔕 Waived</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:160px">
            <label class="form-label">Date</label>
            <input type="date" name="date" class="form-control"
                   value="<?= htmlspecialchars($filter_date) ?>" onchange="this.form.submit()">
        </div>
        <div style="display:flex;gap:8px">
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="theatre_billing.php" class="btn btn-secondary">✕ Clear</a>
        </div>
    </form>
</div>

<!-- ── Operations + Billing Table ── -->
<div class="card">
    <div class="card-header">
        <h3>Operation Billing Records</h3>
        <span class="badge badge-info"><?= count($operations) ?> records</span>
    </div>

    <?php if (empty($operations)): ?>
        <div style="text-align:center;padding:48px;color:var(--muted)">
            <div style="font-size:48px;margin-bottom:12px">🏥</div>
            <p>No operations found.</p>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Patient</th>
                    <th>Operation</th>
                    <th>Theatre</th>
                    <th>Date</th>
                    <th>Op Status</th>
                    <th>Charges (Rs.)</th>
                    <th>Billing Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $theatreLabels = [1=>'T1–General',2=>'T2–Emergency',3=>'T3–Labour',4=>'T4–Minor'];
            $opBadge  = ['scheduled'=>'badge-info','confirmed'=>'badge-success','in_progress'=>'badge-warning','completed'=>'badge-success','cancelled'=>'badge-danger'];
            $bilBadge = ['pending'=>'badge-warning','invoiced'=>'badge-info','paid'=>'badge-success','waived'=>'badge-neutral'];
            foreach ($operations as $op):
                $hasBilling = $op['theatre_billing_id'] !== null;
                $total = $hasBilling ? (float)$op['total_charge'] : (float)$op['default_charge'];
            ?>
            <tr>
                <td><strong>#<?= $op['operation_id'] ?></strong></td>
                <td><?= htmlspecialchars($op['patient_name']) ?></td>
                <td>
                    <?= htmlspecialchars($op['operation_type']) ?>
                    <div style="font-size:11px;color:var(--muted)">Dr. <?= htmlspecialchars($op['surgeon_name']) ?></div>
                </td>
                <td><?= $theatreLabels[$op['theatre_number']] ?? 'T'.$op['theatre_number'] ?></td>
                <td>
                    <strong><?= date('d M Y', strtotime($op['scheduled_date'])) ?></strong>
                    <div style="font-size:11px;color:var(--muted)"><?= date('h:i A', strtotime($op['scheduled_time'])) ?></div>
                </td>
                <td>
                    <span class="badge <?= $opBadge[$op['op_status']] ?? 'badge-neutral' ?>">
                        <?= ucfirst(str_replace('_',' ',$op['op_status'])) ?>
                    </span>
                </td>
                <td>
                    <?php if ($hasBilling): ?>
                        <div style="font-size:12px;line-height:1.7">
                            <span>Op: <strong>Rs. <?= number_format((float)$op['operation_charge'],2) ?></strong></span><br>
                            <span>Anaes: Rs. <?= number_format((float)$op['anaesthesia_charge'],2) ?></span><br>
                            <span>Consm: Rs. <?= number_format((float)$op['consumables_charge'],2) ?></span><br>
                            <span style="color:var(--success);font-weight:700">Total: Rs. <?= number_format((float)$op['total_charge'],2) ?></span>
                        </div>
                    <?php else: ?>
                        <span style="color:var(--muted);font-style:italic;font-size:12px">Not set</span>
                        <?php if ($op['default_charge'] > 0): ?>
                            <div style="font-size:11px;color:var(--muted)">Default: Rs. <?= number_format($op['default_charge'],2) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($hasBilling): ?>
                        <!-- inline status update -->
                        <form method="POST" style="margin:0">
                            <input type="hidden" name="action"       value="update_status">
                            <input type="hidden" name="operation_id" value="<?= $op['operation_id'] ?>">
                            <select name="billing_status" class="form-control"
                                    style="font-size:12px;padding:4px 8px"
                                    onchange="this.form.submit()">
                                <?php foreach(['pending'=>'⏳ Pending','invoiced'=>'📄 Invoiced','paid'=>'✅ Paid','waived'=>'🔕 Waived'] as $v=>$l): ?>
                                    <option value="<?= $v ?>" <?= $op['billing_status']===$v?'selected':'' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php if ($op['invoice_id']): ?>
                            <div style="font-size:11px;color:var(--accent-dark);margin-top:4px">📄 Inv #<?= $op['invoice_id'] ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge badge-neutral">No record</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        <?php if (!$hasBilling): ?>
                            <button class="btn btn-sm btn-primary"
                                    onclick="openCreateModal(
                                        <?= $op['operation_id'] ?>,
                                        <?= $op['patient_id'] ?>,
                                        '<?= addslashes($op['patient_name']) ?>',
                                        '<?= addslashes($op['operation_type']) ?>',
                                        <?= (float)$op['default_charge'] ?>)">
                                ➕ Add Billing
                            </button>
                        <?php else: ?>
                            <?php if (!$op['invoice_id'] && $op['billing_status'] !== 'waived'): ?>
                                <button class="btn btn-sm btn-secondary"
                                        onclick="openLinkModal(<?= $op['operation_id'] ?>, '<?= addslashes($op['patient_name']) ?>', <?= (float)$op['total_charge'] ?>)">
                                    🔗 Link Invoice
                                </button>
                            <?php endif; ?>
                            <a href="theatre_billing_detail.php?op=<?= $op['operation_id'] ?>"
                               class="btn btn-sm btn-secondary">View</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ════════════════ Create Billing Modal ════════════════ -->
<div id="createModal" class="tb-modal-ov" style="display:none" onclick="if(event.target===this)closeCreate()">
    <div class="tb-modal-box">
        <div class="tb-modal-hd">
            <h3>➕ Add Theatre Billing</h3>
            <button onclick="closeCreate()" class="tb-modal-x">✕</button>
        </div>
        <div class="tb-modal-bd">
            <div class="tb-info-row"><span class="tb-lbl">Patient</span><span id="cm-pat" class="tb-val"></span></div>
            <div class="tb-info-row"><span class="tb-lbl">Operation</span><span id="cm-op" class="tb-val"></span></div>

            <form method="POST" style="margin-top:16px">
                <input type="hidden" name="action"       value="create_billing">
                <input type="hidden" name="operation_id" id="cm-op-id">
                <input type="hidden" name="patient_id"   id="cm-pat-id">

                <div class="form-group">
                    <label class="form-label">Operation Charge (Rs.)</label>
                    <input type="number" name="operation_charge" id="cm-op-charge"
                           class="form-control" min="0" step="0.01"
                           oninput="calcTotal()" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Anaesthesia Charge (Rs.)</label>
                    <input type="number" name="anaesthesia_charge" id="cm-an-charge"
                           class="form-control" min="0" step="0.01" value="0"
                           oninput="calcTotal()">
                </div>
                <div class="form-group">
                    <label class="form-label">Consumables Charge (Rs.)</label>
                    <input type="number" name="consumables_charge" id="cm-con-charge"
                           class="form-control" min="0" step="0.01" value="0"
                           oninput="calcTotal()">
                </div>
                <div class="tb-total-row">
                    <span>Total Charge:</span>
                    <span id="cm-total" style="font-weight:700;color:var(--success)">Rs. 0.00</span>
                </div>
                <div class="form-group" style="margin-top:14px">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes…"></textarea>
                </div>
                <div class="tb-modal-ft">
                    <button type="button" class="btn btn-secondary" onclick="closeCreate()">Cancel</button>
                    <button type="submit" class="btn btn-primary">💾 Save Billing</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ════════════════ Link Invoice Modal ════════════════ -->
<div id="linkModal" class="tb-modal-ov" style="display:none" onclick="if(event.target===this)closeLink()">
    <div class="tb-modal-box">
        <div class="tb-modal-hd">
            <h3>🔗 Link to Invoice</h3>
            <button onclick="closeLink()" class="tb-modal-x">✕</button>
        </div>
        <div class="tb-modal-bd">
            <div class="tb-info-row"><span class="tb-lbl">Patient</span><span id="lm-pat" class="tb-val"></span></div>
            <div class="tb-info-row"><span class="tb-lbl">Theatre Charge</span><span id="lm-charge" class="tb-val" style="color:var(--success);font-weight:700"></span></div>

            <form method="POST" style="margin-top:16px">
                <input type="hidden" name="action"       value="link_invoice">
                <input type="hidden" name="operation_id" id="lm-op-id">

                <div class="form-group">
                    <label class="form-label">Select Open Invoice</label>
                    <select name="invoice_id" class="form-control" required>
                        <option value="">— Select invoice —</option>
                        <?php foreach ($invoices as $inv): ?>
                            <option value="<?= $inv['invoice_id'] ?>">
                                #<?= $inv['invoice_id'] ?> — <?= htmlspecialchars($inv['patient_name']) ?>
                                (Rs. <?= number_format((float)$inv['total_amount'],2) ?> / Balance: Rs. <?= number_format(max(0,(float)$inv['total_amount']-(float)$inv['paid_amount']),2) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($invoices)): ?>
                        <p style="font-size:12px;color:var(--muted);margin-top:6px">No open invoices. Create one in Billing first.</p>
                    <?php endif; ?>
                </div>
                <div class="tb-modal-ft">
                    <button type="button" class="btn btn-secondary" onclick="closeLink()">Cancel</button>
                    <button type="submit" class="btn btn-primary">🔗 Link &amp; Add Charge</button>
                </div>
            </form>
        </div>
    </div>
</div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<style>
.alert-msg { padding:12px 16px; border-radius:var(--radius); margin-bottom:16px; font-size:14px; font-weight:500; }
.alert-msg.success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
.alert-msg.danger  { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

/* Modal */
.tb-modal-ov  { position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;display:flex;align-items:center;justify-content:center; }
.tb-modal-box { background:var(--surface);border-radius:12px;width:100%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,.25);animation:tbsu .2s ease; }
@keyframes tbsu { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:none} }
.tb-modal-hd  { display:flex;align-items:center;justify-content:space-between;padding:18px 20px 14px;border-bottom:1px solid var(--border-light); }
.tb-modal-hd h3 { font-size:1.05rem;font-weight:700; }
.tb-modal-x   { background:none;border:none;font-size:18px;cursor:pointer;color:var(--muted); }
.tb-modal-x:hover { color:var(--danger); }
.tb-modal-bd  { padding:20px; }
.tb-modal-ft  { display:flex;justify-content:flex-end;gap:10px;margin-top:20px;padding-top:16px;border-top:1px solid var(--border-light); }
.tb-info-row  { display:flex;justify-content:space-between;margin-bottom:10px;font-size:13.5px; }
.tb-lbl       { color:var(--muted);font-weight:500; }
.tb-val       { font-weight:600; }
.tb-total-row { display:flex;justify-content:space-between;padding:10px 12px;background:var(--bg);border-radius:var(--radius);font-size:14px;margin-bottom:4px; }
</style>

<script>
function openCreateModal(opId, patId, patName, opType, defaultCharge) {
    document.getElementById('cm-op-id').value     = opId;
    document.getElementById('cm-pat-id').value    = patId;
    document.getElementById('cm-pat').textContent = patName;
    document.getElementById('cm-op').textContent  = opType;
    document.getElementById('cm-op-charge').value = defaultCharge || '';
    document.getElementById('cm-an-charge').value = defaultCharge ? (defaultCharge * 0.15).toFixed(2) : '0';
    document.getElementById('cm-con-charge').value= defaultCharge ? (defaultCharge * 0.08).toFixed(2) : '0';
    calcTotal();
    document.getElementById('createModal').style.display = 'flex';
}
function closeCreate() { document.getElementById('createModal').style.display = 'none'; }

function openLinkModal(opId, patName, charge) {
    document.getElementById('lm-op-id').value     = opId;
    document.getElementById('lm-pat').textContent = patName;
    document.getElementById('lm-charge').textContent = 'Rs. ' + parseFloat(charge).toLocaleString('en-LK',{minimumFractionDigits:2});
    document.getElementById('linkModal').style.display = 'flex';
}
function closeLink() { document.getElementById('linkModal').style.display = 'none'; }

function calcTotal() {
    const op  = parseFloat(document.getElementById('cm-op-charge').value)  || 0;
    const an  = parseFloat(document.getElementById('cm-an-charge').value)   || 0;
    const con = parseFloat(document.getElementById('cm-con-charge').value)  || 0;
    document.getElementById('cm-total').textContent = 'Rs. ' + (op + an + con).toLocaleString('en-LK',{minimumFractionDigits:2});
}
</script>
