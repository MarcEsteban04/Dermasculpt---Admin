<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../config/connection.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$input = json_decode(file_get_contents('php://input'), true);
$messageId = $input['message_id'] ?? 0;
$replyMessage = trim($input['reply_message'] ?? '');

if (!$messageId || !$replyMessage) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Get original message details
$stmt = $conn->prepare("SELECT * FROM contact_messages WHERE id = ?");
$stmt->bind_param("i", $messageId);
$stmt->execute();
$result = $stmt->get_result();
$originalMessage = $result->fetch_assoc();
$stmt->close();

if (!$originalMessage) {
    echo json_encode(['success' => false, 'message' => 'Original message not found']);
    exit;
}

// Get dermatologist details
$dermatologistId = $_SESSION['dermatologist_id'];
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM dermatologists WHERE dermatologist_id = ?");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$result = $stmt->get_result();
$dermatologist = $result->fetch_assoc();
$stmt->close();

if (!$dermatologist) {
    echo json_encode(['success' => false, 'message' => 'Dermatologist information not found']);
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
    $mail->setFrom('marcdelacruzesteban@gmail.com', 'DermaSculpt - Dr. ' . $dermatologist['first_name'] . ' ' . $dermatologist['last_name']);
    $mail->addAddress($originalMessage['email'], $originalMessage['name']);
    $mail->addReplyTo($dermatologist['email'], 'Dr. ' . $dermatologist['first_name'] . ' ' . $dermatologist['last_name']);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Re: Your Inquiry - DermaSculpt Response';
    
    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Response to Your Inquiry - DermaSculpt</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
            <h1 style="color: white; margin: 0; font-size: 28px;">DermaSculpt</h1>
            <p style="color: #e0f7fa; margin: 10px 0 0 0;">Dermatology • Aesthetics • Lasers</p>
        </div>
        
        <div style="background: #f8fafc; padding: 40px 30px; border-radius: 0 0 10px 10px; border: 1px solid #e2e8f0;">
            <h2 style="color: #0891b2; margin-top: 0;">Response to Your Inquiry</h2>
            
            <p>Dear ' . htmlspecialchars($originalMessage['name']) . ',</p>
            
            <p>Thank you for contacting DermaSculpt. Dr. ' . htmlspecialchars($dermatologist['first_name'] . ' ' . $dermatologist['last_name']) . ' has personally reviewed your inquiry and provided the following response:</p>
            
            <div style="background: white; border-left: 4px solid #0891b2; padding: 20px; margin: 30px 0; border-radius: 0 8px 8px 0;">
                <h3 style="margin-top: 0; color: #0891b2; font-size: 18px;">Doctor\'s Response:</h3>
                <div style="color: #374151; line-height: 1.6; white-space: pre-wrap;">' . nl2br(htmlspecialchars($replyMessage)) . '</div>
            </div>
            
            <div style="background: #e0f7fa; border: 1px solid #0891b2; border-radius: 8px; padding: 20px; margin: 30px 0;">
                <h4 style="margin-top: 0; color: #0891b2;">Your Original Message:</h4>
                <p style="color: #374151; font-style: italic; margin: 0; line-height: 1.5;">"' . htmlspecialchars($originalMessage['message']) . '"</p>
                <p style="color: #64748b; font-size: 12px; margin: 10px 0 0 0;">Sent on ' . date('F j, Y \a\t g:i A', strtotime($originalMessage['created_at'])) . '</p>
            </div>
            
            <div style="background: #f0f9ff; border: 1px solid #0891b2; border-radius: 8px; padding: 20px; margin: 30px 0;">
                <h4 style="margin-top: 0; color: #0891b2;">Need Further Assistance?</h4>
                <p style="margin: 0; color: #374151;">If you have additional questions or would like to schedule an appointment, please:</p>
                <ul style="color: #374151; margin: 10px 0;">
                    <li>Reply to this email directly</li>
                    <li>Call our clinic for immediate assistance</li>
                    <li>Visit our website to book an appointment online</li>
                </ul>
            </div>
            
            <p>We appreciate your trust in DermaSculpt and look forward to helping you achieve your skincare goals.</p>
            
            <p>Best regards,<br>
            <strong>Dr. ' . htmlspecialchars($dermatologist['first_name'] . ' ' . $dermatologist['last_name']) . '</strong><br>
            DermaSculpt Team</p>
        </div>
        
        <div style="text-align: center; padding: 20px; color: #64748b; font-size: 12px;">
            <p>This email was sent in response to your inquiry submitted on ' . date('F j, Y', strtotime($originalMessage['created_at'])) . '.</p>
            <p>&copy; ' . date('Y') . ' DermaSculpt. All rights reserved.</p>
        </div>
    </body>
    </html>';

    $mail->AltBody = "Dear " . $originalMessage['name'] . ",\n\n" .
                     "Thank you for contacting DermaSculpt. Dr. " . $dermatologist['first_name'] . " " . $dermatologist['last_name'] . " has personally reviewed your inquiry and provided the following response:\n\n" .
                     "Doctor's Response:\n" . $replyMessage . "\n\n" .
                     "Your Original Message:\n\"" . $originalMessage['message'] . "\"\n" .
                     "Sent on " . date('F j, Y \a\t g:i A', strtotime($originalMessage['created_at'])) . "\n\n" .
                     "If you have additional questions, please reply to this email or contact our clinic.\n\n" .
                     "Best regards,\nDr. " . $dermatologist['first_name'] . " " . $dermatologist['last_name'] . "\nDermaSculpt Team";

    $mail->send();
    
    // Save the reply to database
    $insertReplyStmt = $conn->prepare("INSERT INTO inquiry_replies (original_message_id, dermatologist_id, reply_message) VALUES (?, ?, ?)");
    $insertReplyStmt->bind_param("iis", $messageId, $dermatologistId, $replyMessage);
    $insertReplyStmt->execute();
    $insertReplyStmt->close();
    
    // Update message status to 'replied'
    $updateStmt = $conn->prepare("UPDATE contact_messages SET status = 'replied', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $updateStmt->bind_param("i", $messageId);
    $updateStmt->execute();
    $updateStmt->close();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Reply sent successfully to ' . $originalMessage['email']
    ]);
    
} catch (Exception $e) {
    error_log("PHPMailer Error: " . $mail->ErrorInfo);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to send reply. Please try again later.'
    ]);
}

$conn->close();
?>
