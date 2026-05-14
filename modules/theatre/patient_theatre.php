<?php
// 1. Session Check
require_once '../../includes/session_check.php';

// 2. Database Connection
require_once __DIR__ . '/../../config/db_config.php';

// 3. Get patient data
$user_id = $_SESSION['user_id'];

// Patient ID ලබා ගැනීම
$stmt_p = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ? LIMIT 1");
$stmt_p->execute([$user_id]);
$patient_id = $stmt_p->fetchColumn();

// රෝගියාට අදාළ ශල්‍යකර්ම සියල්ල ලබා ගැනීම
$stmt_ops = $pdo->prepare("SELECT * FROM theatre_operations WHERE patient_id = ?");
$stmt_ops->execute([$patient_id]);
$theatre_operations = $stmt_ops->fetchAll(PDO::FETCH_ASSOC);

// ශල්‍යකර්ම සංඛ්‍යාව
$operation_count = count($theatre_operations);

// 4. Function for schedule details
function Schedule_Details($room='null', $time='none', $operation_id=0){
    $link = "view_prep_instructions.php?operation_id=$operation_id";
    echo <<<HTML
    <div style="background: #f8f9fa; padding: 15px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; margin-bottom:10px; border-left: 4px solid #ffa000;">
        <div style="display: flex; gap: 15px; align-items: center;">
            <div style="width: 10px; height: 10px; background: #ffa000; border-radius: 50%;"></div>
            <div>
                <div style="font-weight: 500; color: #333;">$room</div>
                <small style="color: #888;">$time</small>
            </div>
        </div>
        <div style="display:flex; gap:20px; align-items: center;">
            <a href="$link" style="color: #1a237e; font-weight: 600; font-size: 0.8rem; text-decoration: none;">View Prep Instructions</a>
        </div>
    </div> 
HTML;
}

// 5. Page settings
$pageTitle  = "Theatre Management";
$useSidebar = true;
$isPublic   = false;

// 6. Include header and sidebar
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --primary: #1a237e;
        --info: #0288d1;
        --success: #2e7d32;
        --bg-light: #f0f4f8;
    }

    .main-content {
        padding: 40px;
        max-width: 1400px;
        margin: auto;
    }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.02);
    }

    .icon-box {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        background: #e8eaf6;
        color: var(--primary);
    }

    .calendar-card {
        background: white;
        padding: 25px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
    }

    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 10px;
        text-align: center;
        margin-top: 15px;
    }

    .day {
        padding: 15px 5px;
        border-radius: 12px;
        background: #f8f9fa;
        font-size: 0.9rem;
        position: relative;
    }

    .day.active {
        background: var(--primary);
        color: white;
    }

    .day.scheduled,
    .day.active.scheduled {
        background: var(--success);
        color: white;
    }

    .day.has-event::after {
        content: '';
        position: absolute;
        bottom: 5px;
        left: 50%;
        transform: translateX(-50%);
        width: 5px;
        height: 5px;
        background: #ffa000;
        border-radius: 50%;
    }
</style>

<main class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="color: var(--primary); margin: 0; font-size: 1.8rem;">MediCare Patient Portal</h1>
            <p style="color: #777;">Operation Theatre Schedule & Status</p>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="icon-box"><i class="fas fa-user-check"></i></div>
            <div>
                <small style="color: #888;">Your Status</small>
                <div style="font-weight: 600;">Confirmed</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="icon-box" style="background: #e1f5fe; color: var(--info);"><i class="fas fa-notes-medical"></i>
            </div>
            <div>
                <small style="color: #888;">Total Operations</small>
                <div style="font-weight: 600; color: var(--primary);">
                    <?php echo $operation_count; ?> Scheduled
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="icon-box" style="background: #e8f5e9; color: var(--success);"><i class="fas fa-clock"></i></div>
            <div>
                <small style="color: #888;">Waiting Time</small>
                <div style="font-weight: 600;">~ 45 Mins</div>
            </div>
        </div>
    </div>

    <div class="calendar-card">
        <h3 style="color: var(--primary); margin-top: 0;">Surgery Calendar - May 2026</h3>
        <div class="calendar-grid">
            <div style="font-weight:600; color:#888;">MON</div>
            <div style="font-weight:600; color:#888;">TUE</div>
            <div style="font-weight:600; color:#888;">WED</div>
            <div style="font-weight:600; color:#888;">THU</div>
            <div style="font-weight:600; color:#888;">FRI</div>
            <div style="font-weight:600; color:#888;">SAT</div>
            <div style="font-weight:600; color:#888;">SUN</div>

            <?php
            $date_arr = [];
            if ($operation_count > 0) {
                foreach ($theatre_operations as $op) {
                    $temp_date = "";
                    // echo $temp_date = $op['scheduled_at'][5].$temp_date = $op['scheduled_at'][6]."  ";
                    if($op['scheduled_at'][8] == '0'){
                        $temp_date = $op['scheduled_at'][9];
                    }else{
                        $temp_date = $op['scheduled_at'][8] . $op['scheduled_at'][9];
                    }
                    array_push($date_arr,(int)$temp_date);
                }
            } else {
                echo '<p style="color: #888; text-align: center;">No operations found.</p>';
            }


            for ($day = 1; $day <= 31; $day++) {
                $class = 'day';
                if ($day == 14) $class .= ' active';
                if (in_array($day, [5, 13, 20])) $class .= ' has-event';
                echo "<div class='$class " .(in_array($day,$date_arr)? 'scheduled' :'') ." ' id='$day'>$day</div>";
            }
            ?>
        </div>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <h4 style="font-size: 0.9rem; color: #666; margin-bottom: 15px;">Your Schedule Details</h4>
            <?php

            if($theatre_operations[0]['scheduled_at'][8] == '0'){
                $temp_date = $theatre_operations[0]['scheduled_at'][8];
            }else{
                $temp_date = $theatre_operations[0]['scheduled_at'][8] . $theatre_operations[0]['scheduled_at'][9];
            }

            


            if ($operation_count > 0) {
                foreach ($theatre_operations as $op) {
                    Schedule_Details(
                        $op['post_op_room_type'],
                        $op['scheduled_at'],
                        $op['operation_id']
                    );
                }
            } else {
                echo '<p style="color: #888; text-align: center;">No operations found.</p>';
            }
            ?>
        </div>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>