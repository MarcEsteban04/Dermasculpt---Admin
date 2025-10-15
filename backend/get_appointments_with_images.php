<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../config/connection.php';

try {
    $dermatologistId = $_SESSION['dermatologist_id'];
    
    // Get appointments with images
    $stmt = $conn->prepare("
        SELECT 
            appointment_id, 
            patient_name, 
            appointment_date, 
            appointment_time, 
            status, 
            image_paths,
            reason_for_appointment,
            created_at
        FROM appointments 
        WHERE dermatologist_id = ? 
        AND image_paths IS NOT NULL 
        AND image_paths != '' 
        ORDER BY appointment_date DESC, appointment_time DESC
    ");
    
    $stmt->bind_param("i", $dermatologistId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        // Parse image paths (they might be JSON or comma-separated)
        $imagePaths = [];
        if (!empty($row['image_paths'])) {
            // Try to decode as JSON first
            $decoded = json_decode($row['image_paths'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $imagePaths = $decoded;
            } else {
                // If not JSON, treat as single path
                $imagePaths = [$row['image_paths']];
            }
            
            // Filter out empty paths
            $imagePaths = array_filter($imagePaths, function($path) {
                return !empty(trim($path));
            });
        }
        
        if (!empty($imagePaths)) {
            $row['image_paths'] = $imagePaths;
            $appointments[] = $row;
        }
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true, 
        'appointments' => $appointments
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error fetching appointments: ' . $e->getMessage()
    ]);
}
?>
