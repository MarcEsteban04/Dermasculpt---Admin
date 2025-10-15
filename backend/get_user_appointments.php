<?php
session_start();

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../config/connection.php';

$dermatologistId = $_SESSION['dermatologist_id'];
$userId = $_GET['user_id'] ?? 0;

if (empty($userId)) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

try {
    // Get appointments for the specific user and dermatologist
    $stmt = $conn->prepare("
        SELECT appointment_id, appointment_date, appointment_time, status, patient_name, reason_for_appointment
        FROM appointments 
        WHERE user_id = ? AND dermatologist_id = ? 
        AND status IN ('Scheduled', 'Pending')
        AND appointment_date >= CURDATE()
        ORDER BY appointment_date ASC, appointment_time ASC
    ");
    
    $stmt->bind_param("ii", $userId, $dermatologistId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'appointments' => $appointments
    ]);

} catch (Exception $e) {
    error_log("Get user appointments error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch appointments']);
}

$conn->close();
?>
