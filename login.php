<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/config/db_config.php';

// Already logged in — redirect
if (isset($_SESSION['user_id'])) {
    header('Location: /Web/Hospital-Management-System/index.php');
    exit();
}

$pageTitle  = 'Login';
$useSidebar = false;
$isPublic   = true;     // suppress app topbar; we render our own header below

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("
            SELECT user_id, full_name, email, password_hash, role, status
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account has been deactivated. Contact the administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['role']      = $user['role'];

            $base = '/Web/Hospital-Management-System';
            $redirectMap = [
                'admin'      => "$base/modules/admin/dashboard.php",

                'doctor'     => "$base/modules/appointments/doctor.php",

                'reception'  => "$base/modules/appointments/opd_walkin.php",
                'pharmacist' => "$base/modules/pharmacy/pharmacy_queue.php",
                'patient'    => "$base/modules/appointments/my_appointments.php",
                'dispatcher' => "$base/modules/emergency/dispatcher_dashboard.php",
                'driver'     => "$base/modules/emergency/driver_job.php",
            ];
            header('Location: ' . ($redirectMap[$user['role']] ?? "$base/index.php"));
            exit();
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="auth-split">
    <!-- Left Panel -->
    <div class="auth-panel-left">
        <div class="auth-panel-left-content">
            <div class="auth-left-logo">M</div>
            <h2 class="auth-left-title">MediCare<br>Hospital System</h2>
            <p class="auth-left-sub">
                The centralised management system for all hospital operations — appointments, admissions, pharmacy, billing, and more.
            </p>
            <ul class="auth-features">
                <li>Unified appointment booking for patients and walk-ins</li>
                <li>Real-time ward and bed management</li>
                <li>Automated billing across all modules</li>
                <li>Prescription and dispensing management</li>
                <li>Emergency ambulance dispatch system</li>
            </ul>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="auth-panel-right">
        <div class="auth-form-box">
            <h2>Welcome back</h2>
            <p class="auth-subhead">Sign in to your account to continue</p>

            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" novalidate>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="your@email.com"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        autocomplete="email"
                        required
                    >
                    <div class="form-error" id="emailErr" style="display:none"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="input-toggle" id="togglePwd" aria-label="Show password">👁</button>
                    </div>
                    <div class="form-error" id="passErr" style="display:none"></div>
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:8px">
                    Sign In
                </button>
            </form>

            <div class="auth-divider" style="margin-top:24px">or register as</div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:4px">
                <a href="/Web/Hospital-Management-System/register_patient.php" class="btn btn-secondary" style="justify-content:center">
                    👤 Patient
                </a>
                <a href="/Web/Hospital-Management-System/register_staff.php" class="btn btn-secondary" style="justify-content:center">
                    🏥 Staff
                </a>
            </div>

            <div class="auth-links" style="margin-top:22px">
                <a href="/Web/Hospital-Management-System/home.php">← Back to Hospital Website</a>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
document.getElementById('togglePwd').addEventListener('click', function() {
    const p = document.getElementById('password');
    const isText = p.type === 'text';
    p.type = isText ? 'password' : 'text';
    this.textContent = isText ? '👁' : '🙈';
});

// Client-side validation
document.getElementById('loginForm').addEventListener('submit', function(e) {
    let valid = true;

    const emailVal = document.getElementById('email').value.trim();
    const emailErr = document.getElementById('emailErr');
    if (!emailVal || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
        emailErr.textContent = 'Please enter a valid email address.';
        emailErr.style.display = 'block';
        document.getElementById('email').classList.add('error');
        valid = false;
    } else {
        emailErr.style.display = 'none';
        document.getElementById('email').classList.remove('error');
    }

    const passVal = document.getElementById('password').value;
    const passErr = document.getElementById('passErr');
    if (!passVal) {
        passErr.textContent = 'Password is required.';
        passErr.style.display = 'block';
        document.getElementById('password').classList.add('error');
        valid = false;
    } else {
        passErr.style.display = 'none';
        document.getElementById('password').classList.remove('error');
    }

    if (!valid) e.preventDefault();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
