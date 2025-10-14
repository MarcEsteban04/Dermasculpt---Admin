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
$invoiceId = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
if ($invoiceId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invoice_id is required']);
    exit;
}

$sql = "SELECT i.*, u.first_name, u.last_name FROM invoices i JOIN users u ON u.user_id = i.user_id WHERE i.invoice_id = ? AND i.dermatologist_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $invoiceId, $dermatologistId);
$stmt->execute();
$res = $stmt->get_result();
$invoice = $res->fetch_assoc();
$stmt->close();

if (!$invoice) {
    http_response_code(404);
    echo json_encode(['error' => 'Invoice not found']);
    exit;
}

$itemsStmt = $conn->prepare("SELECT item_id, description, quantity, unit_price, line_total FROM invoice_items WHERE invoice_id = ? ORDER BY item_id ASC");
$itemsStmt->bind_param('i', $invoiceId);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

$pstmt = $conn->prepare("SELECT payment_id, amount, payment_method, reference, paid_at FROM payments WHERE invoice_id = ? ORDER BY paid_at ASC");
$pstmt->bind_param('i', $invoiceId);
$pstmt->execute();
$payments = $pstmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pstmt->close();

ob_clean();
echo json_encode(['invoice' => $invoice, 'items' => $items, 'payments' => $payments]);
exit;
?>


