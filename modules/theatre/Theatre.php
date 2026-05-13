


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theatre Management - MediCare HMS</title>
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <style>
    body {
        font-family: 'Poppins', sans-serif;
        background-color: #f0f2f5;
        color: #333;
    }

    .main-content { padding: 30px; }

    /* Title Style */
    h1 { font-weight: 600; color: #1a237e; margin-bottom: 5px; }
    p { color: #666; font-size: 0.95rem; }

    /* Stats Card ලස්සන කරමු */
    .stats-row {
        display: flex;
        gap: 20px;
        margin: 25px 0;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 15px;
        flex: 1;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: transform 0.3s ease; /* Hover effect */
        border-left: 5px solid #1a237e;
    }

    .stat-card:hover { transform: translateY(-5px); }

    .stat-card i { font-size: 2rem; color: #1a237e; opacity: 0.8; }
    .stat-label { font-size: 0.8rem; color: #888; text-transform: uppercase; font-weight: 600; }
    .stat-count { display: block; font-size: 1.5rem; font-weight: 600; }

    /* Table එක ලස්සන කරමු */
    .table-container {
        background: white;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    .custom-table th {
        text-align: left;
        padding: 15px;
        background-color: #f8f9fa;
        color: #555;
        font-size: 0.85rem;
        text-transform: uppercase;
        border-bottom: 2px solid #eee;
    }

    .custom-table td {
        padding: 15px;
        border-bottom: 1px solid #f1f1f1;
        font-size: 0.9rem;
    }

    /* Status Badges */
    .badge {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    .status-scheduled { background: #fff8e1; color: #f57c00; }
    .status-ongoing { 
        background: #e3f2fd; color: #1976d2; 
        animation: pulse 2s infinite; /* Ongoing එකට animation එකක් */
    }

    /* Animation for Ongoing Status */
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.6; }
        100% { opacity: 1; }
    }

    /* View Button */
    .btn-view {
        background: #1a237e;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        cursor: pointer;
        transition: 0.3s;
    }
    .btn-view:hover { background: #3949ab; box-shadow: 0 4px 10px rgba(26,35,126,0.3); }
</style>
    <style>
        /* පිටුවට අදාළ අමතර CSS කිහිපයක් */
        .status-scheduled { background-color: #fff3cd; color: #856404; padding: 5px 10px; border-radius: 15px; font-size: 12px; }
        .status-ongoing { background-color: #cce5ff; color: #004085; padding: 5px 10px; border-radius: 15px; font-size: 12px; }
        .status-completed { background-color: #d4edda; color: #155724; padding: 5px 10px; border-radius: 15px; font-size: 12px; }
    </style>
</head>
<body>

<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <h1>Operation Theatre</h1>
            <p>Manage and view surgical schedules and theatre availability.</p>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="icon-box">📅</div>
                <div>
                    <span class="label">TOTAL TODAY</span>
                    <span class="count">05</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-box">⏳</div>
                <div>
                    <span class="label">PENDING</span>
                    <span class="count">02</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-box">✅</div>
                <div>
                    <span class="label">COMPLETED</span>
                    <span class="count">03</span>
                </div>
            </div>
        </div>

        <div class="table-container shadow">
            <h3>Upcoming Operations</h3>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>OP ID</th>
                        <th>PATIENT NAME</th>
                        <th>SURGEON</th>
                        <th>THEATRE</th>
                        <th>DATE & TIME</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>#OP-2045</td>
                        <td>Kasun Kalhara</td>
                        <td>Dr. Saman Perera</td>
                        <td>Theatre 01</td>
                        <td>2026-05-14 <br> <small>09:30 AM</small></td>
                        <td><span class="status-scheduled">Scheduled</span></td>
                        <td>
                            <button class="btn-view">View</button>
                        </td>
                    </tr>
                    <tr>
                        <td>#OP-2044</td>
                        <td>Nimali Fernando</td>
                        <td>Dr. Nilanthi Silva</td>
                        <td>Theatre 02</td>
                        <td>2026-05-14 <br> <small>11:00 AM</small></td>
                        <td><span class="status-ongoing">Ongoing</span></td>
                        <td>
                            <button class="btn-view">View</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html> 