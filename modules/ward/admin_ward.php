<?php
declare(strict_types=1);
// modules/ward/admin_ward.php
// Admin: Room management, occupancy overview, ward reports, newborn records

session_start();
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole(['admin']);

$pageTitle  = 'Ward Management — Admin';
$useSidebar = true;
$pageCss    = '/Web/Hospital-Management-System/modules/ward/ward.css';

$msg   = '';
$error = '';
$tab   = $_GET['tab'] ?? 'overview';

// ──────────────────────────────────────────────
// POST HANDLERS
// ──────────────────────────────────────────────

// Add new room
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_room') {
    $roomNumber = trim($_POST['room_number'] ?? '');
    $roomType   = $_POST['room_type'] ?? '';
    $floor      = (int)($_POST['floor'] ?? 1);
    $dailyRate  = (float)($_POST['daily_rate'] ?? 0);
    $bedCount   = (int)($_POST['bed_count'] ?? 1);

    if ($roomNumber && $roomType) {
        try {
            $pdo->prepare("INSERT INTO rooms (room_number, room_type, floor, daily_rate, is_available) VALUES (?,?,?,?,1)")
                ->execute([$roomNumber, $roomType, $floor, $dailyRate]);
            $roomId = $pdo->lastInsertId();

            // Add beds for shared rooms
            if (in_array($roomType, ['general_ward','semi_private']) && $bedCount > 1) {
                for ($i = 1; $i <= $bedCount; $i++) {
                    $pdo->prepare("INSERT INTO beds (room_id, bed_number, status) VALUES (?,?,'available')")
                        ->execute([$roomId, "B{$i}"]);
                }
            }
            $msg = "✅ Room {$roomNumber} added successfully.";
        } catch (Exception $e) {
            $error = '❌ ' . $e->getMessage();
        }
    }
}

// Toggle room availability (maintenance)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_maintenance') {
    $roomId = (int)($_POST['room_id'] ?? 0);
    if ($roomId) {
        $pdo->prepare("UPDATE rooms SET is_available = NOT is_available WHERE room_id=? AND room_id NOT IN (SELECT room_id FROM admissions WHERE status='admitted')")
            ->execute([$roomId]);
        $msg = '✅ Room status updated.';
    }
}

// Add newborn record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_newborn') {
    $motherId    = (int)($_POST['mother_id'] ?? 0);
    $babyName    = trim($_POST['baby_name'] ?? '');
    $dob         = $_POST['date_of_birth'] ?? '';
    $weight      = (float)($_POST['weight_kg'] ?? 0);
    $gender      = $_POST['gender'] ?? 'unknown';
    $healthStatus= trim($_POST['health_status'] ?? '');
    $assignedRoom= ($_POST['assigned_room'] ?? '') ? (int)$_POST['assigned_room'] : null;
    $notes       = trim($_POST['notes'] ?? '');

    if ($motherId && $dob) {
        $pdo->prepare("INSERT INTO newborn_records (mother_id, baby_name, date_of_birth, weight_kg, gender, health_status, assigned_room, notes)
                       VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$motherId, $babyName ?: null, $dob, $weight ?: null, $gender, $healthStatus ?: null, $assignedRoom, $notes ?: null]);
        $msg = '✅ Newborn record added.';
        $tab = 'newborn';
    }
}

// ──────────────────────────────────────────────
// FETCH DATA
// ──────────────────────────────────────────────

// Ward occupancy stats
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total_rooms,
        SUM(CASE WHEN is_available=1 THEN 1 ELSE 0 END) AS available_rooms,
        SUM(CASE WHEN is_available=0 THEN 1 ELSE 0 END) AS occupied_rooms
    FROM rooms
")->fetch();

$admittedCount = $pdo->query("SELECT COUNT(*) FROM admissions WHERE status='admitted'")->fetchColumn();
$pendingCount  = $pdo->query("SELECT COUNT(*) FROM admission_requests WHERE status='pending'")->fetchColumn();

// Rooms list
$rooms = $pdo->query("
    SELECT r.*,
        COUNT(b.bed_id) AS bed_count,
        SUM(CASE WHEN b.status='available' THEN 1 ELSE 0 END) AS available_beds
    FROM rooms r
    LEFT JOIN beds b ON r.room_id = b.room_id
    GROUP BY r.room_id
    ORDER BY r.room_type, r.room_number
")->fetchAll();

// Room type occupancy breakdown
$occupancyByType = $pdo->query("
    SELECT r.room_type,
           COUNT(DISTINCT r.room_id) AS total,
           SUM(CASE WHEN r.is_available=0 THEN 1 ELSE 0 END) AS occupied
    FROM rooms r
    GROUP BY r.room_type
")->fetchAll();

// All current inpatients
$allInpatients = $pdo->query("
    SELECT a.*, u_p.full_name AS patient_name, p.nic,
           u_d.full_name AS doctor_name,
           r.room_number, r.room_type, b.bed_number,
           DATEDIFF(CURDATE(), a.admission_date) AS days_stayed,
           ROUND(DATEDIFF(CURDATE(), a.admission_date) * r.daily_rate, 2) AS accrued
    FROM admissions a
    JOIN patients p  ON a.patient_id = p.patient_id
    JOIN users u_p   ON p.user_id = u_p.user_id
    JOIN doctors d   ON a.doctor_id = d.doctor_id
    JOIN users u_d   ON d.user_id = u_d.user_id
    JOIN rooms r     ON a.room_id = r.room_id
    LEFT JOIN beds b ON a.bed_id = b.bed_id
    WHERE a.status = 'admitted'
    ORDER BY r.room_type, r.room_number
")->fetchAll();

// Newborn records
$newborns = $pdo->query("
    SELECT nb.*, u_m.full_name AS mother_name, p_m.nic AS mother_nic,
           r.room_number
    FROM newborn_records nb
    JOIN patients p_m  ON nb.mother_id = p_m.patient_id
    JOIN users u_m     ON p_m.user_id = u_m.user_id
    LEFT JOIN rooms r  ON nb.assigned_room = r.room_id
    ORDER BY nb.date_of_birth DESC
")->fetchAll();

// Maternity patients (for newborn form)
$maternityPatients = $pdo->query("
    SELECT p.patient_id, u.full_name, p.nic
    FROM patients p
    JOIN users u ON p.user_id=u.user_id
    WHERE p.gender='female'
    ORDER BY u.full_name
")->fetchAll();

// Children rooms (for newborn assignment)
$childrenRooms = $pdo->query("
    SELECT * FROM rooms WHERE room_type='children' AND is_available=1
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <div class="page-header-title">
            <h2>🏥 Ward &amp; Room Management</h2>
            <p>Room availability, occupancy tracking, admissions overview</p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('addRoomModal').style.display='flex'">
            + Add Room
        </button>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- STAT CARDS -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon blue">🏥</div>
            <div>
                <div class="stat-label">Total Rooms</div>
                <div class="stat-value"><?= (int)$stats['total_rooms'] ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div>
                <div class="stat-label">Available Rooms</div>
                <div class="stat-value"><?= (int)$stats['available_rooms'] ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">🛏️</div>
            <div>
                <div class="stat-label">Occupied Rooms</div>
                <div class="stat-value"><?= (int)$stats['occupied_rooms'] ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon teal">👤</div>
            <div>
                <div class="stat-label">Current Inpatients</div>
                <div class="stat-value"><?= (int)$admittedCount ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">⏳</div>
            <div>
                <div class="stat-label">Pending Requests</div>
                <div class="stat-value"><?= (int)$pendingCount ?></div>
            </div>
        </div>
    </div>

    <!-- OCCUPANCY BY TYPE -->
    <div class="occupancy-bars">
        <?php foreach ($occupancyByType as $ot): ?>
        <?php $pct = $ot['total'] ? round(($ot['occupied']/$ot['total'])*100) : 0; ?>
        <div class="occupancy-item">
            <div class="occupancy-label"><?= str_replace('_',' ',strtoupper($ot['room_type'])) ?></div>
            <div class="occupancy-bar-track">
                <div class="occupancy-bar-fill" style="width:<?= $pct ?>%;background:<?= $pct>80?'#ef4444':($pct>50?'#f97316':'#22c55e') ?>"></div>
            </div>
            <div class="occupancy-numbers"><?= (int)$ot['occupied'] ?> / <?= (int)$ot['total'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- TABS -->
    <div class="ward-tabs">
        <button class="ward-tab <?= $tab==='overview'?'active':'' ?>" onclick="showTab('overview')">🗺️ Room Overview</button>
        <button class="ward-tab <?= $tab==='inpatients'?'active':'' ?>" onclick="showTab('inpatients')">🛏️ All Inpatients</button>
        <button class="ward-tab <?= $tab==='newborn'?'active':'' ?>" onclick="showTab('newborn')">👶 Newborn Records</button>
    </div>

    <!-- TAB: OVERVIEW -->
    <div id="tab-overview" class="tab-content <?= $tab==='overview'?'active':'' ?>">
        <div class="room-grid">
            <?php foreach ($rooms as $room): ?>
            <div class="room-card <?= $room['is_available']?'available':'occupied' ?>">
                <div class="room-card-number"><?= htmlspecialchars($room['room_number']) ?></div>
                <div class="room-card-type"><?= str_replace('_',' ',strtoupper($room['room_type'])) ?></div>
                <div class="room-card-floor">Floor <?= $room['floor'] ?></div>
                <div class="room-card-rate">LKR <?= number_format($room['daily_rate'],0) ?>/day</div>
                <?php if ($room['bed_count'] > 0): ?>
                    <div class="room-card-beds">Beds: <?= (int)$room['available_beds'] ?> / <?= (int)$room['bed_count'] ?> available</div>
                <?php endif; ?>
                <div class="room-card-status <?= $room['is_available']?'status-available':'status-occupied' ?>">
                    <?= $room['is_available'] ? '✅ Available' : '🔴 Occupied' ?>
                </div>
                <form method="POST" style="margin-top:8px">
                    <input type="hidden" name="action" value="toggle_maintenance">
                    <input type="hidden" name="room_id" value="<?= $room['room_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-secondary" <?= !$room['is_available'] && $room['is_available']!=0 ? 'disabled' : '' ?>>
                        <?= $room['is_available'] ? 'Set Maintenance' : 'Mark Available' ?>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- TAB: ALL INPATIENTS -->
    <div id="tab-inpatients" class="tab-content <?= $tab==='inpatients'?'active':'' ?>">
        <h3 class="section-title">All Current Inpatients (<?= count($allInpatients) ?>)</h3>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>Patient</th><th>NIC</th><th>Room</th><th>Type</th><th>Bed</th><th>Doctor</th><th>Admitted</th><th>Days</th><th>Accrued (LKR)</th><th>Meal</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($allInpatients as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['patient_name']) ?></td>
                        <td><?= htmlspecialchars($p['nic']) ?></td>
                        <td><?= htmlspecialchars($p['room_number']) ?></td>
                        <td><span class="room-type-badge"><?= str_replace('_',' ',strtoupper($p['room_type'])) ?></span></td>
                        <td><?= $p['bed_number'] ?? '—' ?></td>
                        <td><?= htmlspecialchars($p['doctor_name']) ?></td>
                        <td><?= date('d M Y', strtotime($p['admission_date'])) ?></td>
                        <td><?= (int)$p['days_stayed'] ?></td>
                        <td class="amount"><?= number_format((float)$p['accrued'], 2) ?></td>
                        <td><?= $p['meal_type'] ? str_replace('_',' ',ucfirst($p['meal_type'])) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$allInpatients): ?><tr><td colspan="10" class="empty-state">No current inpatients.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: NEWBORN -->
    <div id="tab-newborn" class="tab-content <?= $tab==='newborn'?'active':'' ?>">
        <div class="section-header-row">
            <h3 class="section-title">👶 Newborn Records</h3>
            <button class="btn btn-primary btn-sm" onclick="document.getElementById('addNewbornModal').style.display='flex'">+ Add Newborn</button>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>Baby Name</th><th>Mother</th><th>Mother NIC</th><th>DOB</th><th>Gender</th><th>Weight (kg)</th><th>Health</th><th>Room</th><th>Notes</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($newborns as $nb): ?>
                    <tr>
                        <td><?= $nb['baby_name'] ? htmlspecialchars($nb['baby_name']) : '<em>Unnamed</em>' ?></td>
                        <td><?= htmlspecialchars($nb['mother_name']) ?></td>
                        <td><?= htmlspecialchars($nb['mother_nic']) ?></td>
                        <td><?= date('d M Y', strtotime($nb['date_of_birth'])) ?></td>
                        <td><?= ucfirst($nb['gender']) ?></td>
                        <td><?= $nb['weight_kg'] ?? '—' ?></td>
                        <td><?= htmlspecialchars($nb['health_status'] ?? '—') ?></td>
                        <td><?= $nb['room_number'] ?? '—' ?></td>
                        <td><?= htmlspecialchars(substr($nb['notes']??'',0,50)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$newborns): ?><tr><td colspan="9" class="empty-state">No newborn records yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- ADD ROOM MODAL -->
<div id="addRoomModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-box">
        <div class="modal-header">
            <h3>🏥 Add New Room</h3>
            <button class="modal-close" onclick="document.getElementById('addRoomModal').style.display='none'">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_room">
            <div class="form-row">
                <div class="form-group">
                    <label>Room Number *</label>
                    <input type="text" name="room_number" required class="form-control" placeholder="e.g. PR04">
                </div>
                <div class="form-group">
                    <label>Floor *</label>
                    <input type="number" name="floor" min="1" max="10" value="1" required class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Room Type *</label>
                <select name="room_type" required class="form-control">
                    <option value="general_ward">General Ward</option>
                    <option value="semi_private">Semi-Private</option>
                    <option value="private">Private</option>
                    <option value="children">Children's</option>
                    <option value="icu">ICU</option>
                    <option value="maternity">Maternity</option>
                    <option value="recovery">Recovery</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Daily Rate (LKR) *</label>
                    <input type="number" name="daily_rate" min="0" step="0.01" required class="form-control" placeholder="5000.00">
                </div>
                <div class="form-group">
                    <label>Number of Beds (shared rooms)</label>
                    <input type="number" name="bed_count" min="1" max="10" value="1" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addRoomModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Room</button>
            </div>
        </form>
    </div>
</div>

<!-- ADD NEWBORN MODAL -->
<div id="addNewbornModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-box">
        <div class="modal-header">
            <h3>👶 Add Newborn Record</h3>
            <button class="modal-close" onclick="document.getElementById('addNewbornModal').style.display='none'">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_newborn">
            <div class="form-group">
                <label>Mother (Patient) *</label>
                <select name="mother_id" required class="form-control">
                    <option value="">-- Select Mother --</option>
                    <?php foreach ($maternityPatients as $mp): ?>
                        <option value="<?= $mp['patient_id'] ?>"><?= htmlspecialchars($mp['full_name']) ?> (<?= htmlspecialchars($mp['nic']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Baby Name (optional)</label>
                    <input type="text" name="baby_name" class="form-control" placeholder="Temp ID or name">
                </div>
                <div class="form-group">
                    <label>Date of Birth *</label>
                    <input type="date" name="date_of_birth" required class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" class="form-control">
                        <option value="unknown">Unknown</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Weight (kg)</label>
                    <input type="number" name="weight_kg" min="0" max="10" step="0.01" class="form-control" placeholder="3.20">
                </div>
            </div>
            <div class="form-group">
                <label>Health Status</label>
                <input type="text" name="health_status" class="form-control" placeholder="e.g. Healthy, NICU required, observation...">
            </div>
            <div class="form-group">
                <label>Assign to Children's Room</label>
                <select name="assigned_room" class="form-control">
                    <option value="">-- No room yet --</option>
                    <?php foreach ($childrenRooms as $cr): ?>
                        <option value="<?= $cr['room_id'] ?>"><?= htmlspecialchars($cr['room_number']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" rows="2" class="form-control" placeholder="Any additional notes..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addNewbornModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Newborn Record</button>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.ward-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.target.classList.add('active');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
