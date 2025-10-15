<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/connection.php';

$dermatologistId = $_SESSION['dermatologist_id'];

// Handle adding a day off
if (isset($_POST['add_day_off'])) {
    $offDate = $_POST['off_date'];
    $reason = $_POST['reason'] ?? null;

    if (empty($offDate)) {
        // Handle error: date is required
        $_SESSION['schedule_error'] = "Date is required to add a day off.";
        header('Location: ../pages/schedules.php');
        exit;
    }
    
    // Optional: Check if the day off already exists to prevent duplicates
    $checkStmt = $conn->prepare("SELECT day_off_id FROM dermatologist_day_off WHERE dermatologist_id = ? AND off_date = ?");
    $checkStmt->bind_param("is", $dermatologistId, $offDate);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['schedule_error'] = "This date has already been set as a day off.";
        header('Location: ../pages/schedules.php');
        exit;
    }
    $checkStmt->close();


    $stmt = $conn->prepare("INSERT INTO dermatologist_day_off (dermatologist_id, off_date, reason) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $dermatologistId, $offDate, $reason);

    if ($stmt->execute()) {
        $_SESSION['schedule_success'] = "Day off has been successfully added.";
    } else {
        $_SESSION['schedule_error'] = "An error occurred. Please try again.";
    }

    $stmt->close();
    $conn->close();
    header('Location: ../pages/schedules.php');
    exit;
}


// Handle deleting a day off
if (isset($_POST['delete_day_off'])) {
    $dayOffId = $_POST['day_off_id'];

    if (empty($dayOffId)) {
        // Handle error: ID is missing
        header('Location: ../pages/schedules.php');
        exit;
    }
    
    // The query ensures a dermatologist can only delete their own day off
    $stmt = $conn->prepare("DELETE FROM dermatologist_day_off WHERE day_off_id = ? AND dermatologist_id = ?");
    $stmt->bind_param("ii", $dayOffId, $dermatologistId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
             $_SESSION['schedule_success'] = "Day off has been successfully removed.";
        } else {
            // This case handles if someone tries to delete a day off that isn't theirs, or doesn't exist.
             $_SESSION['schedule_error'] = "Could not remove the selected day off.";
        }
    } else {
        $_SESSION['schedule_error'] = "An error occurred. Please try again.";
    }

    $stmt->close();
    $conn->close();
    header('Location: ../pages/schedules.php');
    exit;
}

// Redirect back if accessed directly without POST data
header('Location: ../pages/schedules.php');
exit;
?>
