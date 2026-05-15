<?php

require_once '../../includes/session_check.php';


require_once __DIR__ . '/../../config/db_config.php';


$pageTitle  = "Theatre Management";
$useSidebar = true;
$isPublic   = false;

$user_id = $_SESSION['user_id'];


$stmt_patient = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ? LIMIT 1");
$stmt_patient->execute([$user_id]);
$patient_id = $stmt_patient->fetchColumn();

if (!$patient_id) {
    die("Error: Patient record not found for this user.");
}

$stmt_ops = $pdo->prepare("SELECT * FROM theatre_operations WHERE patient_id = ? ORDER BY scheduled_at ASC");
$stmt_ops->execute([$patient_id]);
$theatre_operations = $stmt_ops->fetchAll(PDO::FETCH_ASSOC);

function Schedule_Details($room = 'N/A', $time = 'Not Scheduled', $operation_id = 0) {
    $link = "view_prep_instructions.php?operation_id=$operation_id";
    echo <<<HTML
    <div style="background: #f8f9fa; padding: 15px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; margin-bottom:10px; border-left: 5px solid var(--warning);">
        <div style="display: flex; gap: 15px; align-items: center;">
            <div style="width: 12px; height: 12px; background: var(--warning); border-radius: 50%;"></div>
            <div>
                <div style="font-weight: 600; color: #333;">Room: $room</div>
                <small style="color: #666;"><i class="fas fa-clock"></i> $time</small>
            </div>
        </div>
        <div style="display:flex; gap:15px; align-items: center;">
            <a href="$link" style="color: var(--primary); font-weight: 600; font-size: 0.85rem; text-decoration: none; cursor: pointer;">View Prep Instructions</a>
        </div>
    </div> 
HTML;
}


include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<style>
   :root {
      
        --primary: #1a237e;  
        --secondary: #3949ab;  
        --success: #2e7d32;    
        --warning: #ffa000;    
        --bg-light: #f0f4f8;
        --white: #ffffff;

        
        --info: #0288d1;       
        --danger: #c62828;     
        --completed: #757575;  
    }

    
    .main-content { 
        padding: 40px; 
        max-width: 1200px; 
        margin-left: auto;
        margin-right: auto;
    }
    
    .page-header { margin-bottom: 30px; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
    .page-header h1 { color: var(--primary); margin: 0; font-weight: 600; }

    .calendar-card {
        background: var(--white);
        padding: 25px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    }

    .patient-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .patient-table th {
        background: #e8eaf6;
        color: var(--primary);
        padding: 12px;
        text-align: left;
    }
    .patient-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
    }
</style>

<main class="main-content">
    <div class="page-header">
        <h1>MediCare Patient Portal</h1>
        <p>Operation Theatre Schedule & Status</p>
    </div>

    <div class="calendar-card">
        
        
        <h3 style="color: var(--primary);">Summary Table</h3>
        <div style="overflow-x: auto;">
            <table class="patient-table">
                <thead>
                    <tr>
                        <th>Operation ID</th>
                        <th>Room Type</th>
                        <th>Scheduled Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($theatre_operations as $op): ?>
                        <tr>
                            <td style="font-weight: 600;">#<?= $op['operation_id'] ?></td>
                            <td><?= $op['post_op_room_type'] ?></td>
                            <td><?= $op['scheduled_at'] ?></td>
                            <td>
                                
                                <?php
                            if ($op['status'] == 'scheduled') {
                                echo '<span style="color: var(--primary); font-weight: 600;">scheduled</span>';
                                } elseif ($op['status'] == 'confirmed') {
                                    echo '<span style="color: var(--success); font-weight: 600;">confirmed</span>';
                            } elseif ($op['status'] == 'in_progress') {
                                echo '<span style="color: var(--info); font-weight: 600;">in_progress</span>';
                            } elseif ($op['status'] == 'completed') {
                                echo '<span style="color: var(--secondary); font-weight: 600;">completed</span>';
                            } elseif ($op['status'] == 'cancelled') {
                                echo '<span style="color: var(--danger); font-weight: 600;">cancelled</span>';
                                } elseif ($op['status'] == 'transferred') {
                                    echo '<span style="color: var(--warning); font-weight: 600;">transferred</span>';
                                    }
                                    ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">
    <h3 style="color: var(--primary); margin-top: 0;">Your Surgery Details</h3>
    
    <?php if (!empty($theatre_operations)): ?>
        <?php foreach ($theatre_operations as $op): ?>
            <?php Schedule_Details($op['post_op_room_type'], $op['scheduled_at'], $op['operation_id']); ?>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="color: #888; text-align: center; padding: 20px;">No scheduled operations found for you.</p>
    <?php endif; ?>
    </div>
</main>

<?php 

include '../../includes/footer.php'; 
?>