<?php
session_start();

header('Content-Type: application/json');

require_once '../config/connection.php';

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin'] || !isset($_SESSION['dermatologist_id'])) {
    http_response_code(401); 
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$dermatologistId = $_SESSION['dermatologist_id'];
$receiverId = $_POST['receiver_id'] ?? null;
$messageText = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';
$attachment_url = null;
$attachment_type = null;

if (empty($receiverId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Receiver ID is missing.']);
    exit;
}

if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['attachment'];

    $max_size = 15 * 1024 * 1024; // 15 MB
    if ($file['size'] > $max_size) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File is too large. Maximum size is 15MB.']);
        exit;
    }

    $allowed_types = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'application/msword', 
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid file type.']);
        exit;
    }

    $upload_dir = '../uploads/attachments/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0775, true);
    }
    
    $safe_filename = preg_replace("/[^a-zA-Z0-9-_\.]/", "", basename($file['name']));
    $unique_filename = uniqid() . '-' . $safe_filename;
    $destination = $upload_dir . $unique_filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $attachment_url = 'uploads/attachments/' . $unique_filename;
        $attachment_type = $mime_type;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
        exit;
    }
}

if (empty($messageText) && empty($attachment_url)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A message or attachment is required.']);
    exit;
}

$stmt = $conn->prepare(
    "INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message_text, attachment_url, attachment_type) VALUES (?, 'dermatologist', ?, 'patient', ?, ?, ?)"
);
$stmt->bind_param("iisss", $dermatologistId, $receiverId, $messageText, $attachment_url, $attachment_type);

if ($stmt->execute()) {
    $newMessageId = $stmt->insert_id;
    $stmt->close();
    
    $selectStmt = $conn->prepare("SELECT * FROM messages WHERE message_id = ?");
    $selectStmt->bind_param("i", $newMessageId);
    $selectStmt->execute();
    $newMessage = $selectStmt->get_result()->fetch_assoc();
    $selectStmt->close();

    echo json_encode(['success' => true, 'messageData' => $newMessage]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: Failed to send message.']);
}

$conn->close();
?>