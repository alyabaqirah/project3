<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass = password_hash($_POST['new_pass'], PASSWORD_DEFAULT);
    $id = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    if($stmt->execute([$new_pass, $id])) {
        echo json_encode(['status' => 'success', 'message' => 'Password changed successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to change password.']);
    }
}
?>