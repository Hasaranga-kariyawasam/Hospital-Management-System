<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
$requiredRoles = ['admin', 'doctor'];
require_once __DIR__ . '/../../includes/role_check.php';





$pageTitle  = 'Theatre Schedule';
$useSidebar = true;



$where  = ['1=1'];
$params = [];



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
            <h2><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><circle cx="12" cy="8" r="3"/><path d="M12 11v10M8 21h8M6 15h12M17 5l2-2M15 3l4 4"/></svg> Theatre Schedule</h2>
            <p>Manage and view all scheduled operations</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            
            <a href="create_operation.php" class="btn btn-primary"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Schedule Operation</a>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0"><circle cx="12" cy="8" r="3"/><path d="M12 11v10M8 21h8M6 15h12M17 5l2-2M15 3l4 4"/></svg></div>
            <div>
                <div class="stat-label">Total Operations</div>
                <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
            <div>
                <div class="stat-label">Today's Operations</div>
                <div class="stat-value"><?= $stats['today'] ?? 0 ?></div>
            </div>
        </div>
      
    </div>

    

    <!-- Operations Table -->
    <div class="card">
        <div class="card-header">
            <h3>Operations List</h3>
            <span class="badge badge-info"><?= count($operations) ?> records</span>
        </div>

        <?php if (empty($operations)): ?>
            <div style="text-align:center;padding:48px;color:var(--muted)">
                <div style="font-size:48px;margin-bottom:12px"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0"><circle cx="12" cy="8" r="3"/><path d="M12 11v10M8 21h8M6 15h12M17 5l2-2M15 3l4 4"/></svg></div>
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
                                1 => 'T1 – General',
                                2 => 'T2 – Emergency',
                                3 => 'T3 – Labour',
                                4 => 'T4 – Minor',
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
                      
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <a href="operation_details.php?id=<?= $op['operation_id'] ?>" class="btn btn-sm btn-secondary">View</a>
                               
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