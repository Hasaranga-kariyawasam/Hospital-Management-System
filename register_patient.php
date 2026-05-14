<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/config/db_config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /Web/Hospital-Management-System/index.php');
    exit();
}

$pageTitle  = 'Patient Registration';
$useSidebar = false;
$isPublic   = true;

$step    = 1;   // current step (1 = account, 2 = profile)
$message = '';
$error   = '';
$errors  = [];

// ── STEP 2: Complete profile after account creation ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && (int)$_POST['step'] === 2) {
    $userId = (int)($_SESSION['reg_user_id'] ?? 0);
    if (!$userId) {
        header('Location: /Web/Hospital-Management-System/register_patient.php');
        exit();
    }

    $nic       = trim($_POST['nic']       ?? '');
    $dob       = trim($_POST['dob']       ?? '');
    $gender    = trim($_POST['gender']    ?? '');
    $bloodType = trim($_POST['blood_type'] ?? '');
    $phone     = trim($_POST['phone']     ?? '');
    $address   = trim($_POST['address']   ?? '');
    $emergency = trim($_POST['emergency_contact'] ?? '');

    if ($nic === '' || $dob === '' || $gender === '' || $phone === '') {
        $error = 'NIC, date of birth, gender, and phone are required.';
        $step  = 2;
    } else {
        // Check NIC unique
        $check = $pdo->prepare("SELECT patient_id FROM patients WHERE nic = ?");
        $check->execute([$nic]);
        if ($check->fetch()) {
            $error = 'This NIC is already registered.';
            $step  = 2;
        } else {
            $ins = $pdo->prepare("
                INSERT INTO patients (user_id, nic, dob, gender, blood_type, phone, address, emergency_contact)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([$userId, $nic, $dob, $gender, $bloodType, $phone, $address, $emergency]);

            unset($_SESSION['reg_user_id']);
            $message = 'Registration complete! You can now log in to your patient portal.';
            $step = 'done';
        }
    }
}

// ── STEP 1: Account creation ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && (int)$_POST['step'] === 1) {
    $fullName  = trim($_POST['full_name']        ?? '');
    $email     = trim($_POST['email']            ?? '');
    $password  = $_POST['password']              ?? '';
    $confirm   = $_POST['confirm_password']      ?? '';

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
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = $pdo->prepare("
                INSERT INTO users (full_name, email, password_hash, role, status)
                VALUES (?, ?, ?, 'patient', 'active')
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
            <div class="auth-left-logo">👤</div>
            <h2 class="auth-left-title">Patient<br>Registration</h2>
            <p class="auth-left-sub">
                Create your MediCare patient account to book appointments, view your medical history, request ambulances, and manage your bills — all from one place.
            </p>
            <ul class="auth-features">
                <li>Book appointments with any specialist online</li>
                <li>View your medical records and prescriptions</li>
                <li>Download billing invoices and receipts</li>
                <li>Request emergency ambulance dispatch</li>
                <li>Track your admission status in real time</li>
            </ul>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="auth-panel-right">
        <div class="auth-form-box" style="max-width:500px">

            <?php if ($step === 'done'): ?>
                <!-- ── Success ── -->
                <div style="text-align:center;padding:20px 0">
                    <div style="font-size:4rem;margin-bottom:16px">✅</div>
                    <h2 style="margin-bottom:10px">You're Registered!</h2>
                    <p class="auth-subhead" style="margin-bottom:28px">
                        Welcome to MediCare, <?php echo htmlspecialchars($_SESSION['reg_full_name'] ?? ''); ?>!
                        Your patient account is ready.
                    </p>
                    <a href="/Web/Hospital-Management-System/login.php" class="btn btn-primary btn-full btn-lg">
                        Sign In to Your Portal →
                    </a>
                </div>

            <?php elseif ($step === 2): ?>
                <!-- ── Step 2: Medical Profile ── -->
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

                <h2>Your Medical Profile</h2>
                <p class="auth-subhead">This helps our doctors provide better care.</p>

                <?php if ($error): ?>
                    <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" id="profileForm" novalidate>
                    <input type="hidden" name="step" value="2">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">NIC Number <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="nic" class="form-control" placeholder="e.g. 199012345678" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth <span style="color:var(--danger)">*</span></label>
                            <input type="date" name="dob" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Gender <span style="color:var(--danger)">*</span></label>
                            <select name="gender" class="form-control" required>
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Blood Type</label>
                            <select name="blood_type" class="form-control">
                                <option value="">Unknown</option>
                                <option>A+</option><option>A−</option>
                                <option>B+</option><option>B−</option>
                                <option>O+</option><option>O−</option>
                                <option>AB+</option><option>AB−</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number <span style="color:var(--danger)">*</span></label>
                        <input type="tel" name="phone" class="form-control" placeholder="+94 77 123 4567" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Home Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Street, City, Province"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Emergency Contact</label>
                        <input type="text" name="emergency_contact" class="form-control" placeholder="Name & phone of a family member">
                    </div>

                    <button type="submit" class="btn btn-primary btn-full btn-lg">
                        Complete Registration →
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

                <h2>Create Patient Account</h2>
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
                            placeholder="As on your NIC"
                            value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address <span style="color:var(--danger)">*</span></label>
                        <input type="email" name="email" class="form-control"
                            placeholder="your@email.com"
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
                    Already have an account? <a href="/Web/Hospital-Management-System/login.php">Sign In</a>
                    &nbsp;|&nbsp;
                    <a href="/Web/Hospital-Management-System/home.php">← Back to Website</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
// Toggle password
const toggleBtn = document.getElementById('togglePwd');
if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
        const p = document.getElementById('pwd');
        const isText = p.type === 'text';
        p.type = isText ? 'password' : 'text';
        this.textContent = isText ? '👁' : '🙈';
    });
}

// Password strength
const pwdInput = document.getElementById('pwd');
const bar      = document.getElementById('strengthBar');
const label    = document.getElementById('strengthLabel');
if (pwdInput) {
    pwdInput.addEventListener('input', function () {
        const v = this.value;
        let score = 0;
        if (v.length >= 8)  score++;
        if (/[A-Z]/.test(v)) score++;
        if (/[0-9]/.test(v)) score++;
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
