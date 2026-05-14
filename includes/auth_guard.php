<?php
declare(strict_types=1);

/**
 * auth_guard.php
 * Shared authentication & role guard for all modules.
 *
 * Usage:
 *   require_once __DIR__ . '/../../includes/auth_guard.php';
 *   requireRole(['admin', 'doctor']);
 */

if (!function_exists('requireRole')) {
    /**
     * Ensures the current session user has one of the allowed roles.
     * Redirects to login if not authenticated, or to home if wrong role.
     *
     * @param string[] $allowedRoles
     */
    function requireRole(array $allowedRoles): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /Web/Hospital-Management-System/login.php');
            exit();
        }

        $role = $_SESSION['role'] ?? '';
        if (!in_array($role, $allowedRoles, true)) {
            // Redirect to their own dashboard
            $base = '/Web/Hospital-Management-System';
            $map  = [
                'admin'      => "$base/modules/admin/dashboard.php",
                'doctor'     => "$base/modules/theatre/theatre_schedule.php",
                'reception'  => "$base/modules/appointments/opd_walkin.php",
                'pharmacist' => "$base/modules/pharmacy/pharmacy_queue.php",
                'patient'    => "$base/modules/appointments/my_appointments.php",
                'dispatcher' => "$base/modules/emergency/dispatcher_dashboard.php",
                'driver'     => "$base/modules/emergency/driver_job.php",
            ];
            header('Location: ' . ($map[$role] ?? "$base/home.php"));
            exit();
        }
    }
}
