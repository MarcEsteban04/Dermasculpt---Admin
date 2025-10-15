<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    session_start();
    require_once '../config/connection.php';

    if ($conn->connect_error) {
        throw new Exception("Database Connection Failed: " . $conn->connect_error);
    }

    if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin'] || !isset($_SESSION['dermatologist_id'])) {
        http_response_code(401);
        throw new Exception('Unauthorized access.');
    }

    $dermatologistId = $_SESSION['dermatologist_id'];
    $data = json_decode(file_get_contents('php://input'), true);
    $messageId = $data['message_id'] ?? null;
    $newMessageText = $data['message_text'] ?? null;

    if (empty($messageId) || !isset($newMessageText)) {
        http_response_code(400);
        throw new Exception('Required data is missing.');
    }

    $stmt = $conn->prepare("UPDATE messages SET message_text = ?, edited_at = NOW() WHERE message_id = ? AND sender_id = ? AND sender_role = 'dermatologist'");
    if ($stmt === false) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("sii", $newMessageText, $messageId, $dermatologistId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Message updated successfully.']);
        } else {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Could not update message. You may not have permission or the content was unchanged.']);
        }
    } else {
        throw new Exception('Database execute failed: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>