<?php
declare(strict_types=1);
// config/db_config.php — Database connection via PDO

$host   = 'localhost';
$dbName = 'hospital_db';
$user   = 'root';
$pass   = '';          // Change to your XAMPP MySQL password if set

$dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // In production, never expose the real error message
    http_response_code(500);
    die('<div style="font-family:sans-serif;padding:40px;color:#dc2626">
        <h2>Database Connection Error</h2>
        <p>Could not connect to the hospital database. Please check your XAMPP MySQL server is running and the database <strong>hospital_db</strong> exists.</p>
        <p style="color:#6b7280;font-size:13px">Technical: ' . htmlspecialchars($e->getMessage()) . '</p>
    </div>');
}
