<?php
// modules/billing/my_bills.php
// Patient's own billing portal — appointment payments + balance tracking

require_once '../../includes/session_check.php';
// role_check: only 'patient' can access this page
if (($_SESSION['role'] ?? '') !== 'patient') {
    header('Location: /Web/Hospital-Management-System/login.php');
    exit;
}

$host   = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'hospital_db';

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// ── Current patient ────────────────────────────────────────────
$user_id = (int) $_SESSION['user_id'];

$patRow = $conn->query(
    "SELECT p.patient_id, u.full_name, p.phone, p.gender, p.dob
     FROM patients p
     JOIN users u ON u.user_id = p.user_id
     WHERE p.user_id = $user_id
     LIMIT 1"
)->fetch_assoc();

$patient_id   = (int) ($patRow['patient_id'] ?? 0);
$patientName  = $patRow['full_name'] ?? 'Patient';

// ── Appointment payment rows (joined with patient_billing_status) ──
// patient_billing_status links appointment_id → payment_status
$billRows = $conn->query(
    "SELECT
         a.appointment_id,
         a.ref_number,
         a.appt_date,
         a.appt_time,
         a.source,
         u_doc.full_name  AS doctor_name,
         d.specialization,
         d.consultation_fee,
         COALESCE(pbs.payment_status, 'Pending') AS payment_status,
         pbs.paid_amount,
         pbs.payment_method,
         pbs.payment_date,
         pbs.receipt_number
     FROM appointments a
     JOIN patients pat       ON pat.patient_id   = a.patient_id
     JOIN doctors d          ON d.doctor_id      = a.doctor_id
     JOIN users u_doc        ON u_doc.user_id    = d.user_id
     LEFT JOIN patient_billing_status pbs
                             ON pbs.appointment_id = a.appointment_id
     WHERE a.patient_id = $patient_id
     ORDER BY a.appt_date DESC, a.appt_time DESC"
);
$bills = $billRows ? $billRows->fetch_all(MYSQLI_ASSOC) : [];

// ── Summary stats ─────────────────────────────────────────────
$totalBills   = count($bills);
$totalDue     = 0;
$totalPaid    = 0;
$pendingCount = 0;

foreach ($bills as $b) {
    $fee = (float)($b['consultation_fee'] ?? 0);
    $totalDue += $fee;
    if (strtolower($b['payment_status']) === 'done') {
        $totalPaid += (float)($b['paid_amount'] ?? $fee);
    } else {
        $pendingCount++;
    }
}
$totalBalance = $totalDue - $totalPaid;

// ── Page setup ────────────────────────────────────────────────
$pageTitle  = "My Bills";
$useSidebar = true;
$isPublic   = false;

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-title">
            <h2>My Bills &amp; Payments</h2>
            <p>View your appointment invoices, payment status, and receipts — <?php echo htmlspecialchars($patientName); ?></p>
        </div>
        <div class="flex gap-12 items-center">
            <a href="/Web/Hospital-Management-System/modules/appointments/book.php" class="btn btn-primary">
                <span class="icon"></span> Book Appointment
            </a>
        </div>
    </div>

    <!-- ── Summary Stat Cards ── -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon blue">

             <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
                    <path d="M12 2v4" />
                    <path d="M12 18v4" />
                </svg>
            </div>
            <div>
                <div class="stat-label">Total Appointments</div>
                <div class="stat-value"><?php echo $totalBills; ?></div>
                <div class="stat-change">All time</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">

            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                </svg>
            </div>
            <div>
                <div class="stat-label">Total Paid</div>
                <div class="stat-value">Rs. <?php echo number_format($totalPaid, 2); ?></div>
                <div class="stat-change">Settled payments</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow">

            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
        </div>
            <div>
                <div class="stat-label">Pending Bills</div>
                <div class="stat-value"><?php echo $pendingCount; ?></div>
                <div class="stat-change">Awaiting payment</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">

            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"/>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
        </div>
            <div>
                <div class="stat-label">Outstanding Balance</div>
                <div class="stat-value">Rs. <?php echo number_format(max(0, $totalBalance), 2); ?></div>
                <div class="stat-change">Amount due</div>
            </div>
        </div>
    </div>

    <!-- ── Appointment Payment Table ── -->
    <div class="card">
        <div class="card-header">
            <h3>Appointment Payments</h3>
            <span class="badge badge-info"><?php echo $totalBills; ?> records</span>
        </div>

        <?php if (empty($bills)): ?>
            <div class="empty-state">
                <div class="empty-icon"></div>
                <p class="empty-title">No billing records found</p>
                <p class="empty-sub">Your appointment payments will appear here once booked.</p>
            </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ref #</th>
                        <th>Doctor</th>
                        <th>Appointment Date</th>
                        <th>Time</th>
                        <th>Source</th>
                        <th>Fee (Rs.)</th>
                        <th>Payment Status</th>
                        
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bills as $b):
                        $status      = strtolower($b['payment_status']);
                        $isDone      = ($status === 'done');
                        $isCancelled = ($status === 'cancelled');

                        $statusClass = match(true) {
                            $isDone      => 'badge-success',
                            $isCancelled => 'badge-danger',
                            default      => 'badge-warning',
                        };
                        $statusLabel = match(true) {
                            $isDone      => 'Done',
                            $isCancelled => 'Cancelled',
                            default      => 'Pending',
                        };
                    ?>
                    <tr>
                        <td><span class="ref-num">#<?php echo htmlspecialchars($b['ref_number']); ?></span></td>
                        <td>
                            <div class="doc-cell">
                              
                                <div>
                                    <div class="doc-name">Dr. <?php echo htmlspecialchars($b['doctor_name']); ?></div>
                                    <div class="doc-spec"><?php echo htmlspecialchars($b['specialization']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo date('d M Y', strtotime($b['appt_date'])); ?></td>
                        <td><?php echo date('h:i A', strtotime($b['appt_time'])); ?></td>
                        <td><span class="source-tag"><?php echo htmlspecialchars(strtoupper($b['source'] ?? 'OPD')); ?></span></td>
                        <td class="fee-cell">Rs. <?php echo number_format((float)$b['consultation_fee'], 2); ?></td>
                        <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                        
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Patient Info Footer Card ── -->
    <div class="card info-card">
        <div class="card-header">
            <h3>Payment Information</h3>
        </div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Payment Methods Accepted</div>
                <div class="info-value">Cash &bull; Card &bull; Online Transfer &bull; Bank Payment</div>
            </div>
            <div class="info-item">
                <div class="info-label">For queries, contact</div>
                <div class="info-value">Reception Desk &mdash; Ext. 100</div>
            </div>
            <div class="info-item">
                <div class="info-label">Billing Hours</div>
                <div class="info-value">8:00 AM – 6:00 PM (Mon–Sat)</div>
            </div>
        </div>
    </div>

</main>

<?php include '../../includes/footer.php'; ?>

<style>
/* ── Page-specific styles for my_bills.php ── */

.flex        { display: flex; }
.gap-12      { gap: 12px; }
.items-center { align-items: center; }

/* Table */
.table-wrap  { overflow-x: auto; }

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}
.data-table th {
    text-align: left;
    padding: 10px 14px;
    font-weight: 600;
    font-size: 12px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.4px;
    background: var(--bg);
    border-bottom: 2px solid var(--border-light);
    white-space: nowrap;
}
.data-table td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border-light);
    vertical-align: middle;
    color: var(--text);
}
.data-table tbody tr:hover { background: var(--bg); }
.data-table tbody tr:last-child td { border-bottom: none; }

/* Doctor cell */
.doc-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.doc-name { font-weight: 600; font-size: 13.5px; }
.doc-spec  { font-size: 11px; color: var(--muted); }

/* Badges */
.badge-success { background: #d1fae5; color: #065f46; }
.badge-warning { background: #fef9c3; color: #854d0e; }
.badge-danger  { background: #fee2e2; color: #991b1b; }
.badge-info    { background: var(--accent-light); color: var(--accent-dark); }

/* Misc cells */
.ref-num    { font-family: monospace; font-size: 12.5px; color: var(--accent-dark); font-weight: 600; }
.fee-cell   { font-weight: 700; color: var(--success); white-space: nowrap; }
.muted-text { font-size: 12px; color: var(--muted); font-style: italic; }

.source-tag {
    background: var(--accent-light);
    color: var(--accent-dark);
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 4px;
    letter-spacing: 0.5px;
}

/* Stat icon colors */
.stat-icon.red { background: #fee2e2; }

/* Info card */
.info-card { margin-top: 0; }
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    padding-top: 4px;
}
.info-item {}
.info-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 4px; font-weight: 600; }
.info-value { font-size: 13.5px; color: var(--text); font-weight: 500; }

/* Empty state */
.empty-state { text-align: center; padding: 48px 20px; }
.empty-icon  { font-size: 44px; margin-bottom: 12px; }
.empty-title { font-weight: 700; color: var(--text-mid); margin-bottom: 6px; }
.empty-sub   { font-size: 13px; color: var(--muted); }
</style>
