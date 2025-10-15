<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../config/connection.php';
$dermatologistId = $_SESSION['dermatologist_id'];

$sql = "
    SELECT
        u.user_id,
        last_msg.message_text AS last_message,
        last_msg.attachment_url AS last_attachment_url,
        last_msg.timestamp AS last_message_timestamp,
        last_msg.is_read AS last_message_is_read,
        last_msg.sender_role AS last_message_sender_role,
        COALESCE(unread.unread_count, 0) AS unread_count
    FROM (
        SELECT IF(m.sender_id = ? AND m.sender_role = 'dermatologist', m.receiver_id, m.sender_id) AS user_id,
               MAX(m.message_id) AS last_message_id
        FROM messages m WHERE (m.sender_id = ? AND m.sender_role = 'dermatologist') OR (m.receiver_id = ? AND m.receiver_role = 'dermatologist')
        GROUP BY user_id
    ) AS convos
    JOIN messages AS last_msg ON convos.last_message_id = last_msg.message_id
    JOIN users AS u ON convos.user_id = u.user_id
    LEFT JOIN (
        SELECT sender_id, COUNT(*) AS unread_count FROM messages
        WHERE receiver_id = ? AND is_read = 0 AND sender_role = 'patient'
        GROUP BY sender_id
    ) AS unread ON unread.sender_id = convos.user_id
    ORDER BY last_msg.timestamp DESC;
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $dermatologistId, $dermatologistId, $dermatologistId, $dermatologistId);
$stmt->execute();
$result = $stmt->get_result();
$conversations = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($conversations);

$stmt->close();
$conn->close();
?>