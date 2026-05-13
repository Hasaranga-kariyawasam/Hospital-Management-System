<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../includes/auth_guard.php';

requireRole(['admin', 'doctor']);

$pageTitle  = 'Theatre Reports';
$useSidebar = true;

// ── Filters ───────────────────────────────────────────────────────
$from    = $_GET['from']    ?? date('Y-m-01');
$to      = $_GET['to']      ?? date('Y-m-d');
$theatre = $_GET['theatre'] ?? '';
$status  = $_GET['status']  ?? '';

$theatres = ['Theatre 1', 'Theatre 2', 'Labour Theatre', 'Emergency Theatre'];
$statuses = ['Scheduled', 'Confirmed', 'In Progress', 'Completed', 'Cancelled', 'Transferred'];

// ── Base query ────────────────────────────────────────────────────
$where  = "WHERE o.scheduled_date BETWEEN :from AND :to";
$params = [':from' => $from, ':to' => $to];

if ($theatre) { $where .= " AND o.theatre_number = :theatre"; $params[':theatre'] = $theatre; }
if ($status)  { $where .= " AND o.status = :status";          $params[':status']  = $status; }

// ── All operations ────────────────────────────────────────────────
$ops = $pdo->prepare("
    SELECT o.*, u.full_name AS patient_name, ls.full_name AS surgeon_name
    FROM theatre_operations o
    JOIN patients  p  ON o.patient_id      = p.patient_id
    JOIN users     u  ON p.user_id         = u.user_id
    JOIN users     ls ON o.lead_surgeon_id = ls.user_id
    $where
    ORDER BY o.scheduled_date DESC, o.scheduled_time
");
$ops->execute($params);
$operations = $ops->fetchAll();

// ── Summary stats ─────────────────────────────────────────────────
$total      = count($operations);
$completed  = count(array_filter($operations, fn($o) => $o['status'] === 'Completed'));
$pending    = count(array_filter($operations, fn($o) => in_array($o['status'], ['Scheduled','Confirmed'])));
$cancelled  = count(array_filter($operations, fn($o) => $o['status'] === 'Cancelled'));
$maternity  = count(array_filter($operations, fn($o) => $o['is_maternity']));

// ── Theatre usage count ───────────────────────────────────────────
$byTheatre = array_count_values(array_column($operations, 'theatre_number'));

// ── Doctor-wise count ─────────────────────────────────────────────
$bySurgeon = [];
foreach ($operations as $op) {
    $bySurgeon[$op['surgeon_name']] = ($bySurgeon[$op['surgeon_name']] ?? 0) + 1;
}
arsort($bySurgeon);

// ── Billing total ─────────────────────────────────────────────────
$billTotal = 0;
if ($operations) {
    $ids = implode(',', array_map(fn($o) => (int)$o['operation_id'], $operations));
    $billTotal = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM theatre_billing_items WHERE operation_id IN ($ids)")->fetchColumn();
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📊 Theatre Reports</h1>
        <p class="page-sub">Operations report from <?= date('d M Y', strtotime($from)) ?> to <?= date('d M Y', strtotime($to)) ?></p>
    </div>
</div>

<!-- ── Filters ──────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:24px">
    <form method="GET" class="filter-row">
        <div class="form-group" style="flex:1;min-width:150px">
            <label class="form-label">From Date</label>
            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
        </div>
        <div class="form-group" style="flex:1;min-width:150px">
            <label class="form-label">To Date</label>
            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
        </div>
        <div class="form-group" style="flex:1;min-width:180px">
            <label class="form-label">Theatre</label>
            <select name="theatre" class="form-control">
                <option value="">All Theatres</option>
                <?php foreach ($theatres as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>" <?= $theatre === $t ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex:1;min-width:150px">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;align-items:flex-end;gap:8px">
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="theatre_report.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- ── Summary Cards ─────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px">
    <?php
    $cards = [
        ['Total Operations', $total,     '#2563eb', '🔬'],
        ['Completed',        $completed, '#059669', '✅'],
        ['Pending / Booked', $pending,   '#d97706', '⏳'],
        ['Cancelled',        $cancelled, '#dc2626', '❌'],
        ['Maternity Cases',  $maternity, '#be185d', '🤱'],
        ['Total Revenue',    'Rs. ' . number_format($billTotal, 0), '#7c3aed', '💳'],
    ];
    foreach ($cards as [$label, $val, $col, $icon]): ?>
    <div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:16px;border-top:3px solid <?= $col ?>">
        <div style="font-size:1.5rem"><?= $icon ?></div>
        <div style="font-size:1.6rem;font-weight:700;color:<?= $col ?>;margin:4px 0"><?= $val ?></div>
        <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($label) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">

    <!-- Theatre Usage Breakdown -->
    <div class="card">
        <div class="card-header"><h3>🏨 Theatre Usage</h3></div>
        <div style="padding:16px">
            <?php if (empty($byTheatre)): ?>
                <p style="color:var(--muted)">No data.</p>
            <?php else: ?>
                <?php foreach ($theatres as $t):
                    $cnt = $byTheatre[$t] ?? 0;
                    $pct = $total > 0 ? round($cnt / $total * 100) : 0;
                ?>
                <div style="margin-bottom:12px">
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                        <span><?= htmlspecialchars($t) ?></span>
                        <strong><?= $cnt ?> ops (<?= $pct ?>%)</strong>
                    </div>
                    <div style="height:8px;background:var(--border);border-radius:4px;overflow:hidden">
                        <div style="height:100%;width:<?= $pct ?>%;background:var(--primary);border-radius:4px"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Surgeons -->
    <div class="card">
        <div class="card-header"><h3>👨‍⚕️ Operations by Surgeon</h3></div>
        <div style="padding:16px">
            <?php if (empty($bySurgeon)): ?>
                <p style="color:var(--muted)">No data.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Surgeon</th><th style="text-align:right">Operations</th></tr></thead>
                    <tbody>
                        <?php foreach ($bySurgeon as $name => $cnt): ?>
                        <tr>
                            <td><?= htmlspecialchars($name) ?></td>
                            <td style="text-align:right;font-weight:600"><?= $cnt ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Operations List ─────────────────────────────────────────── -->
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <h3>🗒️ Operations List (<?= $total ?>)</h3>
    </div>
    <?php if (empty($operations)): ?>
        <p style="padding:20px;color:var(--muted)">No operations found for the selected filters.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date & Time</th>
                    <th>Patient</th>
                    <th>Operation</th>
                    <th>Theatre</th>
                    <th>Lead Surgeon</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $statusBadgeMap = [
                    'Scheduled'=>'badge-info','Confirmed'=>'badge-primary','In Progress'=>'badge-warning',
                    'Completed'=>'badge-success','Cancelled'=>'badge-danger','Transferred'=>'badge-secondary'
                ];
                foreach ($operations as $op): ?>
                <tr>
                    <td><?= $op['operation_id'] ?></td>
                    <td>
                        <div><?= date('d M Y', strtotime($op['scheduled_date'])) ?></div>
                        <small class="text-muted"><?= date('h:i A', strtotime($op['scheduled_time'])) ?></small>
                    </td>
                    <td><?= htmlspecialchars($op['patient_name']) ?></td>
                    <td>
                        <?= htmlspecialchars($op['operation_type']) ?>
                        <?php if ($op['is_maternity']): ?><span class="badge badge-pink" style="font-size:10px">🤱</span><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($op['theatre_number']) ?></td>
                    <td><?= htmlspecialchars($op['surgeon_name']) ?></td>
                    <td>
                        <span class="badge <?= $statusBadgeMap[$op['status']] ?? 'badge-secondary' ?>">
                            <?= htmlspecialchars($op['status']) ?>
                        </span>
                    </td>
                    <td>
                        <a href="operation_details.php?id=<?= $op['operation_id'] ?>"
                           class="btn btn-sm btn-secondary">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
