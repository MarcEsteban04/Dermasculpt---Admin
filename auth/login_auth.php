<?php

session_start();

require_once '../config/connection.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("location: ../index.php");
    exit;
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    $_SESSION['login_error'] = "Email and password are required.";
    header("location: ../index.php");
    exit;
}

$sql = "SELECT dermatologist_id, first_name, password FROM dermatologists WHERE email = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {
        session_regenerate_id(true);

        $_SESSION['loggedin'] = true;
        $_SESSION['dermatologist_id'] = $user['dermatologist_id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['login_success_modal'] = true;
        
        header("location: ../pages/dashboard.php");
        exit;
    }
}

$_SESSION['login_error'] = "The email or password you entered is incorrect.";
header("location: ../index.php");
exit;

$stmt->close();
$conn->close();

?>