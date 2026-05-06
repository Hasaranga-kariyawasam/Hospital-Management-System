<?php
// 1. Session පරීක්ෂා කිරීම
require_once '../../includes/session_check.php';

// 2. Database Connection (ඔබගේ දත්ත සමුදායේ තොරතුරු මෙහි ඇතුළත් කරන්න)
$host = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'hospital_db'; // ඔබගේ Database එකේ නම මෙතැනට දෙන්න

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// 3. පිටුවේ මූලික සැකසුම්
$pageTitle  = "Book New Appointment";
$useSidebar = true;
$isPublic   = false;

// Header සහ Sidebar එකතු කිරීම
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<main class="main-content">
    
    <div class="page-header">
        <div class="page-header-title">
            <h2>Book an Appointment</h2>
            <p>Select your preferred doctor from the available list below.</p>
        </div>
        <a href="my_appointments.php" class="btn btn-secondary">
            <span class="icon">📅</span> My Appointments
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Appointment Details</h3>
        </div>

        <form action="process_booking.php" method="POST" class="mt-16">
            
            <div class="form-row">
                <!-- අංශය තේරීම (Department Selection) -->
                <div class="form-group">
                    <label class="form-label">Medical Department</label>
                    <select name="department" class="form-control" required>
                        <option value="">-- Select Department --</option>
                        <option value="Cardiology">Cardiology</option>
                        <option value="Neurology">Neurology</option>
                        <option value="Pediatrics">Pediatrics</option>
                        <option value="General">General Medicine</option>
                    </select>
                </div>

                <!-- වෛද්‍යවරයා තේරීම (Doctor Selection - DB එකෙන් ගත් දත්ත) -->
                <div class="form-group">
                    <label class="form-label">Select Preferred Doctor</label>
                    <select name="doctor_id" class="form-control" required>
                        <option value="">-- Choose a Doctor --</option>
                        <?php
                        /* 
                         * අදාළ Database Query එක. 
                         * මෙහි 'users' යනු ඔබේ වගුවේ (table) නම ලෙසත්, 
                         * 'user_id', 'full_name' යනු කෝලම් (columns) ලෙසත් සලකා ඇත. 
                         * ඔබගේ MySQL Table Structure එකට අනුව මේවා වෙනස් කරගන්න.
                         */
                        $query = "SELECT user_id, full_name, department FROM users WHERE role = 'doctor'";
                        $result = $conn->query($query);

                        // දත්ත තිබේදැයි පරීක්ෂා කර loop එක මගින් dropdown එකට එකතු කිරීම
                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                // මෙහිදී Doctor ගේ නම සහ අංශය (Department) පෙන්වයි
                                echo '<option value="' . htmlspecialchars($row['user_id']) . '">' 
                                     . htmlspecialchars($row['full_name']) . ' (' . htmlspecialchars($row['department']) . ')</option>';
                            }
                        } else {
                            echo '<option value="">No Doctors Available</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <!-- දිනය තේරීම -->
                <div class="form-group">
                    <label class="form-label">Appointment Date</label>
                    <input type="date" name="app_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <!-- වේලාව තේරීම -->
                <div class="form-group">
                    <label class="form-label">Preferred Time Slot</label>
                    <select name="time_slot" class="form-control" required>
                        <option value="morning">Morning (08:00 AM - 12:00 PM)</option>
                        <option value="afternoon">Afternoon (01:00 PM - 04:00 PM)</option>
                        <option value="evening">Evening (05:00 PM - 08:00 PM)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Reason for Visit (Optional)</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Briefly describe your symptoms..."></textarea>
            </div>

            <div class="flex gap-12 mt-24">
                <button type="submit" class="btn btn-primary btn-lg">Confirm Booking</button>
                <button type="reset" class="btn btn-secondary btn-lg">Clear Form</button>
            </div>
        </form>
    </div>

</main>

<?php 
// Footer එකතු කිරීම
include '../../includes/footer.php';
$conn->close(); // දත්ත සමුදාය සමඟ ඇති සබැඳිය විසන්ධි කිරීම
?>