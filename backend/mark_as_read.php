<?php
session_start();

require_once '../config/connection.php';

if (!isset($_SESSION['loggedin']) || !isset($_SESSION['dermatologist_id'])) {
    exit(); 
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit();
}

$message_id = filter_input(INPUT_POST, 'message_id', FILTER_SANITIZE_NUMBER_INT);
$dermatologist_id = $_SESSION['dermatologist_id'];

if ($message_id) {
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE message_id = ? AND receiver_id = ?");
    $stmt->bind_param("ii", $message_id, $dermatologist_id);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
?>