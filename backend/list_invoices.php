<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin'] || !isset($_SESSION['dermatologist_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../config/connection.php';

$dermatologistId = (int)$_SESSION['dermatologist_id'];
$status = isset($_GET['status']) ? $_GET['status'] : null;
$from = isset($_GET['from']) ? $_GET['from'] : null;
$to = isset($_GET['to']) ? $_GET['to'] : null;

$sql = "SELECT i.invoice_id, i.invoice_number, i.user_id, u.first_name, u.last_name, i.total_amount, i.status, i.issue_date, i.due_date
        FROM invoices i
        JOIN users u ON u.user_id = i.user_id
        WHERE i.dermatologist_id = ?";
$types = 'i';
$params = [$dermatologistId];

if ($status) { $sql .= " AND i.status = ?"; $types .= 's'; $params[] = $status; }
if ($from) { $sql .= " AND i.issue_date >= ?"; $types .= 's'; $params[] = $from; }
if ($to) { $sql .= " AND i.issue_date <= ?"; $types .= 's'; $params[] = $to; }

$sql .= " ORDER BY i.issue_date DESC, i.invoice_id DESC LIMIT 200";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

ob_clean();
echo json_encode(['invoices' => $rows]);
exit;
?>


