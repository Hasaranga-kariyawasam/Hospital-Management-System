<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../includes/auth_guard.php';

requireRole(['admin', 'doctor']);

$pageTitle  = 'Theatre Calendar';
$useSidebar = true;

// Week range
$baseDate  = $_GET['week'] ?? date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week', strtotime($baseDate)));
$weekEnd   = date('Y-m-d', strtotime('sunday this week', strtotime($baseDate)));
$prevWeek  = date('Y-m-d', strtotime($weekStart . ' -7 days'));
$nextWeek  = date('Y-m-d', strtotime($weekStart . ' +7 days'));

$theatres = ['Theatre 1', 'Theatre 2', 'Labour Theatre', 'Emergency Theatre'];

// Fetch all operations this week
$stmt = $pdo->prepare("
    SELECT
        o.*,
        u.full_name AS patient_name,
        ls.full_name AS surgeon_name
    FROM theatre_operations o
    JOIN patients  p  ON o.patient_id      = p.patient_id
    JOIN users     u  ON p.user_id         = u.user_id
    JOIN users     ls ON o.lead_surgeon_id = ls.user_id
    WHERE o.scheduled_date BETWEEN :start AND :end
      AND o.status NOT IN ('Cancelled')
    ORDER BY o.scheduled_date, o.scheduled_time
");
$stmt->execute([':start' => $weekStart, ':end' => $weekEnd]);
$allOps = $stmt->fetchAll();

// Index by theatre + date
$index = [];
foreach ($allOps as $op) {
    $index[$op['theatre_number']][$op['scheduled_date']][] = $op;
}

// Build 7-day range
$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = date('Y-m-d', strtotime($weekStart . " +{$i} days"));
}

$statusColors = [
    'Scheduled'   => '#dbeafe',
    'Confirmed'   => '#d1fae5',
    'In Progress' => '#fef3c7',
    'Completed'   => '#f0fdf4',
    'Transferred' => '#ede9fe',
];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📅 Theatre Calendar</h1>
        <p class="page-sub">Week of <?= date('d M', strtotime($weekStart)) ?> — <?= date('d M Y', strtotime($weekEnd)) ?></p>
    </div>
    <div style="display:flex;gap:10px">
        <a href="theatre_schedule.php" class="btn btn-secondary">📋 List View</a>
        <a href="create_operation.php" class="btn btn-primary">+ Schedule Operation</a>
    </div>
</div>

<!-- Week navigation -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
    <a href="?week=<?= $prevWeek ?>" class="btn btn-secondary btn-sm">← Prev Week</a>
    <a href="?week=<?= date('Y-m-d') ?>" class="btn btn-outline btn-sm">This Week</a>
    <a href="?week=<?= $nextWeek ?>" class="btn btn-secondary btn-sm">Next Week →</a>
</div>

<!-- Calendar Grid: Theatre rows × Day columns -->
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;min-width:900px">
    <thead>
        <tr>
            <th style="width:140px;padding:10px 12px;background:var(--bg-soft);border:1px solid var(--border);text-align:left">Theatre</th>
            <?php foreach ($days as $day): ?>
            <th style="padding:10px 8px;background:<?= $day === date('Y-m-d') ? 'var(--primary)' : 'var(--bg-soft)' ?>;
                       color:<?= $day === date('Y-m-d') ? '#fff' : 'inherit' ?>;
                       border:1px solid var(--border);text-align:center;font-size:13px">
                <div><?= date('D', strtotime($day)) ?></div>
                <div style="font-size:16px;font-weight:700"><?= date('d', strtotime($day)) ?></div>
                <div style="font-size:11px;opacity:0.8"><?= date('M', strtotime($day)) ?></div>
            </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($theatres as $theatre): ?>
        <tr>
            <td style="padding:10px 12px;border:1px solid var(--border);background:var(--bg-soft);font-weight:600;vertical-align:top;font-size:13px">
                <?= htmlspecialchars($theatre) ?>
            </td>
            <?php foreach ($days as $day): ?>
            <?php $ops = $index[$theatre][$day] ?? []; ?>
            <td style="padding:6px;border:1px solid var(--border);vertical-align:top;min-height:80px;background:<?= empty($ops) ? '#fff' : '#f8fafc' ?>">
                <?php if (empty($ops)): ?>
                    <div style="text-align:center;color:var(--muted);font-size:12px;padding:8px 0;opacity:0.5">free</div>
                <?php else: ?>
                    <?php foreach ($ops as $op):
                        $bg = $statusColors[$op['status']] ?? '#f1f5f9';
                    ?>
                    <a href="operation_details.php?id=<?= $op['operation_id'] ?>"
                       title="<?= htmlspecialchars($op['patient_name']) ?> — <?= htmlspecialchars($op['operation_type']) ?>"
                       style="display:block;margin-bottom:4px;padding:6px 8px;border-radius:6px;background:<?= $bg ?>;
                              border-left:3px solid var(--primary);text-decoration:none;color:inherit;font-size:11px">
                        <div style="font-weight:600"><?= date('h:i A', strtotime($op['scheduled_time'])) ?></div>
                        <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px">
                            <?= htmlspecialchars($op['patient_name']) ?>
                        </div>
                        <div style="color:var(--muted)"><?= htmlspecialchars(substr($op['operation_type'], 0, 18)) ?><?= strlen($op['operation_type']) > 18 ? '…' : '' ?></div>
                        <?php if ($op['is_maternity']): ?>
                            <span style="color:#be185d">🤱</span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                    <!-- Quick-add link -->
                    <a href="create_operation.php?date=<?= urlencode($day) ?>&theatre=<?= urlencode($theatre) ?>"
                       style="display:block;text-align:center;font-size:10px;color:var(--muted);padding:2px;text-decoration:none">+ add</a>
                <?php endif; ?>
            </td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- Legend -->
<div style="display:flex;gap:16px;margin-top:16px;flex-wrap:wrap;font-size:12px">
    <?php foreach ($statusColors as $s => $c): ?>
    <div style="display:flex;align-items:center;gap:6px">
        <div style="width:14px;height:14px;border-radius:3px;background:<?= $c ?>;border:1px solid var(--border)"></div>
        <?= htmlspecialchars($s) ?>
    </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
