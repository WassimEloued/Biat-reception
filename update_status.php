<?php
session_start(); 
include 'db.php'; 

if (!isset($_SESSION['cin']) || $_SESSION['role'] !== 'employer') {
    header('Location: index.php');
    exit();
}


if (isset($_POST['update_status'])) {
    $cin = $_POST['cin'];
    $currentStatus = $_POST['current_status'];

    $newStatus = ($currentStatus == 'oui') ? 'non' : 'oui';

    $updateStmt = $conn->prepare("UPDATE employer SET disp = ? WHERE cin = ?");
    $updateStmt->execute([$newStatus, $cin]);

    $successMessage = "Your status has been updated!";
}
?>