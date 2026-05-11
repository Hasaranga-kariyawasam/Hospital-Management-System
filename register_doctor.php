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

// ── STEP 2: Save doctor profile ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && (int)$_POST['step'] === 2) {
    $userId = (int)($_SESSION['reg_user_id'] ?? 0);
    if (!$userId) {
        header('Location: /Web/Hospital-Management-System/register_doctor.php');
        exit();
    }

    $specialization   = trim($_POST['specialization']    ?? '');
    $qualifications   = trim($_POST['qualifications']    ?? '');
    $licenseNumber    = trim($_POST['license_number']    ?? '');
    $consultationFee  = trim($_POST['consultation_fee']  ?? '0');

    if ($specialization === '') {
        $error = 'Specialization is required.';
        $step  = 2;
    } else {
        // Check license number uniqueness (if provided)
        if ($licenseNumber !== '') {
            $check = $pdo->prepare("SELECT doctor_id FROM doctors WHERE license_number = ?");
            $check->execute([$licenseNumber]);
            if ($check->fetch()) {
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

            unset($_SESSION['reg_user_id']);
            $message = 'success';
            $step    = 'done';
        }
    }
}

// ── STEP 1: Create user account ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && (int)$_POST['step'] === 1) {
    $fullName = trim($_POST['full_name']       ?? '');
    $email    = trim($_POST['email']           ?? '');
    $password = $_POST['password']             ?? '';
    $confirm  = $_POST['confirm_password']     ?? '';

    if ($fullName === '' || $email === '' || $password === '') {
        $errors[] = 'All fields are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            // Doctor accounts start inactive — admin must activate
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

include __DIR__ . '/includes/header.php';
?>

<div class="auth-split">
    <!-- Left Panel -->
    <div class="auth-panel-left">
        <div class="auth-panel-left-content">
            <div class="auth-left-logo">🩺</div>
            <h2 class="auth-left-title">Doctor<br>Registration</h2>
            <p class="auth-left-sub">
                Join the MediCare clinical team. Register your professional details and credentials — an administrator will review and activate your account before you can sign in.
            </p>
            <ul class="auth-features">
                <li>Manage your appointment schedule online</li>
                <li>Access patient medical records and history</li>
                <li>Write and issue prescriptions digitally</li>
                <li>Submit and review lab & diagnostic orders</li>
                <li>Your account requires admin approval before login</li>
            </ul>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="auth-panel-right">
        <div class="auth-form-box" style="max-width:520px">

            <?php if ($step === 'done'): ?>
                <!-- ── Success ── -->
                <div style="text-align:center;padding:20px 0">
                    <div style="font-size:4rem;margin-bottom:16px">⏳</div>
                    <h2 style="margin-bottom:10px">Registration Submitted!</h2>
                    <p class="auth-subhead" style="margin-bottom:18px">
                        Welcome, Dr. <?php echo htmlspecialchars($_SESSION['reg_full_name'] ?? ''); ?>!
                        Your account has been submitted for review.
                    </p>
                    <div class="alert alert-info">
                        ℹ️ Your account is currently <strong>inactive</strong>. An administrator must approve it before you can log in. Please contact the hospital IT department if you need urgent access.
                    </div>
                    <a href="/Web/Hospital-Management-System/login.php" class="btn btn-primary btn-full" style="margin-top:16px">
                        Go to Login Page
                    </a>
                </div>

            <?php elseif ($step === 2): ?>
                <!-- ── Step 2: Doctor Profile ── -->
                <div class="steps" style="margin-bottom:28px">
                    <div class="step done">
                        <div class="step-num">✓</div>
                        <div class="step-label">Account</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step active">
                        <div class="step-num">2</div>
                        <div class="step-label">Profile</div>
                    </div>
                </div>

                <h2>Professional Profile</h2>
                <p class="auth-subhead">Enter your medical credentials and specialization.</p>

                <?php if ($error): ?>
                    <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" id="profileForm" novalidate>
                    <input type="hidden" name="step" value="2">

                    <div class="form-group">
                        <label class="form-label">Specialization <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="specialization" class="form-control"
                            placeholder="e.g. Cardiology, General Medicine, Pediatrics"
                            value="<?php echo htmlspecialchars($_POST['specialization'] ?? ''); ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Qualifications</label>
                        <textarea name="qualifications" class="form-control" rows="3"
                            placeholder="e.g. MBBS (Colombo), MD (Cardiology), MRCP"><?php echo htmlspecialchars($_POST['qualifications'] ?? ''); ?></textarea>
                        <div style="font-size:12px;color:var(--muted);margin-top:4px">List your degrees and professional certifications.</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Medical License Number</label>
                            <input type="text" name="license_number" class="form-control"
                                placeholder="e.g. SLMC-2024-001"
                                value="<?php echo htmlspecialchars($_POST['license_number'] ?? ''); ?>">
                            <div style="font-size:12px;color:var(--muted);margin-top:4px">Issued by SLMC or relevant authority.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Consultation Fee (LKR)</label>
                            <input type="number" name="consultation_fee" class="form-control"
                                placeholder="e.g. 2500"
                                min="0" step="0.01"
                                value="<?php echo htmlspecialchars($_POST['consultation_fee'] ?? '0'); ?>">
                        </div>
                    </div>

                    <div class="alert alert-warning" style="font-size:13px">
                        ⚠️ Your account will remain <strong>inactive</strong> until an administrator reviews and approves your registration.
                    </div>

                    <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:8px">
                        Submit Registration →
                    </button>
                </form>

            <?php else: ?>
                <!-- ── Step 1: Account ── -->
                <div class="steps" style="margin-bottom:28px">
                    <div class="step active">
                        <div class="step-num">1</div>
                        <div class="step-label">Account</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step">
                        <div class="step-num">2</div>
                        <div class="step-label">Profile</div>
                    </div>
                </div>

                <h2>Create Doctor Account</h2>
                <p class="auth-subhead">Set up your login credentials</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $e): ?>
                            <div>⚠️ <?php echo htmlspecialchars($e); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="accountForm" novalidate>
                    <input type="hidden" name="step" value="1">

                    <div class="form-group">
                        <label class="form-label">Full Name <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="full_name" class="form-control"
                            placeholder="As on your medical license"
                            value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Work Email <span style="color:var(--danger)">*</span></label>
                        <input type="email" name="email" class="form-control"
                            placeholder="yourname@medicare-hospital.lk"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Password <span style="color:var(--danger)">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="pwd" class="form-control" placeholder="Min. 8 characters" required>
                                <button type="button" class="input-toggle" id="togglePwd">👁</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm Password <span style="color:var(--danger)">*</span></label>
                            <input type="password" name="confirm_password" id="cpwd" class="form-control" placeholder="Repeat password" required>
                        </div>
                    </div>

                    <!-- Password strength bar -->
                    <div style="margin-bottom:16px">
                        <div style="height:4px;background:var(--border);border-radius:4px;overflow:hidden">
                            <div id="strengthBar" style="height:100%;width:0;border-radius:4px;transition:width 0.3s,background 0.3s"></div>
                        </div>
                        <div id="strengthLabel" style="font-size:11px;color:var(--muted);margin-top:5px"></div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full btn-lg">
                        Continue to Profile →
                    </button>
                </form>

                <div class="auth-links">
                    Registering as a patient? <a href="/Web/Hospital-Management-System/register_patient.php">Patient Registration</a>
                    &nbsp;|&nbsp;
                    <a href="/Web/Hospital-Management-System/login.php">Already have an account?</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
// Toggle password visibility
const toggleBtn = document.getElementById('togglePwd');
if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
        const p = document.getElementById('pwd');
        const isText = p.type === 'text';
        p.type = isText ? 'password' : 'text';
        this.textContent = isText ? '👁' : '🙈';
    });
}

// Password strength meter
const pwdInput = document.getElementById('pwd');
const bar      = document.getElementById('strengthBar');
const label    = document.getElementById('strengthLabel');
if (pwdInput) {
    pwdInput.addEventListener('input', function () {
        const v = this.value;
        let score = 0;
        if (v.length >= 8)          score++;
        if (/[A-Z]/.test(v))        score++;
        if (/[0-9]/.test(v))        score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;

        const levels = [
            {pct:'0%',   col:'transparent', txt:''},
            {pct:'25%',  col:'#dc2626',     txt:'Weak'},
            {pct:'50%',  col:'#d97706',     txt:'Fair'},
            {pct:'75%',  col:'#2563eb',     txt:'Good'},
            {pct:'100%', col:'#059669',     txt:'Strong'},
        ];
        const lvl = levels[score] || levels[0];
        bar.style.width      = lvl.pct;
        bar.style.background = lvl.col;
        label.textContent    = lvl.txt;
        label.style.color    = lvl.col;
    });
}

// Validate password match on submit
const form = document.getElementById('accountForm');
if (form) {
    form.addEventListener('submit', function (e) {
        const p1 = document.getElementById('pwd')?.value;
        const p2 = document.getElementById('cpwd')?.value;
        if (p1 && p2 && p1 !== p2) {
            e.preventDefault();
            alert('Passwords do not match. Please check and try again.');
        }
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>