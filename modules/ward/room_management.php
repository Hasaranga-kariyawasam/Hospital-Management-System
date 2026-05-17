<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
$requiredRoles = ['admin'];
require_once __DIR__ . '/../../includes/role_check.php';

$success = '';
$error   = '';

// ── Handle add/edit room ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $roomNumber = trim($_POST['room_number'] ?? '');
    $roomType   = $_POST['room_type'] ?? '';
    $floor      = (int)($_POST['floor'] ?? 1);
    $dailyRate  = (float)($_POST['daily_rate'] ?? 0);
    $bedCount   = (int)($_POST['bed_count'] ?? 0);

    if ($action === 'add_room') {
        if (!$roomNumber || !$roomType) {
            $error = 'Room number and type are required.';
        } else {
            $pdo->prepare("INSERT INTO rooms (room_number, room_type, floor, daily_rate, is_available) VALUES (?,?,?,?,1)")
                ->execute([$roomNumber, $roomType, $floor, $dailyRate]);
            $newRoomId = (int)$pdo->lastInsertId();
            // Add beds if specified
            for ($i = 1; $i <= $bedCount; $i++) {
                $pdo->prepare("INSERT INTO beds (room_id, bed_number, status) VALUES (?,?,'available')")
                    ->execute([$newRoomId, 'B' . $i]);
            }
            $success = "Room $roomNumber added successfully" . ($bedCount > 0 ? " with $bedCount beds." : ".");
        }
    } elseif ($action === 'toggle_room') {
        $roomId = (int)($_POST['room_id'] ?? 0);
        $pdo->prepare("UPDATE rooms SET is_available = NOT is_available WHERE room_id=?")->execute([$roomId]);
        $success = 'Room status updated.';
    } elseif ($action === 'delete_room') {
        $roomId = (int)($_POST['room_id'] ?? 0);
        // Check no active admission
        $chk = $pdo->prepare("SELECT COUNT(*) FROM admissions WHERE room_id=? AND status='admitted'");
        $chk->execute([$roomId]);
        if ($chk->fetchColumn() > 0) {
            $error = 'Cannot delete a room with active admissions.';
        } else {
            $pdo->prepare("DELETE FROM beds WHERE room_id=?")->execute([$roomId]);
            $pdo->prepare("DELETE FROM rooms WHERE room_id=?")->execute([$roomId]);
            $success = 'Room deleted.';
        }
    }
}

// ── Load rooms ────────────────────────────────────────────────────────────
$rooms = $pdo->query("
    SELECT r.*,
           COUNT(b.bed_id)           AS total_beds,
           SUM(b.status='available') AS available_beds
    FROM rooms r
    LEFT JOIN beds b ON b.room_id = r.room_id
    GROUP BY r.room_id
    ORDER BY r.room_type, r.room_number
")->fetchAll();

$typeLabels = [
    'general_ward'  => 'General Ward',
    'semi_private'  => 'Semi-Private',
    'private'       => 'Private Room',
    'children'      => "Children's Room",
    'icu'           => 'ICU',
    'maternity'     => 'Maternity',
    'recovery'      => 'Recovery Room',
];

$pageTitle  = 'Room Management';
$useSidebar = true;
include __DIR__ . '/../../includes/header.php';
?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="main-content">

    <div class="page-header">
        <div class="page-header-title">
            <h2>Room Management</h2>
            <p>Add, edit and manage hospital rooms and beds</p>
        </div>
        <a href="ward_management.php" class="btn btn-secondary">← Dashboard</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom:20px"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:20px"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div>

        <!-- Add Room Form -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">Add New Room</h3></div>
            <div style="padding:24px">
                <form method="POST">
                    <input type="hidden" name="action" value="add_room">
                    <div class="form-group">
                        <label class="form-label">Room Number *</label>
                        <input type="text" name="room_number" class="form-control" placeholder="e.g. 101, ICU-3" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Room Type *</label>
                        <select name="room_type" class="form-control" required>
                            <option value="">— Select Type —</option>
                            <?php foreach ($typeLabels as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Floor</label>
                        <input type="number" name="floor" class="form-control" value="1" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Daily Rate (LKR)</label>
                        <input type="number" name="daily_rate" class="form-control" value="0" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Number of Beds (0 for private/whole room)</label>
                        <input type="number" name="bed_count" class="form-control" value="0" min="0" max="20">
                        <div style="font-size:12px;color:var(--muted);margin-top:4px">Leave 0 for private rooms. Add bed count for shared wards.</div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%">Add Room</button>
                </form>
            </div>
        </div>

        
       
    </div>
</main>

<style>
.page-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.page-header-title h2 { font-size:1.5rem; font-weight:700; margin-bottom:4px; }
.page-header-title p { color:var(--muted); font-size:14px; }
.card { background:var(--surface); border-radius:var(--radius); box-shadow:var(--shadow-sm); overflow:hidden; }
.card-header { display:flex; justify-content:space-between; align-items:center; padding:18px 24px; border-bottom:1px solid var(--border-light); }
.card-title { font-size:15px; font-weight:700; margin:0; }
.form-group { margin-bottom:16px; }
.form-label { display:block; font-size:13px; font-weight:600; color:var(--text-mid); margin-bottom:6px; }
.form-control { width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:var(--radius-sm); font-size:14px; font-family:var(--font-body); color:var(--text); background:var(--surface); }
.form-control:focus { outline:none; border-color:var(--accent); }
.btn { display:inline-flex; align-items:center; gap:6px; padding:9px 18px; border-radius:var(--radius-sm); font-size:14px; font-weight:500; cursor:pointer; border:1px solid transparent; text-decoration:none; transition:all var(--transition); }
.btn-primary { background:var(--accent); color:#fff; border-color:var(--accent); }
.btn-primary:hover { background:var(--accent-dark); color:#fff; }
.btn-secondary { background:var(--surface); color:var(--text-mid); border-color:var(--border); }
.btn-secondary:hover { background:var(--bg); color:var(--text); }
.table { width:100%; border-collapse:collapse; }
.table th { padding:10px 16px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:var(--muted); background:var(--bg); border-bottom:1px solid var(--border-light); }
.table td { padding:12px 16px; border-bottom:1px solid var(--border-light); font-size:14px; vertical-align:middle; }
.table tr:last-child td { border-bottom:none; }
.table tr:hover td { background:#f8fafc; }
.alert { padding:12px 16px; border-radius:var(--radius-sm); font-size:14px; }
.alert-success { background:var(--success-light); color:var(--success); }
.alert-danger  { background:var(--danger-light);  color:var(--danger); }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>