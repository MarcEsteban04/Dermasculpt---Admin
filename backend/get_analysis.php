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
$analysisId = $_GET['id'] ?? null;

if (!$analysisId) {
    echo json_encode(['success' => false, 'message' => 'Analysis ID required']);
    exit;
}

try {
    // Get analysis data
    $stmt = $conn->prepare("
        SELECT analysis_id, patient_name, patient_age, patient_gender, image_path, image_filename,
               analysis_prompt, ai_diagnosis, confidence_score, detected_conditions, recommendations,
               dermatologist_notes, dermatologist_diagnosis, status, created_at, updated_at
        FROM skin_analysis 
        WHERE analysis_id = ? AND dermatologist_id = ?
    ");
    
    $stmt->bind_param("ii", $analysisId, $dermatologistId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Analysis not found');
    }
    
    $analysis = $result->fetch_assoc();
    $stmt->close();
    
    // Parse detected conditions JSON
    $detectedConditions = json_decode($analysis['detected_conditions'], true) ?? [];
    
    // Generate HTML content for modal
    $html = generateAnalysisHTML($analysis, $detectedConditions);
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'data' => $analysis
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function generateAnalysisHTML($analysis, $detectedConditions) {
    $patientInfo = '';
    if ($analysis['patient_name']) $patientInfo .= htmlspecialchars($analysis['patient_name']);
    if ($analysis['patient_age']) $patientInfo .= $patientInfo ? ', ' . $analysis['patient_age'] . ' years' : $analysis['patient_age'] . ' years';
    if ($analysis['patient_gender']) $patientInfo .= $patientInfo ? ', ' . $analysis['patient_gender'] : $analysis['patient_gender'];
    
    $statusClass = getStatusBadgeClass($analysis['status']);
    
    $html = '
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Image Section -->
        <div>
            <div class="mb-4">
                <img src="' . htmlspecialchars($analysis['image_path']) . '" 
                     alt="Skin analysis image" 
                     class="w-full max-w-md mx-auto rounded-lg shadow-lg">
            </div>
            
            <!-- Patient Information -->
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <h4 class="font-semibold text-gray-800 mb-2">Patient Information</h4>
                <p class="text-gray-700">' . ($patientInfo ?: 'Anonymous Patient') . '</p>
                <p class="text-sm text-gray-500 mt-1">
                    Analysis Date: ' . date('M j, Y g:i A', strtotime($analysis['created_at'])) . '
                </p>
                <div class="mt-2">
                    <span class="px-2 py-1 text-xs font-semibold rounded-full ' . $statusClass . '">
                        ' . ucfirst($analysis['status']) . '
                    </span>
                </div>
            </div>
            
            ' . ($analysis['analysis_prompt'] ? '
            <div class="bg-blue-50 rounded-lg p-4">
                <h4 class="font-semibold text-blue-800 mb-2">Analysis Focus</h4>
                <p class="text-blue-700 text-sm">' . nl2br(htmlspecialchars($analysis['analysis_prompt'])) . '</p>
            </div>
            ' : '') . '
        </div>
        
        <!-- Analysis Results -->
        <div class="space-y-4">
            <!-- AI Diagnosis -->
            <div class="bg-white border rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-robot text-blue-500 mr-2"></i>
                        AI Analysis
                    </h4>
                    ' . ($analysis['confidence_score'] ? '
                    <div class="text-right">
                        <div class="text-xs text-gray-600 mb-1">Confidence</div>
                        <div class="flex items-center">
                            <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                <div class="bg-gradient-to-r from-red-500 via-yellow-500 to-green-500 h-2 rounded-full" 
                                     style="width: ' . $analysis['confidence_score'] . '%"></div>
                            </div>
                            <span class="text-sm font-semibold">' . $analysis['confidence_score'] . '%</span>
                        </div>
                    </div>
                    ' : '') . '
                </div>
                
                <div class="mb-3">
                    <h5 class="font-medium text-gray-700 mb-1">Primary Diagnosis:</h5>
                    <p class="text-gray-800">' . htmlspecialchars($analysis['ai_diagnosis']) . '</p>
                </div>
                
                ' . (!empty($detectedConditions) ? '
                <div class="mb-3">
                    <h5 class="font-medium text-gray-700 mb-2">Differential Diagnoses:</h5>
                    <div class="space-y-2">
                        ' . generateConditionsList($detectedConditions) . '
                    </div>
                </div>
                ' : '') . '
                
                ' . ($analysis['recommendations'] ? '
                <div>
                    <h5 class="font-medium text-gray-700 mb-1">Recommendations:</h5>
                    <div class="text-gray-700 text-sm prose prose-sm max-w-none">
                        ' . formatRecommendations($analysis['recommendations']) . '
                    </div>
                </div>
                ' : '') . '
            </div>
            
            <!-- Dermatologist Review -->
            <div class="bg-white border rounded-lg p-4">
                <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                    <i class="fas fa-user-md text-green-500 mr-2"></i>
                    Dermatologist Review
                </h4>
                
                <form id="reviewForm" class="space-y-3">
                    <input type="hidden" name="analysis_id" value="' . $analysis['analysis_id'] . '">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Clinical Diagnosis</label>
                        <textarea name="dermatologist_diagnosis" rows="2" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                  placeholder="Enter your clinical diagnosis...">' . htmlspecialchars($analysis['dermatologist_diagnosis'] ?? '') . '</textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Clinical Notes</label>
                        <textarea name="dermatologist_notes" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                  placeholder="Add your clinical observations and notes...">' . htmlspecialchars($analysis['dermatologist_notes'] ?? '') . '</textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="pending"' . ($analysis['status'] === 'pending' ? ' selected' : '') . '>Pending Review</option>
                            <option value="reviewed"' . ($analysis['status'] === 'reviewed' ? ' selected' : '') . '>Reviewed</option>
                            <option value="confirmed"' . ($analysis['status'] === 'confirmed' ? ' selected' : '') . '>Confirmed</option>
                            <option value="rejected"' . ($analysis['status'] === 'rejected' ? ' selected' : '') . '>Rejected</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <i class="fas fa-save mr-2"></i>Save Review
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    document.getElementById("reviewForm").addEventListener("submit", async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch("../backend/update_analysis.php", {
                method: "POST",
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                Swal.fire("Success", "Analysis updated successfully", "success");
            } else {
                throw new Error(result.message || "Failed to update analysis");
            }
        } catch (error) {
            console.error("Update error:", error);
            Swal.fire("Error", error.message || "Failed to update analysis", "error");
        }
    });
    </script>
    ';
    
    return $html;
}

function generateConditionsList($conditions) {
    $html = '';
    foreach ($conditions as $condition) {
        $probability = $condition['probability'] ?? 0;
        $html .= '
        <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
            <div>
                <span class="font-medium text-sm">' . htmlspecialchars($condition['condition'] ?? '') . '</span>
                ' . (isset($condition['rationale']) ? '
                <p class="text-xs text-gray-600 mt-1">' . htmlspecialchars($condition['rationale']) . '</p>
                ' : '') . '
            </div>
            <span class="text-sm font-semibold text-blue-600">' . $probability . '%</span>
        </div>';
    }
    return $html;
}

function formatRecommendations($recommendations) {
    // Convert markdown-style formatting to HTML
    $formatted = $recommendations;
    $formatted = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $formatted);
    $formatted = nl2br($formatted);
    return $formatted;
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
