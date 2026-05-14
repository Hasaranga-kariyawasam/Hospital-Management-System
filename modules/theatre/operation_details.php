<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
$requiredRoles = ['admin', 'doctor'];
require_once __DIR__ . '/../../includes/role_check.php';





$pageTitle  = 'Operation Details';
$useSidebar = true;

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: theatre.php'); exit; }

$op = $pdo->prepare("
    SELECT
        o.*,
        pu.full_name  AS patient_name,
        pt.nic,
        pt.dob,
        pt.gender,
        pt.blood_type,
        pt.phone      AS patient_phone,
        su.full_name  AS surgeon_name,
        sd.specialization AS surgeon_spec,
        au.full_name  AS anaesthetist_name,
        asu.full_name AS assistant_name,
        cu.full_name  AS created_by_name
    FROM   theatre_operations o
    JOIN   patients  pt  ON pt.patient_id  = o.patient_id
    JOIN   users     pu  ON pu.user_id     = pt.user_id
    JOIN   users     su  ON su.user_id     = o.surgeon_id
    JOIN   doctors   sd  ON sd.user_id     = o.surgeon_id
    LEFT JOIN users  au  ON au.user_id     = o.anaesthetist_id
    LEFT JOIN users  asu ON asu.user_id    = o.assistant_doctor_id
    LEFT JOIN users  cu  ON cu.user_id     = o.created_by
    WHERE  o.operation_id = ?
");
$op->execute([$id]);
$op = $op->fetch();

if (!$op) { header('Location: theatre.php'); exit; }

$created  = isset($_GET['created']);
$updated  = isset($_GET['updated']);

include __DIR__ . '/../../includes/header.php';

$statusBadge = [
    'scheduled'   => 'badge-info',
    'confirmed'   => 'badge-success',
    'in_progress' => 'badge-warning',
    'completed'   => 'badge-success',
    'cancelled'   => 'badge-danger',
    'transferred' => 'badge-neutral',
];
$badgeClass = $statusBadge[$op['status']] ?? 'badge-neutral';

$theatreLabels = [
    1 => ['<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><line x1="12" y1="12" x2="12" y2="18"/><line x1="9" y1="15" x2="15" y2="15"/></svg>', 'Theatre 1', 'General'],
    2 => ['<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>', 'Theatre 2', 'Emergency'],
    3 => ['<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M9 12h.01M15 12h.01M10 16c.5.3 1.1.5 2 .5s1.5-.2 2-.5"/><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z"/></svg>', 'Theatre 3', 'Labour'],
    4 => ['<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>', 'Theatre 4', 'Minor'],
];
$tl = $theatreLabels[$op['theatre_number']] ?? ['<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><circle cx="12" cy="8" r="3"/><path d="M12 11v10M8 21h8M6 15h12M17 5l2-2M15 3l4 4"/></svg>', 'Theatre ' . $op['theatre_number'], ''];
?>

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="main-content">

    <?php if ($created): ?>
        <div class="alert alert-success"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><polyline points="20 6 9 17 4 12"/></svg> Operation scheduled successfully! ID: #<?= $id ?></div>
    <?php endif; ?>
    <?php if ($updated): ?>
        <div class="alert alert-success"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><polyline points="20 6 9 17 4 12"/></svg> Operation updated successfully.</div>
    <?php endif; ?>

    <div class="page-header">
        <div class="page-header-title">
            <h2><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><circle cx="12" cy="8" r="3"/><path d="M12 11v10M8 21h8M6 15h12M17 5l2-2M15 3l4 4"/></svg> Operation #<?= $id ?></h2>
            <p><?= htmlspecialchars($op['operation_type']) ?> &mdash; <?= date('d M Y', strtotime($op['scheduled_date'])) ?></p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php if (in_array($op['status'], ['scheduled', 'confirmed', 'in_progress'])): ?>
                <a href="post_op_update.php?id=<?= $id ?>" class="btn btn-success"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Update Operation</a>
            <?php endif; ?>
            <a href="theatre.php" class="btn btn-secondary">← Back</a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start">

        <!-- Left Column -->
        <div style="display:flex;flex-direction:column;gap:20px">

            <!-- Status Banner -->
            <div style="padding:20px 24px;background:var(--surface);border-radius:var(--radius);border:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;gap:16px;box-shadow:var(--shadow-sm)">
                <div style="display:flex;align-items:center;gap:14px">
                    <span style="font-size:36px"><?= $tl[0] ?></span>
                    <div>
                        <div style="font-size:18px;font-weight:700"><?= htmlspecialchars($op['operation_type']) ?></div>
                        <div style="color:var(--muted);font-size:13px"><?= $tl[1] ?> (<?= $tl[2] ?>) &mdash; <?= date('d M Y', strtotime($op['scheduled_date'])) ?> at <?= date('h:i A', strtotime($op['scheduled_time'])) ?></div>
                    </div>
                </div>
                <span class="badge <?= $badgeClass ?>" style="font-size:13px;padding:6px 14px"><?= ucfirst(str_replace('_',' ',$op['status'])) ?></span>
            </div>

            <!-- Patient Details -->
            <div class="card">
                <div class="card-header">
                    <h3><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Patient Information</h3>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <?php
                    $fields = [
                        ['Name',       $op['patient_name']],
                        ['NIC',        $op['nic']],
                        ['Gender',     ucfirst($op['gender'])],
                        ['DOB',        date('d M Y', strtotime($op['dob']))],
                        ['Blood Type', $op['blood_type'] ?? '–'],
                        ['Phone',      $op['patient_phone']],
                    ];
                    foreach ($fields as [$label, $val]): ?>
                    <div style="padding:12px;background:var(--bg);border-radius:var(--radius-sm)">
                        <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px"><?= $label ?></div>
                        <div style="font-weight:600"><?= htmlspecialchars($val) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Surgical Team -->
            <div class="card">
                <div class="card-header">
                    <h3><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="12" y1="11" x2="12" y2="15"/><line x1="10" y1="13" x2="14" y2="13"/></svg> Surgical Team</h3>
                </div>
                <div style="display:flex;flex-direction:column;gap:12px">
                    <?php
                    $team = [
                        ['<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><circle cx="12" cy="8" r="3"/><path d="M12 11v10M8 21h8M6 15h12M17 5l2-2M15 3l4 4"/></svg>','Lead Surgeon',       $op['surgeon_name'] . ' – ' . $op['surgeon_spec']],
                        ['<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M18 2l4 4-12 12H6v-4L18 2z"/><line x1="9.5" y1="9.5" x2="14.5" y2="14.5"/><path d="M2 22l4-4"/></svg>','Anaesthesiologist',  $op['anaesthetist_name'] ?? 'Not Assigned'],
                        ['<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M20.42 4.58a5.4 5.4 0 0 0-7.65 0l-.77.78-.77-.78a5.4 5.4 0 0 0-7.65 0C1.46 6.7 1.33 10.28 4 13l8 8 8-8c2.67-2.72 2.54-6.3.42-8.42z"/></svg>','Assisting Doctor',   $op['assistant_name'] ?? 'Not Assigned'],
                    ];
                    foreach ($team as [$icon,$role,$name]): ?>
                    <div style="display:flex;align-items:center;gap:14px;padding:14px;background:var(--bg);border-radius:var(--radius-sm)">
                        <span style="font-size:24px;width:36px;text-align:center"><?= $icon ?></span>
                        <div>
                            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px"><?= $role ?></div>
                            <div style="font-weight:600;color:<?= $name === 'Not Assigned' ? 'var(--muted)' : 'var(--text)' ?>">
                                <?= htmlspecialchars($name) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Clinical Notes -->
            <div class="card">
                <div class="card-header">
                    <h3><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg> Clinical Notes</h3>
                </div>
                <div style="display:flex;flex-direction:column;gap:16px">
                    <div>
                        <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">Pre-Op Notes</div>
                        <div style="padding:14px;background:var(--bg);border-radius:var(--radius-sm);color:var(--text-mid);font-size:14px;line-height:1.7;min-height:60px">
                            <?= $op['pre_op_notes'] ? nl2br(htmlspecialchars($op['pre_op_notes'])) : '<span style="color:var(--muted)">No pre-op notes recorded.</span>' ?>
                        </div>
                    </div>
                    <?php if ($op['post_op_notes']): ?>
                    <div>
                        <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">Post-Op Notes</div>
                        <div style="padding:14px;background:var(--success-light);border-radius:var(--radius-sm);color:var(--text-mid);font-size:14px;line-height:1.7">
                            <?= nl2br(htmlspecialchars($op['post_op_notes'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($op['recovery_instructions']): ?>
                    <div>
                        <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">Recovery Instructions</div>
                        <div style="padding:14px;background:var(--accent-light);border-radius:var(--radius-sm);color:var(--text-mid);font-size:14px;line-height:1.7">
                            <?= nl2br(htmlspecialchars($op['recovery_instructions'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Right Column -->
        <div style="display:flex;flex-direction:column;gap:20px">

            <!-- Schedule Card -->
            <div class="card">
                <div class="card-header">
                    <h3><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="14" x2="8" y2="14"/><line x1="12" y1="14" x2="12" y2="14"/></svg> Schedule Info</h3>
                </div>
                <div style="display:flex;flex-direction:column;gap:12px">
                    <?php
                    $schedFields = [
                        ['Date',    date('d M Y', strtotime($op['scheduled_date']))],
                        ['Time',    date('h:i A', strtotime($op['scheduled_time']))],
                        ['Theatre', $tl[0].' '.$tl[1].' ('.$tl[2].')'],
                    ];
                    foreach ($schedFields as [$label,$val]): ?>
                    <div style="display:flex;justify-content:space-between;padding:10px 14px;background:var(--bg);border-radius:var(--radius-sm)">
                        <span style="font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.4px"><?= $label ?></span>
                        <span style="font-weight:700;font-size:14px"><?= $val ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Post-Op Transfer Card -->
            <?php if ($op['post_op_room_type']): ?>
            <div class="card" style="border:1.5px solid var(--success);background:var(--success-light)">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                    <span style="font-size:24px"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><line x1="12" y1="12" x2="12" y2="18"/><line x1="9" y1="15" x2="15" y2="15"/></svg></span>
                    <div>
                        <div style="font-weight:700;color:#065f46">Post-Op Transfer</div>
                        <div style="font-size:13px;color:#065f46;opacity:0.8">Patient transferred to:</div>
                    </div>
                </div>
                <span class="badge badge-success" style="font-size:14px;padding:8px 16px">
                    <?= ucfirst(str_replace('_', ' ', $op['post_op_room_type'])) ?>
                </span>
            </div>
            <?php endif; ?>

            <!-- Meta Info -->
            <div class="card">
                <div style="font-size:12px;color:var(--muted);line-height:1.9">
                    <div><strong>Operation ID:</strong> #<?= $id ?></div>
                    <div><strong>Created By:</strong> <?= htmlspecialchars($op['created_by_name'] ?? '–') ?></div>
                    <div><strong>Created At:</strong> <?= date('d M Y h:i A', strtotime($op['created_at'])) ?></div>
                </div>
            </div>

        </div>
    </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>