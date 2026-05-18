<?php
// includes/sidebar.php
// Renders the left sidebar navigation based on the logged-in user's role.
$role = $_SESSION['role'] ?? 'guest';
?>
<aside class="sidebar">

    <?php if ($role === 'admin'): ?>
        <div class="sidebar-section">
            <div class="sidebar-heading">Overview</div>
            <ul class="sidebar-menu">
                <li><a href="/Web/Hospital-Management-System/modules/admin/dashboard.php">Dashboard</a></li>
              
                <li><a href="/Web/Hospital-Management-System/modules/admin/staff.php">Staff Management</a></li>
            </ul>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-heading">Modules</div>
            <ul class="sidebar-menu">
                <li><a href="/Web/Hospital-Management-System/modules/appointments/appointments.php">Appointments</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/ward/ward_management.php">Ward & Rooms</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/theatre/theatre.php">Theatre</a></li>
               
                <li><a href="/Web/Hospital-Management-System/modules/billing/billing_check.php">Appointment Billing</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/billing/theatre_billing.php">Theater Billing</a></li>
                
            </ul>
        </div>

    <?php elseif ($role === 'doctor'): ?>
        <div class="sidebar-section">
            <div class="sidebar-heading">My Work</div>
            <ul class="sidebar-menu">
               
                <li><a href="/Web/Hospital-Management-System/modules/appointments/doctor_appointments.php">My Schedule</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/appointments/appointments.php">Appointments</a></li>
              
                <li><a href="/Web/Hospital-Management-System/modules/theatre/theatre.php">Operations</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/pharmacy/doctor_prescription.php">Prescriptions</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/ward/doctor_admission_review.php">Admission Requests </a></li>
                
            </ul>
        </div>

    <?php elseif ($role === 'reception'): ?>
        <div class="sidebar-section">
            <div class="sidebar-heading">Reception</div>
            <ul class="sidebar-menu">
                <li><a href="/Web/Hospital-Management-System/modules/appointments/opd_walkin.php">OPD Walk-in</a></li>
              
                <li><a href="/Web/Hospital-Management-System/modules/ward/admission_request.php">Admissions</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/billing/billing_check.php">Appointment Billing</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/billing/theatre_billing.php">Theater Billing</a></li>
            </ul>
        </div>

    <?php elseif ($role === 'pharmacist'): ?>
        <div class="sidebar-section">
            <div class="sidebar-heading">Pharmacy</div>
            <ul class="sidebar-menu">
                <li><a href="/Web/Hospital-Management-System/modules/pharmacy/pharmacist_portal.php">Dispensing Queue</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/pharmacy/drug_catalog.php">Drug Catalogue</a></li>
            </ul>
        </div>

 <?php elseif ($role === 'patient'): ?>
    <div class="sidebar-section">
        <div class="sidebar-heading">My Portal</div>
        <ul class="sidebar-menu">
            <li>
                <a href="/Web/Hospital-Management-System/modules/appointments/my_appointments.php">
                My Appointments
                </a>
            </li>
            <li>
                <a href="/Web/Hospital-Management-System/modules/appointments/book.php">
                Book Appointment
                </a>
            </li>
            
            <li>
                <a href="/Web/Hospital-Management-System/modules/Theatre/patient_theatre.php">
                Operations
                </a>
            </li>
            <li>
                <a href="/Web/Hospital-Management-System/modules/billing/my_bills.php">
                My Bills
                </a>
            </li>
        </ul>
    </div>

    <?php elseif ($role === 'dispatcher'): ?>
        <div class="sidebar-section">
            <div class="sidebar-heading">Dispatch</div>
            <ul class="sidebar-menu">
                <li><a href="/Web/Hospital-Management-System/modules/emergency/dispatcher_dashboard.php">Dispatcher Board</a></li>
                <li><a href="/Web/Hospital-Management-System/modules/emergency/ambulances.php">Ambulances</a></li>
            </ul>
        </div>
    <?php endif; ?>

</aside>