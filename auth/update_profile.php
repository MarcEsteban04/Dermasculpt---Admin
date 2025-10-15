<?php
session_start();

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/connection.php';
$dermatologistId = $_SESSION['dermatologist_id'];

if (isset($_POST['update_picture']) && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_type = $_FILES['profile_picture']['type'];

    if (in_array($file_type, $allowed_types)) {
        $upload_dir_relative_to_script = '../uploads/profiles/';
        $upload_dir_for_db = 'uploads/profiles/';

        if (!is_dir($upload_dir_relative_to_script)) {
            mkdir($upload_dir_relative_to_script, 0777, true);
        }

        $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $new_filename = 'profile_' . $dermatologistId . '_' . time() . '.' . $file_extension;
        $upload_file_path = $upload_dir_relative_to_script . $new_filename;

        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_file_path)) {
            $relative_path_for_db = $upload_dir_for_db . $new_filename;

            $stmt = $conn->prepare("UPDATE dermatologists SET profile_picture_url = ? WHERE dermatologist_id = ?");
            $stmt->bind_param("si", $relative_path_for_db, $dermatologistId);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Profile picture updated successfully.";
                $_SESSION['profile_picture_url'] = $relative_path_for_db;
            } else {
                $_SESSION['error_message'] = "Failed to update database with new profile picture.";
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Failed to upload the file.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid file type. Please upload a JPG, PNG, or GIF.";
    }
    header('Location: ../pages/profile.php');
    exit;
}

if (isset($_POST['update_info'])) {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $specialization = trim($_POST['specialization']);
    $bio = trim($_POST['bio']);

    if (empty($firstName) || empty($lastName) || empty($email)) {
        $_SESSION['error_message'] = "First name, last name, and email cannot be empty.";
        header('Location: ../pages/profile.php');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Invalid email format.";
        header('Location: ../pages/profile.php');
        exit;
    }

    $stmt = $conn->prepare("UPDATE dermatologists SET first_name = ?, last_name = ?, email = ?, specialization = ?, bio = ? WHERE dermatologist_id = ?");
    $stmt->bind_param("sssssi", $firstName, $lastName, $email, $specialization, $bio, $dermatologistId);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Profile information updated successfully.";
        $_SESSION['first_name'] = $firstName;
    } else {
        $_SESSION['error_message'] = "An error occurred. Could not update profile.";
    }
    $stmt->close();
}

if (isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $_SESSION['error_message'] = "All password fields are required.";
        header('Location: ../pages/profile.php');
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        $_SESSION['error_message'] = "New password and confirmation do not match.";
        header('Location: ../pages/profile.php');
        exit;
    }

    $stmt = $conn->prepare("SELECT password FROM dermatologists WHERE dermatologist_id = ?");
    $stmt->bind_param("i", $dermatologistId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($currentPassword, $user['password'])) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE dermatologists SET password = ? WHERE dermatologist_id = ?");
        $updateStmt->bind_param("si", $hashedPassword, $dermatologistId);

        if ($updateStmt->execute()) {
            $_SESSION['success_message'] = "Password changed successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to update password.";
        }
        $updateStmt->close();
    } else {
        $_SESSION['error_message'] = "Incorrect current password.";
    }
    $stmt->close();
}

$conn->close();
header('Location: ../pages/profile.php');
exit;
?>