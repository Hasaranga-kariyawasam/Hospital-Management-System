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
            $ins->execute([
                $userId,
                $specialization,
                $qualifications !== '' ? $qualifications : null,
                $licenseNumber  !== '' ? $licenseNumber  : null,
                $fee,
            ]);
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

    if ($fullName === '' || $email === '' || $password === '')
        $errors[] = 'All fields are required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid email address.';
    if (strlen($password) < 8)
        $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)
        $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = $pdo->prepare("
                INSERT INTO users (full_name, email, password_hash, role, status)
                VALUES (?, ?, ?, 'doctor', 'inactive')
            ");
            $ins->execute([$fullName, $email, $hash]);
            $_SESSION['reg_user_id']   = (int)$pdo->lastInsertId();
            $_SESSION['reg_full_name'] = $fullName;
            $step = 2;
        }
    } else {
        $step = 1;
    }
}

// Output modern glass CSS
echo <<<CSS
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
        font-family: 'DM Sans', 'Segoe UI', sans-serif;
        min-height: 100vh;
        background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 30%, #0d9488 70%, #0f172a 100%);
        background-size: 400% 400%;
        animation: gradientShift 20s ease infinite;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        overflow-y: auto;
    }
    
    @keyframes gradientShift {
        0%, 100% { background-position: 0% 50%; }
        25% { background-position: 100% 0%; }
        50% { background-position: 100% 100%; }
        75% { background-position: 0% 100%; }
    }
    
    .bg-orb {
        position: fixed;
        border-radius: 50%;
        filter: blur(80px);
        opacity: 0.15;
        pointer-events: none;
        z-index: 0;
    }
    .bg-orb-1 {
        width: 500px; height: 500px;
        background: #0d9488;
        top: -200px; right: -200px;
        animation: float1 15s ease-in-out infinite;
    }
    .bg-orb-2 {
        width: 400px; height: 400px;
        background: #3b82f6;
        bottom: -150px; left: -150px;
        animation: float2 18s ease-in-out infinite;
    }
    .bg-orb-3 {
        width: 350px; height: 350px;
        background: #06b6d4;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        animation: float3 12s ease-in-out infinite;
    }
    
    @keyframes float1 {
        0%, 100% { transform: translate(0, 0) scale(1); }
        33% { transform: translate(50px, 30px) scale(1.1); }
        66% { transform: translate(-30px, -20px) scale(0.9); }
    }
    @keyframes float2 {
        0%, 100% { transform: translate(0, 0) scale(1); }
        50% { transform: translate(-40px, -30px) scale(1.15); }
    }
    @keyframes float3 {
        0%, 100% { transform: translate(-50%, -50%) scale(1); }
        50% { transform: translate(-50%, -50%) scale(1.2); }
    }
    
    .auth-container {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 1100px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0;
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(30px);
        -webkit-backdrop-filter: blur(30px);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 32px;
        overflow: hidden;
        box-shadow: 
            0 32px 64px rgba(0, 0, 0, 0.4),
            inset 0 1px 0 rgba(255, 255, 255, 0.1);
    }
    
    .auth-brand {
        background: linear-gradient(135deg, rgba(13, 148, 136, 0.2) 0%, rgba(15, 23, 42, 0.3) 100%);
        padding: 60px 48px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }
    .auth-brand::before {
        content: '';
        position: absolute;
        top: -50%; right: -50%;
        width: 200%; height: 200%;
        background: radial-gradient(circle at 30% 70%, rgba(13, 148, 136, 0.15) 0%, transparent 50%);
        animation: brandGlow 8s ease-in-out infinite;
    }
    @keyframes brandGlow {
        0%, 100% { transform: rotate(0deg); }
        50% { transform: rotate(180deg); }
    }
    
    .brand-logo {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #0d9488, #06b6d4);
        border-radius: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        font-weight: 700;
        color: white;
        margin-bottom: 32px;
        box-shadow: 0 16px 32px rgba(13, 148, 136, 0.3);
        position: relative;
        z-index: 1;
    }
    
    .brand-title {
        font-family: 'Playfair Display', serif;
        font-size: 2.5rem;
        font-weight: 700;
        color: white;
        line-height: 1.2;
        margin-bottom: 20px;
        position: relative;
        z-index: 1;
    }
    .brand-title span {
        background: linear-gradient(135deg, #0d9488, #06b6d4);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .brand-desc {
        color: rgba(255, 255, 255, 0.7);
        font-size: 1rem;
        line-height: 1.8;
        margin-bottom: 36px;
        position: relative;
        z-index: 1;
    }
    
    .brand-features {
        list-style: none;
        position: relative;
        z-index: 1;
    }
    .brand-features li {
        color: rgba(255, 255, 255, 0.85);
        padding: 8px 0;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .brand-features li i {
        color: #0d9488;
        font-size: 1.1rem;
    }
    
    .auth-form-panel {
        padding: 60px 48px;
        display: flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.03);
        max-height: 90vh;
        overflow-y: auto;
    }
    .form-inner {
        width: 100%;
        max-width: 460px;
        margin: 0 auto;
    }
    
    .form-header {
        margin-bottom: 30px;
    }
    .form-header h2 {
        font-family: 'Playfair Display', serif;
        font-size: 2rem;
        color: white;
        margin-bottom: 8px;
        font-weight: 700;
    }
    .form-header p {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.95rem;
    }
    
    /* Steps */
    .steps {
        display: flex;
        align-items: center;
        gap: 0;
        margin-bottom: 32px;
    }
    .step-item {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .step-num {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .step-num.active {
        background: linear-gradient(135deg, #0d9488, #06b6d4);
        color: white;
        box-shadow: 0 4px 12px rgba(13, 148, 136, 0.4);
    }
    .step-num.done {
        background: rgba(34, 197, 94, 0.3);
        color: #4ade80;
        border: 2px solid rgba(34, 197, 94, 0.5);
    }
    .step-num.pending {
        background: rgba(255, 255, 255, 0.08);
        color: rgba(255, 255, 255, 0.4);
        border: 2px solid rgba(255, 255, 255, 0.12);
    }
    .step-line {
        flex: 1;
        height: 2px;
        background: rgba(255, 255, 255, 0.12);
        margin: 0 16px;
        border-radius: 1px;
    }
    .step-line.done {
        background: rgba(34, 197, 94, 0.5);
    }
    .step-label {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.6);
        white-space: nowrap;
    }
    
    .alert-error {
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #fca5a5;
        padding: 14px 18px;
        border-radius: 14px;
        margin-bottom: 20px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .alert-info {
        background: rgba(59, 130, 246, 0.15);
        border: 1px solid rgba(59, 130, 246, 0.3);
        color: #93c5fd;
        padding: 14px 18px;
        border-radius: 14px;
        margin-bottom: 20px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .alert-warning {
        background: rgba(245, 158, 11, 0.15);
        border: 1px solid rgba(245, 158, 11, 0.3);
        color: #fcd34d;
        padding: 14px 18px;
        border-radius: 14px;
        margin-bottom: 20px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-group {
        margin-bottom: 18px;
    }
    .form-label {
        display: block;
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 8px;
        letter-spacing: 0.3px;
    }
    .form-control {
        width: 100%;
        padding: 14px 18px;
        background: rgba(255, 255, 255, 0.06);
        border: 1.5px solid rgba(255, 255, 255, 0.12);
        border-radius: 16px;
        color: white;
        font-size: 0.95rem;
        font-family: inherit;
        transition: all 0.3s ease;
        outline: none;
    }
    .form-control:focus {
        border-color: #0d9488;
        background: rgba(255, 255, 255, 0.1);
        box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.15);
    }
    .form-control::placeholder {
        color: rgba(255, 255, 255, 0.3);
    }
    textarea.form-control {
        resize: vertical;
        min-height: 80px;
    }
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    
    .input-group {
        position: relative;
        display: flex;
        align-items: center;
    }
    .input-group .form-control {
        padding-right: 50px;
    }
    .input-toggle {
        position: absolute;
        right: 6px;
        background: transparent;
        border: none;
        color: rgba(255, 255, 255, 0.5);
        padding: 10px;
        cursor: pointer;
        font-size: 1.1rem;
        transition: color 0.3s;
    }
    .input-toggle:hover {
        color: white;
    }
    
    .strength-bar-container {
        height: 4px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 6px;
    }
    .strength-bar {
        height: 100%;
        width: 0;
        border-radius: 4px;
        transition: width 0.3s, background 0.3s;
    }
    .strength-label {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
    }
    
    .btn-primary {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, #0d9488, #06b6d4);
        border: none;
        border-radius: 16px;
        color: white;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.4s ease;
        box-shadow: 0 8px 24px rgba(13, 148, 136, 0.3);
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 16px 32px rgba(13, 148, 136, 0.5);
    }
    
    .auth-links {
        text-align: center;
        margin-top: 24px;
    }
    .auth-links a {
        color: rgba(255, 255, 255, 0.5);
        text-decoration: none;
        font-size: 0.9rem;
        transition: color 0.3s;
    }
    .auth-links a:hover {
        color: #0d9488;
    }
    
    .success-icon {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, rgba(13, 148, 136, 0.3), rgba(6, 182, 212, 0.3));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 24px;
        font-size: 3rem;
        color: #fcd34d;
    }
    
    @media (max-width: 768px) {
        .auth-container {
            grid-template-columns: 1fr;
            max-width: 500px;
        }
        .auth-brand {
            padding: 36px 32px;
        }
        .auth-form-panel {
            padding: 36px 32px;
        }
        .brand-title {
            font-size: 2rem;
        }
        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>
CSS;
?>

<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>
<div class="bg-orb bg-orb-3"></div>

<div class="auth-container">
    <!-- Left Brand Panel -->
    <div class="auth-brand">
        <div class="brand-logo"><i class="fas fa-user-doctor"></i></div>
        <h1 class="brand-title">Doctor<br><span>Registration</span></h1>
        <p class="brand-desc">
            Join the MediCare clinical team. Register your professional details and credentials — an administrator will review and activate your account before you can sign in.
        </p>
        <ul class="brand-features">
            <li><i class="fas fa-calendar-check"></i> Manage your appointment schedule</li>
            <li><i class="fas fa-file-medical"></i> Access patient medical records</li>
            <li><i class="fas fa-prescription-bottle"></i> Issue digital prescriptions</li>
            <li><i class="fas fa-flask"></i> Submit & review lab orders</li>
            <li><i class="fas fa-shield-alt"></i> Admin approval required</li>
        </ul>
    </div>

    <!-- Right Form Panel -->
    <div class="auth-form-panel">
        <div class="form-inner">

            <?php if ($message === 'success'): ?>
                <!-- Success -->
                <div style="text-align:center;">
                    <div class="success-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <h2 style="color:white;font-family:'Playfair Display',serif;margin-bottom:12px;">Registration Submitted!</h2>
                    <p style="color:rgba(255,255,255,0.7);margin-bottom:20px;line-height:1.6;">
                        Welcome, Dr. <?php echo htmlspecialchars($savedName ?? ''); ?>!<br>
                        Your account has been submitted for administrator review.
                    </p>
                    <div class="alert-info">
                        <i class="fas fa-info-circle"></i>
                        Your account is currently <strong>inactive</strong>. An administrator must approve it before you can log in. Contact the hospital IT department if you need urgent access.
                    </div>
                    <a href="/Web/Hospital-Management-System/login.php" class="btn-primary" style="margin-top:20px;">
                        <i class="fas fa-sign-in-alt"></i> Go to Login Page
                    </a>
                </div>

            <?php elseif ($step === 2): ?>
                <!-- Step 2: Doctor Profile -->
                <div class="steps">
                    <div class="step-item">
                        <div class="step-num done"><i class="fas fa-check"></i></div>
                        <span class="step-label">Account</span>
                    </div>
                    <div class="step-line done"></div>
                    <div class="step-item">
                        <div class="step-num active">2</div>
                        <span class="step-label">Profile</span>
                    </div>
                </div>

                <div class="form-header">
                    <h2>Doctor Profile</h2>
                    <p>Hi <strong style="color:#0d9488;">Dr. <?php echo htmlspecialchars($_SESSION['reg_full_name'] ?? ''); ?></strong>! Now enter your medical credentials.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="step" value="2">

                    <div class="form-group">
                        <label class="form-label">Specialization <span style="color:#ef4444">*</span></label>
                        <input type="text" name="specialization" class="form-control"
                            placeholder="e.g. Cardiology, General Medicine, Pediatrics"
                            value="<?php echo htmlspecialchars($_POST['specialization'] ?? ''); ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Qualifications</label>
                        <textarea name="qualifications" class="form-control" rows="3"
                            placeholder="e.g. MBBS (Colombo), MD (Cardiology), MRCP"><?php echo htmlspecialchars($_POST['qualifications'] ?? ''); ?></textarea>
                        <div style="font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;">List your degrees and professional certifications.</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Medical License No. (SLMC)</label>
                            <input type="text" name="license_number" class="form-control"
                                placeholder="e.g. SLMC-2024-001"
                                value="<?php echo htmlspecialchars($_POST['license_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Consultation Fee (LKR)</label>
                            <input type="number" name="consultation_fee" class="form-control"
                                placeholder="e.g. 2500"
                                min="0" step="0.01"
                                value="<?php echo htmlspecialchars($_POST['consultation_fee'] ?? '0'); ?>">
                        </div>
                    </div>

                    <div class="alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Your account will remain <strong>inactive</strong> until an administrator reviews and approves your registration.
                    </div>

                    <button type="submit" class="btn-primary">
                        Submit Registration <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

            <?php else: ?>
                <!-- Step 1: Account -->
                <div class="steps">
                    <div class="step-item">
                        <div class="step-num active">1</div>
                        <span class="step-label">Account</span>
                    </div>
                    <div class="step-line"></div>
                    <div class="step-item">
                        <div class="step-num pending">2</div>
                        <span class="step-label">Profile</span>
                    </div>
                </div>

                <div class="form-header">
                    <h2>Create Account</h2>
                    <p>Set up your login credentials</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert-error">
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <?php foreach ($errors as $e): ?>
                                <div><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($e); ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" id="accountForm" novalidate>
                    <input type="hidden" name="step" value="1">

                    <div class="form-group">
                        <label class="form-label">Full Name <span style="color:#ef4444">*</span></label>
                        <input type="text" name="full_name" class="form-control"
                            placeholder="As on your medical license"
                            value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Work Email <span style="color:#ef4444">*</span></label>
                        <input type="email" name="email" class="form-control"
                            placeholder="yourname@medicare-hospital.lk"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Password <span style="color:#ef4444">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="pwd" class="form-control" placeholder="Min. 8 characters" required>
                                <button type="button" class="input-toggle" id="togglePwd"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm Password <span style="color:#ef4444">*</span></label>
                            <input type="password" name="confirm_password" id="cpwd" class="form-control" placeholder="Repeat password" required>
                        </div>
                    </div>

                    <div style="margin-bottom:16px">
                        <div class="strength-bar-container">
                            <div id="strengthBar" class="strength-bar"></div>
                        </div>
                        <div id="strengthLabel" class="strength-label"></div>
                    </div>

                    <button type="submit" class="btn-primary">
                        Continue to Profile <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <div class="auth-links">
                    Staff member? <a href="/Web/Hospital-Management-System/register_staff.php">Staff Registration</a>
                    &nbsp;|&nbsp;
                    <a href="/Web/Hospital-Management-System/login.php">Already have an account?</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
// Toggle password
document.getElementById('togglePwd')?.addEventListener('click', function () {
    const p = document.getElementById('pwd');
    const icon = this.querySelector('i');
    const isText = p.type === 'text';
    p.type = isText ? 'password' : 'text';
    icon.className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
});

// Password strength meter
document.getElementById('pwd')?.addEventListener('input', function () {
    const v = this.value;
    let score = 0;
    if (v.length >= 8)          score++;
    if (/[A-Z]/.test(v))        score++;
    if (/[0-9]/.test(v))        score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const levels = [
        {pct:'0%',   col:'transparent', txt:''},
        {pct:'25%',  col:'#ef4444',     txt:'Weak'},
        {pct:'50%',  col:'#f59e0b',     txt:'Fair'},
        {pct:'75%',  col:'#3b82f6',     txt:'Good'},
        {pct:'100%', col:'#10b981',     txt:'Strong'},
    ];
    const bar = document.getElementById('strengthBar');
    const lbl = document.getElementById('strengthLabel');
    const lvl = levels[score] || levels[0];
    bar.style.width      = lvl.pct;
    bar.style.background = lvl.col;
    lbl.textContent      = lvl.txt;
    lbl.style.color      = lvl.col;
});

// Validate password match
document.getElementById('accountForm')?.addEventListener('submit', function (e) {
    const p1 = document.getElementById('pwd')?.value;
    const p2 = document.getElementById('cpwd')?.value;
    if (p1 && p2 && p1 !== p2) {
        e.preventDefault();
        alert('Passwords do not match. Please check and try again.');
    }
});
</script>