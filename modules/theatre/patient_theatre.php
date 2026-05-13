<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theatre Management - MediCare HMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    

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
            margin-bottom: 25px;
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

       
        #searchResultCard {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            display: none; 
            margin-bottom: 25px;
            animation: fadeIn 0.4s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .info-box label { font-size: 0.75rem; color: #888; display: block; text-transform: uppercase; }
        .info-box span { font-weight: 600; color: var(--primary); }

        .btn-pdf {
            background: white; color: var(--primary); border: 1px solid var(--primary);
            padding: 8px 15px; border-radius: 8px; cursor: pointer; font-weight: 600;
        }

       
        .surgery-list-section {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .surgery-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .surgery-table th {
            text-align: left;
            background: #f8f9fa;
            padding: 12px;
            font-size: 0.85rem;
            color: #666;
            border-bottom: 2px solid #eee;
        }
        .surgery-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-approved { background: #e8f5e9; color: #2e7d32; }
        .status-pending { background: #fff3e0; color: #ef6c00; }

        
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
    </div>

   
    <div class="stats-row">
        <div class="stat-card">
            <div class="icon-box"><i class="fas fa-user-injured"></i></div>
            <div>
                <small style="color: #888;">Patient Status</small>
                <div style="font-weight: 600;">System Active</div>
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
                <small style="color: #888;">Current Delay</small>
                <div style="font-weight: 600;">None</div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        
        <div class="left-panel">
            
            
            <div id="searchResultCard">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                    <h3 style="margin: 0; color: var(--primary); font-size: 1.1rem;"><i class="fas fa-id-badge"></i> Patient Records Found</h3>
                    <button class="btn-pdf" onclick="generateReport()"><i class="fas fa-file-pdf"></i> Download Report</button>
                </div>
                <div class="info-grid">
                    <div class="info-box"><label>Patient ID</label><span id="disID"></span></div>
                    <div class="info-box"><label>NIC Number</label><span id="disNIC"></span></div>
                    <div class="info-box"><label>Gender</label><span id="disGender"></span></div>
                    <div class="info-box"><label>Blood Type</label><span id="disBlood"></span></div>
                    <div class="info-box"><label>Contact</label><span id="disPhone"></span></div>
                    <div class="info-box"><label>Address</label><span id="disAddress"></span></div>
                </div>

                
                <div class="surgery-list-section">
                    <h4 style="margin: 0 0 10px 0; color: var(--primary); font-size: 1rem;"><i class="fas fa-notes-medical"></i> Scheduled Surgeries</h4>
                    <table class="surgery-table">
                        <thead>
                            <tr>
                                <th>Surgery Name</th>
                                <th>Doctor</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="surgeryListBody">
                      
                        </tbody>
                    </table>
                </div>
            </div>

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
            </div>
        </div>

       
        <div class="right-panel">
            
            <div class="quick-check">
                <h3><i class="fas fa-search"></i> Find Your Schedule</h3>
                <p style="font-size: 0.8rem; opacity: 0.9;">Enter Patient ID or NIC and click Search.</p>
                <div class="input-group">
                    <input type="text" id="pSearch" placeholder="e.g. 200435800944">
                    <button class="btn-check" onclick="runSearch()">Search</button>
                </div>
            </div>

            <div class="calendar-card">
                <h3 style="margin-top: 0; font-size: 1.1rem; color: var(--primary);">Upcoming Surgeries</h3>
                <ul class="apt-list">
                    <li class="apt-item">
                        <div class="apt-date"><small>MAY</small><span>15</span></div>
                        <div>
                            <div style="font-weight: 500; font-size: 0.9rem;">Dr. Nilanthi Silva</div>
                            <small style="color: #888;">Knee Replacement</small>
                        </div>
                    </li>
                    <li class="apt-item">
                        <div class="apt-date" style="background: #fff4e5; color: var(--warning);"><small>MAY</small><span>18</span></div>
                        <div>
                            <div style="font-weight: 500; font-size: 0.9rem;">Dr. Saman Perera</div>
                            <small style="color: #888;">Heart Checkup</small>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

 

</body>
</html>