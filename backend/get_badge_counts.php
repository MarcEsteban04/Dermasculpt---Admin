<?php
// Prevent any output before JSON response
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    ob_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

require_once '../config/connection.php';

$dermatologistId = $_SESSION['dermatologist_id'];
$response = ['pending_appointments' => 0, 'unread_messages' => 0];

try {
    // Get pending appointments count
    $appointmentSql = "SELECT COUNT(appointment_id) as count FROM appointments WHERE dermatologist_id = ? AND status = 'Pending'";
    $appointmentStmt = $conn->prepare($appointmentSql);
    $appointmentStmt->bind_param("i", $dermatologistId);
    $appointmentStmt->execute();
    $appointmentResult = $appointmentStmt->get_result();
    $appointmentData = $appointmentResult->fetch_assoc();
    $response['pending_appointments'] = $appointmentData['count'];
    $appointmentStmt->close();

    // Get unread messages count
    $messageSql = "SELECT COUNT(message_id) as count FROM messages WHERE receiver_id = ? AND is_read = 0 AND receiver_role = 'dermatologist' AND sender_role = 'user'";
    $messageStmt = $conn->prepare($messageSql);
    $messageStmt->bind_param("i", $dermatologistId);
    $messageStmt->execute();
    $messageResult = $messageStmt->get_result();
    $messageData = $messageResult->fetch_assoc();
    $response['unread_messages'] = $messageData['count'];
    $messageStmt->close();

} catch (Exception $e) {
    $response['error'] = 'Failed to get badge counts.';
}

$conn->close();

// Clean any output buffer and send JSON response
ob_clean();
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
