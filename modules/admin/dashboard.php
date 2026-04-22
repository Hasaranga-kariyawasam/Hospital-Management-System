<?php
declare(strict_types=1);

$requiredRoles = ['admin'];
require_once __DIR__ . '/../../includes/role_check.php';
require_once __DIR__ . '/../../config/db_config.php';

$pageTitle  = 'Admin Dashboard';
$useSidebar = true;
$pageCss    = '/Web/Hospital-Management-System/modules/admin/dashboard.css';

// ── Live stats queries ──────────────────────────────────────
$today = date('Y-m-d');

$todayAppts = $pdo->query("
    SELECT COUNT(*) FROM appointments WHERE appt_date = '$today'
")->fetchColumn();

$admittedPts = $pdo->query("
    SELECT COUNT(*) FROM admissions WHERE status = 'admitted'
")->fetchColumn();

$pendingBills = $pdo->query("
    SELECT COUNT(*) FROM billing_invoices WHERE status = 'open'
")->fetchColumn();

$emergencyOpen = $pdo->query("
    SELECT COUNT(*) FROM emergency_requests WHERE status IN ('pending','dispatched','en_route')
")->fetchColumn();

$bedsAvail = $pdo->query("
    SELECT COUNT(*) FROM rooms WHERE is_available = 1
")->fetchColumn();

$bedsTotal = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();

// Recent appointments
$recentAppts = $pdo->query("
    SELECT a.ref_number, a.appt_date, a.appt_time, a.status,
           u_p.full_name AS patient_name,
           u_d.full_name AS doctor_name
    FROM appointments a
    JOIN patients p  ON a.patient_id = p.patient_id
    JOIN users u_p   ON p.user_id    = u_p.user_id
    JOIN doctors d   ON a.doctor_id  = d.doctor_id
    JOIN users u_d   ON d.user_id    = u_d.user_id
    ORDER BY a.created_at DESC
    LIMIT 8
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <div class="page-header-title">
            <h2>Dashboard</h2>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?> — <?php echo date('l, d F Y'); ?></p>
        </div>
        <a href="/Web/Hospital-Management-System/modules/appointments/appointments.php" class="btn btn-primary">
            + New Appointment
        </a>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon blue">📅</div>
            <div>
                <div class="stat-label">Appointments Today</div>
                <div class="stat-value"><?php echo (int)$todayAppts; ?></div>
                <div class="stat-change">As of <?php echo date('g:i A'); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">🏥</div>
            <div>
                <div class="stat-label">Patients Admitted</div>
                <div class="stat-value"><?php echo (int)$admittedPts; ?></div>
                <div class="stat-change">Currently in wards</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow">💳</div>
            <div>
                <div class="stat-label">Pending Bills</div>
                <div class="stat-value"><?php echo (int)$pendingBills; ?></div>
                <div class="stat-change">Open invoices</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">🚑</div>
            <div>
                <div class="stat-label">Active Emergencies</div>
                <div class="stat-value"><?php echo (int)$emergencyOpen; ?></div>
                <div class="stat-change">Pending / En route</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">🛏️</div>
            <div>
                <div class="stat-label">Beds Available</div>
                <div class="stat-value"><?php echo (int)$bedsAvail; ?> <small style="font-size:1rem;color:var(--muted)">/ <?php echo (int)$bedsTotal; ?></small></div>
                <div class="stat-change">Across all wards</div>
            </div>
        </div>
    </div>

    <!-- ── Recent Appointments ── -->
    <div class="card">
        <div class="card-header">
            <h3>Recent Appointments</h3>
            <a href="/Web/Hospital-Management-System/modules/appointments/appointments.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ref #</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentAppts)): ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:32px">No appointments found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentAppts as $a):
                            $badgeClass = match($a['status']) {
                                'confirmed'  => 'badge-info',
                                'completed'  => 'badge-success',
                                'cancelled'  => 'badge-danger',
                                default      => 'badge-warning',
                            };
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($a['ref_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($a['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($a['doctor_name']); ?></td>
                            <td><?php echo date('d M Y', strtotime($a['appt_date'])); ?></td>
                            <td><?php echo date('g:i A', strtotime($a['appt_time'])); ?></td>
                            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($a['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
