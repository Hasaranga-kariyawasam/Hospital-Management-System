<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
$requiredRoles = ['admin', 'doctor'];
require_once __DIR__ . '/../../includes/role_check.php';





$pageTitle  = 'Post-Operation Update';
$useSidebar = true;

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: theatre.php'); exit; }

// Load operation
$stmt = $pdo->prepare("
    SELECT o.*, pu.full_name AS patient_name, pt.patient_id AS pat_id
    FROM   theatre_operations o
    JOIN   patients pt ON pt.patient_id = o.patient_id
    JOIN   users    pu ON pu.user_id    = pt.user_id
    WHERE  o.operation_id = ?
");
$stmt->execute([$id]);
$op = $stmt->fetch();

if (!$op) { header('Location: theatre.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus       = trim($_POST['status']               ?? '');
    $postOpNotes     = trim($_POST['post_op_notes']        ?? '');
    $recoveryInstr   = trim($_POST['recovery_instructions'] ?? '');
    $postOpRoomType  = trim($_POST['post_op_room_type']     ?? '');

    $allowedStatuses = ['scheduled','confirmed','in_progress','completed','cancelled','transferred'];

    if (!in_array($newStatus, $allowedStatuses)) {
        $error = 'Invalid status selected.';
    } else {
        // Update operation
        $upd = $pdo->prepare("
            UPDATE theatre_operations
            SET    status               = ?,
                   post_op_notes        = ?,
                   recovery_instructions= ?,
                   post_op_room_type    = ?
            WHERE  operation_id         = ?
        ");
        $upd->execute([$newStatus, $postOpNotes, $recoveryInstr, $postOpRoomType ?: null, $id]);

       
        // ── Auto ward transfer when transferred ───────────────
        if ($newStatus === 'transferred' && $postOpRoomType) {
            $roomTypeMap = [
                'icu'           => 'icu',
                'recovery_room' => 'semi_private',
                'private_room'  => 'private',
                'general_ward'  => 'semi_private',
            ];
            $dbRoomType = $roomTypeMap[$postOpRoomType] ?? 'semi_private';

            // Find available room of that type
            $room = $pdo->prepare("SELECT room_id FROM rooms WHERE room_type = ? AND is_available = 1 LIMIT 1");
            $room->execute([$dbRoomType]);
            $room = $room->fetch();

            if ($room) {
                // Create admission record
                $pdo->prepare("
                    INSERT INTO admissions
                        (patient_id, room_id, doctor_id, admitted_by, admission_date, status)
                    VALUES (?, ?, ?, ?, CURDATE(), 'admitted')
                ")->execute([$op['patient_id'], $room['room_id'], $op['surgeon_id'], $_SESSION['user_id']]);

                // Mark room unavailable
                $pdo->prepare("UPDATE rooms SET is_available = 0 WHERE room_id = ?")
                    ->execute([$room['room_id']]);
            }
        }

        header("Location: operation_details.php?id=$id&updated=1");
        exit();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="main-content">

    <div class="page-header">
        <div class="page-header-title">
            <h2><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Post-Operation Update</h2>
            <p>Update status, notes and patient transfer for Operation #<?= $id ?></p>
        </div>
        <a href="operation_details.php?id=<?= $id ?>" class="btn btn-secondary">← Back to Details</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start">

        <!-- Update Form -->
        <div class="card">
            <div class="card-header">
                <h3><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0"><circle cx="12" cy="8" r="3"/><path d="M12 11v10M8 21h8M6 15h12M17 5l2-2M15 3l4 4"/></svg> <?= htmlspecialchars($op['operation_type']) ?></h3>
                <span style="color:var(--muted);font-size:13px">
                    Patient: <strong><?= htmlspecialchars($op['patient_name']) ?></strong>
                    &mdash; <?= date('d M Y', strtotime($op['scheduled_date'])) ?>
                </span>
            </div>

            <form method="POST">

                <!-- Status -->
                <div class="form-group">
                    <label class="form-label">Operation Status <span style="color:var(--danger)">*</span></label>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:6px" id="statusGrid">
                        <?php
                        $statuses = [
                            ['confirmed',   '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><polyline points="20 6 9 17 4 12"/></svg>','Confirmed',   'badge-success'],
                            ['in_progress', '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>','In Progress', 'badge-warning'],
                            ['completed',   '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"/></svg>','Completed',   'badge-success'],
                            ['cancelled',   '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>', 'Cancelled',   'badge-danger'],
                            ['transferred', '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><line x1="12" y1="12" x2="12" y2="18"/><line x1="9" y1="15" x2="15" y2="15"/></svg>','Transferred', 'badge-neutral'],
                        ];
                        foreach ($statuses as [$val, $icon, $label, $cls]):
                            $current = $op['status'] === $val;
                        ?>
                        <label style="cursor:pointer">
                            <input type="radio" name="status" value="<?= $val ?>"
                                   <?= $current ? 'checked' : '' ?>
                                   style="display:none" class="status-radio">
                            <div class="status-option <?= $current ? 'selected' : '' ?>"
                                 style="padding:12px;border:2px solid var(--border);border-radius:var(--radius-sm);text-align:center;transition:all 0.2s;<?= $current ? 'border-color:var(--accent);background:var(--accent-light)' : '' ?>">
                                <div style="font-size:22px"><?= $icon ?></div>
                                <div style="font-size:12px;font-weight:700;margin-top:4px"><?= $label ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Post-Op Notes -->
                <div class="form-group">
                    <label class="form-label">Post-Op Notes</label>
                    <textarea name="post_op_notes" class="form-control" rows="4"
                              placeholder="Procedure summary, complications, observations..."><?= htmlspecialchars($op['post_op_notes'] ?? '') ?></textarea>
                </div>

                <!-- Recovery Instructions -->
                <div class="form-group">
                    <label class="form-label">Recovery Instructions</label>
                    <textarea name="recovery_instructions" class="form-control" rows="3"
                              placeholder="Medication, diet, activity restrictions, follow-up..."><?= htmlspecialchars($op['recovery_instructions'] ?? '') ?></textarea>
                </div>

                <!-- Post-Op Ward Transfer -->
                <div class="form-group" id="transferSection">
                    <label class="form-label">Post-Op Ward / Transfer Destination</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:6px">
                        <?php
                        $wards = [
                            ['icu',           '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="display:inline-block;vertical-align:middle"><circle cx="12" cy="12" r="8"/></svg>','ICU',           'Intensive Care Unit'],
                            ['recovery_room', '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle"><circle cx="12" cy="12" r="8"/><polyline points="12 8 12 12 14 14"/></svg>','Recovery Room', 'Post-anaesthesia care'],
                            ['private_room',  '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle"><circle cx="12" cy="12" r="8"/></svg>','Private Room',  'Individual patient room'],
                            ['general_ward',  '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1" style="display:inline-block;vertical-align:middle"><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="8" fill="none"/></svg>','General Ward',  'Open ward bed'],
                        ];
                        foreach ($wards as [$val, $dot, $label, $desc]):
                            $cur = $op['post_op_room_type'] === $val;
                        ?>
                        <label style="cursor:pointer">
                            <input type="radio" name="post_op_room_type" value="<?= $val ?>"
                                   <?= $cur ? 'checked' : '' ?>
                                   style="display:none" class="ward-radio">
                            <div class="ward-option"
                                 style="padding:14px;border:2px solid var(--border);border-radius:var(--radius-sm);transition:all 0.2s;<?= $cur ? 'border-color:var(--accent);background:var(--accent-light)' : '' ?>">
                                <div style="display:flex;align-items:center;gap:8px">
                                    <span style="font-size:16px"><?= $dot ?></span>
                                    <div>
                                        <div style="font-weight:700;font-size:13px"><?= $label ?></div>
                                        <div style="font-size:11px;color:var(--muted)"><?= $desc ?></div>
                                    </div>
                                </div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-hint">When status is "Transferred", patient will be auto-admitted to an available room.</div>
                </div>

                <div style="display:flex;gap:12px;margin-top:8px;padding-top:16px;border-top:1px solid var(--border-light)">
                    <button type="submit" class="btn btn-primary btn-lg"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save Update</button>
                    <a href="operation_details.php?id=<?= $id ?>" class="btn btn-secondary btn-lg">Cancel</a>
                </div>

            </form>
        </div>

     

            <!-- Ward Transfer Notice -->
            <div class="card" style="border:1.5px solid var(--success);background:var(--success-light)">
                <div style="font-weight:700;color:#065f46;margin-bottom:8px"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><line x1="12" y1="12" x2="12" y2="18"/><line x1="9" y1="15" x2="15" y2="15"/></svg> Auto Ward Transfer</div>
                <div style="font-size:13px;color:#065f46;line-height:1.7">
                    When status is set to <strong>Transferred</strong>, an admission record is automatically created in the ward / room module.
                </div>
            </div>

            <!-- Maternity Notice -->
            <?php if ($op['theatre_number'] == 3): ?>
            <div class="card" style="border:1.5px solid #f472b6;background:#fdf2f8">
                <div style="font-weight:700;color:#9d174d;margin-bottom:6px"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0" ><path d="M9 12h.01M15 12h.01M10 16c.5.3 1.1.5 2 .5s1.5-.2 2-.5"/><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z"/></svg> Labour Theatre</div>
                <div style="font-size:13px;color:#9d174d">After completing this operation, remember to add the newborn record via the Newborn module.</div>
            </div>
            <?php endif; ?>

        </div>
    </div>

</main>

<script>
// Status radio visual toggle
document.querySelectorAll('.status-radio').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.status-option').forEach(el => {
            el.style.borderColor = 'var(--border)';
            el.style.background  = '';
        });
        if (this.checked) {
            const box = this.parentElement.querySelector('.status-option');
            box.style.borderColor = 'var(--accent)';
            box.style.background  = 'var(--accent-light)';
        }
    });
});

// Ward radio visual toggle
document.querySelectorAll('.ward-radio').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.ward-option').forEach(el => {
            el.style.borderColor = 'var(--border)';
            el.style.background  = '';
        });
        if (this.checked) {
            const box = this.parentElement.querySelector('.ward-option');
            box.style.borderColor = 'var(--accent)';
            box.style.background  = 'var(--accent-light)';
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>