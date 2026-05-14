<?php
// modules/billing/billing_check.php
// Reception Billing Portal

require_once '../../includes/session_check.php';

$allowedRoles = ['reception', 'admin'];
if (!in_array($_SESSION['role'] ?? '', $allowedRoles)) {
    header('Location: /Web/Hospital-Management-System/login.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'hospital_db');
if ($conn->connect_error) {
    die("DB error: " . $conn->connect_error);
}

$staff_user_id = (int)$_SESSION['user_id'];
$success_msg   = '';
$error_msg     = '';

// ════════════════════════════════════════
// POST handlers
// ════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action         = $_POST['action']         ?? '';
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);

    // 1. Update appointment status
    if ($action === 'update_appt_status' && $appointment_id > 0) {
        $new_status = $_POST['appt_status'] ?? '';
        $allowed    = ['pending','confirmed','completed','cancelled'];
        if (in_array($new_status, $allowed)) {
            $stmt = $conn->prepare("UPDATE appointments SET status=? WHERE appointment_id=?");
            $stmt->bind_param('si', $new_status, $appointment_id);
            $stmt->execute() ? $success_msg = "Appointment status updated to '$new_status'."
                             : $error_msg   = $stmt->error;
            $stmt->close();
        }
    }

    // 2. Update payment status
    if ($action === 'update_payment_status' && $appointment_id > 0) {

        $pay_status  = $_POST['payment_status'] ?? '';
        $patient_id  = (int)($_POST['patient_id']  ?? 0);
        $paid_amount = (float)($_POST['paid_amount'] ?? 0);
        $pay_method  = trim($_POST['payment_method'] ?? '');
        $pay_method  = $pay_method !== '' ? $pay_method : null;

        $allowed_pay = ['Pending','Done','Cancelled'];

        if (!in_array($pay_status, $allowed_pay) || $patient_id < 1) {
            $error_msg = "Invalid data.";
        } else {

            // Build receipt + date only for Done
            $receipt_number = null;
            $payment_date   = null;
            if ($pay_status === 'Done') {
                $r        = $conn->query("SELECT COUNT(*)+1 AS n FROM patient_billing_status WHERE payment_status='Done'");
                $n        = (int)$r->fetch_assoc()['n'];
                $receipt_number = 'RCP-' . date('Y') . '-' . str_pad($n, 5, '0', STR_PAD_LEFT);
                $payment_date   = date('Y-m-d');
            }

            // Check existing row
            $chk = $conn->prepare("SELECT billing_status_id FROM patient_billing_status WHERE appointment_id=? LIMIT 1");
            $chk->bind_param('i', $appointment_id);
            $chk->execute();
            $exists = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($exists) {
                // UPDATE
                // types: s d s s s i i
                //        pay_status paid_amount pay_method payment_date receipt_number staff_id appt_id
                $stmt = $conn->prepare(
                    "UPDATE patient_billing_status
                        SET payment_status=?, paid_amount=?, payment_method=?,
                            payment_date=?, receipt_number=?, received_by=?, updated_at=NOW()
                      WHERE appointment_id=?"
                );
                $stmt->bind_param('sdsssii',
                    $pay_status, $paid_amount, $pay_method,
                    $payment_date, $receipt_number,
                    $staff_user_id, $appointment_id
                );
            } else {
                // INSERT
                // types: i i s d s s s i
                //        appt_id patient_id pay_status paid_amount pay_method payment_date receipt_number staff_id
                $stmt = $conn->prepare(
                    "INSERT INTO patient_billing_status
                        (appointment_id, patient_id, payment_status, paid_amount,
                         payment_method, payment_date, receipt_number, received_by)
                     VALUES (?,?,?,?,?,?,?,?)"
                );
                $stmt->bind_param('iisdsssi',
                    $appointment_id, $patient_id,
                    $pay_status, $paid_amount,
                    $pay_method, $payment_date, $receipt_number,
                    $staff_user_id
                );
            }

            if ($stmt->execute()) {
                $success_msg = "Payment status set to <strong>$pay_status</strong>" .
                               ($receipt_number ? " — Receipt: <strong>$receipt_number</strong>" : "") . ".";
            } else {
                $error_msg = "DB error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// ════════════════════════════════════════
// Load appointments
// ════════════════════════════════════════
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where = ["1=1"];
if ($filter !== 'all' && in_array($filter, ['pending','confirmed','completed','cancelled'])) {
    $where[] = "a.status = '" . $conn->real_escape_string($filter) . "'";
}
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where[] = "(u_pat.full_name LIKE '%$s%' OR a.ref_number LIKE '%$s%' OR u_doc.full_name LIKE '%$s%')";
}

$rows = $conn->query(
    "SELECT
        a.appointment_id, a.ref_number, a.appt_date, a.appt_time, a.source,
        a.status                                        AS appt_status,
        pat.patient_id,
        u_pat.full_name                                 AS patient_name,
        pat.phone,
        u_doc.full_name                                 AS doctor_name,
        d.specialization, d.consultation_fee,
        COALESCE(pbs.payment_status,'Pending')          AS payment_status,
        COALESCE(pbs.paid_amount, 0)                    AS paid_amount,
        pbs.payment_method, pbs.payment_date, pbs.receipt_number
    FROM appointments a
    JOIN patients  pat  ON pat.patient_id = a.patient_id
    JOIN users   u_pat  ON u_pat.user_id  = pat.user_id
    JOIN doctors    d   ON d.doctor_id    = a.doctor_id
    JOIN users   u_doc  ON u_doc.user_id  = d.user_id
    LEFT JOIN patient_billing_status pbs ON pbs.appointment_id = a.appointment_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY a.appt_date DESC, a.appt_time DESC"
);
$appointments = $rows ? $rows->fetch_all(MYSQLI_ASSOC) : [];

// Stats
$stats = $conn->query(
    "SELECT
        COUNT(*)                                                 AS total,
        SUM(a.status='pending')                                  AS pending,
        SUM(a.status='confirmed')                                AS confirmed,
        SUM(a.status='completed')                                AS completed,
        SUM(COALESCE(pbs.payment_status,'Pending')='Done')       AS paid_count,
        SUM(COALESCE(pbs.payment_status,'Pending')='Pending')    AS unpaid_count,
        COALESCE(SUM(pbs.paid_amount),0)                         AS total_collected
    FROM appointments a
    LEFT JOIN patient_billing_status pbs ON pbs.appointment_id = a.appointment_id"
)->fetch_assoc();

$pageTitle  = "Billing Check";
$useSidebar = true;
$isPublic   = false;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<main class="main-content">

<div class="page-header">
    <div class="page-header-title">
        <h2>💳 Billing Check</h2>
        <p>Manage appointment &amp; payment status — <?php echo date('l, d F Y'); ?></p>
    </div>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert-success">✅ <?php echo $success_msg; ?></div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div class="alert alert-danger">❌ <?php echo htmlspecialchars($error_msg); ?></div>
<?php endif; ?>

<!-- Stat cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue">📋</div>
        <div>
            <div class="stat-label">Total</div>
            <div class="stat-value"><?php echo (int)$stats['total']; ?></div>
            <div class="stat-change">All appointments</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">⏳</div>
        <div>
            <div class="stat-label">Active</div>
            <div class="stat-value"><?php echo (int)$stats['pending'] + (int)$stats['confirmed']; ?></div>
            <div class="stat-change">Pending + Confirmed</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">💰</div>
        <div>
            <div class="stat-label">Collected</div>
            <div class="stat-value"><?php echo (int)$stats['paid_count']; ?></div>
            <div class="stat-change">Rs. <?php echo number_format((float)$stats['total_collected'],2); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">💳</div>
        <div>
            <div class="stat-label">Unpaid</div>
            <div class="stat-value"><?php echo (int)$stats['unpaid_count']; ?></div>
            <div class="stat-change">Pending payment</div>
        </div>
    </div>
</div>

<!-- Table card -->
<div class="card">
    <div class="card-header">
        <h3>📅 Appointments</h3>
    </div>

    <!-- Filter tabs + search -->
    <div class="bc-filter-bar">
        <div class="bc-tabs">
            <?php foreach (['all'=>'All','pending'=>'Pending','confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled'] as $k=>$lbl): ?>
                <a href="?filter=<?php echo $k; ?>&search=<?php echo urlencode($search); ?>"
                   class="bc-tab <?php echo $filter===$k?'active':''; ?>"><?php echo $lbl; ?></a>
            <?php endforeach; ?>
        </div>
        <form method="GET" class="bc-search-form">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <input type="text"   name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search patient / doctor / ref…" class="bc-search-input">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if($search): ?>
                <a href="?filter=<?php echo $filter; ?>" class="btn btn-secondary btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($appointments)): ?>
        <div class="bc-empty">📭 No appointments found.</div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="bc-table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Date / Time</th>
                    <th>Source</th>
                    <th>Fee</th>
                    <th>Appt Status</th>
                    <th>Payment Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($appointments as $r): ?>
            <tr>
                <!-- Ref -->
                <td><span class="ref-tag"><?php echo htmlspecialchars($r['ref_number']); ?></span></td>

                <!-- Patient -->
                <td>
                    <div class="cname"><?php echo htmlspecialchars($r['patient_name']); ?></div>
                    <div class="csub"><?php echo htmlspecialchars($r['phone']); ?></div>
                </td>

                <!-- Doctor -->
                <td>
                    <div class="cname">Dr. <?php echo htmlspecialchars($r['doctor_name']); ?></div>
                    <div class="csub"><?php echo htmlspecialchars($r['specialization']); ?></div>
                </td>

                <!-- Date/Time -->
                <td>
                    <div class="cname"><?php echo date('d M Y', strtotime($r['appt_date'])); ?></div>
                    <div class="csub"><?php echo date('h:i A', strtotime($r['appt_time'])); ?></div>
                </td>

                <!-- Source -->
                <td><span class="src-tag"><?php echo strtoupper($r['source']); ?></span></td>

                <!-- Fee -->
                <td class="fee-val">Rs. <?php echo number_format((float)$r['consultation_fee'],2); ?></td>

                <!-- Appt Status — inline dropdown, submit on change -->
                <td>
                    <form method="POST" style="margin:0">
                        <input type="hidden" name="action"         value="update_appt_status">
                        <input type="hidden" name="appointment_id" value="<?php echo $r['appointment_id']; ?>">
                        <select name="appt_status" class="status-sel appt-sel"
                                onchange="this.form.submit()">
                            <?php foreach(['pending'=>'⏳ Pending','confirmed'=>'✔ Confirmed','completed'=>'✅ Completed','cancelled'=>'❌ Cancelled'] as $v=>$l): ?>
                                <option value="<?php echo $v; ?>" <?php echo $r['appt_status']===$v?'selected':''; ?>>
                                    <?php echo $l; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>

                <!-- Payment Status — inline dropdown, submit on change -->
                <td>
                    <?php if ($r['payment_status'] === 'Done'): ?>
                        <!-- Locked — show badge + info -->
                        <span class="badge badge-success">💰 Done</span>
                        <div class="csub">Rs. <?php echo number_format((float)$r['paid_amount'],2); ?></div>
                        <?php if ($r['receipt_number']): ?>
                            <div class="csub receipt-num">🧾 <?php echo htmlspecialchars($r['receipt_number']); ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Dropdown — Pending or Cancelled can change -->
                        <form method="POST" style="margin:0" id="pf-<?php echo $r['appointment_id']; ?>">
                            <input type="hidden" name="action"         value="update_payment_status">
                            <input type="hidden" name="appointment_id" value="<?php echo $r['appointment_id']; ?>">
                            <input type="hidden" name="patient_id"     value="<?php echo $r['patient_id']; ?>">
                            <!-- These get overwritten by modal before submit when Done is chosen -->
                            <input type="hidden" name="paid_amount"    id="pa-<?php echo $r['appointment_id']; ?>"
                                   value="<?php echo (float)$r['consultation_fee']; ?>">
                            <input type="hidden" name="payment_method" id="pm-<?php echo $r['appointment_id']; ?>" value="">
                            <select name="payment_status"
                                    class="status-sel pay-sel"
                                    id="ps-<?php echo $r['appointment_id']; ?>"
                                    onchange="onPayChange(this,
                                        <?php echo $r['appointment_id']; ?>,
                                        <?php echo $r['patient_id']; ?>,
                                        '<?php echo addslashes($r['patient_name']); ?>',
                                        '<?php echo addslashes($r['ref_number']); ?>',
                                        <?php echo (float)$r['consultation_fee']; ?>)">
                                <option value="Pending"   <?php echo $r['payment_status']==='Pending'   ?'selected':''; ?>>⏳ Pending</option>
                                <option value="Done">✅ Done</option>
                                <option value="Cancelled" <?php echo $r['payment_status']==='Cancelled' ?'selected':''; ?>>❌ Cancelled</option>
                            </select>
                        </form>
                        <?php if ((float)$r['paid_amount'] > 0): ?>
                            <div class="csub">Rs. <?php echo number_format((float)$r['paid_amount'],2); ?> paid</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>

                <!-- Action button -->
                <td>
                    <?php if ($r['payment_status'] !== 'Done'): ?>
                        <button class="btn btn-primary btn-sm"
                                onclick="openModal(
                                    <?php echo $r['appointment_id']; ?>,
                                    <?php echo $r['patient_id']; ?>,
                                    '<?php echo addslashes($r['patient_name']); ?>',
                                    '<?php echo addslashes($r['ref_number']); ?>',
                                    <?php echo (float)$r['consultation_fee']; ?>,
                                    '<?php echo $r['payment_status']; ?>')">
                            💳 Record
                        </button>
                    <?php else: ?>
                        <span class="csub" style="font-style:italic">Settled</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div><!-- /card -->

<!-- ════════════════════════════
     Payment Modal
════════════════════════════ -->
<div id="payModal" class="modal-ov" style="display:none" onclick="if(event.target===this)closeModal()">
    <div class="modal-box">
        <div class="modal-hd">
            <h3>💳 Record Payment</h3>
            <button onclick="closeModal()" class="modal-x">✕</button>
        </div>
        <div class="modal-bd">

            <!-- Info rows -->
            <div class="m-info-row"><span class="m-lbl">Patient</span><span id="m-pat" class="m-val"></span></div>
            <div class="m-info-row"><span class="m-lbl">Ref #</span><span id="m-ref" class="m-val"></span></div>
            <div class="m-info-row"><span class="m-lbl">Consultation Fee</span><span id="m-fee" class="m-val fee-val"></span></div>

            <form method="POST" id="modalForm" style="margin-top:16px">
                <input type="hidden" name="action"         value="update_payment_status">
                <input type="hidden" name="appointment_id" id="m-appt-id">
                <input type="hidden" name="patient_id"     id="m-pat-id">

                <div class="fg">
                    <label class="fl">Payment Status</label>
                    <select name="payment_status" id="m-pay-status" class="fi" onchange="togglePayFields()">
                        <option value="Pending">⏳ Pending</option>
                        <option value="Done"   >✅ Done — Fully Paid</option>
                        <option value="Cancelled">❌ Cancelled</option>
                    </select>
                </div>

                <div id="done-fields" style="display:none">
                    <div class="fg">
                        <label class="fl">Amount Paid (Rs.)</label>
                        <input type="number" name="paid_amount" id="m-amount"
                               class="fi" min="0" step="0.01" placeholder="0.00" required>
                    </div>
                    <div class="fg">
                        <label class="fl">Payment Method</label>
                        <select name="payment_method" id="m-method" class="fi">
                            <option value="">— Select method —</option>
                            <option value="Cash">💵 Cash</option>
                            <option value="Card">💳 Card</option>
                            <option value="Online Transfer">🌐 Online Transfer</option>
                            <option value="Bank Payment">🏦 Bank Payment</option>
                        </select>
                    </div>
                </div>

                <div class="modal-ft">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">💾 Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

</main>
<?php include '../../includes/footer.php'; ?>

<style>
/* alerts */
.alert{padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;font-size:14px;font-weight:500}
.alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
.alert-danger {background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}

/* filter bar */
.bc-filter-bar{padding:14px 20px 10px;display:flex;flex-wrap:wrap;gap:12px;align-items:center}
.bc-tabs{display:flex;flex-wrap:wrap;gap:6px}
.bc-tab{padding:5px 14px;border-radius:20px;font-size:13px;font-weight:500;color:var(--muted);
        background:var(--bg);border:1px solid var(--border-light);text-decoration:none;transition:.15s}
.bc-tab:hover{background:var(--accent-light);color:var(--accent-dark)}
.bc-tab.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.bc-search-form{display:flex;gap:8px;align-items:center;margin-left:auto;flex-wrap:wrap}
.bc-search-input{padding:7px 12px;border:1px solid var(--border);border-radius:var(--radius);
                 font-size:13px;color:var(--text);background:var(--surface);width:210px}
.bc-search-input:focus{outline:none;border-color:var(--accent)}
.bc-empty{padding:40px;text-align:center;color:var(--muted);font-size:14px}

/* table */
.bc-table{width:100%;border-collapse:collapse;font-size:13.5px}
.bc-table th{text-align:left;padding:10px 14px;font-size:12px;font-weight:600;color:var(--muted);
             text-transform:uppercase;letter-spacing:.4px;background:var(--bg);
             border-bottom:2px solid var(--border-light);white-space:nowrap}
.bc-table td{padding:11px 14px;border-bottom:1px solid var(--border-light);vertical-align:middle}
.bc-table tbody tr:hover{background:var(--bg)}
.bc-table tbody tr:last-child td{border-bottom:none}

.cname{font-weight:600;font-size:13.5px}
.csub {font-size:11.5px;color:var(--muted);margin-top:2px}
.ref-tag{font-family:monospace;font-size:12px;font-weight:700;color:var(--accent-dark);
         background:var(--accent-light);padding:2px 8px;border-radius:4px}
.src-tag{font-size:11px;font-weight:700;background:var(--surface);color:var(--text-mid);
         padding:2px 8px;border-radius:4px;border:1px solid var(--border)}
.fee-val{font-weight:700;color:var(--success);white-space:nowrap}
.receipt-num{font-family:monospace;font-size:11px;color:var(--accent-dark)}

/* inline selects */
.status-sel{padding:5px 8px;border-radius:var(--radius);border:1px solid var(--border);
            background:var(--surface);color:var(--text);font-size:12.5px;cursor:pointer;width:100%}
.status-sel:focus{outline:none;border-color:var(--accent)}

/* appt select colours */
.appt-sel option[value="pending"]  {color:#92400e}
.appt-sel option[value="confirmed"]{color:#075985}
.appt-sel option[value="completed"]{color:#065f46}
.appt-sel option[value="cancelled"]{color:#991b1b}

/* pay select colours */
.pay-sel option[value="Done"]     {color:#065f46;font-weight:600}
.pay-sel option[value="Cancelled"]{color:#991b1b;font-weight:600}

/* modal */
.modal-ov{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;
           display:flex;align-items:center;justify-content:center}
.modal-box{background:var(--surface);border-radius:12px;width:100%;max-width:460px;
           box-shadow:0 20px 60px rgba(0,0,0,.25);animation:su .2s ease}
@keyframes su{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
.modal-hd{display:flex;align-items:center;justify-content:space-between;
          padding:18px 20px 14px;border-bottom:1px solid var(--border-light)}
.modal-hd h3{font-size:1.05rem;font-weight:700}
.modal-x{background:none;border:none;font-size:18px;cursor:pointer;color:var(--muted)}
.modal-x:hover{color:var(--danger)}
.modal-bd{padding:20px}
.m-info-row{display:flex;justify-content:space-between;margin-bottom:10px;font-size:13.5px}
.m-lbl{color:var(--muted);font-weight:500}
.m-val{font-weight:600}
.modal-ft{display:flex;justify-content:flex-end;gap:10px;margin-top:20px;
          padding-top:16px;border-top:1px solid var(--border-light)}

/* form inside modal */
.fg{margin-bottom:16px}
.fl{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px;
    text-transform:uppercase;letter-spacing:.4px}
.fi{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:var(--radius);
    font-size:13.5px;color:var(--text);background:var(--bg);box-sizing:border-box}
.fi:focus{outline:none;border-color:var(--accent)}
</style>

<script>
// When reception changes payment dropdown
function onPayChange(sel, apptId, patId, patName, ref, fee) {
    const val = sel.value;
    if (val === 'Done') {
        sel.value = 'Pending'; // reset — let modal handle submit
        openModal(apptId, patId, patName, ref, fee, 'Pending');
    } else if (val === 'Cancelled') {
        if (confirm('Mark payment as Cancelled for ' + patName + '?')) {
            document.getElementById('pf-' + apptId).submit();
        } else {
            sel.value = 'Pending';
        }
    }
    // Pending — no submit needed (already selected)
}

// Open modal
function openModal(apptId, patId, patName, ref, fee, curStatus) {
    document.getElementById('m-appt-id').value  = apptId;
    document.getElementById('m-pat-id').value   = patId;
    document.getElementById('m-pat').textContent = patName;
    document.getElementById('m-ref').textContent = ref;
    document.getElementById('m-fee').textContent = 'Rs. ' + parseFloat(fee).toLocaleString('en-LK',{minimumFractionDigits:2});
    document.getElementById('m-amount').value   = fee;
    document.getElementById('m-method').value   = '';
    document.getElementById('m-pay-status').value = 'Done';
    togglePayFields();
    document.getElementById('payModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('payModal').style.display = 'none';
}

function togglePayFields() {
    const s = document.getElementById('m-pay-status').value;
    document.getElementById('done-fields').style.display = s === 'Done' ? 'block' : 'none';
}
</script>
