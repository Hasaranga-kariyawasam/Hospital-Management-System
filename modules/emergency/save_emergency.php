<?php


$host = 'localhost';
$db   = 'hospital_db';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$name       = trim($_POST['patient_name']    ?? '');
$address    = trim($_POST['patient_address'] ?? '');
$contact    = trim($_POST['contact_number']  ?? '');
$type       = trim($_POST['emergency_type']  ?? '');
$conscious  = trim($_POST['is_conscious']    ?? '');
$assistance = trim($_POST['assistance_on_site'] ?? '');

// contact_number is the only required field
if (empty($contact)) {
    echo json_encode(['success' => false, 'message' => 'Contact number is required.']);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO emergency_requests
        (patient_name, patient_address, contact_number, emergency_type, is_conscious, assistance_on_site)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->bind_param('ssssss', $name, $address, $contact, $type, $conscious, $assistance);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save. ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>