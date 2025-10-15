    <?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../config/connection.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'mark_read':
        $messageId = $input['message_id'] ?? 0;
        
        if (!$messageId) {
            echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE contact_messages SET status = 'read', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("i", $messageId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Message marked as read']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update message status']);
        }
        $stmt->close();
        break;
        
    case 'mark_all_read':
        $stmt = $conn->prepare("UPDATE contact_messages SET status = 'read', updated_at = CURRENT_TIMESTAMP WHERE status = 'unread'");
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            echo json_encode(['success' => true, 'message' => "Marked $affected_rows messages as read"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update message status']);
        }
        $stmt->close();
        break;
        
    case 'delete_message':
        $messageId = $input['message_id'] ?? 0;
        
        if (!$messageId) {
            echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
            exit;
        }
        
        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->bind_param("i", $messageId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Message deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete message']);
        }
        $stmt->close();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$conn->close();
?>
