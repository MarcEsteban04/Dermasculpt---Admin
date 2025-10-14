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
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$invoiceId = isset($input['invoice_id']) ? (int)$input['invoice_id'] : 0;
$amount = isset($input['amount']) ? (float)$input['amount'] : 0.0;
$method = $input['payment_method'] ?? 'cash';
$reference = $input['reference'] ?? null;
$paidAt = $input['paid_at'] ?? null; // optional override

if ($invoiceId <= 0 || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invoice_id and positive amount are required']);
    exit;
}

$conn->begin_transaction();
try {
    // Verify invoice belongs to dermatologist
    $check = $conn->prepare("SELECT total_amount, status FROM invoices WHERE invoice_id = ? AND dermatologist_id = ? FOR UPDATE");
    $check->bind_param('ii', $invoiceId, $dermatologistId);
    $check->execute();
    $res = $check->get_result();
    $invoice = $res->fetch_assoc();
    $check->close();
    if (!$invoice) {
        throw new Exception('Invoice not found');
    }

    $ins = $conn->prepare("INSERT INTO payments (invoice_id, amount, payment_method, reference, paid_at) VALUES (?,?,?,?,COALESCE(?, NOW()))");
    $ins->bind_param('idsss', $invoiceId, $amount, $method, $reference, $paidAt);
    $ins->execute();
    $paymentId = $ins->insert_id;
    $ins->close();

    // Compute total paid to date
    $sum = $conn->prepare("SELECT SUM(amount) as paid FROM payments WHERE invoice_id = ?");
    $sum->bind_param('i', $invoiceId);
    $sum->execute();
    $sumRes = $sum->get_result()->fetch_assoc();
    $sum->close();
    $paid = (float)($sumRes['paid'] ?? 0.0);

    $newStatus = ($paid <= 0.001) ? 'sent' : (($paid + 0.001 >= (float)$invoice['total_amount']) ? 'paid' : 'partial');
    $upd = $conn->prepare("UPDATE invoices SET status = ?, updated_at = NOW() WHERE invoice_id = ?");
    $upd->bind_param('si', $newStatus, $invoiceId);
    $upd->execute();
    $upd->close();

    $conn->commit();
    ob_clean();
    echo json_encode(['payment_id' => $paymentId, 'invoice_status' => $newStatus, 'total_paid' => $paid]);
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    ob_clean();
    echo json_encode(['error' => 'Failed to record payment']);
    exit;
}
?>


