<?php
// db_config.php එක තියෙන තැන නිවැරදිව දෙන්න. 
// ඔබේ structure එක අනුව මේ path එක වෙනස් විය හැක.
include('../../config/db_config.php'); 

header('Content-Type: application/json');

if(isset($_GET['nic'])) {
    $nic = mysqli_real_escape_string($conn, $_GET['nic']);
    
    // වැදගත්: ඔබේ table එකේ නම 'patient' ද සහ column එක 'nic' ද කියා බලන්න
    $query = "SELECT * FROM patient WHERE nic = '$nic' OR patient_id = '$nic'";
    $result = mysqli_query($conn, $query);

    if($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        
        // surgeries දැනට database එකේ නැති නිසා හිස් array එකක් යවනවා
        $row['surgeries'] = []; 
        
        echo json_encode($row); 
    } else {
        // රෝගියා නැතිනම් මේ message එක යනවා
        echo json_encode(['error' => 'No patient found with this NIC/ID']);
    }
} else {
    echo json_encode(['error' => 'No NIC provided']);
}
?>