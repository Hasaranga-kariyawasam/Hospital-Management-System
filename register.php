<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/config/db_config.php';

$pageTitle = 'Register';
$useSidebar = false;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = trim($_POST['role'] ?? '');

    $allowedRoles = ['admin', 'doctor', 'reception', 'pharmacist', 'patient', 'dispatcher', 'driver'];

    if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '' || $role === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (!in_array($role, $allowedRoles, true)) {
        $error = 'Invalid role selected.';
    } else {
        $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);

        if ($checkStmt->fetch()) {
            $error = 'Email already exists.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $insertStmt = $pdo->prepare("
                INSERT INTO users (full_name, email, password_hash, role)
                VALUES (?, ?, ?, ?)
            ");
            $insertStmt->execute([$fullName, $email, $passwordHash, $role]);

            $message = 'Registration successful. Please login.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="form-card">
        <h2 class="form-title">Register</h2>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control" required>
                    <option value="">Select Role</option>
                    <option value="admin">Admin</option>
                    <option value="doctor">Doctor</option>
                    <option value="reception">Reception</option>
                    <option value="pharmacist">Pharmacist</option>
                    <option value="patient">Patient</option>
                    <option value="dispatcher">Dispatcher</option>
                    <option value="driver">Driver</option>
                </select>
            </div>

            <button type="submit" class="btn">Register</button>
        </form>

        <div class="auth-links">
            <a href="/hospital-system/login.php">Already have an account? Login</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>