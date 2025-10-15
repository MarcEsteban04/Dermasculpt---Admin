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
    // Get recent analyses for the current dermatologist
    $stmt = $conn->prepare("
        SELECT analysis_id, patient_name, patient_age, patient_gender, image_filename, 
               ai_diagnosis, confidence_score, status, created_at, dermatologist_diagnosis
        FROM skin_analysis 
        WHERE dermatologist_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    
    $stmt->bind_param("i", $dermatologistId);
    $stmt->execute();
    $result = $stmt->get_result();
    $analyses = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Generate HTML for recent analyses
    $html = generateRecentAnalysesHTML($analyses);

    echo json_encode([
        'success' => true,
        'html' => $html,
        'count' => count($analyses)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function generateRecentAnalysesHTML($analyses) {
    if (empty($analyses)) {
        return '
        <div class="text-center py-8 text-gray-500">
            <i class="fas fa-microscope fa-3x mb-4 opacity-50"></i>
            <p>No analyses yet. Upload your first skin image to get started.</p>
        </div>';
    }

    $html = '';
    foreach ($analyses as $analysis) {
        $statusClass = getStatusBadgeClass($analysis['status']);
        
        $html .= '
        <div class="analysis-card border rounded-lg p-4 cursor-pointer hover:shadow-md" onclick="viewAnalysis(' . $analysis['analysis_id'] . ')">
            <div class="flex justify-between items-start mb-2">
                <div>
                    <h4 class="font-semibold text-gray-800">
                        ' . htmlspecialchars($analysis['patient_name'] ?: 'Anonymous Patient') . '
                    </h4>
                    <p class="text-sm text-gray-600">
                        ' . ($analysis['patient_age'] ? $analysis['patient_age'] . ' years' : '') . '
                        ' . ($analysis['patient_gender'] ? ($analysis['patient_age'] ? ', ' : '') . $analysis['patient_gender'] : '') . '
                    </p>
                </div>
                <span class="px-2 py-1 text-xs font-semibold rounded-full ' . $statusClass . '">
                    ' . ucfirst($analysis['status']) . '
                </span>
            </div>
            
            ' . ($analysis['confidence_score'] ? '
            <div class="mb-2">
                <div class="flex justify-between text-xs text-gray-600 mb-1">
                    <span>Confidence</span>
                    <span>' . $analysis['confidence_score'] . '%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="confidence-bar h-2 rounded-full" style="width: ' . $analysis['confidence_score'] . '%"></div>
                </div>
            </div>
            ' : '') . '
            
            <p class="text-sm text-gray-600 mb-2">
                ' . date('M j, Y g:i A', strtotime($analysis['created_at'])) . '
            </p>
            
            ' . ($analysis['ai_diagnosis'] ? '
            <p class="text-sm text-gray-700 line-clamp-2">
                ' . htmlspecialchars(substr($analysis['ai_diagnosis'], 0, 100)) . '...
            </p>
            ' : '') . '
        </div>';
    }

    return $html;
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'reviewed':
            return 'bg-blue-100 text-blue-800';
        case 'confirmed':
            return 'bg-green-100 text-green-800';
        case 'rejected':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}
?>
