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
    'doctor'     => ['icon' => 'fas fa-user-doctor',   'label' => 'Doctor',       'desc' => 'Specialists and general physicians'],
    'reception'  => ['icon' => 'fas fa-hospital-user', 'label' => 'Receptionist', 'desc' => 'Front desk and admissions staff'],
    'pharmacist' => ['icon' => 'fas fa-capsules',      'label' => 'Pharmacist',   'desc' => 'Pharmacy dispensing staff'],
    'dispatcher' => ['icon' => 'fas fa-map',           'label' => 'Dispatcher',   'desc' => 'Emergency ambulance dispatch'],
    'driver'     => ['icon' => 'fas fa-truck-medical', 'label' => 'Driver',       'desc' => 'Ambulance drivers'],
];

// ── STEP 2: Save role-specific profile ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && (int)$_POST['step'] === 2) {
    $userId = (int)($_SESSION['reg_user_id'] ?? 0);
    $role   = $_SESSION['reg_role']          ?? '';

    if (!$userId || !$role) {
        header('Location: /Web/Hospital-Management-System/register_staff.php');
        exit();
    }

    $step = 2;

    if ($role === 'doctor') {
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
                $ins->execute([$userId, $specialization, $qualifications !== '' ? $qualifications : null, $licenseNumber !== '' ? $licenseNumber : null, $fee]);
                $message = 'success';
            }
        }
    } elseif ($role === 'pharmacist') {
        $pharmacyLicense = trim($_POST['pharmacy_license'] ?? '');
        $shift           = trim($_POST['shift']            ?? '');

        if ($pharmacyLicense === '') {
            $error = 'Pharmacy license number is required.';
        } else {
            $ins = $pdo->prepare("INSERT INTO staff_profiles (user_id, role, pharmacy_license, shift) VALUES (?, 'pharmacist', ?, ?)");
            $ins->execute([$userId, $pharmacyLicense, $shift !== '' ? $shift : null]);
            $message = 'success';
        }
    } elseif ($role === 'reception') {
        $shift     = trim($_POST['shift']     ?? '');
        $extension = trim($_POST['extension'] ?? '');

        $ins = $pdo->prepare("INSERT INTO staff_profiles (user_id, role, shift, desk_extension) VALUES (?, 'reception', ?, ?)");
        $ins->execute([$userId, $shift !== '' ? $shift : null, $extension !== '' ? $extension : null]);
        $message = 'success';
    } elseif ($role === 'dispatcher') {
        $zone  = trim($_POST['zone']  ?? '');
        $shift = trim($_POST['shift'] ?? '');

        $ins = $pdo->prepare("INSERT INTO staff_profiles (user_id, role, zone, shift) VALUES (?, 'dispatcher', ?, ?)");
        $ins->execute([$userId, $zone !== '' ? $zone : null, $shift !== '' ? $shift : null]);
        $message = 'success';
    } elseif ($role === 'driver') {
        $driverLicense = trim($_POST['driver_license'] ?? '');
        $vehicleNumber = trim($_POST['vehicle_number'] ?? '');
        $driverPhone   = trim($_POST['driver_phone']   ?? '');

        if ($driverLicense === '' || $driverPhone === '') {
            $error = 'Driver license number and phone are required.';
        } else {
            $ins = $pdo->prepare("INSERT INTO staff_profiles (user_id, role, driver_license, vehicle_number, phone) VALUES (?, 'driver', ?, ?, ?)");
            $ins->execute([$userId, $driverLicense, $vehicleNumber !== '' ? $vehicleNumber : null, $driverPhone]);
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
            $ins  = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, status, staff_id, department) VALUES (?, ?, ?, ?, 'inactive', ?, ?)");
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
    .bg-orb-1 { width: 500px; height: 500px; background: #0d9488; top: -200px; right: -200px; animation: float1 15s ease-in-out infinite; }
    .bg-orb-2 { width: 400px; height: 400px; background: #3b82f6; bottom: -150px; left: -150px; animation: float2 18s ease-in-out infinite; }
    .bg-orb-3 { width: 350px; height: 350px; background: #06b6d4; top: 50%; left: 50%; transform: translate(-50%, -50%); animation: float3 12s ease-in-out infinite; }
    
    @keyframes float1 { 0%, 100% { transform: translate(0, 0) scale(1); } 33% { transform: translate(50px, 30px) scale(1.1); } 66% { transform: translate(-30px, -20px) scale(0.9); } }
    @keyframes float2 { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(-40px, -30px) scale(1.15); } }
    @keyframes float3 { 0%, 100% { transform: translate(-50%, -50%) scale(1); } 50% { transform: translate(-50%, -50%) scale(1.2); } }
    
    .auth-container {
        position: relative; z-index: 1; width: 100%; max-width: 1100px;
        display: grid; grid-template-columns: 1fr 1fr; gap: 0;
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(30px); -webkit-backdrop-filter: blur(30px);
        border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 32px;
        overflow: hidden;
        box-shadow: 0 32px 64px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1);
    }
    
    .auth-brand {
        background: linear-gradient(135deg, rgba(13, 148, 136, 0.2) 0%, rgba(15, 23, 42, 0.3) 100%);
        padding: 60px 48px; display: flex; flex-direction: column; justify-content: center;
        position: relative; overflow: hidden;
    }
    .auth-brand::before {
        content: ''; position: absolute; top: -50%; right: -50%; width: 200%; height: 200%;
        background: radial-gradient(circle at 30% 70%, rgba(13, 148, 136, 0.15) 0%, transparent 50%);
        animation: brandGlow 8s ease-in-out infinite;
    }
    @keyframes brandGlow { 0%, 100% { transform: rotate(0deg); } 50% { transform: rotate(180deg); } }
    
    .brand-logo {
        width: 80px; height: 80px; background: linear-gradient(135deg, #0d9488, #06b6d4);
        border-radius: 24px; display: flex; align-items: center; justify-content: center;
        font-size: 2rem; color: white; margin-bottom: 32px;
        box-shadow: 0 16px 32px rgba(13, 148, 136, 0.3); position: relative; z-index: 1;
    }
    .brand-title {
        font-family: 'Playfair Display', serif; font-size: 2.5rem; font-weight: 700;
        color: white; line-height: 1.2; margin-bottom: 20px; position: relative; z-index: 1;
    }
    .brand-title span { background: linear-gradient(135deg, #0d9488, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .brand-desc { color: rgba(255, 255, 255, 0.7); font-size: 1rem; line-height: 1.8; margin-bottom: 36px; position: relative; z-index: 1; }
    
    .brand-features { list-style: none; position: relative; z-index: 1; }
    .brand-features li { color: rgba(255, 255, 255, 0.85); padding: 8px 0; font-size: 0.95rem; display: flex; align-items: center; gap: 12px; }
    .brand-features li i { color: #0d9488; font-size: 1.1rem; }
    
    .auth-form-panel {
        padding: 60px 48px; display: flex; align-items: flex-start;
        background: rgba(255, 255, 255, 0.03); max-height: 90vh; overflow-y: auto;
    }
    .form-inner { width: 100%; max-width: 500px; margin: 0 auto; }
    
    .form-header { margin-bottom: 30px; }
    .form-header h2 { font-family: 'Playfair Display', serif; font-size: 2rem; color: white; margin-bottom: 8px; font-weight: 700; }
    .form-header p { color: rgba(255, 255, 255, 0.6); font-size: 0.95rem; }
    
    /* Steps */
    .steps { display: flex; align-items: center; gap: 0; margin-bottom: 32px; }
    .step-item { display: flex; align-items: center; gap: 10px; }
    .step-num {
        width: 36px; height: 36px; border-radius: 50%; display: flex;
        align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 600;
        transition: all 0.3s ease;
    }
    .step-num.active { background: linear-gradient(135deg, #0d9488, #06b6d4); color: white; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.4); }
    .step-num.done { background: rgba(34, 197, 94, 0.3); color: #4ade80; border: 2px solid rgba(34, 197, 94, 0.5); }
    .step-num.pending { background: rgba(255, 255, 255, 0.08); color: rgba(255, 255, 255, 0.4); border: 2px solid rgba(255, 255, 255, 0.12); }
    .step-line { flex: 1; height: 2px; background: rgba(255, 255, 255, 0.12); margin: 0 16px; border-radius: 1px; }
    .step-line.done { background: rgba(34, 197, 94, 0.5); }
    .step-label { font-size: 0.8rem; color: rgba(255, 255, 255, 0.6); white-space: nowrap; }
    
    /* Alerts */
    .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; padding: 14px 18px; border-radius: 14px; margin-bottom: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; }
    .alert-info { background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); color: #93c5fd; padding: 14px 18px; border-radius: 14px; margin-bottom: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; }
    .alert-warning { background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); color: #fcd34d; padding: 14px 18px; border-radius: 14px; margin-bottom: 20px; font-size: 0.85rem; display: flex; align-items: center; gap: 10px; }
    
    /* Form */
    .form-group { margin-bottom: 18px; }
    .form-label { display: block; color: rgba(255, 255, 255, 0.8); font-size: 0.85rem; font-weight: 600; margin-bottom: 8px; letter-spacing: 0.3px; }
    .form-control {
        width: 100%; padding: 14px 18px; background: rgba(255, 255, 255, 0.06);
        border: 1.5px solid rgba(255, 255, 255, 0.12); border-radius: 16px;
        color: white; font-size: 0.95rem; font-family: inherit;
        transition: all 0.3s ease; outline: none;
    }
    .form-control:focus { border-color: #0d9488; background: rgba(255, 255, 255, 0.1); box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.15); }
    .form-control::placeholder { color: rgba(255, 255, 255, 0.3); }
    select.form-control {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='white' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 16px center; padding-right: 40px;
    }
    textarea.form-control { resize: vertical; min-height: 80px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    
    .input-group { position: relative; display: flex; align-items: center; }
    .input-group .form-control { padding-right: 50px; }
    .input-toggle {
        position: absolute; right: 6px; background: transparent; border: none;
        color: rgba(255, 255, 255, 0.5); padding: 10px; cursor: pointer;
        font-size: 1.1rem; transition: color 0.3s;
    }
    .input-toggle:hover { color: white; }
    
    .strength-bar-container { height: 4px; background: rgba(255, 255, 255, 0.1); border-radius: 4px; overflow: hidden; margin-bottom: 6px; }
    .strength-bar { height: 100%; width: 0; border-radius: 4px; transition: width 0.3s, background 0.3s; }
    .strength-label { font-size: 0.75rem; color: rgba(255, 255, 255, 0.5); }
    
    /* Role Tabs */
    .role-tabs { display: flex; gap: 10px; flex-wrap: wrap; }
    .role-tab {
        flex: 1; min-width: 90px; padding: 14px 12px;
        background: rgba(255, 255, 255, 0.05); border: 1.5px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px; text-align: center; cursor: pointer;
        transition: all 0.3s ease; display: flex; flex-direction: column;
        align-items: center; gap: 8px; color: rgba(255, 255, 255, 0.7);
        font-size: 0.8rem; font-weight: 500;
    }
    .role-tab:hover { background: rgba(255, 255, 255, 0.1); border-color: rgba(255, 255, 255, 0.2); }
    .role-tab.active { background: rgba(13, 148, 136, 0.2); border-color: #0d9488; color: white; box-shadow: 0 0 20px rgba(13, 148, 136, 0.2); }
    .role-tab .tab-icon { font-size: 1.4rem; }
    
    /* Buttons */
    .btn-primary {
        width: 100%; padding: 16px; background: linear-gradient(135deg, #0d9488, #06b6d4);
        border: none; border-radius: 16px; color: white; font-size: 1rem;
        font-weight: 700; cursor: pointer; transition: all 0.4s ease;
        box-shadow: 0 8px 24px rgba(13, 148, 136, 0.3); letter-spacing: 0.5px;
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 16px 32px rgba(13, 148, 136, 0.5); }
    
    .btn-secondary {
        width: 100%; padding: 16px; background: rgba(255, 255, 255, 0.06);
        border: 1.5px solid rgba(255, 255, 255, 0.12); border-radius: 16px;
        color: white; font-size: 1rem; font-weight: 600; cursor: pointer;
        transition: all 0.3s ease; text-decoration: none;
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-secondary:hover { background: rgba(255, 255, 255, 0.12); border-color: rgba(255, 255, 255, 0.25); transform: translateY(-2px); }
    
    .auth-links { text-align: center; margin-top: 24px; }
    .auth-links a { color: rgba(255, 255, 255, 0.5); text-decoration: none; font-size: 0.9rem; transition: color 0.3s; }
    .auth-links a:hover { color: #0d9488; }
    
    .success-icon {
        width: 100px; height: 100px;
        background: linear-gradient(135deg, rgba(13, 148, 136, 0.3), rgba(6, 182, 212, 0.3));
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        margin: 0 auto 24px; font-size: 3rem; color: #fcd34d;
    }
    
    @media (max-width: 768px) {
        .auth-container { grid-template-columns: 1fr; max-width: 500px; }
        .auth-brand { padding: 36px 32px; }
        .auth-form-panel { padding: 36px 32px; }
        .brand-title { font-size: 2rem; }
        .form-row { grid-template-columns: 1fr; }
        .role-tabs { gap: 6px; }
        .role-tab { min-width: 70px; padding: 10px 8px; font-size: 0.7rem; }
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
        <div class="brand-logo"><i class="fas fa-building-shield"></i></div>
        <h1 class="brand-title">Staff<br><span>Registration</span></h1>
        <p class="brand-desc">
            Register for access to the MediCare Hospital Management System. Your account will be reviewed and activated by an administrator before you can sign in.
        </p>
        <ul class="brand-features">
            <li><i class="fas fa-check-circle"></i> Step 1: Create your login account and select your role</li>
            <li><i class="fas fa-check-circle"></i> Step 2: Fill in your role-specific professional details</li>
            <li><i class="fas fa-check-circle"></i> An administrator will review and activate your account</li>
            <li><i class="fas fa-check-circle"></i> Each role sees only the modules relevant to them</li>
            <li><i class="fas fa-check-circle"></i> Contact IT support if your account is not activated within 24 hours</li>
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
                        Welcome, <?php echo htmlspecialchars($savedName ?? ''); ?>!<br>
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
                <?php
                    $currentRole = $_SESSION['reg_role'] ?? '';
                    $currentName = $_SESSION['reg_full_name'] ?? '';
                    $roleInfo    = $staffRoles[$currentRole] ?? ['icon'=>'fas fa-user','label'=>ucfirst($currentRole)];
                ?>
                <!-- Step 2: Role-Specific Profile -->
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
                    <h2><i class="<?php echo $roleInfo['icon']; ?>"></i> <?php echo htmlspecialchars($roleInfo['label']); ?> Profile</h2>
                    <p>Hi <strong style="color:#0d9488;"><?php echo htmlspecialchars($currentName); ?></strong>! Now fill in your professional details.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="step" value="2">

                    <?php if ($currentRole === 'doctor'): ?>
                        <div class="form-group">
                            <label class="form-label">Specialization <span style="color:#ef4444">*</span></label>
                            <input type="text" name="specialization" class="form-control"
                                placeholder="e.g. Cardiology, General Medicine, Pediatrics"
                                value="<?php echo htmlspecialchars($_POST['specialization'] ?? ''); ?>" required>
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
                                    placeholder="e.g. 2500" min="0" step="0.01"
                                    value="<?php echo htmlspecialchars($_POST['consultation_fee'] ?? '0'); ?>">
                            </div>
                        </div>

                    <?php elseif ($currentRole === 'pharmacist'): ?>
                        <div class="form-group">
                            <label class="form-label">Pharmacy License Number <span style="color:#ef4444">*</span></label>
                            <input type="text" name="pharmacy_license" class="form-control"
                                placeholder="e.g. SPMC-2024-001"
                                value="<?php echo htmlspecialchars($_POST['pharmacy_license'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Preferred Shift</label>
                            <select name="shift" class="form-control">
                                <option value="">-- Select shift --</option>
                                <option value="morning" <?php echo (($_POST['shift'] ?? '') === 'morning' ? 'selected' : ''); ?>>Morning (6 AM – 2 PM)</option>
                                <option value="afternoon" <?php echo (($_POST['shift'] ?? '') === 'afternoon' ? 'selected' : ''); ?>>Afternoon (2 PM – 10 PM)</option>
                                <option value="night" <?php echo (($_POST['shift'] ?? '') === 'night' ? 'selected' : ''); ?>>Night (10 PM – 6 AM)</option>
                            </select>
                        </div>

                    <?php elseif ($currentRole === 'reception'): ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Preferred Shift</label>
                                <select name="shift" class="form-control">
                                    <option value="">-- Select shift --</option>
                                    <option value="morning" <?php echo (($_POST['shift'] ?? '') === 'morning' ? 'selected' : ''); ?>>Morning (6 AM – 2 PM)</option>
                                    <option value="afternoon" <?php echo (($_POST['shift'] ?? '') === 'afternoon' ? 'selected' : ''); ?>>Afternoon (2 PM – 10 PM)</option>
                                    <option value="night" <?php echo (($_POST['shift'] ?? '') === 'night' ? 'selected' : ''); ?>>Night (10 PM – 6 AM)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Desk Extension</label>
                                <input type="text" name="extension" class="form-control"
                                    placeholder="e.g. 101"
                                    value="<?php echo htmlspecialchars($_POST['extension'] ?? ''); ?>">
                            </div>
                        </div>

                    <?php elseif ($currentRole === 'dispatcher'): ?>
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
                                    <option value="morning" <?php echo (($_POST['shift'] ?? '') === 'morning' ? 'selected' : ''); ?>>Morning (6 AM – 2 PM)</option>
                                    <option value="afternoon" <?php echo (($_POST['shift'] ?? '') === 'afternoon' ? 'selected' : ''); ?>>Afternoon (2 PM – 10 PM)</option>
                                    <option value="night" <?php echo (($_POST['shift'] ?? '') === 'night' ? 'selected' : ''); ?>>Night (10 PM – 6 AM)</option>
                                </select>
                            </div>
                        </div>

                    <?php elseif ($currentRole === 'driver'): ?>
                        <div class="form-group">
                            <label class="form-label">Driver's License Number <span style="color:#ef4444">*</span></label>
                            <input type="text" name="driver_license" class="form-control"
                                placeholder="e.g. B1234567"
                                value="<?php echo htmlspecialchars($_POST['driver_license'] ?? ''); ?>" required>
                        </div>
                        <div class="form-row">
                                                        <div class="form-group">
                                <label class="form-label">Assigned Vehicle Number</label>
                                <input type="text" name="vehicle_number" class="form-control"
                                    placeholder="e.g. AMB-001"
                                    value="<?php echo htmlspecialchars($_POST['vehicle_number'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Mobile Phone <span style="color:#ef4444">*</span></label>
                                <input type="tel" name="driver_phone" class="form-control"
                                    placeholder="e.g. 077 123 4567"
                                    value="<?php echo htmlspecialchars($_POST['driver_phone'] ?? ''); ?>" required>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Your account will remain <strong>inactive</strong> until an administrator reviews and approves your registration.
                    </div>

                    <button type="submit" class="btn-primary">
                        Submit Registration <i class="fas fa-paper-plane"></i>
                    </button>
                </form>

            <?php else: ?>
                <!-- Step 1: Account + Role -->
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
                    <h2>Staff Account Registration</h2>
                    <p>For hospital employees and clinical staff only</p>
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

                <form method="POST" id="staffForm" novalidate>
                    <input type="hidden" name="step" value="1">

                    <!-- Role Selector -->
                    <div style="margin-bottom:22px">
                        <label class="form-label">Your Role <span style="color:#ef4444">*</span></label>
                        <div class="role-tabs">
                            <?php foreach ($staffRoles as $key => $r): ?>
                            <label class="role-tab <?php echo (($_POST['role'] ?? '') === $key ? 'active' : ''); ?>" id="tab-<?php echo $key; ?>">
                                <span class="tab-icon"><i class="<?php echo $r['icon']; ?>"></i></span>
                                <?php echo htmlspecialchars($r['label']); ?>
                                <input type="radio" name="role" value="<?php echo $key; ?>"
                                    style="display:none"
                                    <?php echo (($_POST['role'] ?? '') === $key ? 'checked' : ''); ?>
                                    required>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div id="roleDesc" style="font-size:12px;color:rgba(255,255,255,0.4);margin-top:6px"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Full Name <span style="color:#ef4444">*</span></label>
                        <input type="text" name="full_name" class="form-control"
                            placeholder="As in your employment record"
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
                            <label class="form-label">Employee / Staff ID <span style="color:#ef4444">*</span></label>
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

                    <!-- Password Strength Bar -->
                    <div style="margin-bottom:18px">
                        <div class="strength-bar-container">
                            <div id="strengthBar" class="strength-bar"></div>
                        </div>
                        <div id="strengthLabel" class="strength-label"></div>
                    </div>

                    <div class="alert-warning">
                        <i class="fas fa-shield-alt"></i> Staff accounts require administrator approval before login. Accounts are not immediately active.
                    </div>

                    <button type="submit" class="btn-primary">
                        Continue to Profile <i class="fas fa-arrow-right"></i>
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

// Set initial role description
const initialChecked = document.querySelector('input[name="role"]:checked');
if (initialChecked) {
    const idx = roleKeys.indexOf(initialChecked.value);
    document.getElementById('roleDesc').textContent =
        idx >= 0 ? (Object.values(roleData)[idx] || '') : '';
}

// Toggle password visibility
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
        {pct:'25%',  col:'#ef4444',     txt:'Weak — Add uppercase, numbers & symbols'},
        {pct:'50%',  col:'#f59e0b',     txt:'Fair — Keep going'},
        {pct:'75%',  col:'#3b82f6',     txt:'Good — Almost there'},
        {pct:'100%', col:'#10b981',     txt:'Strong — Excellent!'},
    ];
    const lvl = levels[score] || levels[0];
    const bar = document.getElementById('strengthBar');
    const lbl = document.getElementById('strengthLabel');
    bar.style.width      = lvl.pct;
    bar.style.background = lvl.col;
    lbl.textContent      = lvl.txt;
    lbl.style.color      = lvl.col;
});

// Validate password match on submit
document.getElementById('staffForm')?.addEventListener('submit', function (e) {
    const p1 = document.getElementById('pwd')?.value;
    const p2 = document.getElementById('cpwd')?.value;
    if (p1 && p2 && p1 !== p2) {
        e.preventDefault();
        alert('Passwords do not match. Please check and try again.');
    }
});
</script>