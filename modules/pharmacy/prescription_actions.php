<?php
/**
 * prescription_actions.php
 * MediCare General Hospital — Doctor Prescription Backend
 *
 * Handles AJAX requests from prescription.php:
 *   action=lookup_patient  → returns patient JSON by appointment number
 *   action=submit_rx       → validates & saves prescription, returns confirmation
 *
 * In a real system, replace the static arrays with PDO/MySQLi queries.
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// ── Simulated patient data (replace with DB query) ────────────────────────
$PATIENTS = [
    'APT-2026-0089' => ['name'=>'Kamal Jayasinghe',  'age'=>48, 'id'=>'PT-0024', 'nic'=>'198012345678'],
    'APT-2026-0090' => ['name'=>'Nimali Fernando',    'age'=>34, 'id'=>'PT-0031', 'nic'=>'199034567890'],
    'APT-2026-0091' => ['name'=>'Ruwan Silva',        'age'=>62, 'id'=>'PT-0018', 'nic'=>'196278901234'],
    'APT-2026-0092' => ['name'=>'Dilani Rathnayake',  'age'=>27, 'id'=>'PT-0055', 'nic'=>'199756781234'],
    'APT-2026-0093' => ['name'=>'Suresh Perera',      'age'=>55, 'id'=>'PT-0039', 'nic'=>'196901234567'],
];

// ── Simulated drug stock (replace with DB query) ──────────────────────────
$DRUGS_STOCK = [
     1=>500,  2=>350,  3=>300,  4=>200,  5=>80,
     6=>250,  7=>200,  8=>120,  9=>150, 10=>180,
    11=>90,  12=>60,  13=>40,  14=>400, 15=>300,
    16=>250, 17=>180, 18=>50,  19=>350, 20=>280,
    21=>220, 22=>300, 23=>200, 24=>260, 25=>400,
    26=>300, 27=>350, 28=>250, 29=>300, 30=>150,
    31=>60,  32=>400, 33=>350, 34=>500, 35=>200,
    36=>30,  37=>180, 38=>600, 39=>400, 40=>350,
    41=>400, 42=>300, 43=>250, 44=>500, 45=>200,
    46=>160, 47=>180, 48=>40,  49=>35,  50=>50,
];

// ── Doctor session (replace with $_SESSION['doctor'] in production) ───────
$DOCTOR = ['name'=>'Dr. Saman Perera', 'reg'=>'REG-DOC-0042', 'dept'=>'General Medicine'];

// ── Helpers ───────────────────────────────────────────────────────────────
function jsonOut(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

// ── Router ────────────────────────────────────────────────────────────────
$action = sanitize($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {

    // ── Patient lookup ──────────────────────────────────────────────────
    case 'lookup_patient':
        $appt = strtoupper(sanitize($_GET['appt'] ?? ''));
        if (isset($PATIENTS[$appt])) {
            jsonOut(['ok'=>true, 'patient'=>$PATIENTS[$appt], 'appt'=>$appt]);
        }
        jsonOut(['ok'=>false, 'error'=>'Appointment not found.'], 404);
        break;

    // ── Submit prescription ─────────────────────────────────────────────
    case 'submit_rx':
        $raw = json_decode(file_get_contents('php://input'), true);
        if (!$raw) {
            jsonOut(['ok'=>false, 'error'=>'Invalid request body.'], 400);
        }

        $appt      = strtoupper(sanitize($raw['appt']      ?? ''));
        $diagnosis = sanitize($raw['diagnosis'] ?? '');
        $notes     = sanitize($raw['notes']     ?? '');
        $medicines = $raw['medicines'] ?? [];

        // ── Validation ──────────────────────────────────────────────────
        $errors = [];
        if (!isset($PATIENTS[$appt]))      $errors[] = 'Appointment number not found.';
        if (empty($diagnosis))             $errors[] = 'Diagnosis is required.';
        if (empty($medicines))             $errors[] = 'At least one medicine is required.';

        foreach ($medicines as $i => $med) {
            $id   = intval($med['id']   ?? 0);
            $days = intval($med['days'] ?? 0);
            $tpd  = floatval($med['M'] ?? 0) + floatval($med['A'] ?? 0)
                  + floatval($med['E'] ?? 0) + floatval($med['N'] ?? 0);

            if (!isset($DRUGS_STOCK[$id])) {
                $errors[] = "Medicine row " . ($i+1) . ": unknown drug ID.";
            }
            if ($days < 1 || $days > 365) {
                $errors[] = "Medicine row " . ($i+1) . ": days must be 1–365.";
            }
            if ($tpd <= 0) {
                $errors[] = "Medicine row " . ($i+1) . ": dosage must be > 0.";
            }
            $totalNeeded = $tpd * $days;
            if (isset($DRUGS_STOCK[$id]) && $DRUGS_STOCK[$id] < $totalNeeded) {
                $errors[] = "Medicine row " . ($i+1) . ": insufficient stock (need {$totalNeeded}, have {$DRUGS_STOCK[$id]}).";
            }
        }

        if ($errors) {
            jsonOut(['ok'=>false, 'errors'=>$errors], 422);
        }

        // ── Save to DB (pseudo-code — replace with PDO inserts) ──────────
        /*
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "INSERT INTO prescriptions
               (appointment_no, patient_id, doctor_reg, diagnosis, clinical_notes, issued_at)
             VALUES (?,?,?,?,?, NOW())"
        );
        $patient = $PATIENTS[$appt];
        $stmt->execute([$appt, $patient['id'], $DOCTOR['reg'], $diagnosis, $notes]);
        $rxId = $pdo->lastInsertId();

        foreach ($medicines as $med) {
            $stmt2 = $pdo->prepare(
                "INSERT INTO prescription_items
                   (rx_id, drug_id, dose_morning, dose_afternoon, dose_evening,
                    dose_night, days, total_qty, instruction)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $tpd   = $med['M'] + $med['A'] + $med['E'] + $med['N'];
            $total = $tpd * $med['days'];
            $stmt2->execute([
                $rxId, $med['id'],
                $med['M'], $med['A'], $med['E'], $med['N'],
                $med['days'], $total, $med['instr'] ?? ''
            ]);
            // Deduct stock
            $pdo->prepare("UPDATE drug_inventory SET stock = stock - ? WHERE id = ?")
                ->execute([$total, $med['id']]);
        }
        $pdo->commit();
        */

        // ── Build confirmation response ──────────────────────────────────
        $patient = $PATIENTS[$appt];
        $rxRef   = 'RX-' . date('Ymd') . '-' . strtoupper(substr(md5($appt . time()), 0, 5));

        $summary = array_map(function($med) {
            $tpd   = floatval($med['M']??0) + floatval($med['A']??0)
                   + floatval($med['E']??0) + floatval($med['N']??0);
            $total = $tpd * intval($med['days']??1);
            return [
                'drug_id'     => intval($med['id']),
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
            'doctor'    => $DOCTOR,
            'patient'   => $patient,
            'appt'      => $appt,
            'diagnosis' => $diagnosis,
            'notes'     => $notes,
            'medicines' => $summary,
            'message'   => "Prescription {$rxRef} sent to pharmacy queue.",
        ]);
        break;

    // ── Unknown action ───────────────────────────────────────────────────
    default:
        jsonOut(['ok'=>false, 'error'=>'Unknown action.'], 400);
}
