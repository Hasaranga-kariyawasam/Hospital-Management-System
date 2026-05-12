<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/config/db_config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /Web/Hospital-Management-System/index.php');
    exit();
}

$pageTitle  = 'Login';
$useSidebar = false;
$isPublic   = true;

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
                'doctor'     => "$base/modules/appointments/doctor_potal.php",
                'reception'  => "$base/modules/appointments/opd_walkin.php",
                'pharmacist' => "$base/modules/pharmacy/pharmacy_queue.php",
                'patient'    => "$base/modules/appointments/my_appointments.php",
                'dispatcher' => "$base/modules/emergncy/dispatcher_dashboard.php",
                'driver'     => "$base/modules/emergency/driver_job.php",
            ];
            header('Location: ' . ($redirectMap[$user['role']] ?? "$base/index.php"));
            exit();
        }
    }
}

// Modern Glass CSS
$modernCSS = <<<CSS
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
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
    
    /* Floating orbs */
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
    
    /* Main container */
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
    
    /* Left brand panel */
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
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
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
        font-size: 2.8rem;
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
        padding: 10px 0;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .brand-features li i {
        color: #0d9488;
        font-size: 1.1rem;
    }
    
    /* Right form panel */
    .auth-form-panel {
        padding: 60px 48px;
        display: flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.03);
    }
    
    .form-inner {
        width: 100%;
        max-width: 420px;
        margin: 0 auto;
    }
    
    .form-header {
        margin-bottom: 36px;
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
    
    /* Alert */
    .alert-error {
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #fca5a5;
        padding: 14px 18px;
        border-radius: 14px;
        margin-bottom: 24px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 10px;
        backdrop-filter: blur(10px);
    }
    
    /* Form groups */
    .form-group {
        margin-bottom: 20px;
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
    
    .form-error {
        color: #fca5a5;
        font-size: 0.8rem;
        margin-top: 6px;
    }
    
    /* Buttons */
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
    }
    
    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 16px 32px rgba(13, 148, 136, 0.5);
    }
    
    .btn-secondary {
        padding: 14px;
        background: rgba(255, 255, 255, 0.06);
        border: 1.5px solid rgba(255, 255, 255, 0.12);
        border-radius: 16px;
        color: white;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.12);
        border-color: rgba(255, 255, 255, 0.25);
        transform: translateY(-2px);
    }
    
    .auth-divider {
        display: flex;
        align-items: center;
        gap: 16px;
        color: rgba(255, 255, 255, 0.4);
        font-size: 0.85rem;
        margin: 28px 0 16px;
    }
    
    .auth-divider::before,
    .auth-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: rgba(255, 255, 255, 0.15);
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
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .auth-links a:hover {
        color: #0d9488;
    }
    
    /* Responsive */
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
    }
</style>
CSS;

echo $modernCSS;
?>

<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>
<div class="bg-orb bg-orb-3"></div>

<div class="auth-container">
    <!-- Left Brand Panel -->
    <div class="auth-brand">
        <div class="brand-logo">M</div>
        <h1 class="brand-title">MediCare<br><span>Hospital System</span></h1>
        <p class="brand-desc">
            The centralised management system for all hospital operations — appointments, admissions, pharmacy, billing, and more.
        </p>
        <ul class="brand-features">
            <li><i class="fas fa-calendar-check"></i> Unified appointment booking</li>
            <li><i class="fas fa-bed"></i> Real-time ward management</li>
            <li><i class="fas fa-file-invoice"></i> Automated billing system</li>
            <li><i class="fas fa-prescription-bottle"></i> Prescription management</li>
            <li><i class="fas fa-truck-medical"></i> Emergency dispatch system</li>
        </ul>
    </div>

    <!-- Right Form Panel -->
    <div class="auth-form-panel">
        <div class="form-inner">
            <div class="form-header">
                <h2>Welcome Back</h2>
                <p>Sign in to your account to continue</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
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
                        <button type="button" class="input-toggle" id="togglePwd" aria-label="Show password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="form-error" id="passErr" style="display:none"></div>
                </div>

                <button type="submit" class="btn-primary" style="margin-top:8px">
                    <i class="fas fa-sign-in-alt"></i> &nbsp; Sign In
                </button>
            </form>

            <div class="auth-divider">or register as</div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <a href="/Web/Hospital-Management-System/register_patient.php" class="btn-secondary">
                    <i class="fas fa-user"></i> Patient
                </a>
                <a href="/Web/Hospital-Management-System/register_staff.php" class="btn-secondary">
                    <i class="fas fa-hospital-user"></i> Staff
                </a>
            </div>

            <div class="auth-links">
                <a href="/Web/Hospital-Management-System/home.php">
                    <i class="fas fa-arrow-left"></i> Back to Hospital Website
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePwd').addEventListener('click', function() {
    const p = document.getElementById('password');
    const icon = this.querySelector('i');
    const isText = p.type === 'text';
    p.type = isText ? 'password' : 'text';
    icon.className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
});

document.getElementById('loginForm').addEventListener('submit', function(e) {
    let valid = true;
    const emailVal = document.getElementById('email').value.trim();
    const emailErr = document.getElementById('emailErr');
    if (!emailVal || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
        emailErr.textContent = 'Please enter a valid email address.';
        emailErr.style.display = 'block';
        valid = false;
    } else {
        emailErr.style.display = 'none';
    }
    const passVal = document.getElementById('password').value;
    const passErr = document.getElementById('passErr');
    if (!passVal) {
        passErr.textContent = 'Password is required.';
        passErr.style.display = 'block';
        valid = false;
    } else {
        passErr.style.display = 'none';
    }
    if (!valid) e.preventDefault();
});
</script>