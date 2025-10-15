<?php
session_start();
require_once '../config/connection.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$dermatologistId = $_SESSION['dermatologist_id'];

// Get form data
$patientName = trim($_POST['patient_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phoneNumber = trim($_POST['phone_number'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$appointmentDate = trim($_POST['appointment_date'] ?? '');
$appointmentTime = trim($_POST['appointment_time'] ?? '');
$reasonForAppointment = trim($_POST['reason_for_appointment'] ?? '');
$dermatologistNotes = trim($_POST['dermatologist_notes'] ?? '');

// Validate required fields
if (empty($patientName)) {
    echo json_encode(['success' => false, 'message' => 'Patient name is required']);
    exit;
}

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Patient email is required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit;
}

if (empty($appointmentDate)) {
    echo json_encode(['success' => false, 'message' => 'Appointment date is required']);
    exit;
}

if (empty($appointmentTime)) {
    echo json_encode(['success' => false, 'message' => 'Appointment time is required']);
    exit;
}

// Validate date is not in the past
$appointmentDateTime = $appointmentDate . ' ' . $appointmentTime;
if (strtotime($appointmentDateTime) < time()) {
    echo json_encode(['success' => false, 'message' => 'Appointment date and time cannot be in the past']);
    exit;
}

// Check if dermatologist has conflicting appointment
$conflictCheck = $conn->prepare("SELECT appointment_id FROM appointments WHERE dermatologist_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'Cancelled'");
$conflictCheck->bind_param("iss", $dermatologistId, $appointmentDate, $appointmentTime);
$conflictCheck->execute();
$conflictResult = $conflictCheck->get_result();

if ($conflictResult->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'You already have an appointment scheduled at this date and time']);
    exit;
}
$conflictCheck->close();

try {
    // Begin transaction
    $conn->begin_transaction();

    // Check if user exists, if not create one
    $userCheck = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $userCheck->bind_param("s", $email);
    $userCheck->execute();
    $userResult = $userCheck->get_result();
    
    if ($userResult->num_rows > 0) {
        // User exists, get user_id
        $user = $userResult->fetch_assoc();
        $userId = $user['user_id'];
    } else {
        // Create new user
        $defaultPassword = password_hash('DermaSculpt123!', PASSWORD_DEFAULT); // Default password
        $insertUser = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone_number, gender, password, email_verified) VALUES (?, ?, ?, ?, ?, ?, 1)");
        
        // Split name into first and last name
        $nameParts = explode(' ', $patientName, 2);
        $firstName = $nameParts[0];
        $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
        
        $insertUser->bind_param("ssssss", $firstName, $lastName, $email, $phoneNumber, $gender, $defaultPassword);
        
        if (!$insertUser->execute()) {
            throw new Exception('Failed to create user account');
        }
        
        $userId = $conn->insert_id;
        $insertUser->close();
    }
    $userCheck->close();

    // Create appointment
    $insertAppointment = $conn->prepare("INSERT INTO appointments (user_id, dermatologist_id, patient_name, email, phone_number, appointment_date, appointment_time, reason_for_appointment, dermatologist_notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', NOW())");
    
    $insertAppointment->bind_param("iisssssss", $userId, $dermatologistId, $patientName, $email, $phoneNumber, $appointmentDate, $appointmentTime, $reasonForAppointment, $dermatologistNotes);
    
    if (!$insertAppointment->execute()) {
        throw new Exception('Failed to create appointment');
    }
    
    $appointmentId = $conn->insert_id;
    $insertAppointment->close();

    // Commit transaction
    $conn->commit();

    // Send email notification to patient (optional)
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
        $mail->Subject = 'Appointment Confirmation - DermaSculpt';
        
        $formattedDate = date('F j, Y', strtotime($appointmentDate));
        $formattedTime = date('g:i A', strtotime($appointmentTime));
        
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Appointment Confirmation - DermaSculpt</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="color: white; margin: 0; font-size: 28px;">DermaSculpt</h1>
                <p style="color: #e0f7fa; margin: 10px 0 0 0;">Dermatology • Aesthetics • Lasers</p>
            </div>
            
            <div style="background: #f8fafc; padding: 40px 30px; border-radius: 0 0 10px 10px; border: 1px solid #e2e8f0;">
                <h2 style="color: #0891b2; margin-top: 0;">Appointment Confirmed!</h2>
                
                <p>Dear ' . htmlspecialchars($patientName) . ',</p>
                
                <p>Your appointment has been successfully scheduled with our dermatology team.</p>
                
                <div style="background: white; border: 2px solid #0891b2; border-radius: 8px; padding: 20px; margin: 30px 0;">
                    <h3 style="margin: 0; color: #0891b2; font-size: 20px;">Appointment Details</h3>
                    <div style="margin: 15px 0;">
                        <p style="margin: 5px 0;"><strong>Date:</strong> ' . $formattedDate . '</p>
                        <p style="margin: 5px 0;"><strong>Time:</strong> ' . $formattedTime . '</p>
                        <p style="margin: 5px 0;"><strong>Status:</strong> Scheduled</p>
                        ' . (!empty($reasonForAppointment) ? '<p style="margin: 5px 0;"><strong>Reason:</strong> ' . htmlspecialchars($reasonForAppointment) . '</p>' : '') . '
                    </div>
                </div>
                
                <p><strong>Important Notes:</strong></p>
                <ul style="color: #64748b;">
                    <li>Please arrive 15 minutes before your scheduled time</li>
                    <li>Bring a valid ID and insurance information</li>
                    <li>If you need to reschedule, please contact us at least 24 hours in advance</li>
                </ul>
                
                <p>If you have any questions or need to make changes to your appointment, please contact our office.</p>
                
                <p>Best regards,<br>The DermaSculpt Team</p>
            </div>
            
            <div style="text-align: center; padding: 20px; color: #64748b; font-size: 12px;">
                <p>This is an automated message, please do not reply to this email.</p>
                <p>&copy; ' . date('Y') . ' DermaSculpt. All rights reserved.</p>
            </div>
        </body>
        </html>';

        $mail->AltBody = "Dear $patientName,\n\nYour appointment has been confirmed for $formattedDate at $formattedTime.\n\nPlease arrive 15 minutes early and bring valid ID and insurance information.\n\nBest regards,\nThe DermaSculpt Team";

        $mail->send();
        
    } catch (PHPMailerException $e) {
        // Email failed but appointment was created successfully
        error_log("Email notification failed: " . $mail->ErrorInfo);
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Appointment created successfully',
        'appointment_id' => $appointmentId,
        'appointment_date' => $appointmentDate,
        'appointment_time' => $appointmentTime,
        'patient_name' => $patientName
    ]);

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    error_log("Appointment creation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to create appointment. Please try again.']);
}

$conn->close();
?>