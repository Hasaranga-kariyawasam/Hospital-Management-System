<?php
/**
 * modules/admin/admin_appointments.php
 * MediCare HMS — Admin Appointment Management
 * Group 05 | ICT1242 Web Development Practicum
 *
 * DROP THIS FILE INTO: /Hospital-Management-System/modules/admin/admin_appointments.php
 * ACCESS VIA: http://localhost/Web/Hospital-Management-System/modules/admin/admin_appointments.php
 *
 * FEATURES:
 *  - View all appointments with filter by status / source / doctor / date
 *  - Search by patient name, ref number, doctor name
 *  - Change appointment status inline (pending → confirmed → completed → cancelled)
 *  - Reschedule appointment (change date + time)
 *  - Delete / hard-cancel appointment with confirm
 *  - Create new OPD walk-in appointment (admin side)
 *  - Pagination (20 per page)
 *  - Summary stat cards
 */

declare(strict_types=1);
session_start();

// ── Auth guard ──────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'reception'])) {
    header('Location: /Web/Hospital-Management-System/login.php');
    exit();
}

// ── DB ───────────────────────────────────────────────────────
$conn = new mysqli('localhost', 'root', '', 'hospital_db');
if ($conn->connect_error) {
    die('<p style="color:red;padding:2rem">DB Connection failed: ' . htmlspecialchars($conn->connect_error) . '</p>');
}
$conn->set_charset('utf8mb4');

$adminId   = (int)$_SESSION['user_id'];
$adminRole = $_SESSION['role'] ?? 'admin';
$adminName = htmlspecialchars($_SESSION['full_name'] ?? 'Admin');

// ════════════════════════════════════════════════════════════
// POST HANDLERS
// ════════════════════════════════════════════════════════════
$flash = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 1. Update status ─────────────────────────────────────
    if ($action === 'update_status') {
        $apptId    = (int)($_POST['appointment_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';
        $allowed   = ['pending', 'confirmed', 'completed', 'cancelled'];
        if ($apptId > 0 && in_array($newStatus, $allowed)) {
            $stmt = $conn->prepare("UPDATE appointments SET status=? WHERE appointment_id=?");
            $stmt->bind_param('si', $newStatus, $apptId);
            $stmt->execute() && $stmt->affected_rows > 0
                ? $flash = ['type' => 'success', 'msg' => "Appointment status updated to <strong>" . ucfirst($newStatus) . "</strong>."]
                : $flash = ['type' => 'error',   'msg' => "Could not update status: " . htmlspecialchars($stmt->error)];
            $stmt->close();
        }
    }

    // ── 2. Reschedule ────────────────────────────────────────
    if ($action === 'reschedule') {
        $apptId  = (int)($_POST['appointment_id'] ?? 0);
        $newDate = trim($_POST['new_date'] ?? '');
        $newTime = trim($_POST['new_time'] ?? '');
        if ($apptId > 0 && $newDate !== '' && $newTime !== '') {
            // Check double-booking on that doctor/date/time
            $chk = $conn->prepare("
                SELECT appointment_id FROM appointments
                WHERE doctor_id=(SELECT doctor_id FROM appointments WHERE appointment_id=?)
                  AND appt_date=? AND appt_time=? AND status NOT IN ('cancelled')
                  AND appointment_id != ?
                LIMIT 1
            ");
            $chk->bind_param('issi', $apptId, $newDate, $newTime, $apptId);
            $chk->execute();
            $conflict = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($conflict) {
                $flash = ['type' => 'error', 'msg' => "That doctor already has an appointment at that date &amp; time. Please pick a different slot."];
            } else {
                $stmt = $conn->prepare("UPDATE appointments SET appt_date=?, appt_time=?, status='confirmed' WHERE appointment_id=?");
                $stmt->bind_param('ssi', $newDate, $newTime, $apptId);
                $stmt->execute()
                    ? $flash = ['type' => 'success', 'msg' => "Appointment rescheduled to <strong>$newDate at $newTime</strong> and marked Confirmed."]
                    : $flash = ['type' => 'error',   'msg' => "Reschedule failed: " . htmlspecialchars($stmt->error)];
                $stmt->close();
            }
        } else {
            $flash = ['type' => 'error', 'msg' => "Please provide both a new date and time."];
        }
    }

    // ── 3. Delete appointment ─────────────────────────────────
    if ($action === 'delete') {
        $apptId = (int)($_POST['appointment_id'] ?? 0);
        if ($apptId > 0) {
            // First remove linked prescriptions + treatment records (FK)
            $conn->query("DELETE FROM prescriptions    WHERE appointment_id=$apptId");
            $conn->query("DELETE FROM treatment_records WHERE appointment_id=$apptId");
            $stmt = $conn->prepare("DELETE FROM appointments WHERE appointment_id=?");
            $stmt->bind_param('i', $apptId);
            $stmt->execute()
                ? $flash = ['type' => 'success', 'msg' => "Appointment deleted successfully."]
                : $flash = ['type' => 'error',   'msg' => "Delete failed: " . htmlspecialchars($stmt->error)];
            $stmt->close();
        }
    }

    // ── 4. Create new OPD appointment ─────────────────────────
    if ($action === 'create_opd') {
        $patientId = (int)($_POST['patient_id']  ?? 0);
        $doctorId  = (int)($_POST['doctor_id']   ?? 0);
        $apptDate  = trim($_POST['appt_date']    ?? '');
        $apptTime  = trim($_POST['appt_time']    ?? '');
        $notes     = trim($_POST['notes']        ?? '');

        if ($patientId < 1 || $doctorId < 1 || $apptDate === '' || $apptTime === '') {
            $flash = ['type' => 'error', 'msg' => "Patient, Doctor, Date, and Time are all required."];
        } elseif (strtotime($apptDate) < strtotime('today')) {
            $flash = ['type' => 'error', 'msg' => "Appointment date cannot be in the past."];
        } else {
            // Duplicate check
            $chk = $conn->prepare("
                SELECT appointment_id FROM appointments
                WHERE doctor_id=? AND appt_date=? AND appt_time=? AND status NOT IN ('cancelled')
                LIMIT 1
            ");
            $chk->bind_param('iss', $doctorId, $apptDate, $apptTime);
            $chk->execute();
            $conflict = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($conflict) {
                $flash = ['type' => 'error', 'msg' => "That doctor already has an appointment at that date &amp; time. Choose another slot."];
            } else {
                // Generate unique ref number: OPD-YYYYMMDD-XXXX
                $refNum = 'OPD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

                $stmt = $conn->prepare("
                    INSERT INTO appointments
                        (patient_id, doctor_id, appt_date, appt_time, source, status, ref_number, notes, booked_by)
                    VALUES (?, ?, ?, ?, 'opd', 'confirmed', ?, ?, ?)
                ");
                $stmt->bind_param('iissssi', $patientId, $doctorId, $apptDate, $apptTime, $refNum, $notes, $adminId);
                $stmt->execute()
                    ? $flash = ['type' => 'success', 'msg' => "OPD Appointment created! Ref: <strong>$refNum</strong>"]
                    : $flash = ['type' => 'error',   'msg' => "Could not create appointment: " . htmlspecialchars($stmt->error)];
                $stmt->close();
            }
        }
    }
}

// ════════════════════════════════════════════════════════════
// FILTERS
// ════════════════════════════════════════════════════════════
$fStatus   = $_GET['status']    ?? 'all';
$fSource   = $_GET['source']    ?? 'all';
$fDoctor   = (int)($_GET['doctor_id'] ?? 0);
$fDate     = trim($_GET['appt_date']  ?? '');
$search    = trim($_GET['search']     ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;
$offset    = ($page - 1) * $perPage;

$where = ["1=1"];
$statusList = ['pending', 'confirmed', 'completed', 'cancelled'];
$sourceList = ['online', 'opd'];

if ($fStatus !== 'all' && in_array($fStatus, $statusList)) {
    $s = $conn->real_escape_string($fStatus);
    $where[] = "a.status='$s'";
}
if ($fSource !== 'all' && in_array($fSource, $sourceList)) {
    $s = $conn->real_escape_string($fSource);
    $where[] = "a.source='$s'";
}
if ($fDoctor > 0) {
    $where[] = "a.doctor_id=" . (int)$fDoctor;
}
if ($fDate !== '') {
    $d = $conn->real_escape_string($fDate);
    $where[] = "a.appt_date='$d'";
}
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where[] = "(u_pat.full_name LIKE '%$s%' OR a.ref_number LIKE '%$s%' OR u_doc.full_name LIKE '%$s%' OR pat.phone LIKE '%$s%')";
}

$whereStr = implode(' AND ', $where);

// Total count for pagination
$countRes = $conn->query("
    SELECT COUNT(*) AS n
    FROM appointments a
    JOIN patients p    ON p.patient_id = a.patient_id
    JOIN users u_pat   ON u_pat.user_id = p.user_id
    JOIN doctors d     ON d.doctor_id   = a.doctor_id
    JOIN users u_doc   ON u_doc.user_id = d.user_id
    JOIN patients pat  ON pat.patient_id = a.patient_id
    WHERE $whereStr
");
$totalRows = (int)($countRes->fetch_assoc()['n'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Main data
$appointments = $conn->query("
    SELECT
        a.appointment_id, a.ref_number, a.appt_date, a.appt_time,
        a.source, a.status, a.notes, a.created_at,
        pat.patient_id, pat.phone,
        u_pat.full_name  AS patient_name,
        d.doctor_id, d.specialization, d.consultation_fee,
        u_doc.full_name  AS doctor_name,
        ub.full_name     AS booked_by_name,
        (SELECT COUNT(*) FROM prescriptions pr WHERE pr.appointment_id = a.appointment_id) AS rx_count,
        (SELECT COUNT(*) FROM treatment_records tr WHERE tr.appointment_id = a.appointment_id) AS has_treatment
    FROM appointments a
    JOIN patients  pat   ON pat.patient_id = a.patient_id
    JOIN users   u_pat   ON u_pat.user_id  = pat.user_id
    JOIN doctors   d     ON d.doctor_id    = a.doctor_id
    JOIN users   u_doc   ON u_doc.user_id  = d.user_id
    LEFT JOIN users ub   ON ub.user_id     = a.booked_by
    WHERE $whereStr
    ORDER BY a.appt_date DESC, a.appt_time DESC
    LIMIT $perPage OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

// ── Stats (always all, unfiltered) ───────────────────────────
$stats = $conn->query("
    SELECT
        COUNT(*)                              AS total,
        SUM(status='pending')                 AS pending,
        SUM(status='confirmed')               AS confirmed,
        SUM(status='completed')               AS completed,
        SUM(status='cancelled')               AS cancelled,
        SUM(source='opd')                     AS opd_count,
        SUM(source='online')                  AS online_count,
        SUM(appt_date=CURDATE())              AS today
    FROM appointments
")->fetch_assoc();

// ── Doctors list for filter + create form ─────────────────────
$doctors = $conn->query("
    SELECT d.doctor_id, u.full_name, d.specialization
    FROM doctors d JOIN users u ON u.user_id=d.user_id
    WHERE u.status='active'
    ORDER BY u.full_name
")->fetch_all(MYSQLI_ASSOC);

// ── Patients list for create form ────────────────────────────
$patients = $conn->query("
    SELECT p.patient_id, u.full_name, p.phone, p.nic
    FROM patients p JOIN users u ON u.user_id=p.user_id
    WHERE u.status='active'
    ORDER BY u.full_name
")->fetch_all(MYSQLI_ASSOC);

// ── Build current query string for pagination links ───────────
function buildQS(array $extra = []): string {
    $params = [];
    foreach (['status','source','doctor_id','appt_date','search'] as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== '') $params[$k] = $_GET[$k];
    }
    return http_build_query(array_merge($params, $extra));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Appointment Management — MediCare HMS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ── Reset ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:15px}
body{font-family:'Inter',sans-serif;background:#f0f4f8;color:#1e293b;min-height:100vh;display:flex}

/* ── Sidebar (same as dashboard) ── */
.sidebar{width:240px;min-height:100vh;background:linear-gradient(180deg,#0f172a 0%,#1e3a5f 100%);color:#fff;display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100;overflow-y:auto}
.sidebar-logo{padding:1.25rem 1rem .85rem;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:.65rem}
.logo-mark{width:36px;height:36px;background:#3b82f6;border-radius:9px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0}
.logo-text{font-size:.78rem}.logo-text strong{display:block;font-size:.92rem}
.sidebar-section{padding:.4rem 0}
.sidebar-section-label{padding:.4rem 1rem .2rem;font-size:.6rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.4)}
.nav-item{display:flex;align-items:center;padding:.55rem 1rem;color:rgba(255,255,255,.75);text-decoration:none;gap:.65rem;font-size:.83rem;border-left:3px solid transparent;transition:all .2s}
.nav-item:hover,.nav-item.active{background:rgba(59,130,246,.15);color:#fff;border-left-color:#3b82f6}
.nav-item i{width:16px;text-align:center;font-size:.85rem}
.sidebar-footer{margin-top:auto;padding:.85rem 1rem;border-top:1px solid rgba(255,255,255,.1);font-size:.75rem;color:rgba(255,255,255,.5)}
.sidebar-footer a{color:#ef4444;text-decoration:none}

/* ── Main ── */
.main{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh}

/* ── Topbar ── */
.topbar{background:#fff;border-bottom:1px solid #e2e8f0;padding:.75rem 1.75rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;position:sticky;top:0;z-index:50}
.topbar-left{display:flex;align-items:center;gap:.75rem}
.topbar-back{color:#64748b;text-decoration:none;font-size:.82rem;display:flex;align-items:center;gap:.3rem}
.topbar-back:hover{color:#3b82f6}
.topbar-title{font-size:1rem;font-weight:600}
.topbar-right{display:flex;align-items:center;gap:.75rem}
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .9rem;border-radius:8px;font-size:.82rem;font-weight:500;cursor:pointer;border:none;font-family:inherit;text-decoration:none;transition:all .2s}
.btn-primary{background:#3b82f6;color:#fff}.btn-primary:hover{background:#2563eb}
.btn-secondary{background:#f1f5f9;color:#475569;border:1px solid #e2e8f0}.btn-secondary:hover{background:#e2e8f0}
.btn-danger{background:#fee2e2;color:#dc2626}.btn-danger:hover{background:#fecaca}
.btn-sm{padding:.3rem .65rem;font-size:.77rem}
.btn-icon{padding:.4rem;width:32px;height:32px;justify-content:center}

/* ── Content ── */
.content{padding:1.5rem 1.75rem;flex:1}

/* ── Flash ── */
.flash{padding:.75rem 1rem;border-radius:10px;font-size:.85rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.6rem}
.flash-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
.flash-error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}

/* ── Stat cards ── */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.85rem;margin-bottom:1.25rem}
.stat-card{background:#fff;border-radius:12px;padding:1rem;box-shadow:0 1px 3px rgba(0,0,0,.06);border:1px solid #e8edf3;border-top:3px solid var(--accent,#3b82f6)}
.stat-label{font-size:.72rem;color:#64748b;font-weight:500;margin-bottom:.25rem}
.stat-value{font-size:1.75rem;font-weight:700;color:#0f172a;line-height:1}
.stat-sub{font-size:.7rem;color:#94a3b8;margin-top:.2rem}

/* ── Filter bar ── */
.filter-bar{background:#fff;border:1px solid #e8edf3;border-radius:12px;padding:.85rem 1rem;margin-bottom:1.25rem;display:flex;flex-wrap:wrap;gap:.65rem;align-items:center}
.filter-bar select, .filter-bar input[type=text], .filter-bar input[type=date]{
    padding:.4rem .7rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.82rem;color:#374151;background:#f8fafc;font-family:inherit
}
.filter-bar select:focus,.filter-bar input:focus{outline:none;border-color:#3b82f6}
.filter-label{font-size:.75rem;font-weight:600;color:#64748b;display:flex;align-items:center;gap:.25rem}
.filter-group{display:flex;flex-direction:column;gap:.2rem}
.filter-spacer{flex:1}

/* ── Card ── */
.card{background:#fff;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.06);border:1px solid #e8edf3;overflow:hidden;margin-bottom:1.25rem}
.card-header{padding:.9rem 1.25rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;gap:1rem}
.card-title{font-size:.9rem;font-weight:600;color:#1e293b;display:flex;align-items:center;gap:.4rem}
.card-title i{color:#3b82f6}

/* ── Table ── */
.table-wrap{overflow-x:auto}
.data-table{width:100%;border-collapse:collapse;font-size:.82rem;min-width:900px}
.data-table th{background:#f8fafc;padding:.6rem 1rem;text-align:left;font-size:.68rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap}
.data-table td{padding:.7rem 1rem;border-bottom:1px solid #f1f5f9;color:#374151;vertical-align:middle}
.data-table tr:last-child td{border-bottom:none}
.data-table tr:hover td{background:#fafcff}
.empty-row td{text-align:center;color:#94a3b8;padding:3rem;font-style:italic}
.col-actions{white-space:nowrap;display:flex;gap:.3rem;align-items:center}

/* ── Badges ── */
.badge{display:inline-flex;align-items:center;padding:.18rem .6rem;border-radius:99px;font-size:.7rem;font-weight:600;white-space:nowrap}
.badge-pending{background:#fef3c7;color:#92400e}
.badge-confirmed{background:#dbeafe;color:#1e40af}
.badge-completed{background:#d1fae5;color:#065f46}
.badge-cancelled{background:#fee2e2;color:#991b1b}
.badge-opd{background:#f3e8ff;color:#6b21a8}
.badge-online{background:#ecfdf5;color:#047857}

/* ── Inline status select ── */
.status-select{padding:.3rem .5rem;border:1px solid #e2e8f0;border-radius:6px;font-size:.77rem;font-family:inherit;cursor:pointer;background:#f8fafc;color:#374151}
.status-select:focus{outline:none;border-color:#3b82f6}

/* ── Row secondary text ── */
.row-sub{font-size:.72rem;color:#94a3b8;margin-top:.15rem}
.row-strong{font-weight:600}
.ref-tag{font-family:monospace;font-size:.72rem;background:#eff6ff;color:#1d4ed8;padding:.1rem .4rem;border-radius:4px;font-weight:700}

/* ── Modals ── */
.modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:200;display:flex;align-items:center;justify-content:center;padding:1rem}
.modal-overlay.hidden{display:none}
.modal-box{background:#fff;border-radius:16px;width:100%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:modalIn .2s ease}
.modal-box.wide{max-width:600px}
@keyframes modalIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
.modal-header{padding:1.1rem 1.25rem .85rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between}
.modal-title{font-size:.95rem;font-weight:700}
.modal-close{background:none;border:none;font-size:1.1rem;color:#94a3b8;cursor:pointer;padding:.25rem}
.modal-close:hover{color:#ef4444}
.modal-body{padding:1.25rem}
.modal-footer{padding:.85rem 1.25rem;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;gap:.6rem}

/* ── Form fields inside modal ── */
.fg{margin-bottom:1rem}
.fg:last-child{margin-bottom:0}
.fl{display:block;font-size:.72rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.35rem}
.fi{width:100%;padding:.55rem .8rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.85rem;color:#1e293b;background:#f8fafc;font-family:inherit}
.fi:focus{outline:none;border-color:#3b82f6;background:#fff}
.fi-row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
.fi-info{background:#eff6ff;border:1px solid #bfdbfe;padding:.5rem .8rem;border-radius:8px;font-size:.82rem;color:#1d4ed8}

/* ── Appointment info block in modals ── */
.appt-info{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:.85rem 1rem;margin-bottom:1rem}
.appt-info-row{display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:.4rem}
.appt-info-row:last-child{margin-bottom:0}
.appt-info-label{color:#64748b;font-weight:500}
.appt-info-value{font-weight:600;color:#1e293b}

/* ── Pagination ── */
.pagination{display:flex;align-items:center;gap:.4rem;justify-content:center;padding:1.1rem}
.page-btn{padding:.35rem .7rem;border-radius:8px;font-size:.8rem;font-weight:500;text-decoration:none;color:#475569;border:1px solid #e2e8f0;background:#fff;transition:all .15s}
.page-btn:hover{background:#f1f5f9}
.page-btn.active{background:#3b82f6;color:#fff;border-color:#3b82f6}
.page-btn.disabled{color:#cbd5e1;pointer-events:none}
.page-info{font-size:.78rem;color:#94a3b8;margin:0 .5rem}

/* ── Confirm strip inside delete modal ── */
.danger-box{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:.85rem 1rem;font-size:.85rem;color:#991b1b;display:flex;align-items:flex-start;gap:.6rem}
.danger-box i{margin-top:.1rem;flex-shrink:0}

/* ── Responsive ── */
@media(max-width:800px){.sidebar{width:200px}.main{margin-left:200px}.fi-row{grid-template-columns:1fr}}
</style>
</head>
<body>

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-mark">M</div>
        <div class="logo-text"><strong>MediCare HMS</strong><?= ucfirst($adminRole) ?> Panel</div>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-section-label">Overview</div>
        <a class="nav-item" href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-section-label">Hospital</div>
        <a class="nav-item" href="#"><i class="fas fa-user-injured"></i> Patients</a>
        <a class="nav-item" href="#"><i class="fas fa-user-md"></i> Doctors</a>
        <a class="nav-item active" href="admin_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
        <a class="nav-item" href="#"><i class="fas fa-bed"></i> Ward &amp; Rooms</a>
        <a class="nav-item" href="#"><i class="fas fa-procedures"></i> Theatre</a>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-section-label">Finance &amp; Pharmacy</div>
        <a class="nav-item" href="#"><i class="fas fa-file-invoice-dollar"></i> Billing</a>
        <a class="nav-item" href="#"><i class="fas fa-pills"></i> Pharmacy</a>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-section-label">Emergency</div>
        <a class="nav-item" href="#"><i class="fas fa-ambulance"></i> Ambulance</a>
    </div>
    <div class="sidebar-footer">
        <strong><?= $adminName ?></strong><br>
        <a href="/Web/Hospital-Management-System/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<!-- ═══ MAIN ═══ -->
<div class="main">

    <!-- Topbar -->
    <header class="topbar">
        <div class="topbar-left">
            <a class="topbar-back" href="admin_dashboard.php"><i class="fas fa-chevron-left"></i> Dashboard</a>
            <span style="color:#e2e8f0">/</span>
            <span class="topbar-title"><i class="fas fa-calendar-check" style="color:#3b82f6;margin-right:.4rem"></i>Appointment Management</span>
        </div>
        <div class="topbar-right">
            <span style="font-size:.78rem;color:#64748b"><?= date('D, d M Y') ?></span>
            <button class="btn btn-primary" onclick="openCreateModal()">
                <i class="fas fa-plus"></i> New OPD Appointment
            </button>
        </div>
    </header>

    <div class="content">

        <!-- Flash message -->
        <?php if ($flash['msg']): ?>
        <div class="flash flash-<?= $flash['type'] ?>">
            <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= $flash['msg'] ?>
        </div>
        <?php endif; ?>

        <!-- Stat cards -->
        <div class="stats-grid">
            <div class="stat-card" style="--accent:#64748b">
                <div class="stat-label">Total Appointments</div>
                <div class="stat-value"><?= number_format((int)$stats['total']) ?></div>
                <div class="stat-sub">All time</div>
            </div>
            <div class="stat-card" style="--accent:#f59e0b">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?= (int)$stats['pending'] ?></div>
                <div class="stat-sub">Awaiting confirm</div>
            </div>
            <div class="stat-card" style="--accent:#3b82f6">
                <div class="stat-label">Confirmed</div>
                <div class="stat-value"><?= (int)$stats['confirmed'] ?></div>
                <div class="stat-sub">Scheduled</div>
            </div>
            <div class="stat-card" style="--accent:#10b981">
                <div class="stat-label">Completed</div>
                <div class="stat-value"><?= (int)$stats['completed'] ?></div>
                <div class="stat-sub">Done</div>
            </div>
            <div class="stat-card" style="--accent:#ef4444">
                <div class="stat-label">Cancelled</div>
                <div class="stat-value"><?= (int)$stats['cancelled'] ?></div>
                <div class="stat-sub"></div>
            </div>
            <div class="stat-card" style="--accent:#06b6d4">
                <div class="stat-label">Today</div>
                <div class="stat-value"><?= (int)$stats['today'] ?></div>
                <div class="stat-sub"><?= date('d M') ?></div>
            </div>
            <div class="stat-card" style="--accent:#8b5cf6">
                <div class="stat-label">OPD Walk-in</div>
                <div class="stat-value"><?= (int)$stats['opd_count'] ?></div>
                <div class="stat-sub">vs <?= (int)$stats['online_count'] ?> online</div>
            </div>
        </div>

        <!-- Filter bar -->
        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label class="filter-label"><i class="fas fa-circle-dot"></i> Status</label>
                <select name="status">
                    <option value="all"      <?= $fStatus === 'all'       ? 'selected' : '' ?>>All Statuses</option>
                    <option value="pending"  <?= $fStatus === 'pending'   ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed"<?= $fStatus === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="completed"<?= $fStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled"<?= $fStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label"><i class="fas fa-tag"></i> Source</label>
                <select name="source">
                    <option value="all"   <?= $fSource === 'all'    ? 'selected' : '' ?>>All Sources</option>
                    <option value="opd"   <?= $fSource === 'opd'    ? 'selected' : '' ?>>OPD Walk-in</option>
                    <option value="online"<?= $fSource === 'online' ? 'selected' : '' ?>>Online</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label"><i class="fas fa-user-md"></i> Doctor</label>
                <select name="doctor_id">
                    <option value="0">All Doctors</option>
                    <?php foreach ($doctors as $doc): ?>
                    <option value="<?= $doc['doctor_id'] ?>" <?= $fDoctor === (int)$doc['doctor_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($doc['full_name']) ?> (<?= htmlspecialchars($doc['specialization']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label"><i class="fas fa-calendar"></i> Date</label>
                <input type="date" name="appt_date" value="<?= htmlspecialchars($fDate) ?>">
            </div>
            <div class="filter-group">
                <label class="filter-label"><i class="fas fa-search"></i> Search</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Patient / Doctor / Ref…" style="width:180px">
            </div>
            <div style="display:flex;gap:.4rem;align-items:flex-end;padding-bottom:.05rem">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
                <a href="admin_appointments.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>

        <!-- Table card -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-list"></i> Appointments
                    <span style="font-size:.75rem;font-weight:400;color:#94a3b8;margin-left:.5rem">
                        <?= number_format($totalRows) ?> result<?= $totalRows !== 1 ? 's' : '' ?>
                        <?= $totalPages > 1 ? "· Page $page of $totalPages" : '' ?>
                    </span>
                </span>
                <div style="display:flex;gap:.5rem">
                    <a href="?<?= buildQS(['status' => 'pending']) ?>" class="btn btn-secondary btn-sm">Pending only</a>
                    <a href="?<?= buildQS(['appt_date' => date('Y-m-d')]) ?>" class="btn btn-secondary btn-sm">Today</a>
                </div>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ref #</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date &amp; Time</th>
                            <th>Source</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Rx / Tx</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($appointments)): ?>
                        <tr class="empty-row"><td colspan="9">No appointments match your filters.</td></tr>
                    <?php else: foreach ($appointments as $a): ?>
                        <tr>
                            <!-- Ref -->
                            <td>
                                <span class="ref-tag"><?= htmlspecialchars($a['ref_number']) ?></span>
                                <div class="row-sub">#<?= $a['appointment_id'] ?></div>
                            </td>

                            <!-- Patient -->
                            <td>
                                <div class="row-strong"><?= htmlspecialchars($a['patient_name']) ?></div>
                                <div class="row-sub"><?= htmlspecialchars($a['phone']) ?></div>
                            </td>

                            <!-- Doctor -->
                            <td>
                                <div class="row-strong"><?= htmlspecialchars($a['doctor_name']) ?></div>
                                <div class="row-sub"><?= htmlspecialchars($a['specialization']) ?></div>
                            </td>

                            <!-- Date & Time -->
                            <td>
                                <div class="row-strong"><?= date('d M Y', strtotime($a['appt_date'])) ?></div>
                                <div class="row-sub"><?= date('h:i A', strtotime($a['appt_time'])) ?></div>
                            </td>

                            <!-- Source -->
                            <td><span class="badge badge-<?= $a['source'] ?>"><?= strtoupper($a['source']) ?></span></td>

                            <!-- Fee -->
                            <td style="font-weight:600;color:#059669;white-space:nowrap">Rs <?= number_format((float)$a['consultation_fee'], 2) ?></td>

                            <!-- Status — inline dropdown form -->
                            <td>
                                <form method="POST" style="margin:0">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="appointment_id" value="<?= $a['appointment_id'] ?>">
                                    <select name="new_status" class="status-select" onchange="this.form.submit()" title="Change status">
                                        <?php foreach (['pending'=>'Pending','confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled'] as $v=>$l): ?>
                                        <option value="<?= $v ?>" <?= $a['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>

                            <!-- Rx count / Treatment -->
                            <td style="text-align:center">
                                <?php if ((int)$a['rx_count'] > 0): ?>
                                    <span class="badge badge-confirmed" title="<?= $a['rx_count'] ?> prescription(s)"><i class="fas fa-prescription-bottle-alt"></i> <?= $a['rx_count'] ?></span>
                                <?php endif; ?>
                                <?php if ((int)$a['has_treatment'] > 0): ?>
                                    <span class="badge badge-completed" style="margin-top:.2rem" title="Treatment record exists"><i class="fas fa-file-medical"></i></span>
                                <?php endif; ?>
                                <?php if ((int)$a['rx_count'] === 0 && (int)$a['has_treatment'] === 0): ?>
                                    <span style="color:#cbd5e1">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Actions -->
                            <td>
                                <div class="col-actions">
                                    <!-- Reschedule -->
                                    <?php if (!in_array($a['status'], ['completed','cancelled'])): ?>
                                    <button class="btn btn-secondary btn-icon" title="Reschedule"
                                        onclick="openRescheduleModal(
                                            <?= $a['appointment_id'] ?>,
                                            '<?= addslashes($a['patient_name']) ?>',
                                            '<?= addslashes($a['doctor_name']) ?>',
                                            '<?= $a['appt_date'] ?>',
                                            '<?= $a['appt_time'] ?>',
                                            '<?= $a['ref_number'] ?>'
                                        )">
                                        <i class="fas fa-calendar-edit"></i>
                                    </button>
                                    <?php endif; ?>

                                    <!-- View notes -->
                                    <?php if ($a['notes']): ?>
                                    <button class="btn btn-secondary btn-icon" title="View Notes"
                                        onclick="alert('Notes:\n\n<?= addslashes(htmlspecialchars($a['notes'])) ?>')">
                                        <i class="fas fa-sticky-note"></i>
                                    </button>
                                    <?php endif; ?>

                                    <!-- Delete -->
                                    <?php if (in_array($a['status'], ['pending','cancelled']) || $adminRole === 'admin'): ?>
                                    <button class="btn btn-danger btn-icon" title="Delete Appointment"
                                        onclick="openDeleteModal(
                                            <?= $a['appointment_id'] ?>,
                                            '<?= addslashes($a['patient_name']) ?>',
                                            '<?= $a['ref_number'] ?>',
                                            <?= (int)$a['rx_count'] + (int)$a['has_treatment'] ?>
                                        )">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a class="page-btn" href="?<?= buildQS(['page' => 1]) ?>"><i class="fas fa-angle-double-left"></i></a>
                    <a class="page-btn" href="?<?= buildQS(['page' => $page - 1]) ?>"><i class="fas fa-angle-left"></i></a>
                <?php else: ?>
                    <span class="page-btn disabled"><i class="fas fa-angle-double-left"></i></span>
                    <span class="page-btn disabled"><i class="fas fa-angle-left"></i></span>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                for ($p = $start; $p <= $end; $p++):
                ?>
                    <a class="page-btn <?= $p === $page ? 'active' : '' ?>" href="?<?= buildQS(['page' => $p]) ?>"><?= $p ?></a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a class="page-btn" href="?<?= buildQS(['page' => $page + 1]) ?>"><i class="fas fa-angle-right"></i></a>
                    <a class="page-btn" href="?<?= buildQS(['page' => $totalPages]) ?>"><i class="fas fa-angle-double-right"></i></a>
                <?php else: ?>
                    <span class="page-btn disabled"><i class="fas fa-angle-right"></i></span>
                    <span class="page-btn disabled"><i class="fas fa-angle-double-right"></i></span>
                <?php endif; ?>

                <span class="page-info">Showing <?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage,$totalRows) ?> of <?= number_format($totalRows) ?></span>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ═══════════════════════════════════════════
     MODAL 1: Reschedule
═══════════════════════════════════════════ -->
<div class="modal-overlay hidden" id="rescheduleModal" onclick="if(event.target===this)closeReschedule()">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-title"><i class="fas fa-calendar-edit" style="color:#3b82f6;margin-right:.4rem"></i>Reschedule Appointment</span>
            <button class="modal-close" onclick="closeReschedule()">✕</button>
        </div>
        <div class="modal-body">
            <div class="appt-info" id="reschedule-info"></div>
            <form method="POST" id="rescheduleForm">
                <input type="hidden" name="action" value="reschedule">
                <input type="hidden" name="appointment_id" id="rs-appt-id">
                <div class="fi-row">
                    <div class="fg">
                        <label class="fl">New Date</label>
                        <input type="date" name="new_date" id="rs-date" class="fi" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="fg">
                        <label class="fl">New Time</label>
                        <input type="time" name="new_time" id="rs-time" class="fi" required>
                    </div>
                </div>
                <div class="fi-info" style="font-size:.78rem">
                    <i class="fas fa-info-circle"></i>
                    The system will check for double-booking on the selected slot before saving.
                    Status will be updated to <strong>Confirmed</strong>.
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeReschedule()">Cancel</button>
            <button class="btn btn-primary" onclick="document.getElementById('rescheduleForm').submit()">
                <i class="fas fa-calendar-check"></i> Confirm Reschedule
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     MODAL 2: Delete Confirmation
═══════════════════════════════════════════ -->
<div class="modal-overlay hidden" id="deleteModal" onclick="if(event.target===this)closeDelete()">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-title" style="color:#dc2626"><i class="fas fa-trash" style="margin-right:.4rem"></i>Delete Appointment</span>
            <button class="modal-close" onclick="closeDelete()">✕</button>
        </div>
        <div class="modal-body">
            <div class="danger-box" style="margin-bottom:1rem">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>This action cannot be undone.</strong><br>
                    <span id="delete-warn-text"></span>
                </div>
            </div>
            <div class="appt-info" id="delete-info"></div>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="appointment_id" id="del-appt-id">
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDelete()">Cancel</button>
            <button class="btn btn-danger" onclick="document.getElementById('deleteForm').submit()">
                <i class="fas fa-trash"></i> Yes, Delete
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     MODAL 3: Create OPD Appointment
═══════════════════════════════════════════ -->
<div class="modal-overlay hidden" id="createModal" onclick="if(event.target===this)closeCreate()">
    <div class="modal-box wide">
        <div class="modal-header">
            <span class="modal-title"><i class="fas fa-calendar-plus" style="color:#3b82f6;margin-right:.4rem"></i>New OPD Walk-in Appointment</span>
            <button class="modal-close" onclick="closeCreate()">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="createForm">
                <input type="hidden" name="action" value="create_opd">

                <div class="fg">
                    <label class="fl">Patient <span style="color:#ef4444">*</span></label>
                    <select name="patient_id" id="create-patient" class="fi" required>
                        <option value="">— Select patient —</option>
                        <?php foreach ($patients as $pt): ?>
                        <option value="<?= $pt['patient_id'] ?>">
                            <?= htmlspecialchars($pt['full_name']) ?> | NIC: <?= htmlspecialchars($pt['nic']) ?> | <?= htmlspecialchars($pt['phone']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="fg">
                    <label class="fl">Doctor <span style="color:#ef4444">*</span></label>
                    <select name="doctor_id" id="create-doctor" class="fi" required>
                        <option value="">— Select doctor —</option>
                        <?php foreach ($doctors as $doc): ?>
                        <option value="<?= $doc['doctor_id'] ?>" data-fee="<?= $doc['consultation_fee'] ?? 0 ?>">
                            <?= htmlspecialchars($doc['full_name']) ?> — <?= htmlspecialchars($doc['specialization']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="fee-display" style="display:none;margin-bottom:1rem">
                    <div class="fi-info"><i class="fas fa-rupee-sign"></i> Consultation Fee: <strong id="fee-val"></strong></div>
                </div>

                <div class="fi-row">
                    <div class="fg">
                        <label class="fl">Appointment Date <span style="color:#ef4444">*</span></label>
                        <input type="date" name="appt_date" id="create-date" class="fi" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="fg">
                        <label class="fl">Appointment Time <span style="color:#ef4444">*</span></label>
                        <input type="time" name="appt_time" id="create-time" class="fi" required>
                    </div>
                </div>

                <div class="fg">
                    <label class="fl">Notes (optional)</label>
                    <textarea name="notes" class="fi" rows="2" placeholder="Reason for visit, special notes…" style="resize:vertical"></textarea>
                </div>

                <div class="fi-info" style="font-size:.78rem">
                    <i class="fas fa-info-circle"></i>
                    Source will be set to <strong>OPD</strong>. Status will be set to <strong>Confirmed</strong>.
                    A unique reference number will be auto-generated.
                    System will check for duplicate booking before saving.
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeCreate()">Cancel</button>
            <button class="btn btn-primary" onclick="document.getElementById('createForm').submit()">
                <i class="fas fa-calendar-check"></i> Create Appointment
            </button>
        </div>
    </div>
</div>

<!-- ═══ JS ═══ -->
<script>
// ── Reschedule modal ──
function openRescheduleModal(id, patient, doctor, date, time, ref) {
    document.getElementById('rs-appt-id').value = id;
    document.getElementById('rs-date').value = date;
    document.getElementById('rs-time').value = time;
    document.getElementById('reschedule-info').innerHTML =
        `<div class="appt-info-row"><span class="appt-info-label">Ref #</span><span class="appt-info-value">${ref}</span></div>
         <div class="appt-info-row"><span class="appt-info-label">Patient</span><span class="appt-info-value">${patient}</span></div>
         <div class="appt-info-row"><span class="appt-info-label">Doctor</span><span class="appt-info-value">${doctor}</span></div>
         <div class="appt-info-row"><span class="appt-info-label">Current Date</span><span class="appt-info-value">${formatDate(date)} at ${formatTime(time)}</span></div>`;
    document.getElementById('rescheduleModal').classList.remove('hidden');
}
function closeReschedule() {
    document.getElementById('rescheduleModal').classList.add('hidden');
}

// ── Delete modal ──
function openDeleteModal(id, patient, ref, linkedCount) {
    document.getElementById('del-appt-id').value = id;
    document.getElementById('delete-info').innerHTML =
        `<div class="appt-info-row"><span class="appt-info-label">Ref #</span><span class="appt-info-value">${ref}</span></div>
         <div class="appt-info-row"><span class="appt-info-label">Patient</span><span class="appt-info-value">${patient}</span></div>`;
    document.getElementById('delete-warn-text').innerHTML = linkedCount > 0
        ? `This appointment has <strong>${linkedCount} linked record(s)</strong> (prescriptions / treatment records) that will also be deleted.`
        : 'The appointment record will be permanently removed from the system.';
    document.getElementById('deleteModal').classList.remove('hidden');
}
function closeDelete() {
    document.getElementById('deleteModal').classList.add('hidden');
}

// ── Create OPD modal ──
function openCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
}
function closeCreate() {
    document.getElementById('createModal').classList.add('hidden');
}

// Show consultation fee when doctor selected
document.getElementById('create-doctor').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const fee = parseFloat(opt.dataset.fee || 0);
    const wrap = document.getElementById('fee-display');
    if (fee > 0 && this.value) {
        document.getElementById('fee-val').textContent = 'Rs ' + fee.toLocaleString('en-LK', {minimumFractionDigits:2});
        wrap.style.display = 'block';
    } else {
        wrap.style.display = 'none';
    }
});

// Helpers
function formatDate(d) {
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const dt = new Date(d + 'T00:00');
    return dt.getDate() + ' ' + months[dt.getMonth()] + ' ' + dt.getFullYear();
}
function formatTime(t) {
    const [h, m] = t.split(':');
    const hr = parseInt(h);
    return (hr > 12 ? hr-12 : hr) + ':' + m + ' ' + (hr >= 12 ? 'PM' : 'AM');
}

// Close modals on Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeReschedule();
        closeDelete();
        closeCreate();
    }
});

// Auto-dismiss flash after 5s
const flash = document.querySelector('.flash');
if (flash) setTimeout(() => flash.style.display = 'none', 5000);
</script>

</body>
</html>
<?php $conn->close(); ?>