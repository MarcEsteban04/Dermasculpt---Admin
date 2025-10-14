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

$userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$appointmentId = isset($input['appointment_id']) && $input['appointment_id'] !== null ? (int)$input['appointment_id'] : null;
$issueDate = $input['issue_date'] ?? date('Y-m-d');
$dueDate = $input['due_date'] ?? null;
$items = $input['items'] ?? [];
$discountAmount = isset($input['discount_amount']) ? (float)$input['discount_amount'] : 0.0;
$taxAmount = isset($input['tax_amount']) ? (float)$input['tax_amount'] : 0.0;
$notes = $input['notes'] ?? null;

if ($userId <= 0 || empty($items)) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id and items are required']);
    exit;
}

$conn->begin_transaction();
try {
    $subtotal = 0.0;
    foreach ($items as $it) {
        $qty = isset($it['quantity']) ? (float)$it['quantity'] : 1.0;
        $unit = isset($it['unit_price']) ? (float)$it['unit_price'] : 0.0;
        $subtotal += $qty * $unit;
    }
    $total = max(0.0, $subtotal - $discountAmount + $taxAmount);

    // generate invoice_number simple pattern: INV-YYYYMMDD-random4
    $invoiceNumber = 'INV-' . date('Ymd') . '-' . substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4);

    $sql = "INSERT INTO invoices (invoice_number, appointment_id, user_id, dermatologist_id, issue_date, due_date, subtotal_amount, discount_amount, tax_amount, total_amount, status, notes) VALUES (?,?,?,?,?,?,?,?,?,?, 'sent', ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'siiisssddds',
        $invoiceNumber,
        $appointmentId,
        $userId,
        $dermatologistId,
        $issueDate,
        $dueDate,
        $subtotal,
        $discountAmount,
        $taxAmount,
        $total,
        $notes
    );
    $stmt->execute();
    $invoiceId = $stmt->insert_id;
    $stmt->close();

    $itemSql = "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total) VALUES (?,?,?,?,?)";
    $itemStmt = $conn->prepare($itemSql);
    foreach ($items as $it) {
        $desc = trim((string)($it['description'] ?? 'Item'));
        $qty = isset($it['quantity']) ? (float)$it['quantity'] : 1.0;
        $unit = isset($it['unit_price']) ? (float)$it['unit_price'] : 0.0;
        $line = $qty * $unit;
        $itemStmt->bind_param('isddd', $invoiceId, $desc, $qty, $unit, $line);
        $itemStmt->execute();
    }
    $itemStmt->close();

    $conn->commit();
    ob_clean();
    echo json_encode(['invoice_id' => $invoiceId, 'invoice_number' => $invoiceNumber, 'total_amount' => $total]);
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    ob_clean();
    echo json_encode(['error' => 'Failed to create invoice']);
    exit;
}
?>


