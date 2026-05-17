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
                'doctor'     => "$base/modules/appointments/doctor_appointments.php",
                'reception'  => "$base/modules/appointments/opd_walkin.php",
                'pharmacist' => "$base/modules/pharmacy/pharmacist_portal.php",
                'patient'    => "$base/modules/appointments/my_appointments.php",
                'dispatcher' => "$base/modules/emergency/dispatcher.php",
                'driver'     => "$base/modules/emergency/driver_portal.php",
            ];
            header('Location: ' . ($redirectMap[$user['role']] ?? "$base/index.php"));
            exit();
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
    /* ── Hospital Matching Login Styles ── */
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
    
    /* Left Panel - Brand */
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
        top: -100px;
        right: -100px;
        width: 300px;
        height: 300px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.05);
    }
    
    .auth-brand-panel::after {
        content: '';
        position: absolute;
        bottom: -80px;
        left: -60px;
        width: 250px;
        height: 250px;
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
        width: 64px;
        height: 64px;
        background: white;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        font-weight: 700;
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
        font-size: 2.2rem;
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
    
    /* Right Panel - Form */
    .auth-form-panel {
        padding: 60px 56px;
        display: flex;
        align-items: center;
        background: white;
    }
    
    .form-container {
        width: 100%;
        max-width: 400px;
        margin: 0 auto;
    }
    
    .form-header {
        margin-bottom: 32px;
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
    
    /* Alert */
    .alert {
        padding: 14px 18px;
        border-radius: 12px;
        margin-bottom: 24px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .alert-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #dc2626;
    }
    
    /* Form Elements */
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        color: #374151;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .form-label .required {
        color: #dc2626;
    }
    
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
    
    .form-input::placeholder {
        color: #94a3b8;
    }
    
    .form-input.error {
        border-color: #dc2626;
        background: #fef2f2;
    }
    
    .input-group {
        position: relative;
        display: flex;
        align-items: center;
    }
    
    .input-group .form-input {
        padding-right: 50px;
    }
    
    .input-toggle-btn {
        position: absolute;
        right: 6px;
        background: transparent;
        border: none;
        color: #94a3b8;
        padding: 10px;
        cursor: pointer;
        font-size: 1.1rem;
        transition: color 0.2s;
    }
    
    .input-toggle-btn:hover {
        color: #1e293b;
    }
    
    .form-error-msg {
        color: #dc2626;
        font-size: 0.78rem;
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
    }
    
    .btn-primary:hover {
        background: #1e40af;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(26, 86, 219, 0.35);
    }
    
    .btn-outline {
        background: white;
        border: 1.5px solid #e2e8f0;
        color: #374151;
        flex: 1;
    }
    
    .btn-outline:hover {
        border-color: #1a56db;
        color: #1a56db;
        background: #f8fafc;
    }
    
    .divider-text {
        display: flex;
        align-items: center;
        gap: 16px;
        color: #94a3b8;
        font-size: 0.85rem;
        margin: 28px 0 16px;
    }
    
    .divider-text::before,
    .divider-text::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #e2e8f0;
    }
    
    .btn-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    
    .back-link {
        text-align: center;
        margin-top: 24px;
    }
    
    .back-link a {
        color: #64748b;
        text-decoration: none;
        font-size: 0.9rem;
        transition: color 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .back-link a:hover {
        color: #1a56db;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .auth-wrapper {
            grid-template-columns: 1fr;
            max-width: 480px;
        }
        
        .auth-brand-panel {
            padding: 40px 32px;
        }
        
        .auth-form-panel {
            padding: 40px 32px;
        }
        
        .brand-title {
            font-size: 1.8rem;
        }
    }
</style>

<div class="auth-wrapper">
    <!-- Left Brand Panel -->
    <div class="auth-brand-panel">
        <div class="brand-logo-section">
            <div class="brand-logo-circle">M</div>
            <div class="brand-name-block">
                <h3>MediCare</h3>
                <p>General Hospital</p>
            </div>
        </div>
        
        <h1 class="brand-title">Hospital Management System</h1>
        
        <p class="brand-desc">
            The centralised platform for all hospital operations — appointments, admissions, pharmacy, billing, and more.
        </p>
        
        <ul class="brand-feature-list">
            <li><i class="fas fa-calendar-check"></i> Unified appointment booking</li>
            <li><i class="fas fa-bed"></i> Real-time ward & bed management</li>
            <li><i class="fas fa-file-invoice"></i> Automated billing system</li>
            <li><i class="fas fa-prescription-bottle"></i> Prescription management</li>
            <li><i class="fas fa-truck-medical"></i> Emergency dispatch system</li>
        </ul>
    </div>

    <!-- Right Form Panel -->
    <div class="auth-form-panel">
        <div class="form-container">
            <div class="form-header">
                <h2>Welcome Back</h2>
                <p>Sign in to your account to continue</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" novalidate>
                <div class="form-group">
                    <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input"
                        placeholder="your@email.com"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        autocomplete="email"
                        required
                    >
                    <div class="form-error-msg" id="emailErr" style="display:none"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password <span class="required">*</span></label>
                    <div class="input-group">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="input-toggle-btn" id="togglePwd" aria-label="Show password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="form-error-msg" id="passErr" style="display:none"></div>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top:8px">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div class="divider-text">or register as</div>

            <div class="btn-grid">
                <a href="/Web/Hospital-Management-System/register_patient.php" class="btn btn-outline">
                    <i class="fas fa-user"></i> Patient
                </a>
                <a href="/Web/Hospital-Management-System/register_staff.php" class="btn btn-outline">
                    <i class="fas fa-hospital-user"></i> Staff
                </a>
            </div>

            <div class="back-link">
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
    const emailInput = document.getElementById('email');
    
    if (!emailVal || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
        emailErr.textContent = 'Please enter a valid email address.';
        emailErr.style.display = 'block';
        emailInput.classList.add('error');
        valid = false;
    } else {
        emailErr.style.display = 'none';
        emailInput.classList.remove('error');
    }

    const passVal = document.getElementById('password').value;
    const passErr = document.getElementById('passErr');
    const passInput = document.getElementById('password');
    
    if (!passVal) {
        passErr.textContent = 'Password is required.';
        passErr.style.display = 'block';
        passInput.classList.add('error');
        valid = false;
    } else {
        passErr.style.display = 'none';
        passInput.classList.remove('error');
    }

    if (!valid) e.preventDefault();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>