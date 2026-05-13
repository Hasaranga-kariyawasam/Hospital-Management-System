<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
$requiredRoles = ['admin', 'doctor'];
require_once __DIR__ . '/../../includes/role_check.php';





$pageTitle  = 'Theatre Schedule';
$useSidebar = true;

// ── Filters ──────────────────────────────────────────────────
$filterDate    = $_GET['date']    ?? '';
$filterTheatre = $_GET['theatre'] ?? '';
$filterStatus  = $_GET['status']  ?? '';

$where  = ['1=1'];
$params = [];

if ($filterDate !== '') {
    $where[]  = 'DATE(o.scheduled_at) = ?';
    $params[] = $filterDate;
}
if ($filterTheatre !== '') {
    $where[]  = 'o.theatre_number = ?';
    $params[] = (int)$filterTheatre;
}
if ($filterStatus !== '') {
    $where[]  = 'o.status = ?';
    $params[] = $filterStatus;
}

$sql = "
    SELECT
        o.operation_id,
        o.operation_type,
        o.theatre_number,
        DATE(o.scheduled_at) AS sched_date,
        TIME(o.scheduled_at) AS sched_time,
        o.status,
        o.post_op_room_type,
        p.patient_id,
        pu.full_name   AS patient_name,
        su.full_name   AS surgeon_name,
        au.full_name   AS anaesthetist_name
    FROM   theatre_operations o
    JOIN   patients            p   ON p.patient_id  = o.patient_id
    JOIN   users               pu  ON pu.user_id    = p.user_id
    JOIN   users               su  ON su.user_id    = o.surgeon_id
    LEFT JOIN users            au  ON au.user_id    = o.anaesthetist_id
    WHERE  " . implode(' AND ', $where) . "
    ORDER  BY o.scheduled_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$operations = $stmt->fetchAll();

// ── Stats ─────────────────────────────────────────────────────
$stats = $pdo->query("
    SELECT
        COUNT(*)                                                        AS total,
        SUM(status = 'scheduled')                                       AS scheduled,
        SUM(status = 'in_progress')                                     AS in_progress,
        SUM(status = 'completed')                                       AS completed,
        SUM(DATE(scheduled_at) = CURDATE())                           AS today
    FROM theatre_operations
")->fetch();

include __DIR__ . '/../../includes/header.php';
?>

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-title">
            <h2>🔬 Theatre Schedule</h2>
            <p>Manage and view all scheduled operations</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a href="calendar.php" class="btn btn-secondary">📅 Calendar View</a>
            <a href="create_operation.php" class="btn btn-primary">➕ Schedule Operation</a>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon blue">🔬</div>
            <div>
                <div class="stat-label">Total Operations</div>
                <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow">📅</div>
            <div>
                <div class="stat-label">Today's Operations</div>
                <div class="stat-value"><?= $stats['today'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">⏳</div>
            <div>
                <div class="stat-label">Scheduled</div>
                <div class="stat-value"><?= $stats['scheduled'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div>
                <div class="stat-label">Completed</div>
                <div class="stat-value"><?= $stats['completed'] ?? 0 ?></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:24px">
        <form method="GET" style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin:0;flex:1;min-width:150px">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control"
                       value="<?= htmlspecialchars($filterDate) ?>">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:150px">
                <label class="form-label">Theatre</label>
                <select name="theatre" class="form-control">
                    <option value="">All Theatres</option>
                    <option value="1" <?= $filterTheatre === '1' ? 'selected' : '' ?>>Theatre 1 – General</option>
                    <option value="2" <?= $filterTheatre === '2' ? 'selected' : '' ?>>Theatre 2 – Emergency</option>
                    <option value="3" <?= $filterTheatre === '3' ? 'selected' : '' ?>>Theatre 3 – Labour</option>
                    <option value="4" <?= $filterTheatre === '4' ? 'selected' : '' ?>>Theatre 4 – Minor</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:150px">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="scheduled"   <?= $filterStatus === 'scheduled'   ? 'selected' : '' ?>>Scheduled</option>
                    <option value="confirmed"   <?= $filterStatus === 'confirmed'   ? 'selected' : '' ?>>Confirmed</option>
                    <option value="in_progress" <?= $filterStatus === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="completed"   <?= $filterStatus === 'completed'   ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled"   <?= $filterStatus === 'cancelled'   ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div style="display:flex;gap:8px">
                <button type="submit" class="btn btn-primary">🔍 Filter</button>
                <a href="theatre.php" class="btn btn-secondary">✕ Clear</a>
            </div>
        </form>
    </div>

    <!-- Operations Table -->
    <div class="card">
        <div class="card-header">
            <h3>Operations List</h3>
            <span class="badge badge-info"><?= count($operations) ?> records</span>
        </div>

        <?php if (empty($operations)): ?>
            <div style="text-align:center;padding:48px;color:var(--muted)">
                <div style="font-size:48px;margin-bottom:12px">🔬</div>
                <p>No operations found. <a href="create_operation.php">Schedule one now →</a></p>
            </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Patient</th>
                        <th>Operation Type</th>
                        <th>Theatre</th>
                        <th>Date & Time</th>
                        <th>Lead Surgeon</th>
                        <th>Anaesthetist</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($operations as $op): ?>
                    <tr>
                        <td><strong>#<?= $op['operation_id'] ?></strong></td>
                        <td><?= htmlspecialchars($op['patient_name']) ?></td>
                        <td><?= htmlspecialchars($op['operation_type']) ?></td>
                        <td>
                            <?php
                            $theatreLabels = [
                                1 => '🏥 T1 – General',
                                2 => '🚨 T2 – Emergency',
                                3 => '👶 T3 – Labour',
                                4 => '🔧 T4 – Minor',
                            ];
                            echo $theatreLabels[$op['theatre_number']] ?? 'Theatre ' . $op['theatre_number'];
                            ?>
                        </td>
                        <td>
                            <strong><?= date('d M Y', strtotime($op['sched_date'])) ?></strong><br>
                            <span style="color:var(--muted);font-size:12px"><?= date('h:i A', strtotime($op['sched_time'])) ?></span>
                        </td>
                        <td><?= htmlspecialchars($op['surgeon_name']) ?></td>
                        <td><?= $op['anaesthetist_name'] ? htmlspecialchars($op['anaesthetist_name']) : '<span style="color:var(--muted)">–</span>' ?></td>
                        <td><?php
                            $badgeMap = [
                                'scheduled'   => 'badge-info',
                                'confirmed'   => 'badge-success',
                                'in_progress' => 'badge-warning',
                                'completed'   => 'badge-success',
                                'cancelled'   => 'badge-danger',
                                'transferred' => 'badge-neutral',
                            ];
                            $cls = $badgeMap[$op['status']] ?? 'badge-neutral';
                            ?>
                            <span class="badge <?= $cls ?>"><?= ucfirst(str_replace('_', ' ', $op['status'])) ?></span>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <a href="operation_details.php?id=<?= $op['operation_id'] ?>" class="btn btn-sm btn-secondary">View</a>
                                <?php if (in_array($op['status'], ['scheduled', 'confirmed', 'in_progress'])): ?>
                                    <a href="post_op_update.php?id=<?= $op['operation_id'] ?>" class="btn btn-sm btn-success">Update</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>