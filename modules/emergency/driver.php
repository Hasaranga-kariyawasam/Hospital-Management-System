<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../config/db_config.php';

// Check if user is logged in and is a driver
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: /Web/Hospital-Management-System/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$pageTitle = 'Driver Dashboard';
$useSidebar = true;

// Get driver information
$stmt = $pdo->prepare("
    SELECT d.*, u.full_name, u.email, u.phone, u.employee_id
    FROM drivers d
    JOIN users u ON d.user_id = u.user_id
    WHERE d.user_id = ?
");
$stmt->execute([$user_id]);
$driver = $stmt->fetch();

if (!$driver) {
    echo "<div style='padding: 20px; text-align: center;'>";
    echo "<h2>Driver profile not found</h2>";
    echo "<p>Please contact administrator to set up your driver profile.</p>";
    echo "<a href='/Web/Hospital-Management-System/logout.php' class='btn btn-primary'>Logout</a>";
    echo "</div>";
    exit;
}

// Update driver status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE drivers SET status = ? WHERE driver_id = ?");
    $stmt->execute([$new_status, $driver['driver_id']]);
    header("Location: driver_dashboard.php");
    exit();
}

// Accept emergency request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_emergency'])) {
    $emergency_id = $_POST['emergency_id'];
    
    // Start transaction
    $pdo->beginTransaction();
    try {
        // Assign driver to emergency
        $stmt = $pdo->prepare("
            UPDATE emergency_requests 
            SET assigned_driver_id = ?, status = 'dispatched', dispatch_time = NOW() 
            WHERE emergency_id = ? AND status = 'pending'
        ");
        $stmt->execute([$driver['driver_id'], $emergency_id]);
        
        // Update driver status
        $stmt = $pdo->prepare("
            UPDATE drivers 
            SET status = 'on_duty', assigned_emergency_id = ? 
            WHERE driver_id = ?
        ");
        $stmt->execute([$emergency_id, $driver['driver_id']]);
        
        $pdo->commit();
        header("Location: driver_dashboard.php");
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to accept emergency: " . $e->getMessage();
    }
    exit();
}

// Complete emergency
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_emergency'])) {
    $emergency_id = $_POST['emergency_id'];
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            UPDATE emergency_requests 
            SET status = 'resolved' 
            WHERE emergency_id = ? AND assigned_driver_id = ?
        ");
        $stmt->execute([$emergency_id, $driver['driver_id']]);
        
        $stmt = $pdo->prepare("
            UPDATE drivers 
            SET status = 'available', assigned_emergency_id = NULL 
            WHERE driver_id = ?
        ");
        $stmt->execute([$driver['driver_id']]);
        
        $pdo->commit();
        header("Location: driver_dashboard.php");
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to complete emergency: " . $e->getMessage();
    }
    exit();
}

// Get pending emergencies
$pending = $pdo->prepare("
    SELECT * FROM emergency_requests 
    WHERE status = 'pending' 
    ORDER BY submitted_at ASC
");
$pending->execute();
$pendingRequests = $pending->fetchAll();

// Get current active emergency
$active = null;
if ($driver['assigned_emergency_id']) {
    $stmt = $pdo->prepare("
        SELECT * FROM emergency_requests 
        WHERE emergency_id = ?
    ");
    $stmt->execute([$driver['assigned_emergency_id']]);
    $active = $stmt->fetch();
}

// Get history
$history = $pdo->prepare("
    SELECT * FROM emergency_requests 
    WHERE assigned_driver_id = ? AND status = 'resolved'
    ORDER BY dispatch_time DESC LIMIT 20
");
$history->execute([$driver['driver_id']]);
$historyList = $history->fetchAll();

$typeLabels = [
    'cardiac' => 'Cardiac Arrest / Heart Attack',
    'accident' => 'Accident / Trauma',
    'breathing' => 'Breathing Difficulty',
    'stroke' => 'Stroke',
    'burn' => 'Severe Burns',
    'poisoning' => 'Poisoning / Overdose',
    'fracture' => 'Fracture',
    'other' => 'Other Critical'
];

$consciousLabels = [
    'yes' => 'Fully Conscious',
    'semi' => 'Semi-Conscious',
    'no' => 'Unconscious'
];

include __DIR__ . '/../../includes/header.php';
?>

<style>
.driver-container {
    padding: 24px;
    max-width: 1400px;
    margin: 0 auto;
}

.status-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 16px;
    margin-bottom: 24px;
}

.status-badge {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.status-available { background: #10b981; color: white; }
.status-on_duty { background: #f59e0b; color: white; }
.status-off_duty { background: #6b7280; color: white; }
.status-on_leave { background: #ef4444; color: white; }

.emergency-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border: 1px solid #e5e7eb;
}

.emergency-critical {
    border-left: 4px solid #ef4444;
    background: linear-gradient(135deg, #fff 0%, #fef2f2 100%);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin: 20px 0;
}

.info-item {
    padding: 12px;
    background: #f9fafb;
    border-radius: 12px;
}

.info-label {
    font-size: 11px;
    text-transform: uppercase;
    color: #6b7280;
    font-weight: 600;
    letter-spacing: 0.05em;
}

.info-value {
    font-size: 16px;
    font-weight: 600;
    margin-top: 6px;
    color: #1f2937;
}

.request-list {
    display: grid;
    gap: 16px;
}

.request-item {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s;
    cursor: pointer;
}

.request-item:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59,130,246,0.1);
}

.btn {
    padding: 10px 24px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
}

.btn-primary { background: #3b82f6; color: white; }
.btn-primary:hover { background: #2563eb; transform: translateY(-1px); }
.btn-success { background: #10b981; color: white; }
.btn-success:hover { background: #059669; }
.btn-warning { background: #f59e0b; color: white; }
.btn-outline { background: transparent; border: 1px solid #d1d5db; color: #374151; }
.btn-outline:hover { border-color: #3b82f6; color: #3b82f6; }

.driver-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-box {
    background: white;
    padding: 16px;
    border-radius: 12px;
    text-align: center;
    border: 1px solid #e5e7eb;
}

.stat-number {
    font-size: 28px;
    font-weight: 700;
    color: #3b82f6;
}

.stat-label {
    font-size: 13px;
    color: #6b7280;
    margin-top: 4px;
}

.history-table {
    width: 100%;
    overflow-x: auto;
}

.history-table table {
    width: 100%;
    border-collapse: collapse;
}

.history-table th {
    text-align: left;
    padding: 12px;
    background: #f9fafb;
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
}

.history-table td {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
}
</style>

<div class="driver-container">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h1 style="font-size: 28px; font-weight: 700;">Driver Dashboard</h1>
            <p style="color: #6b7280; margin-top: 4px;">
                Welcome back, <?= htmlspecialchars($driver['full_name']) ?>
            </p>
        </div>
        <div>
            <span class="status-badge status-<?= $driver['status'] ?>">
                <?= ucfirst(str_replace('_', ' ', $driver['status'])) ?>
            </span>
        </div>
    </div>

    <!-- Driver Stats -->
    <div class="driver-stats">
        <div class="stat-box">
            <div class="stat-number"><?= $driver['ambulance_number'] ?? 'N/A' ?></div>
            <div class="stat-label">Ambulance Number</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?= $driver['ambulance_type'] ?></div>
            <div class="stat-label">Ambulance Type</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?= $driver['years_of_experience'] ?> yrs</div>
            <div class="stat-label">Experience</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?= count($historyList) ?></div>
            <div class="stat-label">Completed Trips</div>
        </div>
    </div>

    <!-- Update Status -->
    <div class="status-card">
        <h3 style="margin-bottom: 16px;">Update Your Status</h3>
        <form method="POST" style="display: flex; gap: 12px; align-items: center;">
            <select name="status" style="padding: 10px 16px; border-radius: 8px; border: none; font-family: inherit;">
                <option value="available" <?= $driver['status'] == 'available' ? 'selected' : '' ?>>Available for Dispatch</option>
                <option value="on_duty" <?= $driver['status'] == 'on_duty' ? 'selected' : '' ?>>On Duty / Responding</option>
                <option value="off_duty" <?= $driver['status'] == 'off_duty' ? 'selected' : '' ?>>Off Duty</option>
                <option value="on_leave" <?= $driver['status'] == 'on_leave' ? 'selected' : '' ?>>On Leave</option>
            </select>
            <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
        </form>
    </div>

    <!-- Active Emergency -->
    <?php if ($active): ?>
    <div class="emergency-card emergency-critical">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 style="color: #dc2626;">ACTIVE EMERGENCY RESPONSE</h2>
            <span class="status-badge status-on_duty">In Progress</span>
        </div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Emergency Type</div>
                <div class="info-value"><?= $typeLabels[$active['emergency_type']] ?? $active['emergency_type'] ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Patient Name</div>
                <div class="info-value"><?= htmlspecialchars($active['patient_name'] ?: 'Not provided') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Location / Address</div>
                <div class="info-value"><?= htmlspecialchars($active['patient_address'] ?: 'Address provided on dispatch') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Contact Number</div>
                <div class="info-value"><a href="tel:<?= $active['contact_number'] ?>"><?= htmlspecialchars($active['contact_number']) ?></a></div>
            </div>
            <div class="info-item">
                <div class="info-label">Conscious Status</div>
                <div class="info-value"><?= $consciousLabels[$active['is_conscious']] ?? 'Unknown' ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Dispatched At</div>
                <div class="info-value"><?= date('d M Y, H:i', strtotime($active['dispatch_time'])) ?></div>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="emergency_id" value="<?= $active['emergency_id'] ?>">
            <button type="submit" name="complete_emergency" class="btn btn-success">Mark as Completed / Patient Delivered</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Pending Requests -->
    <?php if ($driver['status'] === 'available' && !$active && !empty($pendingRequests)): ?>
    <div style="margin-top: 24px;">
        <h2 style="margin-bottom: 16px;">New Emergency Requests</h2>
        <div class="request-list">
            <?php foreach ($pendingRequests as $req): ?>
            <div class="request-item">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                    <div>
                        <span style="font-size: 24px; margin-right: 8px;">
                            <?php
                            $icons = ['cardiac'=>'', 'accident'=>'', 'breathing'=>'', 'stroke'=>'', 'burn'=>'', 'poisoning'=>'', 'fracture'=>'', 'other'=>''];
                            echo $icons[$req['emergency_type']] ?? '';
                            ?>
                        </span>
                        <strong style="font-size: 18px; color: #dc2626;"><?= $typeLabels[$req['emergency_type']] ?? $req['emergency_type'] ?></strong>
                    </div>
                    <small style="color: #6b7280;"><?= date('d M Y, H:i', strtotime($req['submitted_at'])) ?></small>
                </div>
                <div class="info-grid" style="margin-top: 12px;">
                    <div>
                        <strong>Patient:</strong> <?= htmlspecialchars($req['patient_name'] ?: 'Not provided') ?>
                    </div>
                    <div>
                        <strong>Location:</strong> <?= htmlspecialchars($req['patient_address'] ?: 'Address provided') ?>
                    </div>
                    <div>
                        <strong>Contact:</strong> <a href="tel:<?= $req['contact_number'] ?>"><?= htmlspecialchars($req['contact_number']) ?></a>
                    </div>
                    <div>
                        <strong>Condition:</strong> <?= $consciousLabels[$req['is_conscious']] ?? 'Unknown' ?>
                    </div>
                </div>
                <form method="POST" style="margin-top: 16px;">
                    <input type="hidden" name="emergency_id" value="<?= $req['emergency_id'] ?>">
                    <button type="submit" name="accept_emergency" class="btn btn-primary">Accept & Respond</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php elseif ($driver['status'] === 'available' && !$active && empty($pendingRequests)): ?>
    <div class="emergency-card" style="text-align: center;">
        <div style="font-size: 48px; margin-bottom: 16px;"></div>
        <h3>No Pending Requests</h3>
        <p style="color: #6b7280; margin-top: 8px;">You're available. New emergency requests will appear here.</p>
    </div>
    <?php endif; ?>

    <!-- History -->
    <?php if (!empty($historyList)): ?>
    <div style="margin-top: 32px;">
        <h2 style="margin-bottom: 16px;">Recent Dispatch History</h2>
        <div class="history-table">
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Emergency Type</th>
                        <th>Patient</th>
                        <th>Location</th>
                        <th>Response Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historyList as $item): ?>
                    <tr>
                        <td><?= date('d M Y, H:i', strtotime($item['submitted_at'])) ?></td>
                        <td><?= $typeLabels[$item['emergency_type']] ?? $item['emergency_type'] ?></td>
                        <td><?= htmlspecialchars($item['patient_name'] ?: '—') ?></td>
                        <td><?= htmlspecialchars(substr($item['patient_address'] ?: '—', 0, 30)) ?></td>
                        <td>
                            <?php 
                            $response = (strtotime($item['dispatch_time']) - strtotime($item['submitted_at'])) / 60;
                            echo round($response) . ' min';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Auto-refresh every 30 seconds
setInterval(() => {
    location.reload();
}, 30000);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>