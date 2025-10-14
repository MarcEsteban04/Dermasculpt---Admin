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
    $appointmentId = $_POST['appointment_id'] ?? null;
    $dermatologistNotes = $_POST['dermatologist_notes'] ?? '';

    if (!$appointmentId) {
        throw new Exception('Appointment ID is required');
    }

    // Validate that the appointment belongs to the current dermatologist and is completed
    $stmt = $conn->prepare("
        SELECT appointment_id, status 
        FROM appointments 
        WHERE appointment_id = ? AND dermatologist_id = ? AND status = 'Completed'
    ");
    
    $stmt->bind_param("ii", $appointmentId, $dermatologistId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Appointment not found, not completed, or access denied');
    }
    
    $stmt->close();

    // Update the appointment notes
    $updateStmt = $conn->prepare("
        UPDATE appointments 
        SET dermatologist_notes = ?, updated_at = NOW()
        WHERE appointment_id = ? AND dermatologist_id = ?
    ");
    
    $updateStmt->bind_param("sii", $dermatologistNotes, $appointmentId, $dermatologistId);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update appointment notes');
    }

    if ($updateStmt->affected_rows === 0) {
        throw new Exception('No changes made to the appointment');
    }

    $updateStmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Appointment notes updated successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
