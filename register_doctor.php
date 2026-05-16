<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/config/db_config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /Web/Hospital-Management-System/index.php');
    exit();
}

$pageTitle  = 'Doctor Registration';
$useSidebar = false;
$isPublic   = true;

$step    = 1;
$message = '';
$error   = '';
$errors  = [];

// ── STEP 2: Save doctor profile ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && (int)$_POST['step'] === 2) {
    $userId = (int)($_SESSION['reg_user_id'] ?? 0);
    if (!$userId) {
        header('Location: /Web/Hospital-Management-System/register_doctor.php');
        exit();
    }

    $specialization  = trim($_POST['specialization']   ?? '');
    $qualifications  = trim($_POST['qualifications']   ?? '');
    $licenseNumber   = trim($_POST['license_number']   ?? '');
    $consultationFee = trim($_POST['consultation_fee'] ?? '0');

    if ($specialization === '') {
        $error = 'Specialization is required.';
        $step  = 2;
    } else {
        if ($licenseNumber !== '') {
            $chk = $pdo->prepare("SELECT doctor_id FROM doctors WHERE license_number = ?");
            $chk->execute([$licenseNumber]);
            if ($chk->fetch()) {
                $error = 'This medical license number is already registered.';
                $step  = 2;
            }
        }

        if ($step !== 2) {
            $fee = is_numeric($consultationFee) ? (float)$consultationFee : 0.00;
            $ins = $pdo->prepare("
                INSERT INTO doctors (user_id, specialization, qualifications, license_number, consultation_fee)
                VALUES (?, ?, ?, ?, ?)
            ");
            $ins->execute([$userId, $specialization, $qualifications !== '' ? $qualifications : null, $licenseNumber !== '' ? $licenseNumber : null, $fee]);
            $savedName = $_SESSION['reg_full_name'] ?? '';
            unset($_SESSION['reg_user_id'], $_SESSION['reg_full_name']);
            $message = 'success';
        }
    }
}

// ── STEP 1: Create user account ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && (int)$_POST['step'] === 1) {
    $fullName = trim($_POST['full_name']       ?? '');
    $email    = trim($_POST['email']           ?? '');
    $password = $_POST['password']             ?? '';
    $confirm  = $_POST['confirm_password']     ?? '';

    if ($fullName === '' || $email === '' || $password === '') $errors[] = 'All fields are required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'doctor', 'inactive')");
            $ins->execute([$fullName, $email, $hash]);
            $_SESSION['reg_user_id']   = (int)$pdo->lastInsertId();
            $_SESSION['reg_full_name'] = $fullName;
            $step = 2;
        }
    } else {
        $step = 1;
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
        font-family: 'DM Sans', 'Segoe UI', sans-serif;
        min-height: 100vh;
        background: #f0f4f8;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    .auth-wrapper {
        width: 100%;
        max-width: 1100px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        background: white;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08), 0 1px 4px rgba(0, 0, 0, 0.04);
    }
    
    .auth-brand-panel {
        background: linear-gradient(150deg, #1e3a5f 0%, #1a56db 40%, #1e40af 100%);
        padding: 60px 48px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
        overflow: hidden;
        color: white;
    }
    
    .auth-brand-panel::before {
        content: '';
        position: absolute;
        top: -100px; right: -100px;
        width: 300px; height: 300px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.05);
    }
    
    .auth-brand-panel::after {
        content: '';
        position: absolute;
        bottom: -80px; left: -60px;
        width: 250px; height: 250px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.03);
    }
    
    .brand-logo-section {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 40px;
        position: relative;
        z-index: 1;
    }
    
    .brand-logo-circle {
        width: 64px; height: 64px;
        background: white;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: #1a56db;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }
    
    .brand-name-block h3 {
        font-family: 'Playfair Display', serif;
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1.2;
    }
    
    .brand-name-block p {
        font-size: 0.8rem;
        opacity: 0.8;
        letter-spacing: 0.5px;
    }
    
    .brand-title {
        font-family: 'Playfair Display', serif;
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.2;
        margin-bottom: 16px;
        position: relative;
        z-index: 1;
    }
    
    .brand-desc {
        font-size: 0.95rem;
        line-height: 1.7;
        opacity: 0.85;
        margin-bottom: 36px;
        position: relative;
        z-index: 1;
    }
    
    .brand-feature-list {
        list-style: none;
        position: relative;
        z-index: 1;
    }
    
    .brand-feature-list li {
        padding: 6px 0;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 12px;
        opacity: 0.9;
    }
    
    .brand-feature-list li i {
        color: #60a5fa;
        font-size: 0.9rem;
        width: 18px;
        text-align: center;
    }
    
    .auth-form-panel {
        padding: 60px 56px;
        display: flex;
        align-items: flex-start;
        background: white;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .form-container {
        width: 100%;
        max-width: 420px;
        margin: 0 auto;
    }
    
    .form-header {
        margin-bottom: 28px;
    }
    
    .form-header h2 {
        font-family: 'Playfair Display', serif;
        font-size: 1.8rem;
        color: #1e293b;
        margin-bottom: 6px;
        font-weight: 700;
    }
    
    .form-header p {
        color: #64748b;
        font-size: 0.9rem;
    }
    
    .steps-container {
        display: flex;
        align-items: center;
        margin-bottom: 28px;
    }
    
    .step-item {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .step-circle {
        width: 32px; height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .step-circle.active { background: #1a56db; color: white; box-shadow: 0 2px 8px rgba(26, 86, 219, 0.3); }
    .step-circle.done { background: #059669; color: white; }
    .step-circle.pending { background: #f1f5f9; color: #94a3b8; border: 2px solid #e2e8f0; }
    
    .step-line { flex: 1; height: 2px; background: #e2e8f0; margin: 0 12px; }
    .step-line.done { background: #059669; }
    
    .step-label-text { font-size: 0.78rem; color: #64748b; white-space: nowrap; }
    
    .alert {
        padding: 14px 18px;
        border-radius: 12px;
        margin-bottom: 20px;
        font-size: 0.9rem;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    
    .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
    .alert-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1a56db; }
    .alert-warning { background: #fffbeb; border: 1px solid #fde68a; color: #d97706; }
    
    .form-group { margin-bottom: 18px; }
    
    .form-label {
        display: block;
        color: #374151;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .form-label .required { color: #dc2626; }
    
    .form-input {
        width: 100%;
        padding: 14px 16px;
        background: #f8fafc;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        color: #1e293b;
        font-size: 0.95rem;
        font-family: inherit;
        transition: all 0.2s ease;
        outline: none;
    }
    
    .form-input:focus {
        border-color: #1a56db;
        background: white;
        box-shadow: 0 0 0 4px rgba(26, 86, 219, 0.08);
    }
    
    .form-input::placeholder { color: #94a3b8; }
    
    textarea.form-input { resize: vertical; min-height: 80px; }
    
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    
    .input-group { position: relative; display: flex; align-items: center; }
    .input-group .form-input { padding-right: 50px; }
    
    .input-toggle-btn {
        position: absolute; right: 6px;
        background: transparent; border: none;
        color: #94a3b8; padding: 10px;
        cursor: pointer; font-size: 1.1rem;
        transition: color 0.2s;
    }
    
    .input-toggle-btn:hover { color: #1e293b; }
    
    .strength-meter { height: 4px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin-bottom: 6px; }
    .strength-fill { height: 100%; width: 0; border-radius: 4px; transition: width 0.3s, background 0.3s; }
    .strength-text { font-size: 0.75rem; color: #64748b; }
    
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 24px;
        border-radius: 12px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        border: none;
        font-family: inherit;
    }
    
    .btn-primary {
        width: 100%;
        background: #1a56db;
        color: white;
        box-shadow: 0 4px 12px rgba(26, 86, 219, 0.25);
        margin-top: 8px;
    }
    
    .btn-primary:hover {
        background: #1e40af;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(26, 86, 219, 0.35);
    }
    
    .back-link { text-align: center; margin-top: 24px; }
    .back-link a { color: #64748b; text-decoration: none; font-size: 0.9rem; transition: color 0.2s; }
    .back-link a:hover { color: #1a56db; }
    
    .success-icon-circle {
        width: 80px; height: 80px;
        background: #fef3c7;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 2.5rem;
        color: #d97706;
    }
    
    @media (max-width: 768px) {
        .auth-wrapper { grid-template-columns: 1fr; max-width: 480px; }
        .auth-brand-panel { padding: 40px 32px; }
        .auth-form-panel { padding: 40px 32px; }
        .brand-title { font-size: 1.6rem; }
        .form-row { grid-template-columns: 1fr; }
    }
</style>

<div class="auth-wrapper">
    <!-- Left Brand Panel -->
    <div class="auth-brand-panel">
        <div class="brand-logo-section">
            <div class="brand-logo-circle"><i class="fas fa-user-doctor"></i></div>
            <div class="brand-name-block">
                <h3>MediCare</h3>
                <p>General Hospital</p>
            </div>
        </div>
        
        <h1 class="brand-title">Doctor Registration</h1>
        
        <p class="brand-desc">
            Join the MediCare clinical team. Register your professional details and credentials — an administrator will review and activate your account before you can sign in.
        </p>
        
        <ul class="brand-feature-list">
            <li><i class="fas fa-calendar-check"></i> Manage your appointment schedule</li>
            <li><i class="fas fa-file-medical"></i> Access patient medical records</li>
            <li><i class="fas fa-prescription-bottle"></i> Issue digital prescriptions</li>
            <li><i class="fas fa-flask"></i> Submit & review lab orders</li>
            <li><i class="fas fa-shield-alt"></i> Admin approval required</li>
        </ul>
    </div>

    <!-- Right Form Panel -->
    <div class="auth-form-panel">
        <div class="form-container">

            <?php if ($message === 'success'): ?>
                <div style="text-align:center;">
                    <div class="success-icon-circle">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <h2 style="font-family:'Playfair Display',serif;color:#1e293b;margin-bottom:12px;">Registration Submitted!</h2>
                    <p style="color:#64748b;margin-bottom:20px;line-height:1.6;">
                        Welcome, Dr. <?php echo htmlspecialchars($savedName ?? ''); ?>!<br>
                        Your account has been submitted for administrator review.
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Your account is currently <strong>inactive</strong>. An administrator must approve it before you can log in.
                    </div>
                    <a href="/Web/Hospital-Management-System/login.php" class="btn btn-primary" style="margin-top:20px;">
                        <i class="fas fa-sign-in-alt"></i> Go to Login Page
                    </a>
                </div>

            <?php elseif ($step === 2): ?>
                <div class="steps-container">
                    <div class="step-item">
                        <div class="step-circle done"><i class="fas fa-check"></i></div>
                        <span class="step-label-text">Account</span>
                    </div>
                    <div class="step-line done"></div>
                    <div class="step-item">
                        <div class="step-circle active">2</div>
                        <span class="step-label-text">Profile</span>
                    </div>
                </div>

                <div class="form-header">
                    <h2>Doctor Profile</h2>
                    <p>Hi <strong style="color:#1a56db;">Dr. <?php echo htmlspecialchars($_SESSION['reg_full_name'] ?? ''); ?></strong>! Now enter your medical credentials.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="step" value="2">

                    <div class="form-group">
                        <label class="form-label">Specialization <span class="required">*</span></label>
                        <input type="text" name="specialization" class="form-input"
                            placeholder="e.g. Cardiology, General Medicine"
                            value="<?php echo htmlspecialchars($_POST['specialization'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Qualifications</label>
                        <textarea name="qualifications" class="form-input" rows="3"
                            placeholder="e.g. MBBS (Colombo), MD (Cardiology), MRCP"><?php echo htmlspecialchars($_POST['qualifications'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Medical License No. (SLMC)</label>
                            <input type="text" name="license_number" class="form-input"
                                placeholder="e.g. SLMC-2024-001"
                                value="<?php echo htmlspecialchars($_POST['license_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Consultation Fee (LKR)</label>
                            <input type="number" name="consultation_fee" class="form-input"
                                placeholder="e.g. 2500" min="0" step="0.01"
                                value="<?php echo htmlspecialchars($_POST['consultation_fee'] ?? '0'); ?>">
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Your account will remain <strong>inactive</strong> until an administrator reviews and approves your registration.
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Submit Registration <i class="fas fa-paper-plane"></i>
                    </button>
                </form>

            <?php else: ?>
                <div class="steps-container">
                    <div class="step-item">
                        <div class="step-circle active">1</div>
                        <span class="step-label-text">Account</span>
                    </div>
                    <div class="step-line"></div>
                    <div class="step-item">
                        <div class="step-circle pending">2</div>
                        <span class="step-label-text">Profile</span>
                    </div>
                </div>

                <div class="form-header">
                    <h2>Create Account</h2>
                    <p>Set up your login credentials</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <div style="display:flex;flex-direction:column;gap:4px;">
                            <?php foreach ($errors as $e): ?>
                                <div><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($e); ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" id="accountForm" novalidate>
                    <input type="hidden" name="step" value="1">

                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" class="form-input"
                            placeholder="As on your medical license"
                            value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Work Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-input"
                            placeholder="yourname@medicare-hospital.lk"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Password <span class="required">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="pwd" class="form-input" placeholder="Min. 8 characters" required>
                                <button type="button" class="input-toggle-btn" id="togglePwd"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm Password <span class="required">*</span></label>
                            <input type="password" name="confirm_password" id="cpwd" class="form-input" placeholder="Repeat password" required>
                        </div>
                    </div>

                    <div style="margin-bottom:16px">
                        <div class="strength-meter">
                            <div id="strengthBar" class="strength-fill"></div>
                        </div>
                        <div id="strengthLabel" class="strength-text"></div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Continue to Profile <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <div class="back-link">
                    Staff member? <a href="/Web/Hospital-Management-System/register_staff.php">Staff Registration</a>
                    &nbsp;|&nbsp;
                    <a href="/Web/Hospital-Management-System/login.php">Already have an account?</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
document.getElementById('togglePwd')?.addEventListener('click', function () {
    const p = document.getElementById('pwd');
    const icon = this.querySelector('i');
    const isText = p.type === 'text';
    p.type = isText ? 'password' : 'text';
    icon.className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
});

document.getElementById('pwd')?.addEventListener('input', function () {
    const v = this.value;
    let score = 0;
    if (v.length >= 8) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    
    const levels = [
        {pct:'0%', col:'transparent', txt:''},
        {pct:'25%', col:'#dc2626', txt:'Weak'},
        {pct:'50%', col:'#d97706', txt:'Fair'},
        {pct:'75%', col:'#2563eb', txt:'Good'},
        {pct:'100%', col:'#059669', txt:'Strong'},
    ];
    const lvl = levels[score] || levels[0];
    document.getElementById('strengthBar').style.width = lvl.pct;
    document.getElementById('strengthBar').style.background = lvl.col;
    document.getElementById('strengthLabel').textContent = lvl.txt;
    document.getElementById('strengthLabel').style.color = lvl.col;
});

document.getElementById('accountForm')?.addEventListener('submit', function (e) {
    const p1 = document.getElementById('pwd')?.value;
    const p2 = document.getElementById('cpwd')?.value;
    if (p1 && p2 && p1 !== p2) {
        e.preventDefault();
        alert('Passwords do not match. Please check and try again.');
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>