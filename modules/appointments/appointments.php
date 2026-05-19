<?php
// modules/appointments/appointments.php
// Doctor Schedule Planner — DB connected, layout fixed

declare(strict_types=1);

require_once '../../includes/session_check.php';
require_once '../../config/db_config.php';

$message     = '';
$messageType = 'success'; 

// ── Resolve logged-in doctor ──────────────────────────────────────────────────
$user_id = (int)$_SESSION['user_id'];

$stmtDoc = $pdo->prepare(
    "SELECT d.doctor_id, u.full_name, d.specialization
     FROM doctors d
     JOIN users u ON u.user_id = d.user_id
     WHERE d.user_id = :uid
     LIMIT 1"
);
$stmtDoc->execute([':uid' => $user_id]);
$docRow = $stmtDoc->fetch();

$doctor_id   = (int)($docRow['doctor_id']   ?? 0);
$doctor_name = $docRow['full_name']          ?? 'Doctor';
$specialization = $docRow['specialization']  ?? '';

// ── Handle form POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $doctor_id > 0) {

    $appt_date     = $_POST['date']              ?? '';
    $start_time    = $_POST['start_time']        ?? '';
    $end_time      = $_POST['end_time']          ?? '';
    $slot_duration = (int)($_POST['duration']    ?? 15);
    $total_slots   = (int)($_POST['total_slots_input'] ?? 0);

    // Validate inputs
    if (!$appt_date || !$start_time || !$end_time || $total_slots <= 0) {
        $message     = 'Please fill all fields correctly.';
        $messageType = 'error';
    } else {
        // day_of_week (0=Sun … 6=Sat) from the selected date
        $dow = (int)date('w', strtotime($appt_date));

        try {
            // Upsert: delete any existing slot for this doctor+day, then insert fresh
            $pdo->beginTransaction();

            $del = $pdo->prepare(
                "DELETE FROM doctor_schedules
                 WHERE doctor_id = :did AND day_of_week = :dow"
            );
            $del->execute([':did' => $doctor_id, ':dow' => $dow]);

            $ins = $pdo->prepare(
                "INSERT INTO doctor_schedules
                    (doctor_id, day_of_week, start_time, end_time, slot_duration)
                 VALUES
                    (:did, :dow, :start, :end, :dur)"
            );
            $ins->execute([
                ':did'   => $doctor_id,
                ':dow'   => $dow,
                ':start' => $start_time,
                ':end'   => $end_time,
                ':dur'   => $slot_duration,
            ]);

            $pdo->commit();

            $dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            $message  = "Schedule saved for {$dayNames[$dow]} ({$appt_date}) — {$total_slots} slots available.";

        } catch (\PDOException $e) {
            $pdo->rollBack();
            $message     = 'Database error: ' . htmlspecialchars($e->getMessage());
            $messageType = 'error';
        }
    }
}



$dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

// ── Page config for header/sidebar ───────────────────────────────────────────
$pageTitle  = 'Appointment Schedule';
$useSidebar = true;
$isPublic   = false;

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<main class="main-content">

    <!-- ── Flash message ── -->
    <?php if ($message !== ''): ?>
        <div class="mb-6 flex items-center gap-3 px-5 py-4 rounded-2xl text-sm font-medium
            <?= $messageType === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-700' : 'bg-red-50 border border-red-200 text-red-700' ?>">
          
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- ── Page header ── -->
    <div class="page-header">
        <div class="page-header-title">
            <h2>Plan Your Availability</h2>
            <p>Dr. <?= htmlspecialchars($doctor_name) ?></P>
              
        </div>
       
    </div>

    <!-- ── Main grid ── -->
    <div style="display:grid; grid-template-columns: 1fr 340px; gap: 28px; align-items: start;">

        <!-- LEFT: Form -->
        <div class="card" style="padding: 36px;">

            <div style="display:flex; align-items:center; gap:14px; margin-bottom:28px;">
                <div style="width:48px;height:48px;background:#eef2ff;border-radius:14px;display:flex;align-items:center;justify-content:center;color:#4f46e5;font-size:20px;">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <h3 style="font-size:1.2rem;font-weight:700;color:var(--text-dark);margin:0;">Set Appointment Slots</h3>
                    <p style="font-size:13px;color:var(--muted);margin:4px 0 0;">Your availability will be visible to patients for booking</p>
                </div>
            </div>

            <form action="" method="POST">

                <!-- Row 1 -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">

                    <div>
                        <label class="form-label">Appointment Date</label>
                        <input type="date" name="date" id="datePicker" required
                               class="form-input">
                    </div>

                    <div>
                        <label class="form-label">Time per Patient</label>
                        <select name="duration" id="duration" onchange="calculate()" class="form-input">
                            <option value="10">10 Min / Patient</option>
                            <option value="15" selected>15 Min / Patient</option>
                            <option value="20">20 Min / Patient</option>
                            <option value="30">30 Min / Patient</option>
                        </select>
                    </div>
                </div>

                <!-- Row 2 -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:28px;">

                    <div>
                        <label class="form-label">Session Start</label>
                        <input type="time" name="start_time" id="start" value="08:30"
                               onchange="calculate()" class="form-input">
                    </div>

                    <div>
                        <label class="form-label">Session End</label>
                        <input type="time" name="end_time" id="end" value="12:30"
                               onchange="calculate()" class="form-input">
                    </div>
                </div>

                <input type="hidden" name="total_slots_input" id="hidden_slots">

                <button type="submit" class="btn btn-primary" style="width:100%; padding:14px; font-size:15px;">
                    
                    Confirm Schedule
                </button>

            </form>
        </div>

        <!-- RIGHT: Slot counter + tip + existing schedule -->
        <div style="display:flex; flex-direction:column; gap:20px;">

            <!-- Slot counter -->
            <div class="card" style="padding:28px; text-align:center;">
                <p style="font-size:11px;font-weight:700;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase;margin:0 0 10px;">Available Slots</p>
                <div id="display" style="font-size:72px;font-weight:800;color:#4f46e5;line-height:1;">15</div>
                <p style="font-size:13px;color:var(--muted);margin:10px 0 0;">Patients can book for this session</p>
            </div>

           

        
            

        </div>
    </div>

</main>

<script>
    function calculate() {
        const start    = document.getElementById('start').value;
        const end      = document.getElementById('end').value;
        const duration = parseInt(document.getElementById('duration').value);

        if (start && end) {
            const [sh, sm] = start.split(':').map(Number);
            const [eh, em] = end.split(':').map(Number);
            const diff  = (eh * 60 + em) - (sh * 60 + sm);
            const count = diff > 0 ? Math.floor(diff / duration) : 0;

            document.getElementById('display').textContent    = count;
            document.getElementById('hidden_slots').value = count;
        }
    }

    document.getElementById('datePicker').valueAsDate = new Date();
    calculate();
</script>