<?php
// Start output buffering to prevent any accidental output
ob_start();

// Disable error display to prevent HTML errors in JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

// Clear any previous output and set JSON header
ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../config/connection.php';

$dermatologistId = $_SESSION['dermatologist_id'];

try {
    // Get completed appointments for the current dermatologist
    $stmt = $conn->prepare("
        SELECT appointment_id, patient_name, email, phone_number, appointment_date, appointment_time, 
               reason_for_appointment, dermatologist_notes, created_at, updated_at
        FROM appointments 
        WHERE dermatologist_id = ? AND status = 'Completed'
        ORDER BY appointment_date DESC, appointment_time DESC
    ");
    
    $stmt->bind_param("i", $dermatologistId);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Count completed appointments
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE dermatologist_id = ? AND status = 'Completed'");
    $countStmt->bind_param("i", $dermatologistId);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalCompleted = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    echo json_encode([
        'success' => true,
        'appointments' => $appointments,
        'total' => $totalCompleted
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
