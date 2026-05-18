<?php
// modules/profile/edit_profile.php
// Edit doctor profile — updates both `users` and `doctors` tables

require_once '../../includes/session_check.php';
require_once '../../includes/role_check.php';

$host   = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'hospital_db';

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$user_id = (int)$_SESSION['user_id'];
$success = '';
$errors  = [];

// ── Handle POST (save) ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- collect & sanitise inputs ---
    $full_name       = trim($_POST['full_name']       ?? '');
    $email           = trim($_POST['email']           ?? '');
    $department      = trim($_POST['department']      ?? '');
    $specialization  = trim($_POST['specialization']  ?? '');
    $qualifications  = trim($_POST['qualifications']  ?? '');
    $license_number  = trim($_POST['license_number']  ?? '');
    $consultation_fee= trim($_POST['consultation_fee']?? '0');
    $new_password    = trim($_POST['new_password']    ?? '');
    $confirm_password= trim($_POST['confirm_password']?? '');

    // --- basic validation ---
    if ($full_name === '')      $errors[] = 'Full name is required.';
    if ($email === '')          $errors[] = 'Email is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email format is invalid.';
    if ($specialization === '') $errors[] = 'Specialization is required.';
    if (!is_numeric($consultation_fee) || $consultation_fee < 0) $errors[] = 'Consultation fee must be a positive number.';

    // --- check email unique (excluding current user) ---
    $emailCheck = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $emailCheck->bind_param('si', $email, $user_id);
    $emailCheck->execute();
    if ($emailCheck->get_result()->num_rows > 0) {
        $errors[] = 'That email is already used by another account.';
    }
    $emailCheck->close();

    // --- password change (optional) ---
    $passwordSQL  = '';
    $passwordHash = '';
    if ($new_password !== '') {
        if (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        } else {
            $passwordHash = password_hash($new_password, PASSWORD_DEFAULT);
            $passwordSQL  = ', password_hash = ?';
        }
    }

    // --- save if no errors ---
    if (empty($errors)) {

        // Update users table
        if ($passwordSQL !== '') {
            $stmt = $conn->prepare(
                "UPDATE users
                 SET full_name = ?, email = ?, department = ? $passwordSQL
                 WHERE user_id = ?"
            );
            $stmt->bind_param('sssi', $full_name, $email, $department, $passwordHash, $user_id);
            // Note: bind_param types adjusted below because $passwordSQL adds a param
            $stmt->close();

            $stmt = $conn->prepare(
                "UPDATE users SET full_name=?, email=?, department=?, password_hash=? WHERE user_id=?"
            );
            $stmt->bind_param('ssssi', $full_name, $email, $department, $passwordHash, $user_id);
        } else {
            $stmt = $conn->prepare(
                "UPDATE users SET full_name=?, email=?, department=? WHERE user_id=?"
            );
            $stmt->bind_param('sssi', $full_name, $email, $department, $user_id);
        }
        $stmt->execute();
        $stmt->close();

        // Update doctors table
        $stmt2 = $conn->prepare(
            "UPDATE doctors
             SET specialization=?, qualifications=?, license_number=?, consultation_fee=?
             WHERE user_id=?"
        );
        $fee = (float)$consultation_fee;
        $stmt2->bind_param('sssdi', $specialization, $qualifications, $license_number, $fee, $user_id);
        $stmt2->execute();
        $stmt2->close();

        // Refresh session name if changed
        $_SESSION['full_name'] = $full_name;

        $success = 'Profile updated successfully!';
    }
}

// ── Fetch current data (after possible save) ─────────────────
$row = $conn->query(
    "SELECT u.full_name, u.email, u.department, u.staff_id, u.created_at,
            d.doctor_id, d.specialization, d.qualifications, d.license_number, d.consultation_fee
     FROM users u
     JOIN doctors d ON d.user_id = u.user_id
     WHERE u.user_id = $user_id
     LIMIT 1"
)->fetch_assoc();

if (!$row) die('Doctor record not found.');

// ── Page setup ───────────────────────────────────────────────
$pageTitle  = 'Edit My Profile';
$useSidebar = true;
$isPublic   = false;

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-title">
            <h2>Edit My Profile</h2>
            <p>Update your personal and professional information</p>
        </div>
        <div class="flex gap-12 items-center">
            <a href="../../modules/appointments/doctor_profile.php" class="btn btn-secondary">← Back to Profile</a>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
                <div> <?php echo htmlspecialchars($e); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="edit-form-grid" novalidate>

        <!-- ── LEFT COLUMN ── -->
        <div class="edit-col">

            <!-- Personal Info (users table) -->
            <div class="card">
                <div class="card-header">
                    <h3>👤 Personal Information</h3>
                    <span class="source-badge">users table</span>
                </div>

                <div class="form-group">
                    <label for="full_name">Full Name <span class="req">*</span></label>
                    <input type="text" id="full_name" name="full_name"
                           value="<?php echo htmlspecialchars($row['full_name']); ?>"
                           placeholder="Dr. John Perera" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="req">*</span></label>
                    <input type="email" id="email" name="email"
                           value="<?php echo htmlspecialchars($row['email']); ?>"
                           placeholder="doctor@hospital.lk" required>
                </div>

                <div class="form-group">
                    <label for="department">Department</label>
                    <input type="text" id="department" name="department"
                           value="<?php echo htmlspecialchars($row['department'] ?? ''); ?>"
                           placeholder="e.g. Cardiology">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Staff ID</label>
                        <input type="text" value="<?php echo htmlspecialchars($row['staff_id'] ?? '—'); ?>" disabled>
                        <small>Managed by admin</small>
                    </div>
                    <div class="form-group">
                        <label>Member Since</label>
                        <input type="text" value="<?php echo date('d M Y', strtotime($row['created_at'])); ?>" disabled>
                    </div>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card">
                <div class="card-header">
                    <h3>Change Password</h3>
                    <span class="optional-badge">Optional</span>
                </div>
                <p class="card-hint">Leave blank to keep your current password.</p>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password"
                           placeholder="Minimum 6 characters" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Repeat new password" autocomplete="new-password">
                </div>
            </div>

        </div><!-- /.edit-col -->

        <!-- ── RIGHT COLUMN ── -->
        <div class="edit-col">

            <!-- Professional Info (doctors table) -->
            <div class="card">
                <div class="card-header">
                    <h3>🩺 Professional Information</h3>
                    <span class="source-badge">doctors table</span>
                </div>

                <div class="form-group">
                    <label for="specialization">Specialization <span class="req">*</span></label>
                    <input type="text" id="specialization" name="specialization"
                           value="<?php echo htmlspecialchars($row['specialization']); ?>"
                           placeholder="e.g. Cardiologist" required>
                </div>

                <div class="form-group">
                    <label for="license_number">Medical License Number</label>
                    <input type="text" id="license_number" name="license_number"
                           value="<?php echo htmlspecialchars($row['license_number'] ?? ''); ?>"
                           placeholder="e.g. SLMC-12345">
                </div>

                <div class="form-group">
                    <label for="consultation_fee">Consultation Fee (Rs.) <span class="req">*</span></label>
                    <input type="number" id="consultation_fee" name="consultation_fee"
                           value="<?php echo number_format((float)$row['consultation_fee'], 2, '.', ''); ?>"
                           min="0" step="0.01" placeholder="0.00" required>
                </div>

                <div class="form-group">
                    <label for="qualifications">Qualifications</label>
                    <textarea id="qualifications" name="qualifications"
                              rows="5"
                              placeholder="e.g. MBBS (Colombo), MD (Cardiology), FRCP..."><?php echo htmlspecialchars($row['qualifications'] ?? ''); ?></textarea>
                    <small>One qualification per line is recommended.</small>
                </div>
            </div>

            <!-- Read-only summary -->
            <div class="card readonly-card">
                <div class="card-header">
                    <h3>Current Record</h3>
                </div>
                <table class="mini-table">
                    <tr><th>Doctor ID</th><td>#<?php echo (int)$row['doctor_id']; ?></td></tr>
                    <tr><th>User ID</th><td>#<?php echo $user_id; ?></td></tr>
                    <tr><th>Current Fee</th><td>Rs. <?php echo number_format($row['consultation_fee'], 2); ?></td></tr>
                </table>
            </div>

            <!-- Save button -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-full">Save Changes</button>
                <a href="../../modules/appointments/doctor_profile.php" class="btn btn-secondary btn-full">Cancel</a>
            </div>

        </div><!-- /.edit-col -->

    </form>

</main>

<?php include '../../includes/footer.php'; $conn->close(); ?>

<style>
/* ── Edit Profile Page ── */

.edit-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    align-items: start;
}

.edit-col {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Alerts */
.alert {
    padding: 14px 18px;
    border-radius: var(--radius-sm);
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.6;
}
.alert-success {
    background: #ecfdf5;
    border: 1px solid #6ee7b7;
    color: #065f46;
}
.alert-danger {
    background: #fef2f2;
    border: 1px solid #fca5a5;
    color: #991b1b;
}

/* Form elements */
.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 16px;
}
.form-group:last-child { margin-bottom: 0; }

.form-group label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-mid);
}

.form-group input,
.form-group textarea {
    padding: 10px 13px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 14px;
    color: var(--text);
    background: var(--surface);
    transition: border-color 0.2s, box-shadow 0.2s;
    width: 100%;
    box-sizing: border-box;
    font-family: inherit;
}
.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-light);
}
.form-group input:disabled {
    background: var(--bg);
    color: var(--muted);
    cursor: not-allowed;
}
.form-group textarea { resize: vertical; line-height: 1.5; }
.form-group small { font-size: 12px; color: var(--muted); }

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.req { color: #ef4444; }

/* Card hint */
.card-hint {
    font-size: 13px;
    color: var(--muted);
    margin: -4px 0 14px;
    font-style: italic;
}

/* Badges in card header */
.source-badge {
    font-size: 11px;
    font-weight: 600;
    background: var(--accent-light);
    color: var(--accent-dark);
    padding: 3px 10px;
    border-radius: 20px;
    letter-spacing: 0.3px;
}
.optional-badge {
    font-size: 11px;
    font-weight: 600;
    background: var(--bg);
    color: var(--muted);
    padding: 3px 10px;
    border-radius: 20px;
}

/* Read-only summary table */
.readonly-card { background: var(--bg); }
.mini-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.mini-table th,
.mini-table td { padding: 8px 10px; border-bottom: 1px solid var(--border-light); }
.mini-table tr:last-child th,
.mini-table tr:last-child td { border-bottom: none; }
.mini-table th { font-weight: 600; color: var(--muted); width: 45%; }

/* Save / cancel buttons */
.form-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.btn-full { width: 100%; justify-content: center; text-align: center; }

/* Responsive */
@media (max-width: 900px) {
    .edit-form-grid { grid-template-columns: 1fr; }
    .form-row       { grid-template-columns: 1fr; }
}
</style>