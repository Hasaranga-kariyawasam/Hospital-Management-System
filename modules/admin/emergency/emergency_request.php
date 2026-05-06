<?php

session_start();
require_once '../../config/db_config.php';

$success     = false;
$ticket_no   = '';
$error_msg   = '';

// ── Handle form submission ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name        = trim($_POST['requester_name'] ?? '');
    $phone       = trim($_POST['phone']          ?? '');
    $description = trim($_POST['description']    ?? '');
    $gps_lat     = $_POST['gps_lat'] ?? null;
    $gps_lng     = $_POST['gps_lng'] ?? null;
    $patient_id  = isset($_SESSION['patient_id']) ? (int)$_SESSION['patient_id'] : null;

    // Basic server-side validation
    if ($name === '' || $phone === '') {
        $error_msg = 'Name and phone number are required.';
    } else {
        // Generate unique ticket number: EMG-YYYYMMDD-XXXXX
        $ticket_no = 'EMG-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

        $stmt = $pdo->prepare("
            INSERT INTO emergency_requests
                (ticket_no, patient_id, requester_name, phone, gps_lat, gps_lng, description, status, created_at)
            VALUES
                (:ticket_no, :patient_id, :name, :phone, :lat, :lng, :desc, 'pending', NOW())
        ");

        try {
            $stmt->execute([
                ':ticket_no'  => $ticket_no,
                ':patient_id' => $patient_id,
                ':name'       => $name,
                ':phone'      => $phone,
                ':lat'        => ($gps_lat !== '' && $gps_lat !== null) ? $gps_lat : null,
                ':lng'        => ($gps_lng !== '' && $gps_lng !== null) ? $gps_lng : null,
                ':desc'       => $description,
            ]);
            $success = true;
        } catch (PDOException $e) {
            $error_msg = 'Could not submit your request. Please call us directly. Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Ambulance Request | Hospital Management System</title>
    <link rel="stylesheet" href="../../includes/emergency.css">
</head>
<body>

<!-- ── Page Header ─────────────────────────────────────────── -->
<div class="page-header">
    <span class="icon">🚑</span>
    <div>
        <h1>Emergency Ambulance Request</h1>
        <p>Fill in the form below — our dispatcher will call you back immediately</p>
    </div>
</div>

<!-- ── Main Content ─────────────────────────────────────────── -->
<div class="container">

    <?php if ($success): ?>
    <!-- ── Success Confirmation ── -->
    <div class="card">
        <div class="confirmation-box">
            <div class="tick">✅</div>
            <h2>Request Received!</h2>
            <p>Your emergency ticket number is:</p>
            <div class="ticket-no"><?= htmlspecialchars($ticket_no) ?></div>
            <p>The hospital will call you at your provided number shortly.<br>
               Please stay near your phone and keep the line free.</p>
            <div style="margin-top:24px;">
                <a href="emergency_request.php" class="btn btn-danger">
                    ＋ Submit Another Request
                </a>
            </div>
        </div>
    </div>

    <?php else: ?>

    <!-- ── Emergency notice ── -->
    <div class="alert alert-danger">
        <span>⚠️</span>
        <div>
            <strong>Life-threatening emergency?</strong>
            Call the hospital directly at <strong>011-XXX-XXXX</strong>.
            Use this form for non-immediate ambulance requests or when lines are busy.
        </div>
    </div>

    <?php if ($error_msg): ?>
    <div class="alert alert-danger">
        <span>❌</span> <?= htmlspecialchars($error_msg) ?>
    </div>
    <?php endif; ?>

    <!-- ── Request Form ── -->
    <div class="card">
        <div class="card-title">
            🚑 Ambulance Request Form
        </div>

        <form method="POST" id="emergencyForm" novalidate>

            <!-- Name & Phone -->
            <div class="form-row">
                <div class="form-group">
                    <label for="requester_name">Your Full Name *</label>
                    <input type="text"
                           id="requester_name"
                           name="requester_name"
                           placeholder="e.g. Kamal Perera"
                           value="<?= htmlspecialchars($_POST['requester_name'] ?? '') ?>"
                           required>
                    <span class="err-msg text-muted" id="err_name"></span>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number (for callback) *</label>
                    <input type="tel"
                           id="phone"
                           name="phone"
                           placeholder="e.g. 0771234567"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                           required>
                    <span class="err-msg text-muted" id="err_phone"></span>
                </div>
            </div>

            <!-- GPS Location -->
            <div class="form-group">
                <label>Your Current Location (GPS)</label>
                <div class="gps-row">
                    <input type="text" id="gps_lat" name="gps_lat" placeholder="Latitude"  readonly>
                    <input type="text" id="gps_lng" name="gps_lng" placeholder="Longitude" readonly>
                    <button type="button" class="btn-gps" id="gpsBtn" onclick="getLocation()">
                        📍 Detect Location
                    </button>
                </div>
                <div class="gps-status" id="gpsStatus">Click "Detect Location" to share your GPS coordinates.</div>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label for="description">Brief Description of Emergency</label>
                <textarea id="description"
                          name="description"
                          placeholder="Describe what happened — e.g. 'Patient unconscious, chest pain, road accident'"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn btn-danger btn-full" id="submitBtn">
                🚨 Submit Emergency Request
            </button>

            <p class="text-muted mt-10" style="text-align:center;">
                After submitting, stay near your phone. A dispatcher will call you within minutes.
            </p>

        </form>
    </div>
    <?php endif; ?>

</div><!-- /container -->

<!-- ── JavaScript ─────────────────────────────────────────── -->
<script>
// ── GPS Geolocation ────────────────────────────────────────
function getLocation() {
    const btn    = document.getElementById('gpsBtn');
    const status = document.getElementById('gpsStatus');
    const latEl  = document.getElementById('gps_lat');
    const lngEl  = document.getElementById('gps_lng');

    if (!navigator.geolocation) {
        status.textContent = 'Geolocation is not supported by your browser.';
        status.className   = 'gps-status err';
        return;
    }

    btn.textContent    = '⏳ Detecting...';
    btn.disabled       = true;
    status.textContent = 'Getting your location…';
    status.className   = 'gps-status';

    navigator.geolocation.getCurrentPosition(
        // Success
        (position) => {
            latEl.value = position.coords.latitude.toFixed(8);
            lngEl.value = position.coords.longitude.toFixed(8);
            status.textContent = '✔ Location captured: ' + latEl.value + ', ' + lngEl.value;
            status.className   = 'gps-status ok';
            btn.textContent    = '📍 Location Captured';
            btn.style.background = '#dcfce7';
            btn.style.color      = '#15803d';
        },
        // Error
        (error) => {
            const msgs = {
                1: 'Permission denied. Please allow location access in your browser.',
                2: 'Position unavailable. Enter your location manually.',
                3: 'Location request timed out. Try again.',
            };
            status.textContent = msgs[error.code] || 'Could not get location.';
            status.className   = 'gps-status err';
            btn.textContent    = '📍 Try Again';
            btn.disabled       = false;
        },
        { timeout: 10000, maximumAge: 0 }
    );
}

// ── Form Validation ────────────────────────────────────────
document.getElementById('emergencyForm')?.addEventListener('submit', function(e) {
    let valid = true;

    const name  = document.getElementById('requester_name');
    const phone = document.getElementById('phone');

    // Reset errors
    document.querySelectorAll('.err-msg').forEach(el => el.textContent = '');
    document.querySelectorAll('input').forEach(el => el.style.borderColor = '');

    // Name check
    if (name.value.trim().length < 2) {
        document.getElementById('err_name').textContent = 'Please enter your full name.';
        name.style.borderColor = '#dc2626';
        valid = false;
    }

    // Phone check — Sri Lankan format (07XXXXXXXX, 10 digits)
    const phoneRegex = /^0[0-9]{9}$/;
    if (!phoneRegex.test(phone.value.trim())) {
        document.getElementById('err_phone').textContent = 'Enter a valid 10-digit phone number (e.g. 0771234567).';
        phone.style.borderColor = '#dc2626';
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
        return;
    }

    // Show loading state
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<span class="spinner"></span> Submitting…';
    btn.disabled  = true;
});
</script>

</body>
</html>