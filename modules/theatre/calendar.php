<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
$requiredRoles = ['admin', 'doctor'];
require_once __DIR__ . '/../../includes/role_check.php';





$pageTitle  = 'Theatre Calendar';
$useSidebar = true;

// ── Week Navigation ───────────────────────────────────────────
$weekOffset = (int)($_GET['week'] ?? 0);
$today      = new DateTime();
$weekStart  = clone $today;
$weekStart->modify('monday this week');
$weekStart->modify("$weekOffset weeks");

$weekEnd = clone $weekStart;
$weekEnd->modify('+6 days');

// Build 7-day array
$days = [];
for ($i = 0; $i < 7; $i++) {
    $d = clone $weekStart;
    $d->modify("+$i days");
    $days[] = $d;
}

// ── Load operations for this week ─────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        o.operation_id,
        o.operation_type,
        o.theatre_number,
        DATE(o.scheduled_at) AS sched_date,
        TIME(o.scheduled_at) AS sched_time,
        o.status,
        pu.full_name AS patient_name,
        su.full_name AS surgeon_name
    FROM   theatre_operations o
    JOIN   patients pt ON pt.patient_id = o.patient_id
    JOIN   users    pu ON pu.user_id    = pt.user_id
    JOIN   users    su ON su.user_id    = o.surgeon_id
    WHERE  DATE(o.scheduled_at) BETWEEN ? AND ?
      AND  o.status NOT IN ('cancelled')
    ORDER  BY o.scheduled_at
");
$stmt->execute([
    $weekStart->format('Y-m-d'),
    $weekEnd->format('Y-m-d'),
]);
$allOps = $stmt->fetchAll();

// Index ops by [theatre][date]
$opsByTheatreDate = [];
foreach ($allOps as $op) {
    $t = $op['theatre_number'];
    $d = $op['sched_date'];
    if (!isset($opsByTheatreDate[$t][$d])) $opsByTheatreDate[$t][$d] = [];
    $opsByTheatreDate[$t][$d][] = $op;
}

$theatres = [
    1 => ['<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><line x1="12" y1="12" x2="12" y2="18"/><line x1="9" y1="15" x2="15" y2="15"/></svg>', 'Theatre 1', 'General'],
    2 => ['<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>', 'Theatre 2', 'Emergency'],
    3 => ['<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M9 12h.01M15 12h.01M10 16c.5.3 1.1.5 2 .5s1.5-.2 2-.5"/><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z"/></svg>', 'Theatre 3', 'Labour'],
    4 => ['<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>', 'Theatre 4', 'Minor'],
];

include __DIR__ . '/../../includes/header.php';
?>

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="main-content">

    <div class="page-header">
        <div class="page-header-title">
            <h2><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Theatre Calendar</h2>
            <p>Weekly view — <?= $weekStart->format('d M') ?> to <?= $weekEnd->format('d M Y') ?></p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a href="theatre.php" class="btn btn-secondary"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg> List View</a>
            <a href="create_operation.php" class="btn btn-primary"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Schedule</a>
        </div>
    </div>

    <!-- Week Navigation -->
    <div class="card" style="margin-bottom:24px">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <a href="?week=<?= $weekOffset - 1 ?>" class="btn btn-secondary">← Previous Week</a>
            <div style="text-align:center">
                <div style="font-weight:700;font-size:18px">
                    <?= $weekStart->format('d M Y') ?> – <?= $weekEnd->format('d M Y') ?>
                </div>
                <?php if ($weekOffset === 0): ?>
                <span class="badge badge-info" style="margin-top:4px">Current Week</span>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:8px">
                <?php if ($weekOffset !== 0): ?>
                <a href="?week=0" class="btn btn-secondary">Today</a>
                <?php endif; ?>
                <a href="?week=<?= $weekOffset + 1 ?>" class="btn btn-secondary">Next Week →</a>
            </div>
        </div>
    </div>

    <!-- Legend -->
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;align-items:center">
        <span style="font-size:13px;font-weight:600;color:var(--muted)">Legend:</span>
        <span style="display:flex;align-items:center;gap:6px;font-size:12px">
            <span style="width:12px;height:12px;background:var(--accent-light);border:1.5px solid var(--accent);border-radius:3px;display:inline-block"></span> Scheduled
        </span>
        <span style="display:flex;align-items:center;gap:6px;font-size:12px">
            <span style="width:12px;height:12px;background:var(--warning-light);border:1.5px solid var(--warning);border-radius:3px;display:inline-block"></span> In Progress
        </span>
        <span style="display:flex;align-items:center;gap:6px;font-size:12px">
            <span style="width:12px;height:12px;background:var(--success-light);border:1.5px solid var(--success);border-radius:3px;display:inline-block"></span> Completed
        </span>
        <span style="display:flex;align-items:center;gap:6px;font-size:12px">
            <span style="width:12px;height:12px;background:#f1f5f9;border:1.5px solid var(--border);border-radius:3px;display:inline-block"></span> Free
        </span>
    </div>

    <!-- Calendar Grid -->
    <div class="card" style="padding:0;overflow:hidden">
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;min-width:900px">
                <thead>
                    <tr style="background:var(--primary)">
                        <th style="padding:14px 16px;text-align:left;color:rgba(255,255,255,0.7);font-size:11px;text-transform:uppercase;letter-spacing:0.8px;width:130px">Theatre</th>
                        <?php foreach ($days as $day):
                            $isToday = $day->format('Y-m-d') === $today->format('Y-m-d');
                            $isWeekend = in_array($day->format('N'), ['6','7']);
                        ?>
                        <th style="padding:12px 8px;text-align:center;<?= $isToday ? 'background:rgba(14,165,233,0.30)' : '' ?>">
                            <div style="font-size:11px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:0.5px"><?= $day->format('D') ?></div>
                            <div style="font-size:20px;font-weight:700;color:<?= $isToday ? '#7dd3fc' : '#fff' ?>;line-height:1.2"><?= $day->format('j') ?></div>
                            <div style="font-size:10px;color:rgba(255,255,255,0.5)"><?= $day->format('M') ?></div>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($theatres as $tNum => [$tIcon, $tName, $tType]): ?>
                    <tr style="border-bottom:2px solid var(--border)">
                        <td style="padding:14px 16px;background:var(--bg);border-right:2px solid var(--border);vertical-align:top">
                            <div style="font-size:22px"><?= $tIcon ?></div>
                            <div style="font-weight:700;font-size:13px;margin-top:4px"><?= $tName ?></div>
                            <div style="font-size:11px;color:var(--muted)"><?= $tType ?></div>
                        </td>
                        <?php foreach ($days as $day):
                            $dateStr = $day->format('Y-m-d');
                            $dayOps  = $opsByTheatreDate[$tNum][$dateStr] ?? [];
                            $isToday = $dateStr === $today->format('Y-m-d');
                            $isPast  = $dateStr < $today->format('Y-m-d');
                        ?>
                        <td style="padding:8px;vertical-align:top;border-right:1px solid var(--border-light);min-height:80px;<?= $isToday ? 'background:#f0f9ff' : ($isPast ? 'background:#fafafa' : '') ?>">
                            <?php if (empty($dayOps)): ?>
                                <?php if (!$isPast): ?>
                                <a href="create_operation.php?date=<?= $dateStr ?>&theatre=<?= $tNum ?>"
                                   style="display:block;text-align:center;padding:8px 4px;border:1.5px dashed var(--border);border-radius:var(--radius-sm);color:var(--muted);font-size:11px;text-decoration:none;transition:all 0.2s"
                                   onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'"
                                   onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
                                    + Free
                                </a>
                                <?php else: ?>
                                <div style="text-align:center;color:var(--muted);font-size:11px;padding:8px">–</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php foreach ($dayOps as $op):
                                    $statusColors = [
                                        'scheduled'   => ['var(--accent-light)','var(--accent)','#075985'],
                                        'confirmed'   => ['var(--success-light)','var(--success)','#065f46'],
                                        'in_progress' => ['var(--warning-light)','var(--warning)','#92400e'],
                                        'completed'   => ['#d1fae5','#059669','#065f46'],
                                    ];
                                    [$bg, $border, $text] = $statusColors[$op['status']] ?? ['#f1f5f9','var(--border)','var(--muted)'];
                                ?>
                                <a href="operation_details.php?id=<?= $op['operation_id'] ?>"
                                   style="display:block;margin-bottom:4px;padding:7px 8px;background:<?= $bg ?>;border:1.5px solid <?= $border ?>;border-radius:var(--radius-sm);text-decoration:none;transition:opacity 0.2s"
                                   onmouseover="this.style.opacity='0.8'"
                                   onmouseout="this.style.opacity='1'">
                                    <div style="font-size:10px;font-weight:700;color:<?= $text ?>"><?= date('h:i A', strtotime($op['sched_time'])) ?></div>
                                    <div style="font-size:11px;font-weight:600;color:<?= $text ?>;line-height:1.3;margin-top:2px"><?= htmlspecialchars(mb_strimwidth($op['operation_type'], 0, 20, '…')) ?></div>
                                    <div style="font-size:10px;color:<?= $text ?>;opacity:0.7;margin-top:2px"><?= htmlspecialchars(mb_strimwidth($op['patient_name'], 0, 16, '…')) ?></div>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Weekly Summary -->
    <div style="margin-top:24px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
        <div class="stat-card">
            <div class="stat-icon blue"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0"><circle cx="12" cy="8" r="3"/><path d="M12 11v10M8 21h8M6 15h12M17 5l2-2M15 3l4 4"/></svg></div>
            <div>
                <div class="stat-label">Operations This Week</div>
                <div class="stat-value"><?= count($allOps) ?></div>
            </div>
        </div>
        <?php foreach ($theatres as $tNum => [$tIcon, $tName, $tType]):
            $count = array_sum(array_map('count', $opsByTheatreDate[$tNum] ?? []));
        ?>
        <div class="stat-card">
            <div class="stat-icon <?= ['blue','red','yellow','blue'][$tNum-1] ?>"><?= $tIcon ?></div>
            <div>
                <div class="stat-label"><?= $tName ?></div>
                <div class="stat-value"><?= $count ?></div>
                <div class="stat-change"><?= $count > 0 ? $count . ' operation' . ($count > 1 ? 's' : '') : 'No ops this week' ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>