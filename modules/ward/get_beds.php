<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';

header('Content-Type: application/json');

$roomId = (int)($_GET['room_id'] ?? 0);
if (!$roomId) { echo json_encode([]); exit; }

$stmt = $pdo->prepare("SELECT bed_id, bed_number, status FROM beds WHERE room_id = ? ORDER BY bed_number");
$stmt->execute([$roomId]);
echo json_encode($stmt->fetchAll());