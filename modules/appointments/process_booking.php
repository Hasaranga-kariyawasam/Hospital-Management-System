<?php
// modules/appointments/process_booking.php — Saves appointment (online OR OPD)
declare(strict_types=1);

require_once '../../includes/session_check.php';
require_once '../../config/db_config.php';

// ── Only accept POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: book.php');
    exit;
}

$source = $_POST['source'] ?? 'online';   // 'online' | 'opd'
$role   = $_SESSION['role'] ?? '';

// Role gate
if ($source === 'online' && $role !== 'patient') {
    header('Location: book.php?error=unauthorized');
    exit;
}
if ($source === 'opd' && $role !== 'reception') {
    header('Location: opd_walkin.php?error=unauthorized');
    exit;
}

// ── Collect and sanitise inputs ───────────────────────────────
$doctor_id  = (int)($_POST['doctor_id']  ?? 0);
$patient_id = (int)($_POST['patient_id'] ?? 0);
$appt_date  = trim($_POST['appt_date']   ?? '');
$appt_time  = trim($_POST['appt_time']   ?? '');
$notes      = trim($_POST['notes']       ?? '');
$booked_by  = ($source === 'opd') ? (int)$_SESSION['user_id'] : null;

$backUrl = ($source === 'opd') ? 'opd_walkin.php' : 'book.php';

// ── Basic validation ──────────────────────────────────────────
if (!$doctor_id || !$patient_id || !$appt_date || !$appt_time) {
    header("Location: {$backUrl}?error=missing_fields");
    exit;
}

// Must not be a past date
if ($appt_date < date('Y-m-d')) {
    header("Location: {$backUrl}?error=past_date");
    exit;
}

// Validate time format HH:MM
if (!preg_match('/^\d{2}:\d{2}$/', $appt_time)) {
    header("Location: {$backUrl}?error=invalid_time");
    exit;
}

// ── Double-booking check (race-condition safe) ────────────────
$checkStmt = $pdo->prepare("
    SELECT COUNT(*) AS cnt
    FROM appointments
    WHERE doctor_id  = :did
      AND appt_date  = :date
      AND appt_time  = :time
      AND status    NOT IN ('cancelled')
");
$checkStmt->execute([
    ':did'  => $doctor_id,
    ':date' => $appt_date,
    ':time' => $appt_time . ':00',  // stored as TIME HH:MM:SS
]);
if ((int)$checkStmt->fetchColumn('cnt') > 0) {
    header("Location: {$backUrl}?error=slot_taken");
    exit;
}

// ── Generate unique reference number: APT-YYYYMMDD-XXXX ──────
do {
    $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    $ref  = 'APT-' . date('Ymd') . '-' . $rand;
    $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE ref_number = :ref");
    $dupStmt->execute([':ref' => $ref]);
} while ((int)$dupStmt->fetchColumn() > 0);

// ── Insert appointment ────────────────────────────────────────
try {
    $insertStmt = $pdo->prepare("
        INSERT INTO appointments
            (patient_id, doctor_id, appt_date, appt_time,
             source, status, ref_number, notes, booked_by)
        VALUES
            (:pid, :did, :date, :time,
             :source, 'pending', :ref, :notes, :booked_by)
    ");
    $insertStmt->execute([
        ':pid'      => $patient_id,
        ':did'      => $doctor_id,
        ':date'     => $appt_date,
        ':time'     => $appt_time . ':00',
        ':source'   => $source,
        ':ref'      => $ref,
        ':notes'    => $notes ?: null,
        ':booked_by'=> $booked_by,
    ]);
} catch (\PDOException $e) {
    // If somehow a duplicate slipped through (very rare race), show slot_taken
    if ($e->getCode() === '23000') {
        header("Location: {$backUrl}?error=slot_taken");
    } else {
        header("Location: {$backUrl}?error=db_error");
    }
    exit;
}

// ── Redirect with success ─────────────────────────────────────
if ($source === 'opd') {
    header('Location: opd_walkin.php?success=1&ref=' . urlencode($ref));
} else {
    header('Location: my_appointment.php?success=1&ref=' . urlencode($ref));
}
exit;
