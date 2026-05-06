<?php


session_start();
require_once '../../config/db_config.php';



$msg      = '';
$msg_type = '';

// ── Handle Add ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $vehicle_no   = trim($_POST['vehicle_no']   ?? '');
    $driver_name  = trim($_POST['driver_name']  ?? '');
    $driver_phone = trim($_POST['driver_phone'] ?? '');
    $status       = $_POST['status'] ?? 'available';

    if ($vehicle_no && $driver_name && $driver_phone) {
        try {
            $pdo->prepare("
                INSERT INTO ambulances (vehicle_no, driver_name, driver_phone, status)
                VALUES (:v, :dn, :dp, :s)
            ")->execute([':v'=>$vehicle_no, ':dn'=>$driver_name, ':dp'=>$driver_phone, ':s'=>$status]);
            $msg = 'Ambulance added successfully.';
            $msg_type = 'success';
        } catch (PDOException $e) {
            $msg = 'Error: ' . $e->getMessage();
            $msg_type = 'danger';
        }
    } else {
        $msg = 'All fields are required.';
        $msg_type = 'danger';
    }
}

// ── Handle Delete ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['ambulance_id'] ?? 0);
    if ($id) {
        $pdo->prepare("DELETE FROM ambulances WHERE ambulance_id=:id")->execute([':id'=>$id]);
        $msg = 'Ambulance removed.';
        $msg_type = 'success';
    }
}

// ── Handle Status Update ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    $id     = (int)($_POST['ambulance_id'] ?? 0);
    $status = $_POST['new_status'] ?? '';
    $allowed = ['available','dispatched','maintenance'];
    if ($id && in_array($status, $allowed)) {
        $pdo->prepare("UPDATE ambulances SET status=:s WHERE ambulance_id=:id")->execute([':s'=>$status,':id'=>$id]);
        $msg = 'Ambulance status updated.';
        $msg_type = 'success';
    }
}

// ── Fetch ambulances ──────────────────────────────────────
$ambulances = $pdo->query("SELECT * FROM ambulances ORDER BY vehicle_no")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ambulance Fleet | Hospital Management System</title>
    <link rel="stylesheet" href="../../includes/emergency.css">
</head>
<body>

<div class="page-header">
    <span class="icon">🚑</span>
    <div>
        <h1>Ambulance Fleet Management</h1>
        <p>Add, update, and manage hospital ambulances and drivers</p>
    </div>
</div>

<div class="container">

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>">
        <span><?= $msg_type === 'success' ? '✅' : '❌' ?></span>
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- Add Ambulance -->
    <div class="card">
        <div class="card-title">➕ Add New Ambulance</div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group">
                    <label>Vehicle Number *</label>
                    <input type="text" name="vehicle_no" placeholder="e.g. AMB-004" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="available">Available</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Driver Full Name *</label>
                    <input type="text" name="driver_name" placeholder="e.g. Kamal Perera" required>
                </div>
                <div class="form-group">
                    <label>Driver Phone *</label>
                    <input type="tel" name="driver_phone" placeholder="e.g. 0771234567" required>
                </div>
            </div>
            <button type="submit" class="btn btn-danger">➕ Add Ambulance</button>
        </form>
    </div>

    <!-- Ambulance List -->
    <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:20px 24px 0;">
            <div class="card-title">🚑 Fleet Overview</div>
        </div>
        <?php if (empty($ambulances)): ?>
            <p style="padding:30px;text-align:center;color:var(--gray-400);">No ambulances in the fleet yet.</p>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Vehicle No.</th>
                        <th>Driver</th>
                        <th>Driver Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ambulances as $amb): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($amb['vehicle_no']) ?></strong></td>
                    <td><?= htmlspecialchars($amb['driver_name']) ?></td>
                    <td><?= htmlspecialchars($amb['driver_phone']) ?></td>
                    <td>
                        <?php
                        $sc = ['available'=>'green','dispatched'=>'blue','maintenance'=>'amber'];
                        $s  = $amb['status'];
                        ?>
                        <span class="badge badge-<?= $s === 'available' ? 'arrived' : ($s === 'dispatched' ? 'dispatched' : 'pending') ?>">
                            <?= strtoupper($s) ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-group">
                            <!-- Quick status change -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action"       value="status">
                                <input type="hidden" name="ambulance_id" value="<?= $amb['ambulance_id'] ?>">
                                <select name="new_status" onchange="this.form.submit()" style="padding:5px 8px;border-radius:6px;border:1.5px solid var(--gray-200);font-size:12px;cursor:pointer;">
                                    <option value="">Change status</option>
                                    <option value="available">Available</option>
                                    <option value="dispatched">Dispatched</option>
                                    <option value="maintenance">Maintenance</option>
                                </select>
                            </form>
                            <!-- Delete -->
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Remove this ambulance from the fleet?')">
                                <input type="hidden" name="action"       value="delete">
                                <input type="hidden" name="ambulance_id" value="<?= $amb['ambulance_id'] ?>">
                                <button type="submit" class="btn btn-sm" style="background:var(--red-light);color:var(--red);">🗑 Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div style="text-align:center;margin-top:10px;">
        <a href="emergency_dispatcher.php" class="btn btn-danger">← Back to Dispatcher Panel</a>
    </div>

</div>
</body>
</html>