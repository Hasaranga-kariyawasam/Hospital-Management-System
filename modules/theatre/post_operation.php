<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../includes/auth_guard.php';

requireRole(['admin', 'doctor']);

$pageTitle  = 'Post-Operation Update';
$useSidebar = true;

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: theatre_schedule.php'); exit(); }

// ── Fetch operation ───────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT o.*, u.full_name AS patient_name, p.patient_id AS pid
    FROM theatre_operations o
    JOIN patients p ON o.patient_id = p.patient_id
    JOIN users    u ON p.user_id    = u.user_id
    WHERE o.operation_id = ?
");
$stmt->execute([$id]);
$op = $stmt->fetch();

if (!$op) { header('Location: theatre_schedule.php'); exit(); }

// ── Default billing fee structure ─────────────────────────────────
$defaultFees = [
    'Surgery Fee'       => 15000.00,
    'Theatre Usage Fee' => 8000.00,
    'Anaesthesia Fee'   => 6000.00,
    'Recovery Charge'   => 3000.00,
];

$errors  = [];
$success = false;

// ── POST handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postOpNotes    = trim($_POST['post_op_notes']         ?? '');
    $recoveryInstr  = trim($_POST['recovery_instructions'] ?? '');
    $roomType       = trim($_POST['post_op_room_type']     ?? '');
    $wardId         = (int)($_POST['ward_id'] ?? 0) ?: null;

    $validRooms = ['ICU', 'Recovery Room', 'Private Room', 'General Ward'];
    if (!in_array($roomType, $validRooms)) $errors[] = 'Please select a post-op destination.';
    if ($postOpNotes === '')               $errors[] = 'Post-op notes are required.';

    // Billing amounts (editable)
    $billAmounts = [];
    foreach ($defaultFees as $type => $_) {
        $key = 'fee_' . strtolower(str_replace([' ', '/'], ['_', '_'], $type));
        $billAmounts[$type] = (float)($_POST[$key] ?? 0);
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // 1. Update operation record
            $pdo->prepare("
                UPDATE theatre_operations
                SET status = 'Completed',
                    post_op_notes          = ?,
                    recovery_instructions  = ?,
                    post_op_room_type      = ?,
                    billing_triggered      = 1
                WHERE operation_id = ?
            ")->execute([$postOpNotes, $recoveryInstr, $roomType, $id]);

            // 2. Insert billing items
            $ins = $pdo->prepare("
                INSERT INTO theatre_billing_items
                    (operation_id, patient_id, item_type, amount, description)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($billAmounts as $type => $amount) {
                if ($amount > 0) {
                    $ins->execute([
                        $id,
                        $op['patient_id'],
                        $type,
                        $amount,
                        "Auto-generated from Operation #{$id} — {$op['operation_type']}",
                    ]);
                }
            }

            // 3. Ward transfer — create admission/transfer record if ward selected
            if ($wardId) {
                // Link to ward/admission module
                $pdo->prepare("
                    INSERT INTO ward_admissions
                        (patient_id, room_type, source, source_id, admitted_by, notes, status)
                    VALUES (?, ?, 'theatre', ?, ?, ?, 'active')
                ")->execute([
                    $op['patient_id'],
                    $roomType,
                    $id,
                    $_SESSION['user_id'],
                    "Post-op transfer from Operation #{$id} ({$op['operation_type']}). {$recoveryInstr}",
                ]);
            }

            // 4. Update operation status to Transferred if ward assigned
            if ($wardId) {
                $pdo->prepare("UPDATE theatre_operations SET status = 'Transferred' WHERE operation_id = ?")
                    ->execute([$id]);
            }

            $pdo->commit();
            $success = true;

        } catch (\Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// ── Fetch available wards ─────────────────────────────────────────
// (Only if your ward module has a rooms/wards table)
$wards = [];
try {
    $wards = $pdo->query("
        SELECT room_id, room_number, room_type, floor
        FROM rooms
        WHERE status = 'available'
        ORDER BY room_type, room_number
    ")->fetchAll();
} catch (\Throwable $e) {
    // Ward table may not exist yet — silently skip
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📋 Post-Operation Update</h1>
        <p class="page-sub">
            <?= htmlspecialchars($op['patient_name']) ?> —
            <?= htmlspecialchars($op['operation_type']) ?>
            (<?= date('d M Y', strtotime($op['scheduled_date'])) ?>)
        </p>
    </div>
    <a href="operation_details.php?id=<?= $id ?>" class="btn btn-secondary">← Back to Operation</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success" style="margin-bottom:20px">
        ✅ Post-op record saved! Billing items generated and patient transfer recorded.
        <div style="margin-top:10px;display:flex;gap:10px">
            <a href="operation_details.php?id=<?= $id ?>" class="btn btn-sm btn-primary">View Operation</a>
            <?php if ($op['is_maternity']): ?>
                <a href="../newborn/newborn_record.php?from_op=<?= $id ?>&patient=<?= $op['patient_id'] ?>"
                   class="btn btn-sm btn-secondary">🍼 Create Newborn Record</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" style="margin-bottom:20px">
        <?php foreach ($errors as $e): ?>
            <div>⚠️ <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!$success): ?>
<form method="POST">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

        <!-- Left: Clinical Notes -->
        <div>
            <div class="card" style="margin-bottom:20px">
                <div class="card-header"><h3>📝 Post-Op Clinical Notes</h3></div>
                <div style="padding:20px;display:flex;flex-direction:column;gap:16px">
                    <div class="form-group">
                        <label class="form-label">Post-Op Notes <span style="color:var(--danger)">*</span></label>
                        <textarea name="post_op_notes" class="form-control" rows="5"
                                  placeholder="Describe the outcome of the operation, patient condition, complications if any..."
                                  required><?= htmlspecialchars($_POST['post_op_notes'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Recovery Instructions</label>
                        <textarea name="recovery_instructions" class="form-control" rows="4"
                                  placeholder="Medications, diet restrictions, follow-up instructions, wound care..."><?= htmlspecialchars($_POST['recovery_instructions'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Ward Transfer -->
            <div class="card">
                <div class="card-header"><h3>🛏️ Post-Op Transfer</h3></div>
                <div style="padding:20px;display:flex;flex-direction:column;gap:16px">
                    <div class="form-group">
                        <label class="form-label">Transfer Destination <span style="color:var(--danger)">*</span></label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:6px">
                            <?php foreach (['ICU','Recovery Room','Private Room','General Ward'] as $room): ?>
                            <label style="display:flex;align-items:center;gap:8px;padding:10px;border:2px solid var(--border);border-radius:8px;cursor:pointer"
                                   id="dest-<?= str_replace(' ','-',$room) ?>">
                                <input type="radio" name="post_op_room_type" value="<?= htmlspecialchars($room) ?>"
                                       <?= ($_POST['post_op_room_type'] ?? '') === $room ? 'checked' : '' ?>
                                       required style="accent-color:var(--primary)">
                                <div>
                                    <div style="font-weight:500"><?= htmlspecialchars($room) ?></div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (!empty($wards)): ?>
                    <div class="form-group">
                        <label class="form-label">Assign Specific Room/Ward</label>
                        <select name="ward_id" class="form-control">
                            <option value="">— No specific room —</option>
                            <?php foreach ($wards as $w): ?>
                                <option value="<?= $w['room_id'] ?>">
                                    Room <?= htmlspecialchars($w['room_number']) ?>
                                    (<?= htmlspecialchars($w['room_type']) ?>, Floor <?= htmlspecialchars($w['floor']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Billing -->
        <div>
            <div class="card">
                <div class="card-header">
                    <h3>💳 Auto-Generate Billing</h3>
                    <small style="color:var(--muted)">These will be added to the patient's invoice</small>
                </div>
                <div style="padding:20px;display:flex;flex-direction:column;gap:12px">

                    <?php foreach ($defaultFees as $type => $amount):
                        $key = 'fee_' . strtolower(str_replace([' ', '/'], ['_', '_'], $type));
                        $icons = [
                            'Surgery Fee'       => '🔬',
                            'Theatre Usage Fee' => '🏨',
                            'Anaesthesia Fee'   => '💉',
                            'Recovery Charge'   => '🛏️',
                        ];
                    ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:var(--bg-soft);border-radius:8px">
                        <div style="display:flex;align-items:center;gap:8px">
                            <span><?= $icons[$type] ?? '💳' ?></span>
                            <span style="font-weight:500"><?= htmlspecialchars($type) ?></span>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px">
                            <span style="color:var(--muted);font-size:13px">Rs.</span>
                            <input type="number" name="<?= $key ?>" class="form-control"
                                   style="width:120px;text-align:right"
                                   min="0" step="0.01"
                                   value="<?= htmlspecialchars((string)($_POST[$key] ?? $amount)) ?>">
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div style="padding-top:12px;border-top:1px solid var(--border)">
                        <div style="display:flex;justify-content:space-between;font-weight:600;font-size:1.05rem">
                            <span>Total Theatre Charges</span>
                            <span id="totalDisplay">Rs. <?= number_format(array_sum($defaultFees), 2) ?></span>
                        </div>
                        <p style="color:var(--muted);font-size:12px;margin-top:6px">
                            ℹ️ Additional surgeon and specialist fees may be billed separately.
                        </p>
                    </div>
                </div>
            </div>

            <?php if ($op['is_maternity']): ?>
            <div class="card" style="margin-top:20px;border:2px solid #f9a8d4">
                <div class="card-header" style="background:#fdf2f8">
                    <h3 style="color:#be185d">🤱 Maternity — Newborn Record</h3>
                </div>
                <div style="padding:16px">
                    <p style="margin:0;color:var(--muted);font-size:14px">
                        After saving the post-op record, you can create a newborn record linked to this delivery.
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:24px;justify-content:flex-end">
        <a href="operation_details.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary btn-lg">
            ✅ Complete Post-Op & Generate Billing
        </button>
    </div>
</form>
<?php endif; ?>

<script>
// Live billing total
const feeInputs = document.querySelectorAll('input[type=number]');
const totalEl   = document.getElementById('totalDisplay');
function updateTotal() {
    let t = 0;
    feeInputs.forEach(i => t += parseFloat(i.value) || 0);
    if (totalEl) totalEl.textContent = 'Rs. ' + t.toLocaleString('en-LK', {minimumFractionDigits:2,maximumFractionDigits:2});
}
feeInputs.forEach(i => i.addEventListener('input', updateTotal));

// Highlight selected destination
document.querySelectorAll('input[name=post_op_room_type]').forEach(r => {
    r.addEventListener('change', function() {
        document.querySelectorAll('label[id^=dest-]').forEach(l => {
            l.style.borderColor = 'var(--border)';
            l.style.background  = '';
        });
        const lbl = this.closest('label');
        if (lbl) {
            lbl.style.borderColor = 'var(--primary)';
            lbl.style.background  = 'var(--primary-light, #eff6ff)';
        }
    });
    if (r.checked) r.dispatchEvent(new Event('change'));
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
