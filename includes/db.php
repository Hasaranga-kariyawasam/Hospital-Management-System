<?php
/**
 * includes/db.php
 * MediCare HMS — PDO database connection singleton
 * Group 05 | ICT1242 Web Development Practicum
 */
declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'hospital_db');
define('DB_USER', 'root');       // ← change in production
define('DB_PASS', '');           // ← change in production
define('DB_CHARSET', 'utf8mb4');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // In production log this instead of echoing
            http_response_code(500);
            die(json_encode(['ok' => false, 'error' => 'Database connection failed.']));
        }
    }
    return $pdo;
}
