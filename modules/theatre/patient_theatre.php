<?php

require_once __DIR__ . '/../../config/db_config.php';

session_start();
$user_id = $_SESSION['user_id'];

echo $user_id . "   ";

$patient_id = $pdo->query("
    SELECT patient_id FROM patients WHERE user_id = '$user_id' limit 1
")->fetchColumn();

echo $patient_id;

$theatre_operations = $pdo->query("
    SELECT * FROM theatre_operations WHERE patient_id = '$patient_id'
")->fetchAll(PDO::FETCH_ASSOC);

// echo $theatre_operations[0]['post_op_room_type'];


function Schedule_Details($room='null' ,$time='none'){
    echo <<<HTML
    <div style="background: #f8f9fa; padding: 15px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; margin-bottom:6px;">
        <div style="display: flex; gap: 15px; align-items: center;">
            <div style="width: 10px; height: 10px; background: var(--warning); border-radius: 50%;"></div>
            <div>
                <div id="todaySurgery" style="font-weight: 500;">$room</div>
                <small style="color: #888;">$time</small>
            </div>
        </div>
        <div class="" style="display:flex;gap:20px; justify-content: space-between; align-items: center;">
        <span style="color: var(--primary); font-weight: 600; font-size: 0.8rem;">View Prep Instructions</span>
        <button  class="btn-check">print</button></div>
    </div> 
    HTML;
}

?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theatre Management - MediCare HMS</title>
    <!-- Google Fonts & Font Awesome Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- PDF Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    
    <style>
        :root {
            --primary: #1a237e;
            --secondary: #3949ab;
            --success: #2e7d32;
            --warning: #ffa000;
            --info: #0288d1;
            --danger: #d32f2f;
            --bg-light: #f0f4f8;
            --white: #ffffff;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
            color: #333;
            margin: 0;
            padding: 0;
        }

        .main-content { padding: 40px; max-width: 1400px; margin: auto; }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        h1 { font-weight: 600; color: var(--primary); margin: 0; font-size: 1.8rem; }
        .page-header p { color: #777; margin: 5px 0 0 0; }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-top: 30px;
        }

        .calendar-card {
            background: var(--white);
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            text-align: center;
        }

        .day-name { font-weight: 600; color: #888; font-size: 0.8rem; padding-bottom: 10px; }
        .day {
            padding: 15px 5px;
            border-radius: 12px;
            background: #f8f9fa;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.3s;
            position: relative;
        }
        .day:hover { background: #e8eaf6; color: var(--primary); }
        .day.active { background: var(--primary); color: white; }
        .day.has-event::after {
            content: '';
            position: absolute;
            bottom: 5px;
            left: 50%;
            transform: translateX(-50%);
            width: 5px;
            height: 5px;
            background: var(--warning);
            border-radius: 50%;
        }

        .quick-check {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 25px;
        }

        .input-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .input-group input {
            flex: 1;
            padding: 10px 15px;
            border-radius: 10px;
            border: none;
            outline: none;
        }
        .btn-check {
            background: var(--warning);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.02);
        }

        .icon-box {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e8eaf6;
            color: var(--primary);
        }

        .apt-list { list-style: none; padding: 0; margin: 0; }
        .apt-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            gap: 15px;
        }
        .apt-date {
            background: #e3f2fd;
            color: var(--info);
            padding: 5px 10px;
            border-radius: 8px;
            text-align: center;
            min-width: 50px;
        }
        .apt-date span { display: block; font-weight: 700; font-size: 1.1rem; }

        @media (max-width: 992px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="main-content">
    
    <div class="page-header">
        <div>
            <h1>MediCare Patient Portal</h1>
            <p>Operation Theatre Schedule & Status</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <!-- මෙතන තියෙන්නේ Reports බට්න් එක විතරයි -->
            <button id="reportBtn" class="btn-check" style="background: white; color: var(--primary); border: 1px solid var(--primary);">
                <i class="fas fa-download"></i> Reports
            </button>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="icon-box"><i class="fas fa-user-injured"></i></div>
            <div>
                <small style="color: #888;">Your Status</small>
                <div id="pStatus" style="font-weight: 600;">Confirmed</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-box" style="color: var(--info);"><i class="fas fa-hospital-user"></i></div>
            <div>
                <small style="color: #888;">Assigned Theatre</small>
                <div id="pTheatre" style="font-weight: 600;">Theatre 02</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-box" style="color: var(--success);"><i class="fas fa-clock"></i></div>
            <div>
                <small style="color: #888;">Waiting Time</small>
                <div id="pWait" style="font-weight: 600;">~ 45 Mins</div>
            </div>
        </div>
    </div>

    <div class="">
        <div class="left-panel">
            <div class="calendar-card">
                <div class="calendar-header">
                    <h3 style="margin: 0; color: var(--primary);">Surgery Calendar - May 2026</h3>
                    <div style="display: flex; gap: 10px;">
                        <button style="border: none; background: none; cursor: pointer;"><i class="fas fa-chevron-left"></i></button>
                        <button style="border: none; background: none; cursor: pointer;"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
                
                <div class="calendar-grid">
                    <div class="day-name">MON</div><div class="day-name">TUE</div><div class="day-name">WED</div>
                    <div class="day-name">THU</div><div class="day-name">FRI</div><div class="day-name">SAT</div>
                    <div class="day-name">SUN</div>
                    <div class="day">1</div><div class="day">2</div><div class="day">3</div>
                    <div class="day">4</div><div class="day has-event">5</div><div class="day">6</div><div class="day">7</div>
                    <div class="day">8</div><div class="day">9</div><div class="day">10</div>
                    <div class="day">11</div><div class="day">12</div><div class="day has-event">13</div>
                    <div class="day active">14</div><div class="day">15</div><div class="day">16</div><div class="day">17</div>
                    <div class="day">18</div><div class="day">19</div><div class="day has-event">20</div>
                    <div class="day">21</div><div class="day">22</div><div class="day">23</div><div class="day">24</div>
                </div>

                <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee;">
                    <h4 style="font-size: 0.9rem; color: #666;">Schedule Details for Today</h4>
                    <?php
                        for ($i=0; $i < count($theatre_operations); $i++) { 
                            Schedule_Details($theatre_operations[$i]['post_op_room_type'],$theatre_operations[$i]['scheduled_at']);
                        }
                    ?>
                </div>
            </div>
        </div>

        
    </div>
</div>

<script>
    document.getElementById('reportBtn').addEventListener('click', function() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        // 1. Header Design
        doc.setFillColor(26, 35, 126); 
        doc.rect(0, 0, 210, 40, 'F');
        
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(22);
        doc.text("MEDICARE HEALTH CARE", 15, 20);
        doc.setFontSize(10);
        doc.text("Operation Theatre Management System - Official Report", 15, 30);
        
        const today = new Date().toLocaleString();
        doc.setFontSize(9);
        doc.text("Generated Date: " + today, 140, 20);

        // 2. Summary Section
        doc.setTextColor(0, 0, 0);
        doc.setFontSize(14);
        doc.text("Patient Current Status Summary", 15, 55);

        const status = document.getElementById('pStatus').innerText;
        const theatre = document.getElementById('pTheatre').innerText;
        const wait = document.getElementById('pWait').innerText;

        doc.autoTable({
            startY: 60,
            head: [['Status', 'Assigned Theatre', 'Estimated Waiting']],
            body: [[status, theatre, wait]],
            theme: 'striped',
            headStyles: { fillColor: [57, 73, 171] }
        });

        // 3. Upcoming Schedule Table
        doc.setFontSize(14);
        doc.text("Upcoming Surgery Schedule", 15, doc.lastAutoTable.finalY + 15);

        const rows = [];
        const items = document.querySelectorAll('#upcomingList .apt-item');
        items.forEach(item => {
            const date = item.querySelector('.apt-date span').innerText + " May";
            const doctor = item.querySelector('.doc-name').innerText;
            const surgery = item.querySelector('.sur-type').innerText;
            rows.push([date, doctor, surgery]);
        });

        doc.autoTable({
            startY: doc.lastAutoTable.finalY + 20,
            head: [['Date', 'Surgeon / Doctor', 'Surgery Type']],
            body: rows,
            theme: 'grid',
            headStyles: { fillColor: [26, 35, 126] },
            styles: { fontSize: 10 }
        });

        // 4. Footer
        const finalY = doc.lastAutoTable.finalY + 30;
        doc.setFontSize(10);
        doc.setTextColor(100);
        doc.text("__________________________", 15, finalY);
        doc.text("Authorized Signature", 15, finalY + 7);
        
        doc.setFontSize(8);
        doc.text("This is a computer-generated document. No signature is required.", 15, 285);

        doc.save("MediCare_Surgery_Report.pdf");
    });
</script>

</body>
</html>