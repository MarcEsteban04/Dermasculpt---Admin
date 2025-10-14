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

    if (!$analysisId) {
        throw new Exception('Analysis ID is required');
    }

    // First, get the analysis to check ownership and get file path
    $stmt = $conn->prepare("
        SELECT image_path, image_filename 
        FROM skin_analysis 
        WHERE analysis_id = ? AND dermatologist_id = ?
    ");
    
    $stmt->bind_param("ii", $analysisId, $dermatologistId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Analysis not found or access denied');
    }
    
    $analysis = $result->fetch_assoc();
    $stmt->close();

    // Delete the analysis from database
    $deleteStmt = $conn->prepare("
        DELETE FROM skin_analysis 
        WHERE analysis_id = ? AND dermatologist_id = ?
    ");
    
    $deleteStmt->bind_param("ii", $analysisId, $dermatologistId);
    
    if (!$deleteStmt->execute()) {
        throw new Exception('Failed to delete analysis from database');
    }

    if ($deleteStmt->affected_rows === 0) {
        throw new Exception('Analysis not found or no changes made');
    }

    $deleteStmt->close();

    // Delete the image file if it exists
    if ($analysis['image_path'] && file_exists($analysis['image_path'])) {
        if (!unlink($analysis['image_path'])) {
            // Log warning but don't fail the operation
            error_log("Warning: Could not delete image file: " . $analysis['image_path']);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Analysis deleted successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
