<?php
// 1. පරිශීලකයා ලොග් වී ඇත්දැයි පරීක්ෂා කිරීම (Session Check)
require_once '../../includes/session_check.php';

// 2. පිටුවේ මූලික සැකසුම්
$pageTitle  = "Appointments Management";
$useSidebar = true;
$isPublic   = false;

// 3. Header සහ Sidebar එකතු කිරීම
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<main class="main-content">
    
    <!-- පිටුවේ ශීර්ෂය (Page Header) -->
    <div class="page-header">
        <div class="page-header-title">
            <h2>Appointments</h2>
            <p>Manage all patient appointments and schedules.</p>
        </div>
        <div>
            <a href="book.php" class="btn btn-primary">
                <span class="icon">➕</span> Book New Appointment
            </a>
        </div>
    </div>

    <!-- කෙටි සාරාංශ දත්ත පෙන්වන කොටස (Stat Cards) -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon blue">📅</div>
            <div>
                <span class="stat-label">Total Today</span>
                <span class="stat-value">24</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow">⏳</div>
            <div>
                <span class="stat-label">Pending</span>
                <span class="stat-value">8</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div>
                <span class="stat-label">Completed</span>
                <span class="stat-value">16</span>
            </div>
        </div>
    </div>

    <!-- දත්ත වගුව (Data Table) -->
    <div class="card">
        <div class="card-header">
            <h3>Upcoming Appointments</h3>
        </div>
        
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>App ID</th>
                        <th>Patient Name</th>
                        <th>Doctor</th>
                        <th>Department</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- දත්ත ගබඩාවෙන් (Database) ගත් අයුරින් මෙහි දත්ත පෙන්විය යුතුය -->
                    <tr>
                        <td>#APP-1042</td>
                        <td class="fw-bold">Kasun Kalhara</td>
                        <td>Dr. Saman Perera</td>
                        <td>Cardiology</td>
                        <td>2026-05-06 <br><span class="text-muted">Morning</span></td>
                        <td><span class="badge badge-warning">Pending</span></td>
                        <td>
                            <a href="#" class="btn btn-sm btn-success">Accept</a>
                            <a href="#" class="btn btn-sm btn-secondary">View</a>
                        </td>
                    </tr>
                    <tr>
                        <td>#APP-1041</td>
                        <td class="fw-bold">Nimali Fernando</td>
                        <td>Dr. Nilanthi Silva</td>
                        <td>Neurology</td>
                        <td>2026-05-06 <br><span class="text-muted">Morning</span></td>
                        <td><span class="badge badge-success">Confirmed</span></td>
                        <td>
                            <a href="#" class="btn btn-sm btn-secondary">View</a>
                        </td>
                    </tr>
                    <tr>
                        <td>#APP-1040</td>
                        <td class="fw-bold">Ruwan Kumara</td>
                        <td>Dr. Aruna Bandara</td>
                        <td>General Medicine</td>
                        <td>2026-05-05 <br><span class="text-muted">Evening</span></td>
                        <td><span class="badge badge-neutral">Completed</span></td>
                        <td>
                            <a href="#" class="btn btn-sm btn-secondary">History</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php 
// 4. Footer එකතු කිරීම
include '../../includes/footer.php';
?>