<?php
// modules/appointments/doctor_schedule.php
// Doctor's personal schedule and portal page

require_once '../../includes/session_check.php';
require_once '../../includes/role_check.php'; // ensure role === 'doctor'

$host   = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'hospital_db';

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// ── Current doctor info ──────────────────────────────────────
$user_id    = $_SESSION['user_id'];
$doctorName = $_SESSION['full_name'] ?? 'Doctor';

// ── [UPDATED] Get full doctor profile including qualifications & license ──
$docRow = $conn->query(
    "SELECT d.doctor_id, d.specialization, d.consultation_fee,
            d.qualifications, d.license_number,
            u.department
     FROM doctors d
     JOIN users u ON u.user_id = d.user_id
     WHERE d.user_id = $user_id
     LIMIT 1"
)->fetch_assoc();

$doctor_id       = $docRow['doctor_id']       ?? 0;
$specialization  = $docRow['specialization']  ?? 'General';
$department      = $docRow['department']      ?? 'General Medicine';
$consultFee      = $docRow['consultation_fee']?? 0;
$qualifications  = $docRow['qualifications']  ?? '';   // ← NEW
$licenseNumber   = $docRow['license_number']  ?? '';   // ← NEW

// ── Today's appointments ─────────────────────────────────────
$today = date('Y-m-d');
$apptResult = $conn->query(
    "SELECT a.appointment_id, a.appt_time, a.status, a.notes, a.ref_number,
            p_user.full_name AS patient_name,
            pat.gender, pat.dob, pat.phone
     FROM appointments a
     JOIN patients pat     ON pat.patient_id  = a.patient_id
     JOIN users    p_user  ON p_user.user_id  = pat.user_id
     WHERE a.doctor_id = $doctor_id
       AND a.appt_date = '$today'
     ORDER BY a.appt_time ASC"
);
$todayAppts = $apptResult ? $apptResult->fetch_all(MYSQLI_ASSOC) : [];

// ── Stats: total / pending / completed ───────────────────────
$statsRow = $conn->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'pending')   AS pending,
        SUM(status = 'confirmed') AS confirmed,
        SUM(status = 'completed') AS completed
     FROM appointments
     WHERE doctor_id = $doctor_id
       AND appt_date = '$today'"
)->fetch_assoc();

// ── Weekly schedule slots ─────────────────────────────────────
$scheduleResult = $conn->query(
    "SELECT day_of_week, start_time, end_time, slot_duration
     FROM doctor_schedules
     WHERE doctor_id = $doctor_id
     ORDER BY day_of_week, start_time"
);
$scheduleSlots = $scheduleResult ? $scheduleResult->fetch_all(MYSQLI_ASSOC) : [];

$dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

// ── Page setup ───────────────────────────────────────────────
$pageTitle  = "My Schedule";
$useSidebar = true;
$isPublic   = false;

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-title">
            <h2>My Schedule &amp; Portal</h2>
            <p>Welcome back, Dr. <?php echo htmlspecialchars($doctorName); ?> &mdash; <?php echo htmlspecialchars($specialization); ?> &bull; <?php echo date('l, d F Y'); ?></p>
        </div>
        <div class="flex gap-12 items-center">
            <a href="appointments.php" class="btn btn-secondary"><span class="icon">📋</span> All Appointments</a>
        </div>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon blue"></div>
            <div>
                <div class="stat-label">Today's Appointments</div>
                <div class="stat-value"><?php echo (int)($statsRow['total'] ?? 0); ?></div>
                <div class="stat-change">For <?php echo date('d M Y'); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"></div>
            <div>
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?php echo (int)($statsRow['pending'] ?? 0); ?></div>
                <div class="stat-change">Awaiting confirmation</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"></div>
            <div>
                <div class="stat-label">Confirmed</div>
                <div class="stat-value"><?php echo (int)($statsRow['confirmed'] ?? 0); ?></div>
                <div class="stat-change">Ready to see</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"></div>
            <div>
                <div class="stat-label">Completed</div>
                <div class="stat-value"><?php echo (int)($statsRow['completed'] ?? 0); ?></div>
                <div class="stat-change">Done today</div>
            </div>
        </div>
    </div>

    <div class="schedule-grid">

        <!-- ── Left: Today's Appointment Queue ── -->
        <div class="card">
            <div class="card-header">
                <h3>Today's Patient Queue</h3>
                <span class="badge badge-info"><?php echo count($todayAppts); ?> patients</span>
            </div>

            <?php if (empty($todayAppts)): ?>
                <div class="empty-state">
                    <div class="empty-icon"></div>
                    <p class="empty-title">No appointments today</p>
                    <p class="empty-sub">Your schedule is clear for <?php echo date('d F Y'); ?>.</p>
                </div>
            <?php else: ?>
                <div class="appt-list">
                    <?php foreach ($todayAppts as $appt):
                        $age = (int)date_diff(date_create($appt['dob']), date_create())->y;
                        $statusClass = match($appt['status']) {
                            'confirmed'  => 'badge-info',
                            'completed'  => 'badge-success',
                            'cancelled'  => 'badge-danger',
                            default      => 'badge-warning',
                        };
                        $statusLabel = ucfirst($appt['status']);
                    ?>
                    <div class="appt-item">
                        <div class="appt-time">
                            <?php echo date('h:i A', strtotime($appt['appt_time'])); ?>
                        </div>
                        <div class="appt-avatar">
                            <?php echo strtoupper(substr($appt['patient_name'], 0, 1)); ?>
                        </div>
                        <div class="appt-info">
                            <div class="appt-name"><?php echo htmlspecialchars($appt['patient_name']); ?></div>
                            <div class="appt-meta">
                                <?php echo $age; ?> yrs &bull;
                                <?php echo ucfirst($appt['gender']); ?> &bull;
                                <?php echo htmlspecialchars($appt['phone']); ?>
                            </div>
                            <?php if (!empty($appt['notes'])): ?>
                                <div class="appt-notes"><?php echo htmlspecialchars($appt['notes']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="appt-right">
                            <span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                            <div class="appt-ref">#<?php echo htmlspecialchars($appt['ref_number']); ?></div>
                            <?php if ($appt['status'] === 'confirmed'): ?>
                                <a href="complete_appointment.php?id=<?php echo $appt['appointment_id']; ?>" class="btn btn-success btn-sm">Mark Done</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Right: Profile + Weekly Schedule ── -->
        <div>

            <!-- ══ Doctor Info Card (with full data table) ══ -->
            <div class="card mb-16">
                <div class="card-header">
                    <h3>My Profile</h3>
                    <div class="flex gap-8">
                        <!-- link to full doctor profile page -->
                        <a href="doctor_profile.php" class="btn btn-primary btn-sm">View Full Profile</a>
                        <a href="../profile/edit_profile.php" class="btn btn-secondary btn-sm">Edit</a>
                    </div>
                </div>

                <!-- Avatar + name row -->
                <div class="doctor-profile-row">
                    <div class="doctor-avatar-lg">
                        <?php echo strtoupper(substr($doctorName, 0, 1)); ?>
                    </div>
                    <div>
                        <div class="doctor-fullname">Dr. <?php echo htmlspecialchars($doctorName); ?></div>
                        <div class="doctor-spec"><?php echo htmlspecialchars($specialization); ?></div>
                        <div class="doctor-dept"><?php echo htmlspecialchars($department); ?></div>
                    </div>
                    <div class="doctor-fee-box">
                        <div class="fee-label">Consultation Fee</div>
                        <div class="fee-value">Rs. <?php echo number_format($consultFee, 2); ?></div>
                    </div>
                </div>

                <!-- ── Doctor Data Table (NEW) ── -->
                <div class="doctor-data-table-wrap">
                    <table class="doctor-data-table">
                        <tbody>
                            <tr>
                                <th>Doctor ID</th>
                                <td>#<?php echo (int)$doctor_id; ?></td>
                            </tr>
                            <tr>
                                <th>Full Name</th>
                                <td>Dr. <?php echo htmlspecialchars($doctorName); ?></td>
                            </tr>
                            <tr>
                                <th>Specialization</th>
                                <td><?php echo htmlspecialchars($specialization); ?></td>
                            </tr>
                            <tr>
                                <th>Department</th>
                                <td><?php echo htmlspecialchars($department); ?></td>
                            </tr>
                            <tr>
                                <th>Qualifications</th>
                                <td>
                                    <?php echo !empty($qualifications)
                                        ? nl2br(htmlspecialchars($qualifications))
                                        : '<span class="no-data">Not provided</span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>License No.</th>
                                <td>
                                    <?php echo !empty($licenseNumber)
                                        ? htmlspecialchars($licenseNumber)
                                        : '<span class="no-data">Not provided</span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Consultation Fee</th>
                                <td class="fee-cell">Rs. <?php echo number_format($consultFee, 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div><!-- /.card -->

            <!-- Weekly Availability Card -->
            <div class="card">
                <div class="card-header">
                    <h3>Weekly Availability</h3>
                    <a href="manage_schedule.php" class="btn btn-secondary btn-sm">Manage</a>
                </div>

                <?php if (empty($scheduleSlots)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"></div>
                        <p class="empty-title">No schedule set</p>
                        <p class="empty-sub">Add your weekly availability so patients can book appointments.</p>
                        <a href="manage_schedule.php" class="btn btn-primary btn-sm" style="margin-top:12px">Set Schedule</a>
                    </div>
                <?php else: ?>
                    <?php
                    // Group by day
                    $byDay = [];
                    foreach ($scheduleSlots as $slot) {
                        $byDay[$slot['day_of_week']][] = $slot;
                    }
                    $todayDow = (int)date('w'); // 0=Sun
                    ?>
                    <div class="week-schedule">
                        <?php for ($d = 0; $d <= 6; $d++): ?>
                            <div class="week-day <?php echo $d === $todayDow ? 'week-day-today' : ''; ?>">
                                <div class="week-day-name">
                                    <?php echo substr($dayNames[$d], 0, 3); ?>
                                    <?php if ($d === $todayDow): ?><span class="today-dot"></span><?php endif; ?>
                                </div>
                                <div class="week-day-slots">
                                    <?php if (!empty($byDay[$d])): ?>
                                        <?php foreach ($byDay[$d] as $sl): ?>
                                            <div class="time-slot">
                                                <?php echo date('h:i A', strtotime($sl['start_time'])); ?>
                                                –
                                                <?php echo date('h:i A', strtotime($sl['end_time'])); ?>
                                                <span class="slot-dur"><?php echo $sl['slot_duration']; ?>m slots</span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="no-slot">—</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div><!-- /.right-col -->

    </div><!-- /.schedule-grid -->

</main>

<?php include '../../includes/footer.php'; $conn->close(); ?>

<style>
/* ── Doctor Schedule Page – Extra Styles ── */

.schedule-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 24px;
    align-items: start;
}

/* Appointment List */
.appt-list {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.appt-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 0;
    border-bottom: 1px solid var(--border-light);
}
.appt-item:last-child { border-bottom: none; }

.appt-time {
    font-size: 12px;
    font-weight: 700;
    color: var(--accent-dark);
    width: 68px;
    flex-shrink: 0;
    text-align: center;
    background: var(--accent-light);
    border-radius: var(--radius-sm);
    padding: 6px 4px;
    line-height: 1.3;
}

.appt-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
    color: #fff;
    font-size: 16px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.appt-info { flex: 2; min-width: 0; }
.appt-name { font-weight: 600; font-size: 14px; color: var(--text); }
.appt-meta { font-size: 12px; color: var(--muted); margin-top: 2px; }
.appt-notes {
    font-size: 12px;
    color: var(--text-mid);
    margin-top: 4px;
    background: var(--bg);
    padding: 4px 8px;
    border-radius: 6px;
    font-style: italic;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.appt-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
    flex-shrink: 0;
}
.appt-ref { font-size: 11px; color: var(--muted); }

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
}
.empty-icon { font-size: 40px; margin-bottom: 12px; }
.empty-title { font-weight: 700; color: var(--text-mid); margin-bottom: 6px; }
.empty-sub { font-size: 13px; color: var(--muted); }

/* Doctor Profile Row */
.doctor-profile-row {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 16px;          /* gap before the table */
}
.doctor-avatar-lg {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
    color: #fff;
    font-size: 24px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.doctor-fullname { font-weight: 700; font-size: 15px; }
.doctor-spec { font-size: 13px; color: var(--accent-dark); font-weight: 600; }
.doctor-dept { font-size: 12px; color: var(--muted); }
.doctor-fee-box { margin-left: auto; text-align: right; }
.fee-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
.fee-value { font-size: 1.3rem; font-weight: 700; color: var(--success); }

/* ── Doctor Data Table (NEW) ── */
.doctor-data-table-wrap {
    border-top: 1px solid var(--border-light);
    padding-top: 14px;
    overflow-x: auto;
}
.doctor-data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.doctor-data-table th,
.doctor-data-table td {
    padding: 9px 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-light);
    vertical-align: top;
}
.doctor-data-table tr:last-child th,
.doctor-data-table tr:last-child td {
    border-bottom: none;
}
.doctor-data-table th {
    width: 38%;
    font-weight: 600;
    color: var(--muted);
    white-space: nowrap;
    background: var(--bg);
    border-radius: 4px 0 0 4px;
}
.doctor-data-table td {
    color: var(--text);
}
.doctor-data-table .fee-cell {
    font-weight: 700;
    color: var(--success);
}
.no-data {
    color: var(--muted);
    font-style: italic;
    font-size: 12px;
}

.flex        { display: flex; }
.gap-8       { gap: 8px; }

/* Weekly Schedule */
.week-schedule {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.week-day {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 12px;
    border-radius: var(--radius-sm);
    transition: background var(--transition);
}
.week-day:hover { background: var(--bg); }
.week-day-today {
    background: var(--accent-light);
    border-left: 3px solid var(--accent);
}
.week-day-name {
    width: 36px;
    font-size: 12px;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
    padding-top: 2px;
}
.week-day-today .week-day-name { color: var(--accent-dark); }

.today-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--accent);
    display: inline-block;
}

.week-day-slots { display: flex; flex-wrap: wrap; gap: 6px; }

.time-slot {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 4px 10px;
    font-size: 12px;
    color: var(--text-mid);
    display: flex;
    align-items: center;
    gap: 6px;
}
.week-day-today .time-slot {
    background: #fff;
    border-color: var(--accent);
    color: var(--accent-dark);
}
.slot-dur {
    background: var(--bg);
    border-radius: 4px;
    padding: 1px 5px;
    font-size: 10px;
    color: var(--muted);
}

.no-slot { font-size: 12px; color: var(--border); font-style: italic; }

.mb-16 { margin-bottom: 16px; }

/* Responsive */
@media (max-width: 1100px) {
    .schedule-grid { grid-template-columns: 1fr; }
    .doctor-fee-box { margin-left: 0; text-align: left; }
}
</style>