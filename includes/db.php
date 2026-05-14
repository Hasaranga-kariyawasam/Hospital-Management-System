<?php
/**
 * includes/db.php
 * MediCare HMS — Unified Database Connection
 * Group 05 | ICT1242 Web Development Practicum
 *
 * Provides TWO connection handles used across the HMS:
 *
 *   1. db()   → returns a PDO instance  (used in prescription_actions.php PDO blocks)
 *   2. $conn  → MySQLi instance          (used in prescription_actions.php lookup_patient block)
 *
 * Both point to the same hospital_db database.
 * Configure the four constants below to match your environment.
 */
declare(strict_types=1);

// ── Database credentials ──────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'hospital_db');
define('DB_USER', 'root');        // ← change to your MySQL username
define('DB_PASS', '');            // ← change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

// ── PDO singleton (used by db()) ──────────────────────────────────────────
(function (): void {
    static $pdo = null;

    /**
     * Returns a shared PDO connection.
     * Throws a RuntimeException (logged, not exposed) on failure.
     */
    function db(): PDO
    {
        static $pdo = null;
        if ($pdo !== null) {
            return $pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('[MediCare] PDO connection failed: ' . $e->getMessage());
            // Do NOT expose credentials or internal details to the client
            throw new RuntimeException('Database connection failed. Please contact support.');
        }

        return $pdo;
    }
})();

// ── MySQLi connection ($conn) — used by lookup_patient in prescription_actions.php ──
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_errno) {
    error_log('[MediCare] MySQLi connection failed: ' . $conn->connect_error);
    // If this file is included from an AJAX endpoint, output JSON error and stop
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_GET['action']) || !empty($_POST['action'])) {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'Database unavailable. Please try again later.']);
        exit;
    }
    // Otherwise show a plain error (replace with a proper error page in production)
    die('Database connection error. Please contact the system administrator.');
}

$conn->set_charset(DB_CHARSET);
