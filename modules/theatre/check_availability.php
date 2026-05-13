<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../includes/auth_guard.php';

requireRole(['admin', 'doctor']);

header('Content-Type: application/json');

$theatre  = trim($_GET['theatre']  ?? '');
$date     = trim($_GET['date']     ?? '');
$time     = trim($_GET['time']     ?? '');
$duration = (int)($_GET['duration'] ?? 60);
$editId   = (int)($_GET['edit']    ?? 0);

if (!$theatre || !$date || !$time) {
    echo json_encode(['available' => true]);
    exit();
}

$slotEnd = date('H:i:s', strtotime($time) + ($duration * 60));

$sql = "
    SELECT operation_id FROM theatre_operations
    WHERE theatre_number = :theatre
      AND scheduled_date = :date
      AND status NOT IN ('Cancelled')
      AND (
          (scheduled_time <= :time AND ADDTIME(scheduled_time, SEC_TO_TIME(duration_minutes*60)) > :time)
          OR
          (scheduled_time >= :time AND scheduled_time < :slot_end)
      )
";
$params = [
    ':theatre'  => $theatre,
    ':date'     => $date,
    ':time'     => $time,
    ':slot_end' => $slotEnd,
];
if ($editId > 0) {
    $sql .= " AND operation_id != :edit_id";
    $params[':edit_id'] = $editId;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['available' => !$stmt->fetch()]);
