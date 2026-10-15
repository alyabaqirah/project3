<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $id = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
    if($stmt->execute([$name, $email, $phone, $id])) {
        $_SESSION['full_name'] = $name; 
        echo json_encode(['status' => 'success', 'message' => 'Profile updated!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update.']);
    }
}
?>