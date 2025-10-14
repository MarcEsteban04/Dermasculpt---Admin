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
$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

// Revenue metrics
$revSql = "SELECT 
    COALESCE(SUM(p.amount),0) as revenue,
    COUNT(DISTINCT p.invoice_id) as paid_invoices
  FROM payments p
  JOIN invoices i ON i.invoice_id = p.invoice_id
  WHERE i.dermatologist_id = ? AND p.paid_at BETWEEN CONCAT(?, ' 00:00:00') AND CONCAT(?, ' 23:59:59')";
$revStmt = $conn->prepare($revSql);
$revStmt->bind_param('iss', $dermatologistId, $from, $to);
$revStmt->execute();
$rev = $revStmt->get_result()->fetch_assoc();
$revStmt->close();

// Appointment stats
$apptSql = "SELECT 
    SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status='Cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN status='Scheduled' THEN 1 ELSE 0 END) as scheduled,
    SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending
  FROM appointments 
  WHERE dermatologist_id = ? AND appointment_date BETWEEN ? AND ?";
$apptStmt = $conn->prepare($apptSql);
$apptStmt->bind_param('iss', $dermatologistId, $from, $to);
$apptStmt->execute();
$appt = $apptStmt->get_result()->fetch_assoc();
$apptStmt->close();

// Top services by invoice item description
$svcSql = "SELECT ii.description, SUM(ii.line_total) as total
  FROM invoice_items ii
  JOIN invoices i ON i.invoice_id = ii.invoice_id
  WHERE i.dermatologist_id = ? AND i.issue_date BETWEEN ? AND ?
  GROUP BY ii.description
  ORDER BY total DESC
  LIMIT 10";
$svcStmt = $conn->prepare($svcSql);
$svcStmt->bind_param('iss', $dermatologistId, $from, $to);
$svcStmt->execute();
$svc = $svcStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$svcStmt->close();

ob_clean();
echo json_encode([
  'revenue' => [
    'total' => (float)($rev['revenue'] ?? 0),
    'paid_invoices' => (int)($rev['paid_invoices'] ?? 0)
  ],
  'appointments' => [
    'completed' => (int)($appt['completed'] ?? 0),
    'cancelled' => (int)($appt['cancelled'] ?? 0),
    'scheduled' => (int)($appt['scheduled'] ?? 0),
    'pending' => (int)($appt['pending'] ?? 0)
  ],
  'top_services' => $svc,
  'range' => ['from' => $from, 'to' => $to]
]);
exit;
?>


