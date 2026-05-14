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
            <h2>📝 Post-Operation Update</h2>
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
                <h3>🔬 <?= htmlspecialchars($op['operation_type']) ?></h3>
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
                            ['confirmed',   '✅','Confirmed',   'badge-success'],
                            ['in_progress', '⏳','In Progress', 'badge-warning'],
                            ['completed',   '🏆','Completed',   'badge-success'],
                            ['cancelled',   '✕', 'Cancelled',   'badge-danger'],
                            ['transferred', '🏥','Transferred', 'badge-neutral'],
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
                            ['icu',           '🔴','ICU',           'Intensive Care Unit'],
                            ['recovery_room', '🟡','Recovery Room', 'Post-anaesthesia care'],
                            ['private_room',  '🟢','Private Room',  'Individual patient room'],
                            ['general_ward',  '🔵','General Ward',  'Open ward bed'],
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
                    <button type="submit" class="btn btn-primary btn-lg">💾 Save Update</button>
                    <a href="operation_details.php?id=<?= $id ?>" class="btn btn-secondary btn-lg">Cancel</a>
                </div>

            </form>
        </div>

     

            <!-- Ward Transfer Notice -->
            <div class="card" style="border:1.5px solid var(--success);background:var(--success-light)">
                <div style="font-weight:700;color:#065f46;margin-bottom:8px">🏥 Auto Ward Transfer</div>
                <div style="font-size:13px;color:#065f46;line-height:1.7">
                    When status is set to <strong>Transferred</strong>, an admission record is automatically created in the ward / room module.
                </div>
            </div>

            <!-- Maternity Notice -->
            <?php if ($op['theatre_number'] == 3): ?>
            <div class="card" style="border:1.5px solid #f472b6;background:#fdf2f8">
                <div style="font-weight:700;color:#9d174d;margin-bottom:6px">👶 Labour Theatre</div>
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