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
    1 => ['🏥', 'Theatre 1', 'General'],
    2 => ['🚨', 'Theatre 2', 'Emergency'],
    3 => ['👶', 'Theatre 3', 'Labour'],
    4 => ['🔧', 'Theatre 4', 'Minor'],
];
$tl = $theatreLabels[$op['theatre_number']] ?? ['🔬', 'Theatre ' . $op['theatre_number'], ''];
?>

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="main-content">

    <?php if ($created): ?>
        <div class="alert alert-success">✅ Operation scheduled successfully! ID: #<?= $id ?></div>
    <?php endif; ?>
    <?php if ($updated): ?>
        <div class="alert alert-success">✅ Operation updated successfully.</div>
    <?php endif; ?>

    <div class="page-header">
        <div class="page-header-title">
            <h2>🔬 Operation #<?= $id ?></h2>
            <p><?= htmlspecialchars($op['operation_type']) ?> &mdash; <?= date('d M Y', strtotime($op['scheduled_date'])) ?></p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php if (in_array($op['status'], ['scheduled', 'confirmed', 'in_progress'])): ?>
                <a href="post_op_update.php?id=<?= $id ?>" class="btn btn-success">📝 Update Operation</a>
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
                    <h3>👤 Patient Information</h3>
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
                    <h3>👨‍⚕️ Surgical Team</h3>
                </div>
                <div style="display:flex;flex-direction:column;gap:12px">
                    <?php
                    $team = [
                        ['🔬','Lead Surgeon',       $op['surgeon_name'] . ' – ' . $op['surgeon_spec']],
                        ['💉','Anaesthesiologist',  $op['anaesthetist_name'] ?? 'Not Assigned'],
                        ['🤝','Assisting Doctor',   $op['assistant_name'] ?? 'Not Assigned'],
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
                    <h3>📋 Clinical Notes</h3>
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
                    <h3>🗓️ Schedule Info</h3>
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
                    <span style="font-size:24px">🏥</span>
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

            <!-- Billing Trigger Card -->
            <?php if ($op['status'] === 'completed'): ?>
            <div class="card" style="border:1.5px solid var(--accent);background:var(--accent-light)">
                <div style="font-weight:700;color:#075985;margin-bottom:8px">💳 Billing Auto-Added</div>
                <div style="font-size:13px;color:#075985">
                    Theatre fees have been automatically added to the patient's invoice.
                </div>
                <div style="display:flex;flex-direction:column;gap:4px;margin-top:10px">
                    <?php
                    $charges = ['Surgery Fee','Theatre Usage Fee','Anaesthesia Fee','Recovery Charge'];
                    foreach ($charges as $c): ?>
                    <div style="font-size:12px;display:flex;align-items:center;gap:6px;color:#075985">
                        <span>✓</span> <?= $c ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Maternity Link -->
            <?php if ($op['theatre_number'] == 3 && $op['status'] === 'completed'): ?>
            <div class="card" style="border:1.5px solid #f472b6;background:#fdf2f8">
                <div style="font-weight:700;color:#9d174d;margin-bottom:8px">👶 Maternity Link</div>
                <div style="font-size:13px;color:#9d174d;margin-bottom:12px">Labour theatre operation completed. Register newborn record.</div>
                <a href="../newborn/add_newborn.php?mother_id=<?= $op['patient_id'] ?>&op_id=<?= $id ?>"
                   class="btn btn-sm" style="background:#ec4899;color:#fff;border:none">
                    ➕ Add Newborn Record
                </a>
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