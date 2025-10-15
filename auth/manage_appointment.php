<?php
// Prevent any output before JSON response
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output

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
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Check if appointment_history table exists, create if not
$tableCheck = $conn->query("SHOW TABLES LIKE 'appointment_history'");
if ($tableCheck->num_rows == 0) {
    $createTable = "
        CREATE TABLE `appointment_history` (
          `history_id` int(11) NOT NULL AUTO_INCREMENT,
          `appointment_id` int(11) NOT NULL,
          `old_status` varchar(50) DEFAULT NULL,
          `new_status` varchar(50) NOT NULL,
          `old_date` date DEFAULT NULL,
          `new_date` date DEFAULT NULL,
          `old_time` time DEFAULT NULL,
          `new_time` time DEFAULT NULL,
          `action_type` enum('status_change','reschedule','create','cancel','accept') NOT NULL,
          `performed_by` varchar(100) NOT NULL,
          `performed_by_role` enum('dermatologist','system','admin') NOT NULL DEFAULT 'dermatologist',
          `notes` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`history_id`),
          KEY `appointment_id` (`appointment_id`),
          KEY `action_type` (`action_type`),
          KEY `created_at` (`created_at`),
          CONSTRAINT `appointment_history_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    $conn->query($createTable);
}

// Function to log appointment history
function logAppointmentHistory($conn, $appointmentId, $actionType, $oldStatus = null, $newStatus = null, $oldDate = null, $newDate = null, $oldTime = null, $newTime = null, $notes = null) {
    // Get dermatologist name from session or database
    $performedBy = 'Unknown';
    if (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
        $performedBy = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
    } else if (isset($_SESSION['dermatologist_id'])) {
        // Fallback: get name from database
        $nameStmt = $conn->prepare("SELECT first_name, last_name FROM dermatologists WHERE dermatologist_id = ?");
        $nameStmt->execute([$_SESSION['dermatologist_id']]);
        $nameResult = $nameStmt->fetch();
        if ($nameResult) {
            $performedBy = $nameResult['first_name'] . ' ' . $nameResult['last_name'];
        }
    }
    
    try {
        $historyStmt = $conn->prepare("
            INSERT INTO appointment_history (appointment_id, old_status, new_status, old_date, new_date, old_time, new_time, action_type, performed_by, performed_by_role, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'dermatologist', ?)
        ");
        $historyStmt->execute([$appointmentId, $oldStatus, $newStatus, $oldDate, $newDate, $oldTime, $newTime, $actionType, $performedBy, $notes]);
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log("History logging failed: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $appointmentId = $_POST['appointment_id'] ?? 0;

    if (empty($action) || empty($appointmentId)) {
        $response['message'] = 'Missing required parameters.';
        echo json_encode($response);
        exit;
    }

    // Verify the current dermatologist has ownership before any action
    $verifyStmt = $conn->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = ? AND dermatologist_id = ?");
    $verifyStmt->execute([$appointmentId, $dermatologistId]);
    $verifyStmt->store_result();
    if ($verifyStmt->num_rows === 0) {
        $response['message'] = 'You do not have permission to modify this appointment.';
        echo json_encode($response);
        exit;
    }

    switch ($action) {
        case 'accept':
            // Get current status first
            $currentStmt = $conn->prepare("SELECT status FROM appointments WHERE appointment_id = ?");
            $currentStmt->bind_param("i", $appointmentId);
            $currentStmt->execute();
            $currentStatus = $currentStmt->get_result()->fetch_assoc()['status'];
            $currentStmt->close();
            
            $stmt = $conn->prepare("UPDATE appointments SET status = 'Scheduled' WHERE appointment_id = ?");
            if ($stmt->execute()) {
                logAppointmentHistory($conn, $appointmentId, 'accept', $currentStatus, 'Scheduled', null, null, null, null, 'Appointment accepted by dermatologist');
                $response = ['success' => true, 'message' => 'Appointment accepted successfully.'];
            } else {
                $response['message'] = 'Failed to accept appointment.';
            }
            $stmt->close();
            break;

        case 'cancel':
            // Get current status first
            $currentStmt = $conn->prepare("SELECT status FROM appointments WHERE appointment_id = ?");
            $currentStmt->execute([$appointmentId]);
            $currentStatus = $currentStmt->fetch()['status'];
            
            $stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = ?");
            if ($stmt->execute([$appointmentId])) {
                logAppointmentHistory($conn, $appointmentId, 'cancel', $currentStatus, 'Cancelled', null, null, null, null, 'Appointment cancelled by dermatologist');
                $response = ['success' => true, 'message' => 'Appointment cancelled successfully.'];
            } else {
                $response['message'] = 'Failed to cancel appointment.';
            }
            break;
            
        case 'reschedule':
            $newDate = $_POST['appointment_date'] ?? '';
            $newTime = $_POST['appointment_time'] ?? '';
            
            if(empty($newDate) || empty($newTime)) {
                 $response['message'] = 'Date and time are required for rescheduling.';
                 break;
            }
            
            // Get current date and time first
            $currentStmt = $conn->prepare("SELECT appointment_date, appointment_time FROM appointments WHERE appointment_id = ?");
            $currentStmt->execute([$appointmentId]);
            $current = $currentStmt->fetch();
            
            $stmt = $conn->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ? WHERE appointment_id = ?");
             if ($stmt->execute([$newDate, $newTime, $appointmentId])) {
                logAppointmentHistory($conn, $appointmentId, 'reschedule', null, null, $current['appointment_date'], $newDate, $current['appointment_time'], $newTime, 'Appointment rescheduled by dermatologist');
                $response = ['success' => true, 'message' => 'Appointment rescheduled successfully.'];
            } else {
                $response['message'] = 'Failed to reschedule appointment.';
            }
            break;

        case 'transfer':
            $newDermatologistId = $_POST['new_dermatologist_id'] ?? 0;

            if (empty($newDermatologistId)) {
                $response['message'] = 'Please select a dermatologist to transfer to.';
                break;
            }
            
            // Check if transferring to self
            if ($newDermatologistId == $dermatologistId) {
                $response['message'] = 'You cannot transfer an appointment to yourself.';
                break;
            }
            
            $stmt = $conn->prepare("UPDATE appointments SET dermatologist_id = ? WHERE appointment_id = ?");
             if ($stmt->execute([$newDermatologistId, $appointmentId])) {
                $response = ['success' => true, 'message' => 'Appointment transferred successfully.'];
            } else {
                $response['message'] = 'Failed to transfer appointment.';
            }
            break;

        case 'get_details':
             $detailsStmt = $conn->prepare("
                SELECT a.patient_name, a.appointment_date, a.appointment_time, a.reason_for_appointment, a.status, a.user_notes, a.image_paths, u.email, u.phone_number
                FROM appointments a
                JOIN users u ON a.user_id = u.user_id
                WHERE a.appointment_id = ? AND a.dermatologist_id = ?
            ");
            $detailsStmt->bind_param("ii", $appointmentId, $dermatologistId);
            $detailsStmt->execute();
            $result = $detailsStmt->get_result();
            if ($details = $result->fetch_assoc()) {
                // Handle image_paths - could be JSON or comma-separated string
                if (!empty($details['image_paths'])) {
                    $decoded = json_decode($details['image_paths'], true);
                    $details['image_paths'] = $decoded ?: explode(',', $details['image_paths']);
                } else {
                    $details['image_paths'] = [];
                }
                $response = ['success' => true, 'data' => $details];
            } else {
                $response['message'] = 'Could not retrieve appointment details.';
            }
            $detailsStmt->close();
            break;

        case 'get_history':
            $historyStmt = $conn->prepare("
                SELECT action_type, old_status, new_status, old_date, new_date, old_time, new_time, performed_by, notes, created_at
                FROM appointment_history 
                WHERE appointment_id = ? 
                ORDER BY created_at DESC
            ");
            $historyStmt->bind_param("i", $appointmentId);
            $historyStmt->execute();
            $historyResult = $historyStmt->get_result();
            $history = [];
            while ($row = $historyResult->fetch_assoc()) {
                $history[] = $row;
            }
            $historyStmt->close();
            $response = ['success' => true, 'data' => $history];
            break;

        case 'complete':
            // Get current status first
            $currentStmt = $conn->prepare("SELECT status FROM appointments WHERE appointment_id = ?");
            $currentStmt->bind_param("i", $appointmentId);
            $currentStmt->execute();
            $currentStatus = $currentStmt->get_result()->fetch_assoc()['status'];
            $currentStmt->close();
            
            $stmt = $conn->prepare("UPDATE appointments SET status = 'Completed' WHERE appointment_id = ?");
            $stmt->bind_param("i", $appointmentId);
            if ($stmt->execute()) {
                logAppointmentHistory($conn, $appointmentId, 'status_change', $currentStatus, 'Completed', null, null, null, null, 'Appointment marked as completed by dermatologist');
                $response = ['success' => true, 'message' => 'Appointment marked as completed successfully.'];
            } else {
                $response['message'] = 'Failed to mark appointment as completed.';
            }
            $stmt->close();
            break;

        case 'send_reminder':
            // Get appointment and user details
            $appointmentStmt = $conn->prepare("
                SELECT a.user_id, a.patient_name, a.appointment_date, a.appointment_time, u.first_name, u.last_name
                FROM appointments a
                JOIN users u ON a.user_id = u.user_id
                WHERE a.appointment_id = ? AND a.dermatologist_id = ?
            ");
            $appointmentStmt->bind_param("ii", $appointmentId, $dermatologistId);
            $appointmentStmt->execute();
            $appointmentResult = $appointmentStmt->get_result();
            
            if ($appointmentData = $appointmentResult->fetch_assoc()) {
                $userId = $appointmentData['user_id'];
                $patientName = $appointmentData['patient_name'];
                $appointmentDate = date('F j, Y', strtotime($appointmentData['appointment_date']));
                $appointmentTime = date('g:i A', strtotime($appointmentData['appointment_time']));
                
                // Create reminder message
                $reminderMessage = "Hello {$appointmentData['first_name']}, this is a friendly reminder about your upcoming dermatology appointment for {$patientName} scheduled on {$appointmentDate} at {$appointmentTime}. Please arrive 15 minutes early. If you need to reschedule, please contact us as soon as possible. Thank you!";
                
                // Insert message into messages table
                $messageStmt = $conn->prepare("
                    INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message_text, is_read) 
                    VALUES (?, 'dermatologist', ?, 'user', ?, 0)
                ");
                $messageStmt->bind_param("iis", $dermatologistId, $userId, $reminderMessage);
                
                if ($messageStmt->execute()) {
                    logAppointmentHistory($conn, $appointmentId, 'status_change', null, null, null, null, null, null, 'Appointment reminder sent to patient');
                    $response = ['success' => true, 'message' => 'Reminder sent successfully to patient.'];
                } else {
                    $response['message'] = 'Failed to send reminder.';
                }
                $messageStmt->close();
            } else {
                $response['message'] = 'Appointment not found.';
            }
            $appointmentStmt->close();
            break;

        default:
            $response['message'] = 'Invalid action specified.';
            break;
    }
}

$conn->close();

// Clean any output buffer and send JSON response
ob_clean();
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>