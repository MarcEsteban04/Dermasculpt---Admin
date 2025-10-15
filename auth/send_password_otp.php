<?php
session_start();
require_once '../config/connection.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

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
$currentPassword = trim($_POST['current_password'] ?? '');

if (empty($currentPassword)) {
    echo json_encode(['success' => false, 'message' => 'Current password is required']);
    exit;
}

// Verify current password
$stmt = $conn->prepare("SELECT email, password FROM dermatologists WHERE dermatologist_id = ?");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($currentPassword, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
    exit;
}

$email = $user['email'];

// Generate 6-digit OTP
$otp_code = sprintf("%06d", mt_rand(100000, 999999));

// Set expiration time (10 minutes from now)
$expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Delete any existing OTP codes for this email
$delete_sql = "DELETE FROM otp_codes WHERE email = ?";
$delete_stmt = $conn->prepare($delete_sql);
$delete_stmt->bind_param("s", $email);
$delete_stmt->execute();

// Store the purpose in session for verification
$_SESSION['password_change_otp_email'] = $email;
$_SESSION['password_change_otp_time'] = time();

// Insert new OTP code
$insert_sql = "INSERT INTO otp_codes (email, otp_code, expires_at) VALUES (?, ?, ?)";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("sss", $email, $otp_code, $expires_at);

if (!$insert_stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to generate verification code. Please try again.']);
    exit;
}

// Send email using PHPMailer
$mail = new PHPMailer(true);

try {
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
    $mail->addAddress($email);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Password Change Verification Code - DermaSculpt';
    
    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Change - DermaSculpt</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
            <h1 style="color: white; margin: 0; font-size: 28px;">DermaSculpt</h1>
            <p style="color: #e0f7fa; margin: 10px 0 0 0;">Dermatology • Aesthetics • Lasers</p>
        </div>
        
        <div style="background: #f8fafc; padding: 40px 30px; border-radius: 0 0 10px 10px; border: 1px solid #e2e8f0;">
            <h2 style="color: #0891b2; margin-top: 0;">Password Change Request</h2>
            
            <p>Hello,</p>
            
            <p>We received a request to change your password for your DermaSculpt account. Use the verification code below to proceed with changing your password:</p>
            
            <div style="background: white; border: 2px solid #0891b2; border-radius: 8px; padding: 20px; text-align: center; margin: 30px 0;">
                <h3 style="margin: 0; color: #0891b2; font-size: 24px;">Verification Code</h3>
                <div style="font-size: 36px; font-weight: bold; color: #0891b2; letter-spacing: 8px; margin: 15px 0;">' . $otp_code . '</div>
                <p style="margin: 0; color: #64748b; font-size: 14px;">This code expires in 10 minutes</p>
            </div>
            
            <p><strong>Important:</strong></p>
            <ul style="color: #64748b;">
                <li>This code is valid for 10 minutes only</li>
                <li>Do not share this code with anyone</li>
                <li>If you did not request this password change, please contact support immediately</li>
            </ul>
            
            <p>If you have any questions or need assistance, please contact our support team.</p>
            
            <p>Best regards,<br>The DermaSculpt Team</p>
        </div>
        
        <div style="text-align: center; padding: 20px; color: #64748b; font-size: 12px;">
            <p>This is an automated message, please do not reply to this email.</p>
            <p>&copy; ' . date('Y') . ' DermaSculpt. All rights reserved.</p>
        </div>
    </body>
    </html>';

    $mail->AltBody = "Your DermaSculpt password change verification code is: $otp_code\n\nThis code expires in 10 minutes.\n\nIf you did not request this password change, please contact support immediately.";

    $mail->send();
    
    echo json_encode(['success' => true, 'message' => 'Verification code sent to your email address. Please check your inbox.']);
    
} catch (Exception $e) {
    error_log("PHPMailer Error: " . $mail->ErrorInfo);
    echo json_encode(['success' => false, 'message' => 'Failed to send verification code. Please try again later.']);
}

$conn->close();
?>
