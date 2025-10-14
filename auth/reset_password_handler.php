<?php
session_start();
require_once '../config/connection.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("location: forgot_password.php");
    exit;
}

// Check if email is verified
if (!isset($_SESSION['verified_email'])) {
    header("location: forgot_password.php");
    exit;
}

$email = $_SESSION['verified_email'];
$new_password = trim($_POST['new_password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

// Validate passwords
if (empty($new_password) || empty($confirm_password)) {
    $_SESSION['reset_error'] = "Both password fields are required.";
    header("location: reset_password.php");
    exit;
}

if ($new_password !== $confirm_password) {
    $_SESSION['reset_error'] = "Passwords do not match.";
    header("location: reset_password.php");
    exit;
}

// Validate password strength
if (strlen($new_password) < 8) {
    $_SESSION['reset_error'] = "Password must be at least 8 characters long.";
    header("location: reset_password.php");
    exit;
}

if (!preg_match('/[A-Z]/', $new_password)) {
    $_SESSION['reset_error'] = "Password must contain at least one uppercase letter.";
    header("location: reset_password.php");
    exit;
}

if (!preg_match('/[a-z]/', $new_password)) {
    $_SESSION['reset_error'] = "Password must contain at least one lowercase letter.";
    header("location: reset_password.php");
    exit;
}

if (!preg_match('/[0-9]/', $new_password)) {
    $_SESSION['reset_error'] = "Password must contain at least one number.";
    header("location: reset_password.php");
    exit;
}

// Hash the new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update password in database
$sql = "UPDATE dermatologists SET password = ? WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $hashed_password, $email);

if ($stmt->execute()) {
    // Clean up any remaining OTP codes for this email
    $cleanup_sql = "DELETE FROM otp_codes WHERE email = ?";
    $cleanup_stmt = $conn->prepare($cleanup_sql);
    $cleanup_stmt->bind_param("s", $email);
    $cleanup_stmt->execute();
    
    // Clear session
    unset($_SESSION['verified_email']);
    
    // Set success message and redirect to login
    $_SESSION['login_success'] = "Password reset successful! Please sign in with your new password.";
    header("location: ../index.php?reset=success");
    exit;
} else {
    $_SESSION['reset_error'] = "Failed to reset password. Please try again.";
    header("location: reset_password.php");
    exit;
}

$conn->close();
?>
