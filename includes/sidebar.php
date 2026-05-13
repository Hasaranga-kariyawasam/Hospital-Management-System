<?php
// includes/sidebar.php
// Renders the left sidebar navigation based on the logged-in user's role.
$role = $_SESSION['role'] ?? 'guest';
?>
<aside class="sidebar">
<a href="/Web/Hospital-Management-System/view_doctors.php">🩺 View Doctors</a>
    <?php if ($role === 'admin'): ?>
        <div class="sidebar-section">
            <div class="sidebar-heading">Overview</div>
            <ul class="sidebar-menu">
                <li><a href="/Web/Hospital-Management-System/modules/admin/dashboard.php"><span class="icon">🏠</span> Dashboard</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/admin/reports.php"><span class="icon">📊</span> Reports</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/admin/staff.php"><span class="icon">👥</span> Staff Management</a></li>
            </ul>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-heading">Modules</div>
            <ul class="sidebar-menu">
                <li><a href="/Web/Hospital-Management-System/modules/appointments/appointments.php"><span class="icon">📅</span> Appointments</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/ward/ward_management.php"><span class="icon">🏥</span> Ward & Rooms</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/theatre/theatre.php"><span class="icon">🔬</span> Theatre</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/pharmacy/pharmacy.php"><span class="icon">💊</span> Pharmacy</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/billing/billing.php"><span class="icon">💳</span> Billing</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/emergency/emergency.php"><span class="icon">🚑</span> Emergency</a></li>
            </ul>
        </div>

    <?php elseif ($role === 'doctor'): ?>
        <div class="sidebar-section">
            <div class="sidebar-heading">My Work</div>
            <ul class="sidebar-menu">
                <li><a href="/Web/Hospital-Management-System/modules/appointments/doctor_schedule.php"><span class="icon">📅</span> My Schedule</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/appointments/appointments.php"><span class="icon">📋</span> Appointments</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/theatre/theatre.php"><span class="icon">🔬</span> Operations</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/pharmacy/prescriptions.php"><span class="icon">💊</span> Prescriptions</a></li>
            </ul>
        </div>

    <?php elseif ($role === 'reception'): ?>
        <div class="sidebar-section">
            <div class="sidebar-heading">Reception</div>
            <ul class="sidebar-menu">
                <li><a href="/Web/Hospital-Management-System/modules/appointments/opd_walkin.php"><span class="icon">🚶</span> OPD Walk-in</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/patients/patients.php"><span class="icon">🧑‍⚕️</span> Patients</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/ward/admissions.php"><span class="icon">🏥</span> Admissions</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/billing/billing.php"><span class="icon">💳</span> Billing</a></li>
            </ul>
        </div>

    <?php elseif ($role === 'pharmacist'): ?>
        <div class="sidebar-section">
            <div class="sidebar-heading">Pharmacy</div>
            <ul class="sidebar-menu">
                <li><a href="/Web/Hospital-Management-System/modules/pharmacy/pharmacy_queue.php"><span class="icon">📋</span> Dispensing Queue</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/pharmacy/drugs.php"><span class="icon">💊</span> Drug Catalogue</a></li>
            </ul>
        </div>

 <?php elseif ($role === 'patient'): ?>
    <div class="sidebar-section">
        <div class="sidebar-heading">My Portal</div>
        <ul class="sidebar-menu">
            <li>
                <a href="/Web/Hospital-Management-System/modules/appointments/my_appointments.php">
                    <span class="icon">📅</span> My Appointments
                </a>
            </li>
            <li>
                <a href="/Web/Hospital-Management-System/modules/appointments/book.php">
                    <span class="icon">➕</span> Book Appointment
                </a>
            </li>
            <li>
                <a href="/Web/Hospital-Management-System/modules/emergency/request_ambulance.php">
                    <span class="icon">🚑</span> Ambulance
                </a>
            </li>
            <li>
                <a href="/Web/Hospital-Management-System/modules/Theatre/Theatre.php">
                    <span class="icon">🔬</span> Operations
                </a>
            </li>
            <li>
                <a href="/Web/Hospital-Management-System/modules/billing/my_bills.php">
                    <span class="icon">💳</span> My Bills
                </a>
            </li>
        </ul>
    </div>

    <?php elseif ($role === 'dispatcher'): ?>
        <div class="sidebar-section">
            <div class="sidebar-heading">Dispatch</div>
            <ul class="sidebar-menu">
                <li><a href="/Web/Hospital-Management-System/modules/emergency/dispatcher_dashboard.php"><span class="icon">🗺️</span> Dispatcher Board</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/emergency/ambulances.php"><span class="icon">🚑</span> Ambulances</a></li>
            </ul>
        </div>
    <?php endif; ?>

</aside>
