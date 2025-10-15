<?php
// This endpoint will handle patient replies to dermatologist responses
// It can be called when patients reply via email or through a web form

session_start();
header('Content-Type: application/json');

require_once '../config/connection.php';

$input = json_decode(file_get_contents('php://input'), true);
$originalMessageId = $input['original_message_id'] ?? 0;
$patientReply = trim($input['patient_reply'] ?? '');
$patientName = trim($input['patient_name'] ?? '');
$patientEmail = trim($input['patient_email'] ?? '');

if (!$originalMessageId || !$patientReply || !$patientName || !$patientEmail) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Verify the original message exists
$stmt = $conn->prepare("SELECT * FROM contact_messages WHERE id = ?");
$stmt->bind_param("i", $originalMessageId);
$stmt->execute();
$result = $stmt->get_result();
$originalMessage = $result->fetch_assoc();
$stmt->close();

if (!$originalMessage) {
    echo json_encode(['success' => false, 'message' => 'Original message not found']);
    exit;
}

// Verify the patient email matches the original inquiry
if (strtolower($patientEmail) !== strtolower($originalMessage['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email does not match original inquiry']);
    exit;
}

try {
    // Insert patient reply into inquiry_replies table
    $insertStmt = $conn->prepare("INSERT INTO inquiry_replies (original_message_id, dermatologist_id, reply_message, reply_type, sender_name, sender_email, is_read) VALUES (?, 1, ?, 'patient', ?, ?, 0)");
    $insertStmt->bind_param("isss", $originalMessageId, $patientReply, $patientName, $patientEmail);
    $insertStmt->execute();
    $insertStmt->close();
    
    // Update the original message status back to 'read' since there's a new patient reply
    $updateStmt = $conn->prepare("UPDATE contact_messages SET status = 'read', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $updateStmt->bind_param("i", $originalMessageId);
    $updateStmt->execute();
    $updateStmt->close();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Patient reply received and saved successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to save patient reply. Please try again later.'
    ]);
}

$conn->close();
?>
