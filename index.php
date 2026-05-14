<?php
declare(strict_types=1);
session_start();

// If logged in, route to role dashboard; otherwise go to public homepage
if (isset($_SESSION['user_id'])) {
    $base = '/Web/Hospital-Management-System';
    $map  = [
        'admin'      => "$base/modules/admin/dashboard.php",
        'doctor'     => "$base/modules/appointments/doctor_potal.php",
        'reception'  => "$base/modules/appointments/opd_walkin.php",
        'pharmacist' => "$base/modules/pharmacy/pharmacy_queue.php",
        'patient'    => "$base/modules/appointments/my_appointments.php",
        'dispatcher' => "$base/modules/emergency/dispatcher_dashboard.php",
        'driver'     => "$base/modules/emergency/driver_dashboard.php",  // Add this line
    ];
    $dest = $map[$_SESSION['role']] ?? "$base/home.php";
    header("Location: $dest");
} else {
    header('Location: /Web/Hospital-Management-System/home.php');
}
exit();
