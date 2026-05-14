<?php
/**
 * modules/pharmacy/prescription_action.php
 * MediCare HMS — Doctor Prescription Backend (DB-connected)
 * Group 05 | ICT1242 Web Development Practicum
 *
 * Handles AJAX requests from prescription.php:
 *   GET  action=lookup_patient  → returns patient JSON by appointment ref_number
 *   GET  action=get_drugs       → returns full drug catalogue from pharmacy_drugs
 *   POST action=submit_rx       → validates & saves prescription to DB
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// ── Session guard ─────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Uncomment in production to enforce doctor login:
// if (($_SESSION['role'] ?? '') !== 'doctor') {
//     jsonOut(['ok' => false, 'error' => 'Unauthorized.'], 401);
// }

// ── Helpers ───────────────────────────────────────────────────────────────
function jsonOut(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

// ── Router ────────────────────────────────────────────────────────────────
$action = sanitize($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {

    // ── Patient lookup by appointment ref number ──────────────────────────
    case 'lookup_patient':
        $ref = strtoupper(sanitize($_GET['appt'] ?? ''));
        if ($ref === '') {
            jsonOut(['ok' => false, 'error' => 'Appointment number is required.'], 400);
        }

        $pdo  = db();
        $stmt = $pdo->prepare("
            SELECT
                a.appointment_id,
                a.ref_number,
                a.appt_date,
                a.appt_time,
                a.status      AS appt_status,
                u.full_name   AS name,
                u.user_id,
                p.patient_id,
                p.nic,
                TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age,
                p.gender,
                p.blood_type,
                p.phone
            FROM appointments a
            JOIN patients  p ON p.patient_id = a.patient_id
            JOIN users     u ON u.user_id     = p.user_id
            WHERE a.ref_number = :ref1 OR a.appointment_id = :ref2 LIMIT 1
        ");
        $stmt->execute([':ref1' => $ref, ':ref2' => $ref]);
        $row = $stmt->fetch();

        if (!$row) {
            jsonOut(['ok' => false, 'error' => 'Appointment not found.'], 404);
        }

        // Check this appointment hasn't already had a prescription issued for all drugs
        jsonOut(['ok' => true, 'patient' => $row, 'appt' => $ref]);
        break;

    // ── Drug catalogue ────────────────────────────────────────────────────
    case 'get_drugs':
        $pdo  = db();
        $stmt = $pdo->query("
            SELECT
                drug_id   AS id,
                drug_name AS name,
                category  AS cat,
                unit,
                unit_price,
                stock_qty  AS stock,
                reorder_level AS reorder
            FROM pharmacy_drugs
            WHERE is_active = 1
            ORDER BY category, drug_name
        ");
        $drugs = $stmt->fetchAll();
        jsonOut(['ok' => true, 'drugs' => $drugs]);
        break;

    // ── Submit prescription ───────────────────────────────────────────────
    case 'submit_rx':
        $raw = json_decode(file_get_contents('php://input'), true);
        if (!$raw) {
            jsonOut(['ok' => false, 'error' => 'Invalid request body.'], 400);
        }

        $apptRef   = strtoupper(sanitize($raw['appt']      ?? ''));
        $diagnosis = sanitize($raw['diagnosis'] ?? '');
        $notes     = sanitize($raw['notes']     ?? '');
        $medicines = $raw['medicines'] ?? [];

        // ── Basic validation ──────────────────────────────────────────────
        $errors = [];
        if (empty($apptRef))    $errors[] = 'Appointment number is required.';
        if (empty($diagnosis))  $errors[] = 'Diagnosis is required.';
        if (empty($medicines))  $errors[] = 'At least one medicine is required.';

        if ($errors) {
            jsonOut(['ok' => false, 'errors' => $errors], 422);
        }

        $pdo = db();

        // ── Verify appointment exists and is confirmed/pending ────────────
        $apptStmt = $pdo->prepare("
            SELECT a.appointment_id, a.patient_id, a.doctor_id, a.status,
                   u.full_name AS patient_name,
                   p.patient_id AS pid
            FROM appointments a
            JOIN patients p ON p.patient_id = a.patient_id
            JOIN users    u ON u.user_id    = p.user_id
            WHERE a.ref_number = :ref1 OR a.appointment_id = :ref2 LIMIT 1
        ");
        $apptStmt->execute([':ref1' => $apptRef, ':ref2' => $apptRef]);
        $appt = $apptStmt->fetch();

        if (!$appt) {
            jsonOut(['ok' => false, 'errors' => ['Appointment not found.']], 422);
        }

        // ── Validate each medicine against DB stock ───────────────────────
        foreach ($medicines as $i => $med) {
            $drugId = sanitize((string)($med['id'] ?? ''));
            $days   = intval($med['days'] ?? 0);
            $tpd    = floatval($med['M'] ?? 0) + floatval($med['A'] ?? 0)
                    + floatval($med['E'] ?? 0) + floatval($med['N'] ?? 0);

            if ($days < 1 || $days > 365) {
                $errors[] = "Row " . ($i + 1) . ": days must be 1–365.";
            }
            if ($tpd <= 0) {
                $errors[] = "Row " . ($i + 1) . ": dosage must be greater than 0.";
            }

            // Check drug exists and has enough stock
            $dStmt = $pdo->prepare("
                SELECT drug_name, stock_qty
                FROM pharmacy_drugs
                WHERE drug_id = :id AND is_active = 1
                LIMIT 1
            ");
            $dStmt->execute([':id' => $drugId]);
            $drug = $dStmt->fetch();

            if (!$drug) {
                $errors[] = "Row " . ($i + 1) . ": drug not found in catalogue.";
                continue;
            }

            $totalNeeded = $tpd * $days;
            if ($drug['stock_qty'] < $totalNeeded) {
                $errors[] = "Row " . ($i + 1) . " ({$drug['drug_name']}): "
                          . "insufficient stock (need {$totalNeeded}, have {$drug['stock_qty']}).";
            }
        }

        if ($errors) {
            jsonOut(['ok' => false, 'errors' => $errors], 422);
        }

        // ── Persist to database (transaction) ─────────────────────────────
        try {
            $pdo->beginTransaction();

            foreach ($medicines as $med) {
                $drugId = sanitize((string)($med['id'] ?? ''));
                $days   = intval($med['days'] ?? 1);
                $tpd    = floatval($med['M'] ?? 0) + floatval($med['A'] ?? 0)
                        + floatval($med['E'] ?? 0) + floatval($med['N'] ?? 0);

                // Build dosage string: M-A-E-N
                $dosage = implode('-', [
                    $med['M'] ?? 0,
                    $med['A'] ?? 0,
                    $med['E'] ?? 0,
                    $med['N'] ?? 0,
                ]);

                // Build frequency / instruction note
                $frequency = sanitize($med['instr'] ?? 'As directed');

                // Generate prescription_id
                $rxId = 'RX-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));

                $insStmt = $pdo->prepare("
                    INSERT INTO prescriptions
                        (prescription_id, appointment_id, drug_id,
                         dosage, frequency, duration_days, status)
                    VALUES
                        (:rx_id, :appt_id, :drug_id,
                         :dosage, :freq, :days, 'pending')
                ");
                $insStmt->execute([
                    ':rx_id'   => $rxId,
                    ':appt_id' => $appt['appointment_id'],
                    ':drug_id' => $drugId,
                    ':dosage'  => $dosage,
                    ':freq'    => $frequency,
                    ':days'    => $days,
                ]);

                // Deduct stock
                $pdo->prepare("
                    UPDATE pharmacy_drugs
                    SET stock_qty = stock_qty - :qty
                    WHERE drug_id = :id
                ")->execute([':qty' => $tpd * $days, ':id' => $drugId]);
            }

            // Mark appointment as completed
            $pdo->prepare("
                UPDATE appointments SET status = 'completed'
                WHERE appointment_id = :id
            ")->execute([':id' => $appt['appointment_id']]);

            $pdo->commit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Prescription insert failed: ' . $e->getMessage());
            jsonOut(['ok' => false, 'error' => 'Database error. Please try again.'], 500);
        }

        // ── Build confirmation response ────────────────────────────────────
        $rxRef   = 'RX-' . date('Ymd') . '-' . strtoupper(substr(md5($apptRef . time()), 0, 5));
        $doctor  = [
            'name' => $_SESSION['full_name'] ?? 'Dr. (unknown)',
            'reg'  => $_SESSION['staff_id']  ?? '—',
            'dept' => $_SESSION['department'] ?? '—',
        ];

        $summary = array_map(function ($med) {
            $tpd   = floatval($med['M'] ?? 0) + floatval($med['A'] ?? 0)
                   + floatval($med['E'] ?? 0) + floatval($med['N'] ?? 0);
            $total = $tpd * intval($med['days'] ?? 1);
            return [
                'drug_id'     => sanitize((string)($med['id'] ?? '')),
                'name'        => sanitize($med['name'] ?? ''),
                'dosage'      => "{$med['M']}-{$med['A']}-{$med['E']}-{$med['N']}",
                'days'        => intval($med['days']),
                'total_qty'   => $total,
                'instruction' => sanitize($med['instr'] ?? ''),
            ];
        }, $medicines);

        jsonOut([
            'ok'        => true,
            'rx_ref'    => $rxRef,
            'issued_at' => date('D, d M Y — H:i'),
            'doctor'    => $doctor,
            'patient'   => ['name' => $appt['patient_name'], 'id' => $appt['pid']],
            'appt'      => $apptRef,
            'diagnosis' => $diagnosis,
            'notes'     => $notes,
            'medicines' => $summary,
            'message'   => "Prescription {$rxRef} sent to pharmacy queue.",
        ]);
        break;

    // ── Unknown action ────────────────────────────────────────────────────
    default:
        jsonOut(['ok' => false, 'error' => 'Unknown action.'], 400);
}
