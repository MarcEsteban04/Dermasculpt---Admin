<?php
// Prevent any output before JSON response
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    ob_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

require_once '../config/connection.php';

$dermatologistId = $_SESSION['dermatologist_id'];
$lastUpdate = $_GET['last_update'] ?? '';

$response = ['has_updates' => false, 'appointments' => [], 'counts' => [], 'debug' => [], 'change_types' => [], 'badge_counts' => []];

try {
    // Get appointments created or updated after the last check
    if ($lastUpdate) {
        $sql = "SELECT appointment_id, patient_name, appointment_date, appointment_time, status, updated_at, created_at 
                FROM appointments 
                WHERE dermatologist_id = ? AND (updated_at > ? OR created_at > ?) 
                ORDER BY appointment_date ASC, appointment_time ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $dermatologistId, $lastUpdate, $lastUpdate);
    } else {
        // If no last update time, get all appointments
        $sql = "SELECT appointment_id, patient_name, appointment_date, appointment_time, status, updated_at, created_at 
                FROM appointments 
                WHERE dermatologist_id = ? 
                ORDER BY appointment_date ASC, appointment_time ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $dermatologistId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $appointments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Add debug information
    $response['debug'] = [
        'last_update_received' => $lastUpdate,
        'current_server_time' => date('Y-m-d H:i:s'),
        'appointments_found' => count($appointments),
        'query_used' => $lastUpdate ? 'with_timestamp' : 'all_appointments'
    ];

    if (!empty($appointments)) {
        $response['has_updates'] = true;
        
        // Log what type of changes were detected
        $changeTypes = [];
        foreach ($appointments as $appt) {
            if ($appt['status'] === 'Cancelled') {
                $changeTypes[] = 'cancellation';
            }
            if ($lastUpdate && $appt['created_at'] > $lastUpdate) {
                $changeTypes[] = 'new_appointment';
            }
            if ($lastUpdate && $appt['updated_at'] > $lastUpdate && $appt['created_at'] <= $lastUpdate) {
                $changeTypes[] = 'status_change';
            }
        }
        $response['change_types'] = array_values(array_unique($changeTypes)); // Ensure it's a proper array
        
        // If there are updates, get ALL appointments for complete refresh
        $allSql = "SELECT appointment_id, patient_name, appointment_date, appointment_time, status 
                   FROM appointments 
                   WHERE dermatologist_id = ? 
                   ORDER BY appointment_date ASC, appointment_time ASC";
        $allStmt = $conn->prepare($allSql);
        $allStmt->bind_param("i", $dermatologistId);
        $allStmt->execute();
        $allResult = $allStmt->get_result();
        $allAppointments = $allResult->fetch_all(MYSQLI_ASSOC);
        $allStmt->close();
        
        $response['appointments'] = array_map(function ($appt) {
            return [
                'id' => $appt['appointment_id'],
                'date' => $appt['appointment_date'],
                'time' => $appt['appointment_time'],
                'patient' => $appt['patient_name'],
                'status' => $appt['status']
            ];
        }, $allAppointments);
    }

    // Get updated counts
    $countSql = "SELECT status, COUNT(*) as count FROM appointments WHERE dermatologist_id = ? GROUP BY status";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param("i", $dermatologistId);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    
    $counts = [
        'pending' => 0,
        'scheduled' => 0,
        'finished' => 0,
        'cancelled' => 0
    ];
    
    while ($row = $countResult->fetch_assoc()) {
        $status = strtolower($row['status']);
        if ($status === 'completed') $status = 'finished';
        if (isset($counts[$status])) {
            $counts[$status] = $row['count'];
        }
    }
    $countStmt->close();
    
    $response['counts'] = $counts;
    $response['last_update'] = date('Y-m-d H:i:s');

} catch (Exception $e) {
    $response['error'] = 'Failed to check for updates.';
}

$conn->close();

// Clean any output buffer and send JSON response
ob_clean();
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
