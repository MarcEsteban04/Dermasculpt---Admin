<?php
// Start output buffering to prevent any accidental output
ob_start();

// Disable error display to prevent HTML errors in JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

// Clear any previous output and set JSON header
ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../config/connection.php';

$dermatologistId = $_SESSION['dermatologist_id'];

try {
    $analysisId = $_POST['analysis_id'] ?? null;
    $dermatologistDiagnosis = $_POST['dermatologist_diagnosis'] ?? '';
    $dermatologistNotes = $_POST['dermatologist_notes'] ?? '';
    $status = $_POST['status'] ?? 'pending';

    if (!$analysisId) {
        throw new Exception('Analysis ID is required');
    }

    // Validate status
    $validStatuses = ['pending', 'reviewed', 'confirmed', 'rejected'];
    if (!in_array($status, $validStatuses)) {
        throw new Exception('Invalid status');
    }

    // Update analysis
    $stmt = $conn->prepare("
        UPDATE skin_analysis 
        SET dermatologist_diagnosis = ?, dermatologist_notes = ?, status = ?, updated_at = NOW()
        WHERE analysis_id = ? AND dermatologist_id = ?
    ");

    $stmt->bind_param("sssii", $dermatologistDiagnosis, $dermatologistNotes, $status, $analysisId, $dermatologistId);

    if (!$stmt->execute()) {
        throw new Exception('Failed to update analysis');
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception('Analysis not found or no changes made');
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Analysis updated successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
