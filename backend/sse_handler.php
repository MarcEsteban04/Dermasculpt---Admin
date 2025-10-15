<?php
session_start();
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

if (!isset($_SESSION['loggedin']) || !isset($_SESSION['dermatologist_id'])) {
    exit();
}

require_once '../config/connection.php';
$dermatologistId = $_SESSION['dermatologist_id'];
session_write_close();

$stmt = $conn->prepare("SELECT MAX(message_id) as max_id FROM messages WHERE receiver_id = ?");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$last_known_max_id = $result['max_id'] ?? 0;
$stmt->close();

while (true) {
    if (connection_aborted()) {
        $conn->close();
        exit();
    }
    
    $check_stmt = $conn->prepare("SELECT MAX(message_id) as max_id FROM messages WHERE receiver_id = ?");
    $check_stmt->bind_param("i", $dermatologistId);
    $check_stmt->execute();
    $current_result = $check_stmt->get_result()->fetch_assoc();
    $current_max_id = $current_result['max_id'] ?? 0;
    $check_stmt->close();
    
    if ($current_max_id > $last_known_max_id) {
        echo "event: update\n";
        echo "data: " . json_encode(['status' => 'new_message']) . "\n\n";
        ob_flush();
        flush();
        $last_known_max_id = $current_max_id;
    }
    
    sleep(2);
}
?>