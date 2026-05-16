<?php
// modules/appointments/get_slots.php — AJAX: returns available slots JSON
declare(strict_types=1);

require_once '../../includes/session_check.php';
require_once '../../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

// ── Input validation ──────────────────────────────────────────
$doctor_id = (int)($_GET['doctor_id'] ?? 0);
$date      = trim($_GET['date'] ?? '');

if ($doctor_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid input. Provide doctor_id and date (YYYY-MM-DD).']);
    exit;
}

if ($date < date('Y-m-d')) {
    echo json_encode(['error' => 'Cannot book appointments for past dates.']);
    exit;
}

// ── Day of week (0 = Sunday … 6 = Saturday) ──────────────────
$dow = (int)date('w', strtotime($date));

// ── Get doctor's schedule for this day ────────────────────────
$stmtSched = $pdo->prepare("
    SELECT start_time, end_time, slot_duration
    FROM doctor_schedules
    WHERE doctor_id = :did AND day_of_week = :dow
    LIMIT 1
");
$stmtSched->execute([':did' => $doctor_id, ':dow' => $dow]);
$schedule = $stmtSched->fetch();

if (!$schedule) {
    $dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    echo json_encode([
        'slots'   => [],
        'message' => 'Dr. is not available on ' . $dayNames[$dow] . '. Please try a different date.'
    ]);
    exit;
}

// ── Generate all time slots for this session ──────────────────
$slots    = [];
$current  = strtotime($schedule['start_time']);
$end      = strtotime($schedule['end_time']);
$duration = (int)$schedule['slot_duration'];  // minutes per slot

if ($duration <= 0) $duration = 15;

while ($current < $end) {
    $slots[] = date('H:i:s', $current);
    $current += $duration * 60;
}

if (empty($slots)) {
    echo json_encode(['slots' => [], 'message' => 'No slots configured for this schedule.']);
    exit;
}

// ── Get already booked slots for this doctor on this date ─────
$stmtBooked = $pdo->prepare("
    SELECT appt_time
    FROM appointments
    WHERE doctor_id = :did
      AND appt_date  = :date
      AND status    NOT IN ('cancelled')
");
$stmtBooked->execute([':did' => $doctor_id, ':date' => $date]);
$bookedRows  = $stmtBooked->fetchAll();

// Normalise to HH:MM for comparison
$bookedTimes = array_map(
    fn($row) => substr($row['appt_time'], 0, 5),
    $bookedRows
);

// ── Build response ────────────────────────────────────────────
$result = [];
foreach ($slots as $slotTime) {
    $hm     = substr($slotTime, 0, 5);   // "HH:MM"
    $booked = in_array($hm, $bookedTimes, true);

    // 12-hour display: "9:00 AM"
    $display = date('g:i A', strtotime($slotTime));

    $result[] = [
        'time'    => $hm,       // value sent on booking
        'display' => $display,  // shown to user
        'booked'  => $booked,
    ];
}

echo json_encode([
    'slots'          => $result,
    'total_slots'    => count($result),
    'booked_count'   => count($bookedTimes),
    'available_count'=> count($result) - count($bookedTimes),
]);
