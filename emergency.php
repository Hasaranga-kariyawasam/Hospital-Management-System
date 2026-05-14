<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Services — MediCare General Hospital</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/Web/Hospital-Management-System/includes/layout.css">
    <link rel="stylesheet" href="/Web/Hospital-Management-System/includes/home.css">

    <style>
        /* ── Page backdrop ── */
        .emergency-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f1b3d 0%, #1a2f5e 60%, #0d2247 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 100px 20px 60px;
            font-family: 'DM Sans', sans-serif;
        }

        /* ── Modal card ── */
        .eq-card {
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 32px 80px rgba(0,0,0,0.45);
            width: 100%;
            max-width: 540px;
            padding: 40px 44px 36px;
            position: relative;
            animation: slideUp 0.35s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Close / back button ── */
        .eq-close {
            position: absolute;
            top: 18px;
            right: 20px;
            background: none;
            border: none;
            font-size: 22px;
            color: #555;
            cursor: pointer;
            line-height: 1;
            transition: color 0.2s;
        }
        .eq-close:hover { color: #111; }

        /* ── Emergency number banner ── */
        .eq-number-banner {
            background: linear-gradient(135deg, #fff0f0, #ffe3e3);
            border: 2px solid #ff2d2d;
            border-radius: 12px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }
        .eq-number-pulse {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #ff2d2d;
            flex-shrink: 0;
            animation: pulse-dot 1.4s ease infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255,45,45,0.6); }
            50%       { box-shadow: 0 0 0 8px rgba(255,45,45,0); }
        }
        .eq-number-text {
            flex: 1;
        }
        .eq-number-label {
            font-size: 11px;
            font-weight: 600;
            color: #c0392b;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .eq-number-value {
            font-size: 22px;
            font-weight: 700;
            color: #c0392b;
            line-height: 1.2;
        }

        /* ── Title ── */
        .eq-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0 0 6px;
        }
        .eq-subtitle {
            font-size: 13px;
            color: #777;
            margin: 0 0 28px;
        }

        /* ── Form fields ── */
        .eq-field {
            margin-bottom: 20px;
        }
        .eq-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        .eq-select {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #d0d5e8;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            color: #333;
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23555' stroke-width='1.8' fill='none' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat right 16px center;
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .eq-select:focus {
            border-color: #1a6dff;
            box-shadow: 0 0 0 3px rgba(26,109,255,0.15);
        }
        .eq-select.active-select {
            border-color: #1a6dff;
            box-shadow: 0 0 0 3px rgba(26,109,255,0.15);
        }

        /* ── Text inputs ── */
        .eq-input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #d0d5e8;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            color: #333;
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
            box-sizing: border-box;
        }
        .eq-input:focus {
            border-color: #1a6dff;
            box-shadow: 0 0 0 3px rgba(26,109,255,0.15);
        }
        .eq-input.active-select {
            border-color: #e02020;
            box-shadow: 0 0 0 3px rgba(224,32,32,0.15);
        }
        .eq-input::placeholder { color: #aab; }

        /* ── Divider ── */
        .eq-divider {
            border: none;
            border-top: 1px solid #eef0f6;
            margin: 24px 0;
        }

        /* ── Action row ── */
        .eq-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 4px;
        }
        .eq-btn-cancel {
            background: none;
            border: none;
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            font-weight: 500;
            color: #555;
            cursor: pointer;
            padding: 10px 16px;
            border-radius: 8px;
            transition: background 0.2s, color 0.2s;
        }
        .eq-btn-cancel:hover {
            background: #f4f4f4;
            color: #111;
        }
        .eq-btn-continue {
            background: #e02020;
            color: #fff;
            border: none;
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            font-weight: 700;
            padding: 12px 28px;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 4px 14px rgba(224,32,32,0.35);
        }
        .eq-btn-continue:hover {
            background: #c01010;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(224,32,32,0.4);
        }
        .eq-btn-continue:active {
            transform: translateY(0);
        }

        /* ── Direct call strip ── */
        .eq-call-strip {
            margin-top: 20px;
            background: linear-gradient(135deg, #0f1b3d, #1a3a7a);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .eq-call-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .eq-call-label {
            font-size: 12px;
            color: rgba(255,255,255,0.65);
            font-weight: 500;
        }
        .eq-call-number {
            font-size: 17px;
            font-weight: 700;
            color: #fff;
        }
        .eq-call-btn {
            background: #ff2d2d;
            color: #fff;
            border: none;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 700;
            padding: 10px 22px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: background 0.2s, transform 0.15s;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .eq-call-btn:hover {
            background: #c01010;
            transform: translateY(-1px);
        }

        /* ── Result panel (shown after form submit) ── */
        .eq-result {
            display: none;
            text-align: center;
            padding: 10px 0 4px;
        }
        .eq-result-icon {
            font-size: 52px;
            margin-bottom: 14px;
        }
        .eq-result-title {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        .eq-result-msg {
            font-size: 14px;
            color: #555;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .eq-result-back {
            display: inline-block;
            background: #1a6dff;
            color: #fff;
            padding: 11px 26px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: background 0.2s;
        }
        .eq-result-back:hover { background: #0e52c1; }

        /* ── Nav override for this page ── */
        .pub-nav { position: fixed; width: 100%; top: 0; z-index: 999; }
    </style>
</head>
<body class="public-site">

<!-- ═══════════════ NAV ═══════════════ -->
<nav class="pub-nav scrolled" id="mainNav">
    <div class="pub-nav-inner">
        <a href="home.php" class="pub-nav-logo">
            <div class="pub-logo-mark">M</div>
            <div>
                <span class="pub-logo-name">MediCare</span>
                <span class="pub-logo-sub">General Hospital</span>
            </div>
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

<style>
    .emergency-nav-btn {
        background: #ff2d2d;
        color: white;
        padding: 12px 22px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        margin-right: 12px;
        transition: 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    .emergency-nav-btn:hover {
        background: #d90000;
        color: white;
        transform: translateY(-2px);
    }
</style>

<!-- ═══════════════ EMERGENCY PAGE ═══════════════ -->
<div class="emergency-page">
    <div class="eq-card" id="eqCard">

        <!-- Close / back -->
        <button class="eq-close" onclick="window.location.href='home.php'" title="Go back">✕</button>

        <!-- ── Emergency Number Banner ── -->
        <div class="eq-number-banner">
            <div class="eq-number-pulse"></div>
            <div class="eq-number-text">
                <div class="eq-number-label">📞 24/7 Emergency Hotline</div>
                <div class="eq-number-value">+94 11 234 5678</div>
            </div>
        </div>

        <!-- ── Form panel ── -->
        <div id="eqForm">
            <h2 class="eq-title">Quick Emergency Check</h2>
            <p class="eq-subtitle">Please answer a few quick questions so we can dispatch the right help.</p>

            <div class="eq-field">
                <label class="eq-label" for="patientName">Name</label>
                <input class="eq-input" type="text" id="patientName" placeholder="Enter name">
            </div>

            <div class="eq-field">
                <label class="eq-label" for="patientAddress">Address</label>
                <input class="eq-input" type="text" id="patientAddress" placeholder="Enter current address">
            </div>

            <div class="eq-field">
                <label class="eq-label" for="patientContact">Contact Number</label>
                <input class="eq-input" type="tel" id="patientContact" placeholder="e.g. +94 77 123 4567">
            </div>

            <div class="eq-field">
                <label class="eq-label" for="emergencyType">What is the emergency?</label>
                <select class="eq-select" id="emergencyType">
                    <option value="">Choose one</option>
                    <option value="cardiac">Cardiac Arrest / Chest Pain</option>
                    <option value="accident">Accident / Trauma</option>
                    <option value="breathing">Breathing Difficulty</option>
                    <option value="stroke">Stroke / Sudden Paralysis</option>
                    <option value="burn">Severe Burns</option>
                    <option value="poisoning">Poisoning / Overdose</option>
                    <option value="fracture">Fracture / Bone Injury</option>
                    <option value="other">Other Critical Condition</option>
                </select>
            </div>

            <div class="eq-field">
                <label class="eq-label" for="consciousness">Is the person conscious?</label>
                <select class="eq-select" id="consciousness">
                    <option value="">Choose one</option>
                    <option value="yes">Yes — Fully Conscious</option>
                    <option value="semi">Semi-Conscious / Confused</option>
                    <option value="no">No — Unconscious</option>
                </select>
            </div>

            <div class="eq-field">
                <label class="eq-label" for="assistance">Is there assistance on site?</label>
                <select class="eq-select" id="assistance">
                    <option value="">Choose one</option>
                    <option value="yes">Yes — Someone is helping</option>
                    <option value="no">No — Person is alone</option>
                    <option value="medical">Yes — Medical professional present</option>
                </select>
            </div>

            <hr class="eq-divider">

            <div class="eq-actions">
                <button class="eq-btn-cancel" onclick="window.location.href='home.php'">Cancel</button>
                <button class="eq-btn-continue" onclick="handleContinue()">Continue</button>
            </div>
        </div>

        <!-- ── Result panel (shown after Continue) ── -->
        <div class="eq-result" id="eqResult">
            <div class="eq-result-icon">🚑</div>
            <div class="eq-result-title">Alert Sent — Help is on the Way</div>
            <div class="eq-result-msg" id="eqResultMsg">
                Our emergency team has been notified. An ambulance will be dispatched immediately.<br>
                Please keep the line open and stay with the patient.
            </div>
            <a href="home.php" class="eq-result-back">← Return to Home</a>
        </div>

        <!-- ── Direct Call Strip ── -->
        <div class="eq-call-strip">
            <div class="eq-call-info">
                <span class="eq-call-label">Prefer to speak directly? Call now:</span>
                <span class="eq-call-number">+94 11 234 5678</span>
            </div>
            <a href="tel:+94112345678" class="eq-call-btn">
                📞 Call Directly
            </a>
        </div>

    </div><!-- /.eq-card -->
</div>

<script>
function handleContinue() {
    const name        = document.getElementById('patientName').value.trim();
    const address     = document.getElementById('patientAddress').value.trim();
    const contact     = document.getElementById('patientContact').value.trim();
    const type        = document.getElementById('emergencyType').value;
    const conscious   = document.getElementById('consciousness').value;
    const assistance  = document.getElementById('assistance').value;
 
    // Highlight empty fields
    let valid = true;
    [
        { id: 'patientName',    val: name },
        { id: 'patientAddress', val: address },
        { id: 'patientContact', val: contact },
        { id: 'emergencyType',  val: type },
        { id: 'consciousness',  val: conscious },
        { id: 'assistance',     val: assistance },
    ].forEach(({ id, val }) => {
        const el = document.getElementById(id);
        if (!val) { el.classList.add('active-select'); valid = false; }
        else       { el.classList.remove('active-select'); }
    });
    if (!valid) return;
 
    // Save to database
    const formData = new FormData();
    formData.append('patient_name',        name);
    formData.append('patient_address',     address);
    formData.append('contact_number',      contact);
    formData.append('emergency_type',      type);
    formData.append('is_conscious',        conscious);
    formData.append('assistance_on_site',  assistance);
 
    fetch('save_emergency.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            alert('Error saving request: ' + data.message);
            return;
        }
 
        // Show result panel (same as before)
        const typeLabels = {
            cardiac:'Cardiac Arrest / Chest Pain', accident:'Accident / Trauma',
            breathing:'Breathing Difficulty', stroke:'Stroke / Sudden Paralysis',
            burn:'Severe Burns', poisoning:'Poisoning / Overdose',
            fracture:'Fracture / Bone Injury', other:'Critical Condition'
        };
        const conscLabels = { yes:'conscious', semi:'semi-conscious', no:'unconscious' };
        const urgency = (conscious === 'no') ? 'CRITICAL — ' : '';
 
        document.getElementById('eqResultMsg').innerHTML =
            `<strong>${urgency}${typeLabels[type]}</strong> reported for <strong>${name}</strong>.<br>` +
            `Patient is <strong>${conscLabels[conscious]}</strong>.<br><br>` +
            `📍 <strong>Location:</strong> ${address}<br>` +
            `📞 <strong>Contact:</strong> ${contact}<br><br>` +
            `Our emergency dispatch team has been alerted. An ambulance is being mobilised.<br>` +
            `Stay on the line: <strong>+94 11 234 5678</strong>`;
 
        document.getElementById('eqForm').style.display   = 'none';
        document.getElementById('eqResult').style.display = 'block';
    })
    .catch(() => alert('Network error. Please call us directly: +94 11 234 5678'));
}
</script>

</body>
</html>