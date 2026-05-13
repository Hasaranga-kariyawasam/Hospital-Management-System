<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../includes/session_check.php';


header('Content-Type: application/json');

$theatre = (int)($_GET['theatre'] ?? 0);
$date    = trim($_GET['date']    ?? '');
$time    = trim($_GET['time']    ?? '');
$exclude = (int)($_GET['exclude'] ?? 0); // exclude current op id when editing

if (!$theatre || !$date || !$time) {
    echo json_encode(['available' => true]);
    exit;
}

$sql = "SELECT COUNT(*) FROM theatre_operations
        WHERE theatre_number = ? AND scheduled_date = ? AND scheduled_time = ?
          AND status NOT IN ('cancelled')";
$params = [$theatre, $date, $time];

if ($exclude > 0) {
    $sql .= ' AND operation_id != ?';
    $params[] = $exclude;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$count = (int)$stmt->fetchColumn();

echo json_encode(['available' => $count === 0]);