<?php
session_start();
require_once '../config/connection.php';

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
$otp_code = trim($_POST['otp_code'] ?? '');
$newPassword = trim($_POST['new_password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

// Validate inputs
if (empty($otp_code)) {
    echo json_encode(['success' => false, 'message' => 'OTP code is required']);
    exit;
}

if (empty($newPassword) || empty($confirmPassword)) {
    echo json_encode(['success' => false, 'message' => 'New password and confirmation are required']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'New password and confirmation do not match']);
    exit;
}

if (strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
    exit;
}

// Get user email
$stmt = $conn->prepare("SELECT email FROM dermatologists WHERE dermatologist_id = ?");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$email = $user['email'];

// Check if this is a valid password change OTP request
if (!isset($_SESSION['password_change_otp_email']) || 
    $_SESSION['password_change_otp_email'] !== $email ||
    !isset($_SESSION['password_change_otp_time']) ||
    (time() - $_SESSION['password_change_otp_time']) > 600) { // 10 minutes
    echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP session. Please request a new verification code.']);
    exit;
}

// Verify OTP code
$verify_sql = "SELECT otp_code, expires_at FROM otp_codes WHERE email = ? AND otp_code = ? ORDER BY created_at DESC LIMIT 1";
$verify_stmt = $conn->prepare($verify_sql);
$verify_stmt->bind_param("ss", $email, $otp_code);
$verify_stmt->execute();
$otp_result = $verify_stmt->get_result();
$otp_data = $otp_result->fetch_assoc();
$verify_stmt->close();

if (!$otp_data) {
    echo json_encode(['success' => false, 'message' => 'Invalid verification code']);
    exit;
}

// Check if OTP has expired
if (strtotime($otp_data['expires_at']) < time()) {
    // Delete expired OTP
    $delete_sql = "DELETE FROM otp_codes WHERE email = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("s", $email);
    $delete_stmt->execute();
    
    echo json_encode(['success' => false, 'message' => 'Verification code has expired. Please request a new one.']);
    exit;
}

// Update password
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
$updateStmt = $conn->prepare("UPDATE dermatologists SET password = ? WHERE dermatologist_id = ?");
$updateStmt->bind_param("si", $hashedPassword, $dermatologistId);

if ($updateStmt->execute()) {
    // Delete used OTP and clear session
    $delete_sql = "DELETE FROM otp_codes WHERE email = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("s", $email);
    $delete_stmt->execute();
    
    // Clear password change session variables
    unset($_SESSION['password_change_otp_email']);
    unset($_SESSION['password_change_otp_time']);
    
    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update password. Please try again.']);
}

$updateStmt->close();
$conn->close();
?>
