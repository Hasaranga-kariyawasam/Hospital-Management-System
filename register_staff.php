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
        max-width: 460px;
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
    
    /* Steps */
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
    
    /* Alerts */
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
    
    /* Form */
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
    
    select.form-input {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 16px center;
        padding-right: 40px;
    }
    
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
    
    /* Password Strength */
    .strength-meter { height: 4px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin-bottom: 6px; }
    .strength-fill { height: 100%; width: 0; border-radius: 4px; transition: width 0.3s, background 0.3s; }
    .strength-text { font-size: 0.75rem; color: #64748b; }
    
    /* Role Tabs */
    .role-tabs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .role-tab {
        flex: 1;
        min-width: 85px;
        padding: 12px 10px;
        background: #f8fafc;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        color: #64748b;
        font-size: 0.78rem;
        font-weight: 500;
    }
    
    .role-tab:hover {
        border-color: #93c5fd;
        background: #eff6ff;
    }
    
    .role-tab.active {
        background: #eff6ff;
        border-color: #1a56db;
        color: #1a56db;
        box-shadow: 0 2px 8px rgba(26, 86, 219, 0.1);
    }
    
    .role-tab .tab-icon {
        font-size: 1.3rem;
    }
    
    .role-desc {
        font-size: 0.75rem;
        color: #94a3b8;
        margin-top: 6px;
    }
    
    /* Buttons */
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
        .role-tabs { gap: 6px; }
        .role-tab { min-width: 70px; padding: 10px 8px; font-size: 0.7rem; }
    }
</style>

<div class="auth-wrapper">
    <!-- Left Brand Panel -->
    <div class="auth-brand-panel">
        <div class="brand-logo-section">
            <div class="brand-logo-circle"><i class="fas fa-building-shield"></i></div>
            <div class="brand-name-block">
                <h3>MediCare</h3>
                <p>General Hospital</p>
            </div>
        </div>
        
        <h1 class="brand-title">Staff Registration</h1>
        
        <p class="brand-desc">
            Register for access to the MediCare Hospital Management System. Your account will be reviewed and activated by an administrator before you can sign in.
        </p>
        
        <ul class="brand-feature-list">
            <li><i class="fas fa-check-circle"></i> Step 1: Create your login account and select your role</li>
            <li><i class="fas fa-check-circle"></i> Step 2: Fill in your role-specific professional details</li>
            <li><i class="fas fa-check-circle"></i> An administrator will review and activate your account</li>
            <li><i class="fas fa-check-circle"></i> Each role sees only the modules relevant to them</li>
            <li><i class="fas fa-check-circle"></i> Contact IT support if your account is not activated within 24 hours</li>
        </ul>
    </div>

    <!-- Right Form Panel -->
    <div class="auth-form-panel">
        <div class="form-container">

            <?php if ($message === 'success'): ?>
                <!-- Success -->
                <div style="text-align:center;">
                    <div class="success-icon-circle">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <h2 style="font-family:'Playfair Display',serif;color:#1e293b;margin-bottom:12px;">Registration Submitted!</h2>
                    <p style="color:#64748b;margin-bottom:20px;line-height:1.6;">
                        Welcome, <?php echo htmlspecialchars($savedName ?? ''); ?>!<br>
                        Your account has been submitted for administrator review.
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Your account is currently <strong>inactive</strong>. An administrator must approve it before you can log in. Contact the hospital IT department if you need urgent access.
                    </div>
                    <a href="/Web/Hospital-Management-System/login.php" class="btn btn-primary" style="margin-top:20px;">
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
                    <h2><i class="<?php echo $roleInfo['icon']; ?>" style="color:#1a56db;"></i> <?php echo htmlspecialchars($roleInfo['label']); ?> Profile</h2>
                    <p>Hi <strong style="color:#1a56db;"><?php echo htmlspecialchars($currentName); ?></strong>! Now fill in your professional details.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="step" value="2">

                    <?php if ($currentRole === 'doctor'): ?>
                        <div class="form-group">
                            <label class="form-label">Specialization <span class="required">*</span></label>
                            <input type="text" name="specialization" class="form-input"
                                placeholder="e.g. Cardiology, General Medicine, Pediatrics"
                                value="<?php echo htmlspecialchars($_POST['specialization'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Qualifications</label>
                            <textarea name="qualifications" class="form-input" rows="3"
                                placeholder="e.g. MBBS (Colombo), MD (Cardiology), MRCP"><?php echo htmlspecialchars($_POST['qualifications'] ?? ''); ?></textarea>
                            <div style="font-size:12px;color:#94a3b8;margin-top:4px;">List your degrees and professional certifications.</div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Medical License No. (SLMC)</label>
                                <input type="text" name="license_number" class="form-input"
                                    placeholder="e.g. SLMC-2024-001"
                                    value="<?php echo htmlspecialchars($_POST['license_number'] ?? ''); ?>">
                                <div style="font-size:12px;color:#94a3b8;margin-top:4px;">Issued by SLMC or relevant authority.</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Consultation Fee (LKR)</label>
                                <input type="number" name="consultation_fee" class="form-input"
                                    placeholder="e.g. 2500" min="0" step="0.01"
                                    value="<?php echo htmlspecialchars($_POST['consultation_fee'] ?? '0'); ?>">
                            </div>
                        </div>

                    <?php elseif ($currentRole === 'pharmacist'): ?>
                        <div class="form-group">
                            <label class="form-label">Pharmacy License Number <span class="required">*</span></label>
                            <input type="text" name="pharmacy_license" class="form-input"
                                placeholder="e.g. SPMC-2024-001"
                                value="<?php echo htmlspecialchars($_POST['pharmacy_license'] ?? ''); ?>" required>
                            <div style="font-size:12px;color:#94a3b8;margin-top:4px;">Issued by the Sri Lanka Pharmacy Council.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Preferred Shift</label>
                            <select name="shift" class="form-input">
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
                                <select name="shift" class="form-input">
                                    <option value="">-- Select shift --</option>
                                    <option value="morning" <?php echo (($_POST['shift'] ?? '') === 'morning' ? 'selected' : ''); ?>>Morning (6 AM – 2 PM)</option>
                                    <option value="afternoon" <?php echo (($_POST['shift'] ?? '') === 'afternoon' ? 'selected' : ''); ?>>Afternoon (2 PM – 10 PM)</option>
                                    <option value="night" <?php echo (($_POST['shift'] ?? '') === 'night' ? 'selected' : ''); ?>>Night (10 PM – 6 AM)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Desk Extension</label>
                                <input type="text" name="extension" class="form-input"
                                    placeholder="e.g. 101"
                                    value="<?php echo htmlspecialchars($_POST['extension'] ?? ''); ?>">
                                <div style="font-size:12px;color:#94a3b8;margin-top:4px;">Internal phone extension number.</div>
                            </div>
                        </div>

                    <?php elseif ($currentRole === 'dispatcher'): ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Coverage Zone</label>
                                <input type="text" name="zone" class="form-input"
                                    placeholder="e.g. Colombo North, Kandy"
                                    value="<?php echo htmlspecialchars($_POST['zone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Preferred Shift</label>
                                <select name="shift" class="form-input">
                                    <option value="">-- Select shift --</option>
                                    <option value="morning" <?php echo (($_POST['shift'] ?? '') === 'morning' ? 'selected' : ''); ?>>Morning (6 AM – 2 PM)</option>
                                    <option value="afternoon" <?php echo (($_POST['shift'] ?? '') === 'afternoon' ? 'selected' : ''); ?>>Afternoon (2 PM – 10 PM)</option>
                                    <option value="night" <?php echo (($_POST['shift'] ?? '') === 'night' ? 'selected' : ''); ?>>Night (10 PM – 6 AM)</option>
                                </select>
                            </div>
                        </div>

                    <?php elseif ($currentRole === 'driver'): ?>
                        <div class="form-group">
                            <label class="form-label">Driver's License Number <span class="required">*</span></label>
                            <input type="text" name="driver_license" class="form-input"
                                placeholder="e.g. B1234567"
                                value="<?php echo htmlspecialchars($_POST['driver_license'] ?? ''); ?>" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Assigned Vehicle Number</label>
                                <input type="text" name="vehicle_number" class="form-input"
                                    placeholder="e.g. AMB-001"
                                    value="<?php echo htmlspecialchars($_POST['vehicle_number'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Mobile Phone <span class="required">*</span></label>
                                <input type="tel" name="driver_phone" class="form-input"
                                    placeholder="e.g. 077 123 4567"
                                    value="<?php echo htmlspecialchars($_POST['driver_phone'] ?? ''); ?>" required>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Your account will remain <strong>inactive</strong> until an administrator reviews and approves your registration.
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Submit Registration <i class="fas fa-paper-plane"></i>
                    </button>
                </form>

            <?php else: ?>
                <!-- Step 1: Account + Role -->
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
                    <h2>Staff Account Registration</h2>
                    <p>For hospital employees and clinical staff only</p>
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

                <form method="POST" id="staffForm" novalidate>
                    <input type="hidden" name="step" value="1">

                    <!-- Role Selector -->
                    <div style="margin-bottom:22px">
                        <label class="form-label">Your Role <span class="required">*</span></label>
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
                        <div id="roleDesc" class="role-desc"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" class="form-input"
                            placeholder="As in your employment record"
                            value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Work Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-input"
                            placeholder="yourname@medicare-hospital.lk"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Employee / Staff ID <span class="required">*</span></label>
                            <input type="text" name="staff_id" class="form-input"
                                placeholder="e.g. EMP-2024-001"
                                value="<?php echo htmlspecialchars($_POST['staff_id'] ?? ''); ?>"
                                required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" class="form-input"
                                placeholder="e.g. Cardiology, Pharmacy"
                                value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>">
                        </div>
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

                    <!-- Strength bar -->
                    <div style="margin-bottom:18px">
                        <div class="strength-meter">
                            <div id="strengthBar" class="strength-fill"></div>
                        </div>
                        <div id="strengthLabel" class="strength-text"></div>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-shield-alt"></i> Staff accounts require administrator approval before login. Accounts are not immediately active.
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Continue to Profile <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <div class="back-link">
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

// Toggle password
document.getElementById('togglePwd')?.addEventListener('click', function () {
    const p = document.getElementById('pwd');
    const icon = this.querySelector('i');
    const isText = p.type === 'text';
    p.type = isText ? 'password' : 'text';
    icon.className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
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
        {pct:'25%',  col:'#dc2626',     txt:'Weak — Add uppercase, numbers & symbols'},
        {pct:'50%',  col:'#d97706',     txt:'Fair — Keep going'},
        {pct:'75%',  col:'#2563eb',     txt:'Good — Almost there'},
        {pct:'100%', col:'#059669',     txt:'Strong — Excellent!'},
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

<?php include __DIR__ . '/includes/footer.php'; ?>