<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/config/db_config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /Web/Hospital-Management-System/index.php');
    exit();
}

$pageTitle  = 'Staff Registration';
$useSidebar = false;
$isPublic   = true;

$step    = 1;
$message = '';
$error   = '';
$errors  = [];

// Role definitions with their extra profile fields
$staffRoles = [
    'doctor'     => ['icon' => '🩺', 'label' => 'Doctor',       'desc' => 'Specialists and general physicians'],
    'reception'  => ['icon' => '🏥', 'label' => 'Receptionist', 'desc' => 'Front desk and admissions staff'],
    'pharmacist' => ['icon' => '💊', 'label' => 'Pharmacist',   'desc' => 'Pharmacy dispensing staff'],
    'dispatcher' => ['icon' => '🗺️', 'label' => 'Dispatcher',   'desc' => 'Emergency ambulance dispatch'],
    'driver'     => ['icon' => '🚑', 'label' => 'Driver',       'desc' => 'Ambulance drivers'],
];

// ── STEP 2: Save role-specific profile ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && (int)$_POST['step'] === 2) {
    $userId = (int)($_SESSION['reg_user_id'] ?? 0);
    $role   = $_SESSION['reg_role']          ?? '';

    if (!$userId || !$role) {
        header('Location: /Web/Hospital-Management-System/register_staff.php');
        exit();
    }

    $step = 2; // stay on step 2 if error

    if ($role === 'doctor') {
        // ── Doctor profile ──────────────────────────────────────
        $specialization  = trim($_POST['specialization']   ?? '');
        $qualifications  = trim($_POST['qualifications']   ?? '');
        $licenseNumber   = trim($_POST['license_number']   ?? '');
        $consultationFee = trim($_POST['consultation_fee'] ?? '0');

        if ($specialization === '') {
            $error = 'Specialization is required.';
        } else {
            if ($licenseNumber !== '') {
                $chk = $pdo->prepare("SELECT doctor_id FROM doctors WHERE license_number = ?");
                $chk->execute([$licenseNumber]);
                if ($chk->fetch()) {
                    $error = 'This medical license number is already registered.';
                }
            }

            if (!$error) {
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
                $message = 'success';
            }
        }

    } elseif ($role === 'pharmacist') {
        // ── Pharmacist profile ──────────────────────────────────
        $pharmacyLicense = trim($_POST['pharmacy_license'] ?? '');
        $shift           = trim($_POST['shift']            ?? '');

        if ($pharmacyLicense === '') {
            $error = 'Pharmacy license number is required.';
        } else {
            $ins = $pdo->prepare("
                INSERT INTO staff_profiles (user_id, role, pharmacy_license, shift)
                VALUES (?, 'pharmacist', ?, ?)
            ");
            $ins->execute([
                $userId,
                $pharmacyLicense,
                $shift !== '' ? $shift : null,
            ]);
            $message = 'success';
        }

    } elseif ($role === 'reception') {
        // ── Receptionist profile ────────────────────────────────
        $shift     = trim($_POST['shift']     ?? '');
        $extension = trim($_POST['extension'] ?? '');

        $ins = $pdo->prepare("
            INSERT INTO staff_profiles (user_id, role, shift, desk_extension)
            VALUES (?, 'reception', ?, ?)
        ");
        $ins->execute([
            $userId,
            $shift     !== '' ? $shift     : null,
            $extension !== '' ? $extension : null,
        ]);
        $message = 'success';

    } elseif ($role === 'dispatcher') {
        // ── Dispatcher profile ──────────────────────────────────
        $zone  = trim($_POST['zone']  ?? '');
        $shift = trim($_POST['shift'] ?? '');

        $ins = $pdo->prepare("
            INSERT INTO staff_profiles (user_id, role, zone, shift)
            VALUES (?, 'dispatcher', ?, ?)
        ");
        $ins->execute([
            $userId,
            $zone  !== '' ? $zone  : null,
            $shift !== '' ? $shift : null,
        ]);
        $message = 'success';

    } elseif ($role === 'driver') {
        // ── Driver profile ──────────────────────────────────────
        $driverLicense  = trim($_POST['driver_license']  ?? '');
        $vehicleNumber  = trim($_POST['vehicle_number']  ?? '');
        $driverPhone    = trim($_POST['driver_phone']    ?? '');

        if ($driverLicense === '' || $driverPhone === '') {
            $error = 'Driver license number and phone are required.';
        } else {
            $ins = $pdo->prepare("
                INSERT INTO staff_profiles (user_id, role, driver_license, vehicle_number, phone)
                VALUES (?, 'driver', ?, ?, ?)
            ");
            $ins->execute([
                $userId,
                $driverLicense,
                $vehicleNumber !== '' ? $vehicleNumber : null,
                $driverPhone,
            ]);
            $message = 'success';
        }
    }

    if ($message === 'success') {
        $savedName = $_SESSION['reg_full_name'] ?? '';
        unset($_SESSION['reg_user_id'], $_SESSION['reg_role'], $_SESSION['reg_full_name']);
    }
}

// ── STEP 1: Create user account ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && (int)$_POST['step'] === 1) {
    $fullName = trim($_POST['full_name']       ?? '');
    $email    = trim($_POST['email']           ?? '');
    $password = $_POST['password']             ?? '';
    $confirm  = $_POST['confirm_password']     ?? '';
    $role     = trim($_POST['role']            ?? '');
    $staffId  = trim($_POST['staff_id']        ?? '');
    $dept     = trim($_POST['department']      ?? '');

    if ($fullName === '')  $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (!array_key_exists($role, $staffRoles)) $errors[] = 'Please select a valid staff role.';
    if ($staffId === '') $errors[] = 'Employee / Staff ID is required.';

    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $errors[] = 'This email is already registered in the system.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = $pdo->prepare("
                INSERT INTO users (full_name, email, password_hash, role, status, staff_id, department)
                VALUES (?, ?, ?, ?, 'inactive', ?, ?)
            ");
            $ins->execute([$fullName, $email, $hash, $role, $staffId, $dept !== '' ? $dept : null]);

            $_SESSION['reg_user_id']   = (int)$pdo->lastInsertId();
            $_SESSION['reg_role']      = $role;
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
            <div class="auth-left-logo">🏥</div>
            <h2 class="auth-left-title">Staff<br>Registration</h2>
            <p class="auth-left-sub">
                Register for access to the MediCare Hospital Management System. Your account will be reviewed and activated by an administrator before you can sign in.
            </p>
            <ul class="auth-features">
                <li>Step 1: Create your login account and select your role</li>
                <li>Step 2: Fill in your role-specific professional details</li>
                <li>An administrator will review and activate your account</li>
                <li>Each role sees only the modules relevant to them</li>
                <li>Contact IT support if your account is not activated within 24 hours</li>
            </ul>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="auth-panel-right">
        <div class="auth-form-box" style="max-width:540px">

            <?php if ($message === 'success'): ?>
                <!-- ── Success ── -->
                <div style="text-align:center;padding:20px 0">
                    <div style="font-size:4rem;margin-bottom:16px">⏳</div>
                    <h2 style="margin-bottom:10px">Registration Submitted!</h2>
                    <p class="auth-subhead" style="margin-bottom:18px">
                        Welcome, <?php echo htmlspecialchars($savedName ?? ''); ?>!
                        Your account has been submitted for administrator review.
                    </p>
                    <div class="alert alert-info">
                        ℹ️ Your account is currently <strong>inactive</strong>. An administrator must approve it before you can log in. Contact the hospital IT department if you need urgent access.
                    </div>
                    <a href="/Web/Hospital-Management-System/login.php" class="btn btn-primary btn-full" style="margin-top:16px">
                        Go to Login Page
                    </a>
                </div>

            <?php elseif ($step === 2): ?>
                <?php
                    $currentRole = $_SESSION['reg_role'] ?? '';
                    $currentName = $_SESSION['reg_full_name'] ?? '';
                    $roleInfo    = $staffRoles[$currentRole] ?? ['icon'=>'👤','label'=>ucfirst($currentRole)];
                ?>
                <!-- ── Step 2: Role-Specific Profile ── -->
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

                <h2><?php echo $roleInfo['icon']; ?> <?php echo htmlspecialchars($roleInfo['label']); ?> Profile</h2>
                <p class="auth-subhead">
                    Hi <strong><?php echo htmlspecialchars($currentName); ?></strong>! Now fill in your professional details.
                </p>

                <?php if ($error): ?>
                    <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="step" value="2">

                    <?php if ($currentRole === 'doctor'): ?>
                        <!-- Doctor-specific fields -->
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
                                <label class="form-label">Medical License No. (SLMC)</label>
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

                    <?php elseif ($currentRole === 'pharmacist'): ?>
                        <!-- Pharmacist-specific fields -->
                        <div class="form-group">
                            <label class="form-label">Pharmacy License Number <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="pharmacy_license" class="form-control"
                                placeholder="e.g. SPMC-2024-001"
                                value="<?php echo htmlspecialchars($_POST['pharmacy_license'] ?? ''); ?>"
                                required>
                            <div style="font-size:12px;color:var(--muted);margin-top:4px">Issued by the Sri Lanka Pharmacy Council.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Preferred Shift</label>
                            <select name="shift" class="form-control">
                                <option value="">-- Select shift --</option>
                                <option value="morning"   <?php echo (($_POST['shift'] ?? '') === 'morning'   ? 'selected' : ''); ?>>Morning (6 AM – 2 PM)</option>
                                <option value="afternoon" <?php echo (($_POST['shift'] ?? '') === 'afternoon' ? 'selected' : ''); ?>>Afternoon (2 PM – 10 PM)</option>
                                <option value="night"     <?php echo (($_POST['shift'] ?? '') === 'night'     ? 'selected' : ''); ?>>Night (10 PM – 6 AM)</option>
                            </select>
                        </div>

                    <?php elseif ($currentRole === 'reception'): ?>
                        <!-- Receptionist-specific fields -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Preferred Shift</label>
                                <select name="shift" class="form-control">
                                    <option value="">-- Select shift --</option>
                                    <option value="morning"   <?php echo (($_POST['shift'] ?? '') === 'morning'   ? 'selected' : ''); ?>>Morning (6 AM – 2 PM)</option>
                                    <option value="afternoon" <?php echo (($_POST['shift'] ?? '') === 'afternoon' ? 'selected' : ''); ?>>Afternoon (2 PM – 10 PM)</option>
                                    <option value="night"     <?php echo (($_POST['shift'] ?? '') === 'night'     ? 'selected' : ''); ?>>Night (10 PM – 6 AM)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Desk Extension</label>
                                <input type="text" name="extension" class="form-control"
                                    placeholder="e.g. 101"
                                    value="<?php echo htmlspecialchars($_POST['extension'] ?? ''); ?>">
                                <div style="font-size:12px;color:var(--muted);margin-top:4px">Internal phone extension number.</div>
                            </div>
                        </div>

                    <?php elseif ($currentRole === 'dispatcher'): ?>
                        <!-- Dispatcher-specific fields -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Coverage Zone</label>
                                <input type="text" name="zone" class="form-control"
                                    placeholder="e.g. Colombo North, Kandy"
                                    value="<?php echo htmlspecialchars($_POST['zone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Preferred Shift</label>
                                <select name="shift" class="form-control">
                                    <option value="">-- Select shift --</option>
                                    <option value="morning"   <?php echo (($_POST['shift'] ?? '') === 'morning'   ? 'selected' : ''); ?>>Morning (6 AM – 2 PM)</option>
                                    <option value="afternoon" <?php echo (($_POST['shift'] ?? '') === 'afternoon' ? 'selected' : ''); ?>>Afternoon (2 PM – 10 PM)</option>
                                    <option value="night"     <?php echo (($_POST['shift'] ?? '') === 'night'     ? 'selected' : ''); ?>>Night (10 PM – 6 AM)</option>
                                </select>
                            </div>
                        </div>

                    <?php elseif ($currentRole === 'driver'): ?>
                        <!-- Driver-specific fields -->
                        <div class="form-group">
                            <label class="form-label">Driver's License Number <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="driver_license" class="form-control"
                                placeholder="e.g. B1234567"
                                value="<?php echo htmlspecialchars($_POST['driver_license'] ?? ''); ?>"
                                required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Assigned Vehicle Number</label>
                                <input type="text" name="vehicle_number" class="form-control"
                                    placeholder="e.g. AMB-001"
                                    value="<?php echo htmlspecialchars($_POST['vehicle_number'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Mobile Phone <span style="color:var(--danger)">*</span></label>
                                <input type="tel" name="driver_phone" class="form-control"
                                    placeholder="e.g. 077 123 4567"
                                    value="<?php echo htmlspecialchars($_POST['driver_phone'] ?? ''); ?>"
                                    required>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-warning" style="font-size:13px;margin-top:8px">
                        ⚠️ Your account will remain <strong>inactive</strong> until an administrator reviews and approves your registration.
                    </div>

                    <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:8px">
                        Submit Registration →
                    </button>
                </form>

            <?php else: ?>
                <!-- ── Step 1: Account + Role ── -->
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

                <h2>Staff Account Registration</h2>
                <p class="auth-subhead">For hospital employees and clinical staff only</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $e): ?>
                            <div>⚠️ <?php echo htmlspecialchars($e); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="staffForm" novalidate>
                    <input type="hidden" name="step" value="1">

                    <!-- Role Selector -->
                    <div style="margin-bottom:22px">
                        <label class="form-label">Your Role <span style="color:var(--danger)">*</span></label>
                        <div class="role-tabs" style="flex-wrap:wrap">
                            <?php foreach ($staffRoles as $key => $r): ?>
                            <label class="role-tab <?php echo (($_POST['role'] ?? '') === $key ? 'active' : ''); ?>" id="tab-<?php echo $key; ?>">
                                <span class="tab-icon"><?php echo $r['icon']; ?></span>
                                <?php echo htmlspecialchars($r['label']); ?>
                                <input type="radio" name="role" value="<?php echo $key; ?>"
                                    style="display:none"
                                    <?php echo (($_POST['role'] ?? '') === $key ? 'checked' : ''); ?>
                                    required>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div id="roleDesc" style="font-size:12px;color:var(--muted);margin-top:6px"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Full Name <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="full_name" class="form-control"
                            placeholder="As in your employment record"
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
                            <label class="form-label">Employee / Staff ID <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="staff_id" class="form-control"
                                placeholder="e.g. EMP-2024-001"
                                value="<?php echo htmlspecialchars($_POST['staff_id'] ?? ''); ?>"
                                required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" class="form-control"
                                placeholder="e.g. Cardiology, Pharmacy"
                                value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>">
                        </div>
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

                    <!-- Strength bar -->
                    <div style="margin-bottom:18px">
                        <div style="height:4px;background:var(--border);border-radius:4px;overflow:hidden">
                            <div id="strengthBar" style="height:100%;width:0;border-radius:4px;transition:width 0.3s,background 0.3s"></div>
                        </div>
                        <div id="strengthLabel" style="font-size:11px;color:var(--muted);margin-top:5px"></div>
                    </div>

                    <div class="alert alert-warning" style="font-size:13px">
                        ⚠️ Staff accounts require administrator approval before login. Accounts are not immediately active.
                    </div>

                    <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:8px">
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
// Role descriptions
const roleData = <?php echo json_encode(array_map(fn($r) => $r['desc'], $staffRoles)); ?>;
const roleKeys = <?php echo json_encode(array_keys($staffRoles)); ?>;

document.querySelectorAll('.role-tab').forEach(tab => {
    tab.addEventListener('click', function () {
        document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        const radio = this.querySelector('input[type=radio]');
        if (radio) radio.checked = true;
        const idx = roleKeys.indexOf(radio?.value);
        document.getElementById('roleDesc').textContent =
            idx >= 0 ? (Object.values(roleData)[idx] || '') : '';
    });
});

// Toggle password
document.getElementById('togglePwd')?.addEventListener('click', function () {
    const p = document.getElementById('pwd');
    const isText = p.type === 'text';
    p.type = isText ? 'password' : 'text';
    this.textContent = isText ? '👁' : '🙈';
});

// Strength meter
document.getElementById('pwd')?.addEventListener('input', function () {
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
    const bar = document.getElementById('strengthBar');
    const lbl = document.getElementById('strengthLabel');
    const lvl = levels[score] || levels[0];
    bar.style.width      = lvl.pct;
    bar.style.background = lvl.col;
    lbl.textContent      = lvl.txt;
    lbl.style.color      = lvl.col;
});

// Match check on submit
document.getElementById('staffForm')?.addEventListener('submit', function (e) {
    const p1 = document.getElementById('pwd')?.value;
    const p2 = document.getElementById('cpwd')?.value;
    if (p1 && p2 && p1 !== p2) {
        e.preventDefault();
        alert('Passwords do not match. Please check and try again.');
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>