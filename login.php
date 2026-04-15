<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/config/db_config.php';

$pageTitle = 'Login';
$useSidebar = false;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $stmt = $pdo->prepare("
            SELECT user_id, full_name, email, password_hash, role, status
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Invalid email or password.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account is inactive.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } else {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            switch ($user['role']) {
                case 'admin':
                    header('Location: Web/Hospital-Management-System/modules/admin/dashboard.php');
                    break;
                case 'doctor':
                    header('Location: Web/Hospital-Management-System/modules/appointments/doctor_schedule.php');
                    break;
                case 'reception':
                    header('Location: Web/Hospital-Management-System/modules/appointments/opd_walkin.php');
                    break;
                case 'pharmacist':
                    header('Location: Web/Hospital-Management-System/modules/pharmacy/pharmacy_queue.php');
                    break;
                case 'patient':
                    header('Location: Web/Hospital-Management-System/modules/appointments/my_appointments.php');
                    break;
                case 'dispatcher':
                    header('Location: Web/Hospital-Management-System/modules/emergency/dispatcher_dashboard.php');
                    break;
                case 'driver':
                    header('Location: Web/Hospital-Management-System/modules/emergency/driver_job.php');
                    break;
                default:
                    header('Location: Web/Hospital-Management-System/index.php');
                    break;
            }
            exit();
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="form-card">
        <h2 class="form-title">Login</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn">Login</button>
        </form>

        <div class="auth-links">
            <a href="/hospital-system/register.php">Create new account</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>