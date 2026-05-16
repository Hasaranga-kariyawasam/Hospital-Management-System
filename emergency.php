<?php
// emergency.php — project root
// Handles both: page render (GET) and form submission (POST via fetch)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Always return JSON for POST requests
    header('Content-Type: application/json');

    try {
        require_once __DIR__ . '/config/db_config.php';
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => 'DB config not found: ' . $e->getMessage()]);
        exit;
    }

    // Map display values → ENUM values
    $typeMap = [
        'Cardiac Arrest / Chest Pain' => 'cardiac',
        'Accident / Trauma'           => 'accident',
        'Breathing Difficulty'        => 'breathing',
        'Stroke / Sudden Paralysis'   => 'stroke',
        'Severe Burns'                => 'burn',
        'Poisoning / Overdose'        => 'poisoning',
        'Fracture / Bone Injury'      => 'fracture',
        'Other Critical Condition'    => 'other',
    ];
    $consciousMap = [
        'Yes — Fully Conscious'      => 'yes',
        'Semi-Conscious / Confused'  => 'semi',
        'No — Unconscious'           => 'no',
    ];
    $assistMap = [
        'Yes — Someone is helping'         => 'yes',
        'No — Person is alone'             => 'no',
        'Yes — Medical professional present' => 'medical',
    ];

    $patientName    = trim($_POST['patient_name']    ?? '');
    $patientAddress = trim($_POST['patient_address'] ?? '');
    $contactNumber  = trim($_POST['contact_number']  ?? '');
    $emergencyType  = $typeMap[trim($_POST['emergency_type'] ?? '')] ?? null;
    $isConscious    = $consciousMap[trim($_POST['is_conscious'] ?? '')] ?? null;
    $assistance     = $assistMap[trim($_POST['assistance_on_site'] ?? '')] ?? null;

    if (!$contactNumber || !$emergencyType || !$isConscious || !$assistance) {
        echo json_encode(['ok' => false, 'msg' => 'Missing or invalid fields']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO emergency_requests
                (patient_name, patient_address, contact_number, emergency_type, is_conscious, assistance_on_site, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$patientName, $patientAddress, $contactNumber, $emergencyType, $isConscious, $assistance]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => 'DB error: ' . $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Services — MediCare General Hospital</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/Web/Hospital-Management-System/includes/layout.css">
    <link rel="stylesheet" href="/Web/Hospital-Management-System/includes/home.css">
    <style>
        html, body { height: 100%; overflow: hidden; font-family: 'DM Sans', sans-serif; }
        .emergency-page {
            height: 100vh;
            background: linear-gradient(135deg, #0f1b3d 0%, #1a2f5e 60%, #0d2247 100%);
            display: flex; align-items: center; justify-content: center;
            padding: 80px 20px 0; box-sizing: border-box;
        }
        .eq-card {
            background: #fff; border-radius: 18px;
            box-shadow: 0 32px 80px rgba(0,0,0,0.45);
            width: 100%; max-width: 780px;
            padding: 28px 36px 24px; position: relative;
            box-sizing: border-box; animation: slideUp 0.35s ease;
        }
        @keyframes slideUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
        .eq-close { position:absolute;top:16px;right:18px;background:none;border:none;font-size:20px;color:#666;cursor:pointer; }
        .eq-close:hover { color:#111; }
        .eq-number-banner {
            background:linear-gradient(135deg,#fff0f0,#ffe3e3);
            border:2px solid #ff2d2d; border-radius:10px;
            padding:10px 18px; display:flex; align-items:center; gap:12px; margin-bottom:18px;
        }
        .eq-number-pulse { width:11px;height:11px;border-radius:50%;background:#ff2d2d;flex-shrink:0;animation:pulse-dot 1.4s ease infinite; }
        @keyframes pulse-dot { 0%,100%{box-shadow:0 0 0 0 rgba(255,45,45,0.6)} 50%{box-shadow:0 0 0 7px rgba(255,45,45,0)} }
        .eq-number-label { font-size:10px;font-weight:600;color:#c0392b;text-transform:uppercase;letter-spacing:0.06em; }
        .eq-number-value { font-size:18px;font-weight:700;color:#c0392b;line-height:1.2; }
        .eq-title   { font-size:19px;font-weight:700;color:#1a1a2e;margin:0 0 4px; }
        .eq-subtitle { font-size:12px;color:#888;margin:0 0 16px; }
        .eq-grid { display:grid;grid-template-columns:1fr 1fr;gap:14px 28px; }
        .eq-field { display:flex;flex-direction:column;gap:6px; }
        .eq-label { font-size:13px;font-weight:600;color:#1a1a2e; }
        .eq-input, .eq-select {
            width:100%;padding:10px 14px;border:1.5px solid #d0d5e8;border-radius:9px;
            font-family:'DM Sans',sans-serif;font-size:13px;color:#333;background:#fff;
            box-sizing:border-box;outline:none;transition:border-color 0.2s,box-shadow 0.2s;
        }
        .eq-select {
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='7' viewBox='0 0 11 7'%3E%3Cpath d='M1 1l4.5 4.5L10 1' stroke='%23555' stroke-width='1.6' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat:no-repeat;background-position:right 14px center;appearance:none;-webkit-appearance:none;cursor:pointer;
        }
        .eq-input:focus,.eq-select:focus { border-color:#1a6dff;box-shadow:0 0 0 3px rgba(26,109,255,0.13); }
        .eq-input.err,.eq-select.err     { border-color:#e02020;box-shadow:0 0 0 3px rgba(224,32,32,0.12); }
        .eq-input::placeholder { color:#bbc; }
        .eq-divider { border:none;border-top:1px solid #eef0f6;margin:16px 0 14px; }
        .eq-actions { display:flex;justify-content:flex-end;gap:10px; }
        .eq-btn-cancel { background:none;border:none;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:500;color:#666;cursor:pointer;padding:9px 16px;border-radius:8px;transition:background 0.2s; }
        .eq-btn-cancel:hover { background:#f4f4f4;color:#111; }
        .eq-btn-continue {
            background:#e02020;color:#fff;border:none;font-family:'DM Sans',sans-serif;
            font-size:14px;font-weight:700;padding:10px 26px;border-radius:9px;cursor:pointer;
            box-shadow:0 4px 14px rgba(224,32,32,0.32);transition:background 0.2s,transform 0.15s;
        }
        .eq-btn-continue:hover { background:#c01010;transform:translateY(-1px); }
        .eq-btn-continue:disabled { background:#ccc;cursor:not-allowed;box-shadow:none;transform:none; }
        .eq-call-strip {
            margin-top:14px;background:linear-gradient(135deg,#0f1b3d,#1a3a7a);
            border-radius:10px;padding:13px 18px;display:flex;align-items:center;justify-content:space-between;gap:16px;
        }
        .eq-call-label  { font-size:11px;color:rgba(255,255,255,0.6); }
        .eq-call-number { font-size:16px;font-weight:700;color:#fff; }
        .eq-call-btn {
            background:#ff2d2d;color:#fff;border:none;font-family:'DM Sans',sans-serif;
            font-size:13px;font-weight:700;padding:9px 20px;border-radius:8px;cursor:pointer;
            text-decoration:none;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;flex-shrink:0;transition:background 0.2s;
        }
        .eq-call-btn:hover { background:#c01010; }
        .eq-result { display:none;text-align:center;padding:10px 0 6px; }
        .eq-result-icon  { font-size:46px;margin-bottom:12px; }
        .eq-result-title { font-size:19px;font-weight:700;color:#1a1a2e;margin-bottom:8px; }
        .eq-result-msg   { font-size:13px;color:#555;line-height:1.65;margin-bottom:20px; }
        .eq-result-back  { display:inline-block;background:#1a6dff;color:#fff;padding:10px 24px;border-radius:9px;font-weight:600;font-size:13px;text-decoration:none; }
        .eq-result-back:hover { background:#0e52c1; }
        .pub-nav { position:fixed;width:100%;top:0;z-index:999; }
        .emergency-nav-btn { background:#ff2d2d;color:white;padding:12px 22px;border-radius:10px;text-decoration:none;font-weight:600;margin-right:12px;transition:0.3s ease; }
        .emergency-nav-btn:hover { background:#d90000;color:white; }
    </style>
</head>
<body class="public-site">

<nav class="pub-nav scrolled" id="mainNav">
    <div class="pub-nav-inner">
        <a href="home.php" class="pub-nav-logo">
            <div class="pub-logo-mark">M</div>
            <div><span class="pub-logo-name">MediCare</span><span class="pub-logo-sub">General Hospital</span></div>
        </a>
        <ul class="pub-nav-links">
            <li><a href="home.php#about">About</a></li>
            <li><a href="home.php#departments">Departments</a></li>
            <li><a href="home.php#doctors">Doctors</a></li>
            <li><a href="home.php#services">Services</a></li>
            <li><a href="home.php#contact">Contact</a></li>
        </ul>
        <a href="emergency.php" class="emergency-nav-btn">🚑 Emergency</a>
        <div class="pub-nav-actions">
            <a href="/Web/Hospital-Management-System/login.php" class="btn-nav-outline">Staff Login</a>
            <a href="/Web/Hospital-Management-System/login.php" class="btn-nav-solid">Patient Portal</a>
        </div>
    </div>
</nav>

<div class="emergency-page">
    <div class="eq-card">
        <button class="eq-close" onclick="window.location.href='home.php'">✕</button>

        <div class="eq-number-banner">
            <div class="eq-number-pulse"></div>
            <div>
                <div class="eq-number-label">📞 24/7 Emergency Hotline</div>
                <div class="eq-number-value">+94 11 234 5678</div>
            </div>
        </div>

        <!-- Form -->
        <div id="eqForm">
            <h2 class="eq-title">Quick Emergency Check</h2>
            <p class="eq-subtitle">Please fill in all fields so we can dispatch the right help immediately.</p>

            <div class="eq-grid">
                <!-- LEFT -->
                <div class="eq-field">
                    <label class="eq-label">Name</label>
                    <input class="eq-input" type="text" id="patientName" placeholder="Enter name">
                </div>
                <!-- RIGHT -->
                <div class="eq-field">
                    <label class="eq-label">What is the emergency?</label>
                    <select class="eq-select" id="emergencyType">
                        <option value="">Choose one</option>
                        <option value="Cardiac Arrest / Chest Pain">Cardiac Arrest / Chest Pain</option>
                        <option value="Accident / Trauma">Accident / Trauma</option>
                        <option value="Breathing Difficulty">Breathing Difficulty</option>
                        <option value="Stroke / Sudden Paralysis">Stroke / Sudden Paralysis</option>
                        <option value="Severe Burns">Severe Burns</option>
                        <option value="Poisoning / Overdose">Poisoning / Overdose</option>
                        <option value="Fracture / Bone Injury">Fracture / Bone Injury</option>
                        <option value="Other Critical Condition">Other Critical Condition</option>
                    </select>
                </div>

                <!-- LEFT -->
                <div class="eq-field">
                    <label class="eq-label">Address</label>
                    <input class="eq-input" type="text" id="patientAddress" placeholder="Enter current address">
                </div>
                <!-- RIGHT -->
                <div class="eq-field">
                    <label class="eq-label">Is the person conscious?</label>
                    <select class="eq-select" id="isConscious">
                        <option value="">Choose one</option>
                        <option value="Yes — Fully Conscious">Yes — Fully Conscious</option>
                        <option value="Semi-Conscious / Confused">Semi-Conscious / Confused</option>
                        <option value="No — Unconscious">No — Unconscious</option>
                    </select>
                </div>

                <!-- LEFT -->
                <div class="eq-field">
                    <label class="eq-label">Contact Number</label>
                    <input class="eq-input" type="tel" id="contactNumber" placeholder="e.g. +94 77 123 4567">
                </div>
                <!-- RIGHT -->
                <div class="eq-field">
                    <label class="eq-label">Is there assistance on site?</label>
                    <select class="eq-select" id="assistanceOnSite">
                        <option value="">Choose one</option>
                        <option value="Yes — Someone is helping">Yes — Someone is helping</option>
                        <option value="No — Person is alone">No — Person is alone</option>
                        <option value="Yes — Medical professional present">Yes — Medical professional present</option>
                    </select>
                </div>
            </div>

            <hr class="eq-divider">
            <div class="eq-actions">
                <button class="eq-btn-cancel" onclick="window.location.href='home.php'">Cancel</button>
                <button class="eq-btn-continue" id="continueBtn" onclick="handleContinue()">Continue</button>
            </div>
        </div>

        <!-- Result -->
        <div class="eq-result" id="eqResult">
            <div class="eq-result-icon">🚑</div>
            <div class="eq-result-title">Alert Sent — Help is on the Way</div>
            <div class="eq-result-msg" id="eqResultMsg"></div>
            <a href="home.php" class="eq-result-back">← Return to Home</a>
        </div>

        <!-- Call strip -->
        <div class="eq-call-strip">
            <div>
                <div class="eq-call-label">Prefer to speak directly? Call now:</div>
                <div class="eq-call-number">+94 11 234 5678</div>
            </div>
            <a href="tel:+94112345678" class="eq-call-btn">📞 Call Directly</a>
        </div>
    </div>
</div>

<script>
async function handleContinue() {
    const fields = {
        patientName:      document.getElementById('patientName').value.trim(),
        patientAddress:   document.getElementById('patientAddress').value.trim(),
        contactNumber:    document.getElementById('contactNumber').value.trim(),
        emergencyType:    document.getElementById('emergencyType').value,
        isConscious:      document.getElementById('isConscious').value,
        assistanceOnSite: document.getElementById('assistanceOnSite').value,
    };

    // Validate
    let valid = true;
    const idMap = {
        patientName:'patientName', patientAddress:'patientAddress',
        contactNumber:'contactNumber', emergencyType:'emergencyType',
        isConscious:'isConscious', assistanceOnSite:'assistanceOnSite'
    };
    Object.entries(fields).forEach(([key, val]) => {
        const el = document.getElementById(idMap[key]);
        if (!val) { el.classList.add('err'); valid = false; }
        else el.classList.remove('err');
    });
    if (!valid) return;

    // Disable button while submitting
    const btn = document.getElementById('continueBtn');
    btn.disabled = true;
    btn.textContent = 'Sending...';

    try {
        const body = new FormData();
        body.append('patient_name',     fields.patientName);
        body.append('patient_address',  fields.patientAddress);
        body.append('contact_number',   fields.contactNumber);
        body.append('emergency_type',   fields.emergencyType);
        body.append('is_conscious',     fields.isConscious);
        body.append('assistance_on_site', fields.assistanceOnSite);

        const res  = await fetch('emergency.php', { method: 'POST', body });
        const json = await res.json();

        if (!json.ok) {
            alert('Error: ' + (json.msg || 'Unknown error. Please call us directly: +94 11 234 5678'));
            btn.disabled = false;
            btn.textContent = 'Continue';
            return;
        }
    } catch (e) {
        alert('Network error. Please call us directly: +94 11 234 5678');
        btn.disabled = false;
        btn.textContent = 'Continue';
        return;
    }

    const urgency = fields.isConscious === 'No — Unconscious' ? 'CRITICAL — ' : '';
    document.getElementById('eqResultMsg').innerHTML =
        `<strong>${urgency}${fields.emergencyType}</strong> reported for <strong>${fields.patientName || 'patient'}</strong>.<br>
         Patient is <strong>${fields.isConscious}</strong>.<br><br>
         📍 <strong>Location:</strong> ${fields.patientAddress}<br>
         📞 <strong>Contact:</strong> ${fields.contactNumber}<br><br>
         Our dispatch team has been alerted. An ambulance is being mobilised.<br>
         Stay on the line: <strong>+94 11 234 5678</strong>`;

    document.getElementById('eqForm').style.display   = 'none';
    document.getElementById('eqResult').style.display = 'block';
}

document.querySelectorAll('.eq-input, .eq-select').forEach(el => {
    el.addEventListener('input',  () => el.classList.remove('err'));
    el.addEventListener('change', () => el.classList.remove('err'));
});
</script>
</body>
</html>