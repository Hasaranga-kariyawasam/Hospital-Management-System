<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../includes/auth_guard.php';

// Only admin and doctor can access
requireRole(['admin', 'doctor']);

$pageTitle  = 'Theatre Schedule';
$useSidebar = true;

// ── Filters ──────────────────────────────────────────────────────
$filterDate    = $_GET['date']    ?? date('Y-m-d');
$filterTheatre = $_GET['theatre'] ?? '';
$filterStatus  = $_GET['status']  ?? '';

$theatres = ['Theatre 1', 'Theatre 2', 'Labour Theatre', 'Emergency Theatre'];
$statuses = ['Scheduled', 'Confirmed', 'In Progress', 'Completed', 'Cancelled', 'Transferred'];

// ── Fetch operations ─────────────────────────────────────────────
$sql = "
    SELECT
        o.*,
        p.nic,
        u.full_name  AS patient_name,
        ls.full_name AS surgeon_name,
        an.full_name AS anaesthetist_name
    FROM theatre_operations o
    JOIN patients  pt ON o.patient_id      = pt.patient_id
    JOIN users      u ON pt.user_id        = u.user_id
    JOIN users     ls ON o.lead_surgeon_id = ls.user_id
    JOIN users     an ON o.anaesthetist_id = an.user_id
    WHERE o.scheduled_date = :date
";
$params = [':date' => $filterDate];

if ($filterTheatre !== '') {
    $sql .= " AND o.theatre_number = :theatre";
    $params[':theatre'] = $filterTheatre;
}
if ($filterStatus !== '') {
    $sql .= " AND o.status = :status";
    $params[':status'] = $filterStatus;
}
$sql .= " ORDER BY o.theatre_number, o.scheduled_time";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$operations = $stmt->fetchAll();

// ── Group by theatre ──────────────────────────────────────────────
$byTheatre = [];
foreach ($operations as $op) {
    $byTheatre[$op['theatre_number']][] = $op;
}

// ── Status badge helper ───────────────────────────────────────────
function statusBadge(string $s): string {
    $map = [
        'Scheduled'   => 'badge-info',
        'Confirmed'   => 'badge-primary',
        'In Progress' => 'badge-warning',
        'Completed'   => 'badge-success',
        'Cancelled'   => 'badge-danger',
        'Transferred' => 'badge-secondary',
    ];
    $cls = $map[$s] ?? 'badge-secondary';
    return "<span class='badge {$cls}'>" . htmlspecialchars($s) . "</span>";
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🏥 Theatre Schedule</h1>
        <p class="page-sub">View and manage all operation bookings by theatre and date.</p>
    </div>
    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'doctor'): ?>
    <a href="create_operation.php" class="btn btn-primary">+ Schedule Operation</a>
    <?php endif; ?>
</div>

<!-- ── Filters ───────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:24px">
    <form method="GET" class="filter-row">
        <div class="form-group" style="flex:1;min-width:160px">
            <label class="form-label">Date</label>
            <input type="date" name="date" class="form-control"
                   value="<?= htmlspecialchars($filterDate) ?>">
        </div>
        <div class="form-group" style="flex:1;min-width:180px">
            <label class="form-label">Theatre</label>
            <select name="theatre" class="form-control">
                <option value="">All Theatres</option>
                <?php foreach ($theatres as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>"
                        <?= $filterTheatre === $t ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex:1;min-width:160px">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>"
                        <?= $filterStatus === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;align-items:flex-end;gap:8px">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="theatre_schedule.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- ── Navigation: prev/today/next ──────────────────────────── -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
    <?php
    $prev = date('Y-m-d', strtotime($filterDate . ' -1 day'));
    $next = date('Y-m-d', strtotime($filterDate . ' +1 day'));
    $displayDate = date('l, d F Y', strtotime($filterDate));
    ?>
    <a href="?date=<?= $prev ?>&theatre=<?= urlencode($filterTheatre) ?>&status=<?= urlencode($filterStatus) ?>"
       class="btn btn-secondary btn-sm">← Prev</a>
    <strong style="font-size:1.1rem"><?= $displayDate ?></strong>
    <a href="?date=<?= $next ?>&theatre=<?= urlencode($filterTheatre) ?>&status=<?= urlencode($filterStatus) ?>"
       class="btn btn-secondary btn-sm">Next →</a>
    <a href="?date=<?= date('Y-m-d') ?>&theatre=<?= urlencode($filterTheatre) ?>&status=<?= urlencode($filterStatus) ?>"
       class="btn btn-outline btn-sm">Today</a>
    <a href="theatre_calendar.php" class="btn btn-outline btn-sm">📅 Calendar View</a>
</div>

<!-- ── Theatre Sections ──────────────────────────────────────── -->
<?php if (empty($operations)): ?>
    <div class="empty-state">
        <div style="font-size:3rem;margin-bottom:12px">🏥</div>
        <h3>No operations scheduled</h3>
        <p>No operations found for <?= htmlspecialchars($displayDate) ?>.</p>
        <a href="create_operation.php" class="btn btn-primary" style="margin-top:12px">Schedule an Operation</a>
    </div>
<?php else: ?>
    <?php
    $showTheatres = $filterTheatre !== '' ? [$filterTheatre] : $theatres;
    foreach ($showTheatres as $theatre):
        $ops = $byTheatre[$theatre] ?? [];
    ?>
    <div class="card" style="margin-bottom:24px">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0">
                🏨 <?= htmlspecialchars($theatre) ?>
                <span style="font-size:0.85rem;font-weight:400;color:var(--muted);margin-left:8px">
                    <?= count($ops) ?> operation<?= count($ops) !== 1 ? 's' : '' ?>
                </span>
            </h3>
        </div>

        <?php if (empty($ops)): ?>
            <p style="padding:16px;color:var(--muted)">No operations scheduled in this theatre today.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Operation</th>
                            <th>Lead Surgeon</th>
                            <th>Anaesthetist</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ops as $op): ?>
                        <tr>
                            <td>
                                <strong><?= date('h:i A', strtotime($op['scheduled_time'])) ?></strong>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($op['patient_name']) ?></div>
                                <small class="text-muted">NIC: <?= htmlspecialchars($op['nic']) ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($op['operation_type']) ?>
                                <?php if ($op['is_maternity']): ?>
                                    <span class="badge badge-pink" style="font-size:10px">🤱 Maternity</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($op['surgeon_name']) ?></td>
                            <td><?= htmlspecialchars($op['anaesthetist_name']) ?></td>
                            <td><?= $op['duration_minutes'] ?> min</td>
                            <td><?= statusBadge($op['status']) ?></td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap">
                                    <a href="operation_details.php?id=<?= $op['operation_id'] ?>"
                                       class="btn btn-sm btn-secondary">View</a>
                                    <?php if (in_array($op['status'], ['Scheduled','Confirmed'])): ?>
                                        <a href="create_operation.php?edit=<?= $op['operation_id'] ?>"
                                           class="btn btn-sm btn-outline">Edit</a>
                                    <?php endif; ?>
                                    <?php if ($op['status'] === 'Completed' && !$op['billing_triggered']): ?>
                                        <a href="post_operation.php?id=<?= $op['operation_id'] ?>"
                                           class="btn btn-sm btn-primary">Post-Op</a>
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
    <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
