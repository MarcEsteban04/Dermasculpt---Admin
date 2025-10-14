<?php
session_start();
require_once '../config/connection.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("location: forgot_password.php");
    exit;
}

// Check if email is in session
if (!isset($_SESSION['reset_email'])) {
    header("location: forgot_password.php");
    exit;
}

$email = $_SESSION['reset_email'];
$otp_code = trim($_POST['otp_code'] ?? '');

if (empty($otp_code)) {
    $_SESSION['otp_error'] = "Verification code is required.";
    header("location: verify_otp.php");
    exit;
}

if (!preg_match('/^\d{6}$/', $otp_code)) {
    $_SESSION['otp_error'] = "Please enter a valid 6-digit verification code.";
    header("location: verify_otp.php");
    exit;
}

// Check OTP code in database
$sql = "SELECT id, attempts FROM otp_codes WHERE email = ? AND otp_code = ? AND expires_at > NOW() AND is_used = 0 LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $email, $otp_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Increment attempts for this email
    $update_attempts_sql = "UPDATE otp_codes SET attempts = attempts + 1 WHERE email = ? AND expires_at > NOW() AND is_used = 0";
    $update_stmt = $conn->prepare($update_attempts_sql);
    $update_stmt->bind_param("s", $email);
    $update_stmt->execute();
    
    // Check if too many attempts
    $check_attempts_sql = "SELECT attempts FROM otp_codes WHERE email = ? AND expires_at > NOW() AND is_used = 0 ORDER BY attempts DESC LIMIT 1";
    $check_stmt = $conn->prepare($check_attempts_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $attempts_result = $check_stmt->get_result();
    
    if ($attempts_result->num_rows > 0) {
        $row = $attempts_result->fetch_assoc();
        if ($row['attempts'] >= 5) {
            // Mark all OTP codes for this email as used (block further attempts)
            $block_sql = "UPDATE otp_codes SET is_used = 1 WHERE email = ?";
            $block_stmt = $conn->prepare($block_sql);
            $block_stmt->bind_param("s", $email);
            $block_stmt->execute();
            
            $_SESSION['otp_error'] = "Too many failed attempts. Please request a new verification code.";
            unset($_SESSION['reset_email']);
            header("location: forgot_password.php");
            exit;
        }
    }
    
    $_SESSION['otp_error'] = "Invalid or expired verification code. Please try again.";
    header("location: verify_otp.php");
    exit;
}

// Valid OTP found - mark as used
$otp_row = $result->fetch_assoc();
$update_sql = "UPDATE otp_codes SET is_used = 1 WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("i", $otp_row['id']);
$update_stmt->execute();

// Set session for password reset
$_SESSION['verified_email'] = $email;
unset($_SESSION['reset_email']);

// Redirect to password reset page
header("location: reset_password.php");
exit;

$conn->close();
?>
