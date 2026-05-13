<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theatre Management - MediCare HMS</title>
    <!-- Google Fonts & Font Awesome Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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

        /* Header Section */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        h1 { font-weight: 600; color: var(--primary); margin: 0; font-size: 1.8rem; }
        .page-header p { color: #777; margin: 5px 0 0 0; }

        /* Layout Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-top: 30px;
        }

        /* Calendar Styling */
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

        /* Patient Quick Check Form */
        .quick-check {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 25px;
        }

        .quick-check h3 { margin-top: 0; font-size: 1.1rem; }
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

        /* Status Cards */
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

        /* Appointment List */
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
            <button class="btn-check" style="background: white; color: var(--primary); border: 1px solid var(--primary);">
                <i class="fas fa-download"></i> Reports
            </button>
            <button class="btn-check"><i class="fas fa-plus"></i> Request Booking</button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="icon-box"><i class="fas fa-user-injured"></i></div>
            <div>
                <small style="color: #888;">Your Status</small>
                <div style="font-weight: 600;">Confirmed</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-box" style="color: var(--info);"><i class="fas fa-hospital-user"></i></div>
            <div>
                <small style="color: #888;">Assigned Theatre</small>
                <div style="font-weight: 600;">Theatre 02</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-box" style="color: var(--success);"><i class="fas fa-clock"></i></div>
            <div>
                <small style="color: #888;">Waiting Time</small>
                <div style="font-weight: 600;">~ 45 Mins</div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <!-- Left Side: Calendar and Details -->
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
                    
                    <!-- Calendar Days (Static for demo) -->
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
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <div style="width: 10px; height: 10px; background: var(--warning); border-radius: 50%;"></div>
                            <div>
                                <div style="font-weight: 500;">Minor Surgery - Room 04</div>
                                <small style="color: #888;">10:30 AM - 11:15 AM</small>
                            </div>
                        </div>
                        <span style="color: var(--primary); font-weight: 600; font-size: 0.8rem;">View Prep Instructions</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Search and Upcoming -->
        <div class="right-panel">
            <div class="quick-check">
                <h3><i class="fas fa-search"></i> Find Your Schedule</h3>
                <p style="font-size: 0.8rem; opacity: 0.9;">Enter your Patient ID or NIC to see your surgery details.</p>
                <div class="input-group">
                    <input type="text" placeholder="e.g. PAT-9920">
                    <button class="btn-check">Search</button>
                </div>
            </div>

            <div class="calendar-card">
                <h3 style="margin-top: 0; font-size: 1.1rem; color: var(--primary);">Upcoming Surgeries</h3>
                <ul class="apt-list">
                    <li class="apt-item">
                        <div class="apt-date">
                            <small>MAY</small>
                            <span>15</span>
                        </div>
                        <div>
                            <div style="font-weight: 500; font-size: 0.9rem;">Dr. Nilanthi Silva</div>
                            <small style="color: #888;">Knee Replacement</small>
                        </div>
                    </li>
                    <li class="apt-item">
                        <div class="apt-date" style="background: #fff4e5; color: var(--warning);">
                            <small>MAY</small>
                            <span>18</span>
                        </div>
                        <div>
                            <div style="font-weight: 500; font-size: 0.9rem;">Dr. Saman Perera</div>
                            <small style="color: #888;">Heart Checkup</small>
                        </div>
                    </li>
                    <li class="apt-item" style="border: none;">
                        <div class="apt-date">
                            <small>MAY</small>
                            <span>20</span>
                        </div>
                        <div>
                            <div style="font-weight: 500; font-size: 0.9rem;">Dr. Aruna Bandara</div>
                            <small style="color: #888;">Eye Surgery</small>
                        </div>
                    </li>
                </ul>
            </div>

            <div style="margin-top: 20px; background: #e8f5e9; padding: 15px; border-radius: 15px; color: #2e7d32; display: flex; gap: 10px; align-items: center;">
                <i class="fas fa-info-circle"></i>
                <small>Please arrive 2 hours before your scheduled time.</small>
            </div>
        </div>
    </div>
</div>

</body>
</html>