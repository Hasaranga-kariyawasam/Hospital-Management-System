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

$message = '';
$errors  = [];

$staffRoles = [
    'doctor'     => ['icon' => '🩺', 'label' => 'Doctor',      'desc' => 'Specialists and general physicians'],
    'reception'  => ['icon' => '🏥', 'label' => 'Receptionist','desc' => 'Front desk and admissions staff'],
    'pharmacist' => ['icon' => '💊', 'label' => 'Pharmacist',  'desc' => 'Pharmacy dispensing staff'],
    'dispatcher' => ['icon' => '🗺️', 'label' => 'Dispatcher',  'desc' => 'Emergency ambulance dispatch'],
    'driver'     => ['icon' => '🚑', 'label' => 'Driver',      'desc' => 'Ambulance drivers'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName  = trim($_POST['full_name']       ?? '');
    $email     = trim($_POST['email']           ?? '');
    $password  = $_POST['password']             ?? '';
    $confirm   = $_POST['confirm_password']     ?? '';
    $role      = trim($_POST['role']            ?? '');
    $staffId   = trim($_POST['staff_id']        ?? '');
    $dept      = trim($_POST['department']      ?? '');

    // Validation
    if ($fullName === '')  $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (!array_key_exists($role, $staffRoles)) $errors[] = 'Please select a valid staff role.';
    if ($staffId === '') $errors[] = 'Employee/Staff ID is required.';

    if (empty($errors)) {
        // Check email duplicate
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $errors[] = 'This email is already registered in the system.';
        } else {
            // Staff accounts start as inactive — admin must activate
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = $pdo->prepare("
                INSERT INTO users (full_name, email, password_hash, role, status, staff_id, department)
                VALUES (?, ?, ?, ?, 'inactive', ?, ?)
            ");
            $ins->execute([$fullName, $email, $hash, $role, $staffId, $dept]);
            $message = 'success';
        }
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
                <li>Submit your details and employee ID</li>
                <li>An administrator will review and activate your account</li>
                <li>You'll receive full access based on your role</li>
                <li>Each role sees only the modules relevant to them</li>
                <li>Contact IT support if your account is not activated within 24 hours</li>
            </ul>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="auth-panel-right">
        <div class="auth-form-box" style="max-width:520px">

            <?php if ($message === 'success'): ?>
                <!-- Success State -->
                <div style="text-align:center;padding:20px 0">
                    <div style="font-size:4rem;margin-bottom:16px">⏳</div>
                    <h2 style="margin-bottom:10px">Registration Submitted</h2>
                    <p class="auth-subhead" style="margin-bottom:18px">
                        Your staff account request has been submitted. An administrator will review and activate your account shortly.
                    </p>
                    <div class="alert alert-info">
                        ℹ️ You will not be able to log in until your account is activated. Please contact the IT department or hospital administrator if you need urgent access.
                    </div>
                    <a href="/Web/Hospital-Management-System/login.php" class="btn btn-primary btn-full" style="margin-top:12px">
                        Go to Login Page
                    </a>
                </div>

            <?php else: ?>
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
                                placeholder="e.g. Cardiology"
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
                        Submit Registration
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
// Role tab selection
const roleDescs = <?php echo json_encode(array_map(fn($r) => $r['desc'], $staffRoles)); ?>;
const roleKeys  = <?php echo json_encode(array_keys($staffRoles)); ?>;

document.querySelectorAll('.role-tab').forEach(tab => {
    tab.addEventListener('click', function () {
        document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        const radio = this.querySelector('input[type=radio]');
        if (radio) radio.checked = true;
        const idx = roleKeys.indexOf(radio?.value);
        document.getElementById('roleDesc').textContent =
            idx >= 0 ? (Object.values(roleDescs)[idx] || '') : '';
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
    if (v.length >= 8) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const levels = [
        {pct:'0%',col:'transparent',txt:''},
        {pct:'25%',col:'#dc2626',txt:'Weak'},
        {pct:'50%',col:'#d97706',txt:'Fair'},
        {pct:'75%',col:'#2563eb',txt:'Good'},
        {pct:'100%',col:'#059669',txt:'Strong'},
    ];
    const bar = document.getElementById('strengthBar');
    const lbl = document.getElementById('strengthLabel');
    const lvl = levels[score] || levels[0];
    bar.style.width = lvl.pct;
    bar.style.background = lvl.col;
    lbl.textContent = lvl.txt;
    lbl.style.color = lvl.col;
});

// Match check
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
