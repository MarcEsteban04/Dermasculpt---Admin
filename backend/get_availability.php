<?php
session_start();
require_once '../config/connection.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$dermatologistId = $_SESSION['dermatologist_id'];

try {
    // Get days off for the dermatologist
    $daysOffQuery = $conn->prepare("
        SELECT off_date 
        FROM dermatologist_day_off 
        WHERE dermatologist_id = ? AND off_date >= CURDATE()
        ORDER BY off_date ASC
    ");
    $daysOffQuery->bind_param("i", $dermatologistId);
    $daysOffQuery->execute();
    $daysOffResult = $daysOffQuery->get_result();
    
    $daysOff = [];
    while ($row = $daysOffResult->fetch_assoc()) {
        $daysOff[] = $row['off_date'];
    }
    $daysOffQuery->close();

    // Get booked appointments (date and time combinations)
    $bookedQuery = $conn->prepare("
        SELECT appointment_date, appointment_time, status, patient_name
        FROM appointments 
        WHERE dermatologist_id = ? 
        AND appointment_date >= CURDATE() 
        AND status IN ('Scheduled', 'Pending')
        ORDER BY appointment_date ASC, appointment_time ASC
    ");
    $bookedQuery->bind_param("i", $dermatologistId);
    $bookedQuery->execute();
    $bookedResult = $bookedQuery->get_result();
    
    $bookedSlots = [];
    while ($row = $bookedResult->fetch_assoc()) {
        $date = $row['appointment_date'];
        $time = $row['appointment_time'];
        
        // Normalize time format to HH:MM (remove seconds if present)
        $normalizedTime = substr($time, 0, 5);
        
        if (!isset($bookedSlots[$date])) {
            $bookedSlots[$date] = [];
        }
        $bookedSlots[$date][] = $normalizedTime;
    }
    $bookedQuery->close();

    echo json_encode([
        'success' => true,
        'daysOff' => $daysOff,
        'bookedSlots' => $bookedSlots
    ]);

} catch (Exception $e) {
    error_log("Availability fetch error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch availability data']);
}

$conn->close();
?>
