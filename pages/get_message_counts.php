<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../config/connection.php';

// Count messages by status
$counts = [
    'all' => 0,
    'unread' => 0,
    'read' => 0,
    'replied' => 0
];

$count_sql = "SELECT status, COUNT(*) as count FROM contact_messages GROUP BY status";
$count_result = $conn->query($count_sql);

if ($count_result) {
    while ($row = $count_result->fetch_assoc()) {
        $counts[$row['status']] = $row['count'];
        $counts['all'] += $row['count'];
    }
}

echo json_encode([
    'success' => true,
    'counts' => $counts
]);

$conn->close();
?>
