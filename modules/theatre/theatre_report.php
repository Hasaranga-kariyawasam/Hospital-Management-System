<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
$requiredRoles = ['admin', 'doctor'];
require_once __DIR__ . '/../../includes/role_check.php';





$pageTitle  = 'Theatre Reports';
$useSidebar = true;

// ── Date range filter ─────────────────────────────────────────
$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');

// ── Overall stats ─────────────────────────────────────────────
$overall = $pdo->prepare("
    SELECT
        COUNT(*)                                AS total,
        SUM(status = 'completed')               AS completed,
        SUM(status = 'cancelled')               AS cancelled,
        SUM(status IN ('scheduled','confirmed')) AS pending,
        SUM(status = 'in_progress')             AS in_progress
    FROM theatre_operations
    WHERE scheduled_date BETWEEN ? AND ?
");
$overall->execute([$fromDate, $toDate]);
$overall = $overall->fetch();

// ── By Theatre ────────────────────────────────────────────────
$byTheatre = $pdo->prepare("
    SELECT theatre_number, COUNT(*) AS total,
           SUM(status = 'completed') AS completed,
           SUM(status = 'cancelled') AS cancelled
    FROM   theatre_operations
    WHERE  scheduled_date BETWEEN ? AND ?
    GROUP  BY theatre_number
    ORDER  BY theatre_number
");
$byTheatre->execute([$fromDate, $toDate]);
$byTheatre = $byTheatre->fetchAll();

// ── By Surgeon ────────────────────────────────────────────────
$bySurgeon = $pdo->prepare("
    SELECT u.full_name, d.specialization,
           COUNT(*) AS total,
           SUM(o.status = 'completed') AS completed
    FROM   theatre_operations o
    JOIN   users   u ON u.user_id = o.surgeon_id
    JOIN   doctors d ON d.user_id = o.surgeon_id
    WHERE  o.scheduled_date BETWEEN ? AND ?
    GROUP  BY o.surgeon_id
    ORDER  BY total DESC
    LIMIT  10
");
$bySurgeon->execute([$fromDate, $toDate]);
$bySurgeon = $bySurgeon->fetchAll();

// ── By Operation Type ─────────────────────────────────────────
$byType = $pdo->prepare("
    SELECT operation_type, COUNT(*) AS total,
           SUM(status = 'completed') AS completed
    FROM   theatre_operations
    WHERE  scheduled_date BETWEEN ? AND ?
    GROUP  BY operation_type
    ORDER  BY total DESC
    LIMIT  10
");
$byType->execute([$fromDate, $toDate]);
$byType = $byType->fetchAll();

// ── Recent Operations ─────────────────────────────────────────
$recent = $pdo->prepare("
    SELECT
        o.operation_id,
        o.operation_type,
        o.theatre_number,
        o.scheduled_date,
        o.status,
        pu.full_name AS patient_name,
        su.full_name AS surgeon_name
    FROM   theatre_operations o
    JOIN   patients pt ON pt.patient_id = o.patient_id
    JOIN   users    pu ON pu.user_id    = pt.user_id
    JOIN   users    su ON su.user_id    = o.surgeon_id
    WHERE  o.scheduled_date BETWEEN ? AND ?
    ORDER  BY o.scheduled_date DESC, o.scheduled_time DESC
    LIMIT  20
");
$recent->execute([$fromDate, $toDate]);
$recent = $recent->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="main-content">

    <div class="page-header">
        <div class="page-header-title">
            <h2>📊 Theatre Reports</h2>
            <p>Operation statistics and usage summary</p>
        </div>
        <a href="theatre.php" class="btn btn-secondary">← Back to Schedule</a>
    </div>

    <!-- Date Filter -->
    <div class="card" style="margin-bottom:24px">
        <form method="GET" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">From Date</label>
                <input type="date" name="from" class="form-control" value="<?= $fromDate ?>">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">To Date</label>
                <input type="date" name="to" class="form-control" value="<?= $toDate ?>">
            </div>
            <button type="submit" class="btn btn-primary">🔍 Generate Report</button>
            <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-secondary">This Month</a>
        </form>
    </div>

    <!-- Overall Stats -->
    <div class="stat-grid" style="margin-bottom:28px">
        <div class="stat-card">
            <div class="stat-icon blue">🔬</div>
            <div>
                <div class="stat-label">Total Operations</div>
                <div class="stat-value"><?= $overall['total'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div>
                <div class="stat-label">Completed</div>
                <div class="stat-value"><?= $overall['completed'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow">⏳</div>
            <div>
                <div class="stat-label">Pending / Scheduled</div>
                <div class="stat-value"><?= $overall['pending'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">✕</div>
            <div>
                <div class="stat-label">Cancelled</div>
                <div class="stat-value"><?= $overall['cancelled'] ?? 0 ?></div>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">

        <!-- By Theatre -->
        <div class="card">
            <div class="card-header">
                <h3>🏥 Usage by Theatre</h3>
            </div>
            <?php if (empty($byTheatre)): ?>
                <p style="color:var(--muted);text-align:center;padding:24px">No data for selected period.</p>
            <?php else: ?>
            <?php
            $theatreLabels = [
                1 => ['🏥','Theatre 1','General'],
                2 => ['🚨','Theatre 2','Emergency'],
                3 => ['👶','Theatre 3','Labour'],
                4 => ['🔧','Theatre 4','Minor'],
            ];
            $maxTotal = max(array_column($byTheatre, 'total')) ?: 1;
            foreach ($byTheatre as $row):
                $tl = $theatreLabels[$row['theatre_number']] ?? ['🔬','Theatre '.$row['theatre_number'],''];
                $pct = round(($row['total'] / $maxTotal) * 100);
            ?>
            <div style="margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;margin-bottom:5px">
                    <span style="font-size:13px;font-weight:600"><?= $tl[0] ?> <?= $tl[1] ?> <span style="color:var(--muted);font-weight:400">(<?= $tl[2] ?>)</span></span>
                    <span style="font-size:13px;font-weight:700"><?= $row['total'] ?> ops</span>
                </div>
                <div style="height:8px;background:var(--bg);border-radius:999px;overflow:hidden">
                    <div style="height:100%;width:<?= $pct ?>%;background:var(--accent);border-radius:999px;transition:width 0.5s"></div>
                </div>
                <div style="display:flex;gap:12px;margin-top:4px;font-size:11px;color:var(--muted)">
                    <span>✅ <?= $row['completed'] ?> completed</span>
                    <span>✕ <?= $row['cancelled'] ?> cancelled</span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- By Surgeon -->
        <div class="card">
            <div class="card-header">
                <h3>👨‍⚕️ Operations by Surgeon</h3>
            </div>
            <?php if (empty($bySurgeon)): ?>
                <p style="color:var(--muted);text-align:center;padding:24px">No data for selected period.</p>
            <?php else: ?>
            <div class="table-wrap" style="border:none">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Surgeon</th>
                            <th>Specialization</th>
                            <th>Total</th>
                            <th>Done</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($bySurgeon as $row): ?>
                        <tr>
                            <td><strong>Dr. <?= htmlspecialchars($row['full_name']) ?></strong></td>
                            <td><span style="color:var(--muted);font-size:12px"><?= htmlspecialchars($row['specialization']) ?></span></td>
                            <td><strong><?= $row['total'] ?></strong></td>
                            <td><span class="badge badge-success"><?= $row['completed'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- By Operation Type -->
    <div class="card" style="margin-bottom:24px">
        <div class="card-header">
            <h3>🔬 Top Operation Types</h3>
        </div>
        <?php if (empty($byType)): ?>
            <p style="color:var(--muted);text-align:center;padding:24px">No data for selected period.</p>
        <?php else: ?>
        <?php
        $maxType = max(array_column($byType, 'total')) ?: 1;
        foreach ($byType as $i => $row):
            $pct = round(($row['total'] / $maxType) * 100);
        ?>
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px">
            <div style="width:24px;height:24px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0"><?= $i+1 ?></div>
            <div style="flex:1">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                    <span style="font-weight:600;font-size:13px"><?= htmlspecialchars($row['operation_type']) ?></span>
                    <span style="font-size:13px;color:var(--muted)"><?= $row['total'] ?> × (<?= $row['completed'] ?> done)</span>
                </div>
                <div style="height:7px;background:var(--bg);border-radius:999px;overflow:hidden">
                    <div style="height:100%;width:<?= $pct ?>%;background:var(--accent);border-radius:999px"></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Recent Operations Table -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Recent Operations</h3>
            <span class="badge badge-info"><?= count($recent) ?> shown</span>
        </div>
        <?php if (empty($recent)): ?>
            <p style="text-align:center;padding:24px;color:var(--muted)">No operations in the selected date range.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Patient</th>
                        <th>Operation</th>
                        <th>Theatre</th>
                        <th>Date</th>
                        <th>Surgeon</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $theatreLabels2 = [1=>'T1',2=>'T2',3=>'T3',4=>'T4'];
                $badgeMap = [
                    'scheduled'=>'badge-info','confirmed'=>'badge-success',
                    'in_progress'=>'badge-warning','completed'=>'badge-success',
                    'cancelled'=>'badge-danger','transferred'=>'badge-neutral',
                ];
                foreach ($recent as $op): ?>
                    <tr>
                        <td><strong>#<?= $op['operation_id'] ?></strong></td>
                        <td><?= htmlspecialchars($op['patient_name']) ?></td>
                        <td><?= htmlspecialchars($op['operation_type']) ?></td>
                        <td><?= $theatreLabels2[$op['theatre_number']] ?? $op['theatre_number'] ?></td>
                        <td><?= date('d M Y', strtotime($op['scheduled_date'])) ?></td>
                        <td><?= htmlspecialchars($op['surgeon_name']) ?></td>
                        <td><span class="badge <?= $badgeMap[$op['status']] ?? 'badge-neutral' ?>"><?= ucfirst(str_replace('_',' ',$op['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>