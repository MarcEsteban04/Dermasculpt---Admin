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
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

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

// Function to send email notifications
function sendAppointmentEmail($email, $patientName, $subject, $emailBody) {
    try {
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'marcdelacruzesteban@gmail.com';
        $mail->Password   = 'gnnrblabtfpseolu';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('marcdelacruzesteban@gmail.com', 'DermaSculpt');
        $mail->addAddress($email, $patientName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $emailBody;

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log("Email notification failed: " . $mail->ErrorInfo);
        return false;
    }
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
        $nameStmt->bind_param("i", $_SESSION['dermatologist_id']);
        $nameStmt->execute();
        $nameResult = $nameStmt->get_result()->fetch_assoc();
        $nameStmt->close();
        if ($nameResult) {
            $performedBy = $nameResult['first_name'] . ' ' . $nameResult['last_name'];
        }
    }
    
    try {
        $historyStmt = $conn->prepare("
            INSERT INTO appointment_history (appointment_id, old_status, new_status, old_date, new_date, old_time, new_time, action_type, performed_by, performed_by_role, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'dermatologist', ?)
        ");
        $historyStmt->bind_param("isssssssss", $appointmentId, $oldStatus, $newStatus, $oldDate, $newDate, $oldTime, $newTime, $actionType, $performedBy, $notes);
        $historyStmt->execute();
        $historyStmt->close();
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
    $verifyStmt->bind_param("ii", $appointmentId, $dermatologistId);
    $verifyStmt->execute();
    $verifyStmt->store_result();
    if ($verifyStmt->num_rows === 0) {
        $response['message'] = 'You do not have permission to modify this appointment.';
        $verifyStmt->close();
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    $verifyStmt->close();

    switch ($action) {
        case 'accept':
            // Get appointment details first
            $currentStmt = $conn->prepare("SELECT a.status, a.patient_name, a.email, a.appointment_date, a.appointment_time FROM appointments a WHERE a.appointment_id = ?");
            $currentStmt->bind_param("i", $appointmentId);
            $currentStmt->execute();
            $currentResult = $currentStmt->get_result();
            $appointmentData = $currentResult->fetch_assoc();
            $currentStmt->close();
            
            if (!$appointmentData) {
                $response['message'] = 'Appointment not found.';
                break;
            }
            
            $currentStatus = $appointmentData['status'];
            $patientName = $appointmentData['patient_name'];
            $email = $appointmentData['email'];
            $appointmentDate = date('F j, Y', strtotime($appointmentData['appointment_date']));
            $appointmentTime = date('g:i A', strtotime($appointmentData['appointment_time']));
            
            $stmt = $conn->prepare("UPDATE appointments SET status = 'Scheduled' WHERE appointment_id = ?");
            $stmt->bind_param("i", $appointmentId);
            if ($stmt->execute()) {
                // Send acceptance email
                $subject = 'Appointment Confirmed - DermaSculpt';
                $emailBody = '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Appointment Confirmed - DermaSculpt</title>
                </head>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                        <h1 style="color: white; margin: 0; font-size: 28px;">DermaSculpt</h1>
                        <p style="color: #d1fae5; margin: 10px 0 0 0;">Dermatology • Aesthetics • Lasers</p>
                    </div>
                    
                    <div style="background: #f8fafc; padding: 40px 30px; border-radius: 0 0 10px 10px; border: 1px solid #e2e8f0;">
                        <h2 style="color: #059669; margin-top: 0;">🎉 Appointment Confirmed!</h2>
                        
                        <p>Dear ' . htmlspecialchars($patientName) . ',</p>
                        
                        <p>Great news! Your dermatology appointment has been confirmed and scheduled.</p>
                        
                        <div style="background: white; border: 2px solid #059669; border-radius: 8px; padding: 20px; margin: 30px 0;">
                            <h3 style="margin: 0; color: #059669; font-size: 20px;">✅ Confirmed Appointment Details</h3>
                            <div style="margin: 15px 0;">
                                <p style="margin: 5px 0;"><strong>Date:</strong> ' . $appointmentDate . '</p>
                                <p style="margin: 5px 0;"><strong>Time:</strong> ' . $appointmentTime . '</p>
                                <p style="margin: 5px 0;"><strong>Status:</strong> <span style="color: #059669; font-weight: bold;">Confirmed</span></p>
                            </div>
                        </div>
                        
                        <p><strong>What to expect:</strong></p>
                        <ul style="color: #64748b;">
                            <li>Please arrive 15 minutes before your scheduled time for check-in</li>
                            <li>Bring a valid ID and insurance information</li>
                            <li>Prepare any questions you may have about your skin concerns</li>
                            <li>If you need to make any changes, please contact us as soon as possible</li>
                        </ul>
                        
                        <div style="background: #ecfdf5; border-left: 4px solid #059669; padding: 15px; margin: 20px 0;">
                            <p style="margin: 0; color: #065f46;"><strong>We look forward to seeing you!</strong> Our team is ready to provide you with the best dermatological care.</p>
                        </div>
                        
                        <p>Best regards,<br>The DermaSculpt Team</p>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; color: #64748b; font-size: 12px;">
                        <p>This is an automated message, please do not reply to this email.</p>
                        <p>&copy; ' . date('Y') . ' DermaSculpt. All rights reserved.</p>
                    </div>
                </body>
                </html>';
                
                // Check if user exists in database for internal messaging
                $userCheckStmt = $conn->prepare("SELECT user_id, first_name FROM users WHERE email = ?");
                $userCheckStmt->bind_param("s", $email);
                $userCheckStmt->execute();
                $userResult = $userCheckStmt->get_result();
                
                if ($userData = $userResult->fetch_assoc()) {
                    // User exists - send both email and internal message
                    $userId = $userData['user_id'];
                    $firstName = $userData['first_name'];
                    
                    $confirmationMessage = "Good news! Your dermatology appointment for {$patientName} has been confirmed and scheduled for {$appointmentDate} at {$appointmentTime}. Please arrive 15 minutes early for check-in. If you need to make any changes, please contact us as soon as possible. We look forward to seeing you!";
                    
                    $messageStmt = $conn->prepare("INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message_text, is_read) VALUES (?, 'dermatologist', ?, 'user', ?, 0)");
                    $messageStmt->bind_param("iis", $dermatologistId, $userId, $confirmationMessage);
                    $messageStmt->execute();
                    $messageStmt->close();
                }
                $userCheckStmt->close();
                
                // Send email regardless of user registration status
                sendAppointmentEmail($email, $patientName, $subject, $emailBody);
                
                logAppointmentHistory($conn, $appointmentId, 'accept', $currentStatus, 'Scheduled', null, null, null, null, 'Appointment accepted by dermatologist and email notification sent');
                $response = ['success' => true, 'message' => 'Appointment accepted successfully and patient has been notified via email.'];
            } else {
                $response['message'] = 'Failed to accept appointment.';
            }
            $stmt->close();
            break;

        case 'cancel':
            // Get appointment details first
            $currentStmt = $conn->prepare("SELECT a.status, a.patient_name, a.email, a.appointment_date, a.appointment_time FROM appointments a WHERE a.appointment_id = ?");
            $currentStmt->bind_param("i", $appointmentId);
            $currentStmt->execute();
            $currentResult = $currentStmt->get_result();
            $appointmentData = $currentResult->fetch_assoc();
            $currentStmt->close();
            
            if (!$appointmentData) {
                $response['message'] = 'Appointment not found.';
                break;
            }
            
            $currentStatus = $appointmentData['status'];
            $patientName = $appointmentData['patient_name'];
            $email = $appointmentData['email'];
            $appointmentDate = date('F j, Y', strtotime($appointmentData['appointment_date']));
            $appointmentTime = date('g:i A', strtotime($appointmentData['appointment_time']));
            
            $stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = ?");
            $stmt->bind_param("i", $appointmentId);
            if ($stmt->execute()) {
                // Send cancellation email
                $subject = 'Appointment Cancelled - DermaSculpt';
                $emailBody = '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Appointment Cancelled - DermaSculpt</title>
                </head>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                        <h1 style="color: white; margin: 0; font-size: 28px;">DermaSculpt</h1>
                        <p style="color: #fecaca; margin: 10px 0 0 0;">Dermatology • Aesthetics • Lasers</p>
                    </div>
                    
                    <div style="background: #f8fafc; padding: 40px 30px; border-radius: 0 0 10px 10px; border: 1px solid #e2e8f0;">
                        <h2 style="color: #dc2626; margin-top: 0;">Appointment Cancelled</h2>
                        
                        <p>Dear ' . htmlspecialchars($patientName) . ',</p>
                        
                        <p>We regret to inform you that your appointment has been cancelled.</p>
                        
                        <div style="background: white; border: 2px solid #dc2626; border-radius: 8px; padding: 20px; margin: 30px 0;">
                            <h3 style="margin: 0; color: #dc2626; font-size: 20px;">Cancelled Appointment Details</h3>
                            <div style="margin: 15px 0;">
                                <p style="margin: 5px 0;"><strong>Date:</strong> ' . $appointmentDate . '</p>
                                <p style="margin: 5px 0;"><strong>Time:</strong> ' . $appointmentTime . '</p>
                                <p style="margin: 5px 0;"><strong>Status:</strong> Cancelled</p>
                            </div>
                        </div>
                        
                        <p>If you would like to reschedule or have any questions, please contact our office. We apologize for any inconvenience this may cause.</p>
                        
                        <p>Best regards,<br>The DermaSculpt Team</p>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; color: #64748b; font-size: 12px;">
                        <p>This is an automated message, please do not reply to this email.</p>
                        <p>&copy; ' . date('Y') . ' DermaSculpt. All rights reserved.</p>
                    </div>
                </body>
                </html>';
                
                // Check if user exists in database for internal messaging
                $userCheckStmt = $conn->prepare("SELECT user_id, first_name FROM users WHERE email = ?");
                $userCheckStmt->bind_param("s", $email);
                $userCheckStmt->execute();
                $userResult = $userCheckStmt->get_result();
                
                if ($userData = $userResult->fetch_assoc()) {
                    // User exists - send both email and internal message
                    $userId = $userData['user_id'];
                    $firstName = $userData['first_name'];
                    
                    $cancellationMessage = "We regret to inform you that your appointment on {$appointmentDate} at {$appointmentTime} has been cancelled. You will receive an email confirmation. If you would like to reschedule, please contact our office. We apologize for any inconvenience.";
                    
                    $messageStmt = $conn->prepare("INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message_text, is_read) VALUES (?, 'dermatologist', ?, 'user', ?, 0)");
                    $messageStmt->bind_param("iis", $dermatologistId, $userId, $cancellationMessage);
                    $messageStmt->execute();
                    $messageStmt->close();
                }
                $userCheckStmt->close();
                
                // Send email regardless of user registration status
                sendAppointmentEmail($email, $patientName, $subject, $emailBody);
                
                logAppointmentHistory($conn, $appointmentId, 'cancel', $currentStatus, 'Cancelled', null, null, null, null, 'Appointment cancelled by dermatologist and email notification sent');
                $response = ['success' => true, 'message' => 'Appointment cancelled successfully and patient has been notified via email.'];
            } else {
                $response['message'] = 'Failed to cancel appointment.';
            }
            $stmt->close();
            break;
            
        case 'reschedule':
            $newDate = $_POST['appointment_date'] ?? '';
            $newTime = $_POST['appointment_time'] ?? '';
            
            if(empty($newDate) || empty($newTime)) {
                 $response['message'] = 'Date and time are required for rescheduling.';
                 break;
            }
            
            // Get current appointment details
            $currentStmt = $conn->prepare("SELECT a.appointment_date, a.appointment_time, a.patient_name, a.email FROM appointments a WHERE a.appointment_id = ?");
            $currentStmt->bind_param("i", $appointmentId);
            $currentStmt->execute();
            $currentResult = $currentStmt->get_result();
            $current = $currentResult->fetch_assoc();
            $currentStmt->close();
            
            if (!$current) {
                $response['message'] = 'Appointment not found.';
                break;
            }
            
            $patientName = $current['patient_name'];
            $email = $current['email'];
            $oldDate = date('F j, Y', strtotime($current['appointment_date']));
            $oldTime = date('g:i A', strtotime($current['appointment_time']));
            $newFormattedDate = date('F j, Y', strtotime($newDate));
            $newFormattedTime = date('g:i A', strtotime($newTime));
            
            $stmt = $conn->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ? WHERE appointment_id = ?");
            $stmt->bind_param("ssi", $newDate, $newTime, $appointmentId);
            if ($stmt->execute()) {
                // Send reschedule email
                $subject = 'Appointment Rescheduled - DermaSculpt';
                $emailBody = '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Appointment Rescheduled - DermaSculpt</title>
                </head>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                        <h1 style="color: white; margin: 0; font-size: 28px;">DermaSculpt</h1>
                        <p style="color: #fef3c7; margin: 10px 0 0 0;">Dermatology • Aesthetics • Lasers</p>
                    </div>
                    
                    <div style="background: #f8fafc; padding: 40px 30px; border-radius: 0 0 10px 10px; border: 1px solid #e2e8f0;">
                        <h2 style="color: #f59e0b; margin-top: 0;">Appointment Rescheduled</h2>
                        
                        <p>Dear ' . htmlspecialchars($patientName) . ',</p>
                        
                        <p>Your appointment has been rescheduled to a new date and time.</p>
                        
                        <div style="background: white; border: 2px solid #f59e0b; border-radius: 8px; padding: 20px; margin: 30px 0;">
                            <h3 style="margin: 0; color: #f59e0b; font-size: 20px;">Previous Appointment</h3>
                            <div style="margin: 15px 0; color: #6b7280;">
                                <p style="margin: 5px 0;"><strong>Date:</strong> ' . $oldDate . '</p>
                                <p style="margin: 5px 0;"><strong>Time:</strong> ' . $oldTime . '</p>
                            </div>
                            
                            <h3 style="margin: 20px 0 0 0; color: #059669; font-size: 20px;">New Appointment</h3>
                            <div style="margin: 15px 0;">
                                <p style="margin: 5px 0;"><strong>Date:</strong> ' . $newFormattedDate . '</p>
                                <p style="margin: 5px 0;"><strong>Time:</strong> ' . $newFormattedTime . '</p>
                                <p style="margin: 5px 0;"><strong>Status:</strong> Scheduled</p>
                            </div>
                        </div>
                        
                        <p><strong>Important Notes:</strong></p>
                        <ul style="color: #64748b;">
                            <li>Please arrive 15 minutes before your scheduled time</li>
                            <li>Bring a valid ID and insurance information</li>
                            <li>If you need to make further changes, please contact us as soon as possible</li>
                        </ul>
                        
                        <p>We apologize for any inconvenience and look forward to seeing you at your new appointment time.</p>
                        
                        <p>Best regards,<br>The DermaSculpt Team</p>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; color: #64748b; font-size: 12px;">
                        <p>This is an automated message, please do not reply to this email.</p>
                        <p>&copy; ' . date('Y') . ' DermaSculpt. All rights reserved.</p>
                    </div>
                </body>
                </html>';
                
                // Check if user exists in database for internal messaging
                $userCheckStmt = $conn->prepare("SELECT user_id, first_name FROM users WHERE email = ?");
                $userCheckStmt->bind_param("s", $email);
                $userCheckStmt->execute();
                $userResult = $userCheckStmt->get_result();
                
                if ($userData = $userResult->fetch_assoc()) {
                    // User exists - send both email and internal message
                    $userId = $userData['user_id'];
                    $firstName = $userData['first_name'];
                    
                    $rescheduleMessage = "Your appointment has been rescheduled from {$oldDate} at {$oldTime} to {$newFormattedDate} at {$newFormattedTime}. You will receive an email confirmation with the new details. Please arrive 15 minutes early. If you need to make further changes, please contact us as soon as possible.";
                    
                    $messageStmt = $conn->prepare("INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message_text, is_read) VALUES (?, 'dermatologist', ?, 'user', ?, 0)");
                    $messageStmt->bind_param("iis", $dermatologistId, $userId, $rescheduleMessage);
                    $messageStmt->execute();
                    $messageStmt->close();
                }
                $userCheckStmt->close();
                
                // Send email regardless of user registration status
                sendAppointmentEmail($email, $patientName, $subject, $emailBody);
                
                logAppointmentHistory($conn, $appointmentId, 'reschedule', null, null, $current['appointment_date'], $newDate, $current['appointment_time'], $newTime, 'Appointment rescheduled by dermatologist and email notification sent');
                $response = ['success' => true, 'message' => 'Appointment rescheduled successfully and patient has been notified via email.'];
            } else {
                $response['message'] = 'Failed to reschedule appointment.';
            }
            $stmt->close();
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
            $stmt->bind_param("ii", $newDermatologistId, $appointmentId);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Appointment transferred successfully.'];
            } else {
                $response['message'] = 'Failed to transfer appointment.';
            }
            $stmt->close();
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
            // Get appointment details first
            $currentStmt = $conn->prepare("SELECT a.status, a.patient_name, a.email, a.appointment_date, a.appointment_time FROM appointments a WHERE a.appointment_id = ?");
            $currentStmt->bind_param("i", $appointmentId);
            $currentStmt->execute();
            $currentResult = $currentStmt->get_result();
            $appointmentData = $currentResult->fetch_assoc();
            $currentStmt->close();
            
            if (!$appointmentData) {
                $response['message'] = 'Appointment not found.';
                break;
            }
            
            $currentStatus = $appointmentData['status'];
            $patientName = $appointmentData['patient_name'];
            $email = $appointmentData['email'];
            $appointmentDate = date('F j, Y', strtotime($appointmentData['appointment_date']));
            $appointmentTime = date('g:i A', strtotime($appointmentData['appointment_time']));
            
            $stmt = $conn->prepare("UPDATE appointments SET status = 'Completed' WHERE appointment_id = ?");
            $stmt->bind_param("i", $appointmentId);
            if ($stmt->execute()) {
                // Send completion email
                $subject = 'Appointment Completed - DermaSculpt';
                $emailBody = '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Appointment Completed - DermaSculpt</title>
                </head>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                        <h1 style="color: white; margin: 0; font-size: 28px;">DermaSculpt</h1>
                        <p style="color: #e9d5ff; margin: 10px 0 0 0;">Dermatology • Aesthetics • Lasers</p>
                    </div>
                    
                    <div style="background: #f8fafc; padding: 40px 30px; border-radius: 0 0 10px 10px; border: 1px solid #e2e8f0;">
                        <h2 style="color: #7c3aed; margin-top: 0;">✨ Appointment Completed</h2>
                        
                        <p>Dear ' . htmlspecialchars($patientName) . ',</p>
                        
                        <p>Thank you for visiting DermaSculpt! Your appointment has been successfully completed.</p>
                        
                        <div style="background: white; border: 2px solid #7c3aed; border-radius: 8px; padding: 20px; margin: 30px 0;">
                            <h3 style="margin: 0; color: #7c3aed; font-size: 20px;">📋 Completed Appointment Details</h3>
                            <div style="margin: 15px 0;">
                                <p style="margin: 5px 0;"><strong>Date:</strong> ' . $appointmentDate . '</p>
                                <p style="margin: 5px 0;"><strong>Time:</strong> ' . $appointmentTime . '</p>
                                <p style="margin: 5px 0;"><strong>Status:</strong> <span style="color: #7c3aed; font-weight: bold;">Completed</span></p>
                            </div>
                        </div>
                        
                        <div style="background: #faf5ff; border-left: 4px solid #7c3aed; padding: 15px; margin: 20px 0;">
                            <p style="margin: 0; color: #581c87;"><strong>Thank you for choosing DermaSculpt!</strong> We hope you had a positive experience with our dermatological services.</p>
                        </div>
                        
                        <p><strong>What\'s next:</strong></p>
                        <ul style="color: #64748b;">
                            <li>Follow any post-treatment instructions provided by your dermatologist</li>
                            <li>Schedule any recommended follow-up appointments</li>
                            <li>Contact us if you have any questions or concerns</li>
                            <li>Consider leaving a review about your experience</li>
                        </ul>
                        
                        <p><strong>Need to schedule another appointment?</strong> Feel free to contact our office or use our online booking system.</p>
                        
                        <p>We appreciate your trust in our care and look forward to serving you again in the future.</p>
                        
                        <p>Best regards,<br>The DermaSculpt Team</p>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; color: #64748b; font-size: 12px;">
                        <p>This is an automated message, please do not reply to this email.</p>
                        <p>&copy; ' . date('Y') . ' DermaSculpt. All rights reserved.</p>
                    </div>
                </body>
                </html>';
                
                // Check if user exists in database for internal messaging
                $userCheckStmt = $conn->prepare("SELECT user_id, first_name FROM users WHERE email = ?");
                $userCheckStmt->bind_param("s", $email);
                $userCheckStmt->execute();
                $userResult = $userCheckStmt->get_result();
                
                if ($userData = $userResult->fetch_assoc()) {
                    // User exists - send both email and internal message
                    $userId = $userData['user_id'];
                    $firstName = $userData['first_name'];
                    
                    $completionMessage = "Thank you for visiting DermaSculpt! Your appointment on {$appointmentDate} at {$appointmentTime} has been completed. Please follow any post-treatment instructions provided. If you have any questions or need to schedule a follow-up, please contact us. We appreciate your trust in our care!";
                    
                    $messageStmt = $conn->prepare("INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message_text, is_read) VALUES (?, 'dermatologist', ?, 'user', ?, 0)");
                    $messageStmt->bind_param("iis", $dermatologistId, $userId, $completionMessage);
                    $messageStmt->execute();
                    $messageStmt->close();
                }
                $userCheckStmt->close();
                
                // Send email regardless of user registration status
                sendAppointmentEmail($email, $patientName, $subject, $emailBody);
                
                logAppointmentHistory($conn, $appointmentId, 'status_change', $currentStatus, 'Completed', null, null, null, null, 'Appointment marked as completed by dermatologist and email notification sent');
                $response = ['success' => true, 'message' => 'Appointment marked as completed successfully and patient has been notified via email.'];
            } else {
                $response['message'] = 'Failed to mark appointment as completed.';
            }
            $stmt->close();
            break;

        case 'send_reminder':
            // Get appointment details (check if user exists or just get email from appointments table)
            $appointmentStmt = $conn->prepare("
                SELECT a.patient_name, a.email, a.appointment_date, a.appointment_time
                FROM appointments a
                WHERE a.appointment_id = ? AND a.dermatologist_id = ?
            ");
            $appointmentStmt->bind_param("ii", $appointmentId, $dermatologistId);
            $appointmentStmt->execute();
            $appointmentResult = $appointmentStmt->get_result();
            
            if ($appointmentData = $appointmentResult->fetch_assoc()) {
                $patientName = $appointmentData['patient_name'];
                $email = $appointmentData['email'];
                $appointmentDate = date('F j, Y', strtotime($appointmentData['appointment_date']));
                $appointmentTime = date('g:i A', strtotime($appointmentData['appointment_time']));
                
                // Send reminder email
                $subject = 'Appointment Reminder - DermaSculpt';
                $emailBody = '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Appointment Reminder - DermaSculpt</title>
                </head>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                        <h1 style="color: white; margin: 0; font-size: 28px;">DermaSculpt</h1>
                        <p style="color: #dbeafe; margin: 10px 0 0 0;">Dermatology • Aesthetics • Lasers</p>
                    </div>
                    
                    <div style="background: #f8fafc; padding: 40px 30px; border-radius: 0 0 10px 10px; border: 1px solid #e2e8f0;">
                        <h2 style="color: #3b82f6; margin-top: 0;">Appointment Reminder</h2>
                        
                        <p>Dear ' . htmlspecialchars($patientName) . ',</p>
                        
                        <p>This is a friendly reminder about your upcoming dermatology appointment.</p>
                        
                        <div style="background: white; border: 2px solid #3b82f6; border-radius: 8px; padding: 20px; margin: 30px 0;">
                            <h3 style="margin: 0; color: #3b82f6; font-size: 20px;">Appointment Details</h3>
                            <div style="margin: 15px 0;">
                                <p style="margin: 5px 0;"><strong>Date:</strong> ' . $appointmentDate . '</p>
                                <p style="margin: 5px 0;"><strong>Time:</strong> ' . $appointmentTime . '</p>
                                <p style="margin: 5px 0;"><strong>Status:</strong> Scheduled</p>
                            </div>
                        </div>
                        
                        <p><strong>Important Reminders:</strong></p>
                        <ul style="color: #64748b;">
                            <li>Please arrive 15 minutes before your scheduled time</li>
                            <li>Bring a valid ID and insurance information</li>
                            <li>If you need to reschedule, please contact us as soon as possible</li>
                            <li>Please let us know if you have any symptoms or concerns before your visit</li>
                        </ul>
                        
                        <p>We look forward to seeing you at your appointment!</p>
                        
                        <p>Best regards,<br>The DermaSculpt Team</p>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; color: #64748b; font-size: 12px;">
                        <p>This is an automated message, please do not reply to this email.</p>
                        <p>&copy; ' . date('Y') . ' DermaSculpt. All rights reserved.</p>
                    </div>
                </body>
                </html>';
                
                // Check if user exists in database for internal messaging
                $userCheckStmt = $conn->prepare("SELECT user_id, first_name FROM users WHERE email = ?");
                $userCheckStmt->bind_param("s", $email);
                $userCheckStmt->execute();
                $userResult = $userCheckStmt->get_result();
                
                if ($userData = $userResult->fetch_assoc()) {
                    // User exists - send both email and internal message
                    $userId = $userData['user_id'];
                    $firstName = $userData['first_name'];
                    
                    $reminderMessage = "Hello {$firstName}, this is a friendly reminder about your upcoming dermatology appointment for {$patientName} scheduled on {$appointmentDate} at {$appointmentTime}. Please arrive 15 minutes early. If you need to reschedule, please contact us as soon as possible. Thank you!";
                    
                    $messageStmt = $conn->prepare("
                        INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message_text, is_read) 
                        VALUES (?, 'dermatologist', ?, 'user', ?, 0)
                    ");
                    $messageStmt->bind_param("iis", $dermatologistId, $userId, $reminderMessage);
                    $messageStmt->execute();
                    $messageStmt->close();
                }
                $userCheckStmt->close();
                
                // Send email regardless of user registration status
                sendAppointmentEmail($email, $patientName, $subject, $emailBody);
                
                logAppointmentHistory($conn, $appointmentId, 'status_change', null, null, null, null, null, null, 'Appointment reminder sent via email and internal message');
                $response = ['success' => true, 'message' => 'Reminder sent successfully to patient via email.'];
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