<?php
require_once "db.php"; // or db_connection.php (use the one you have)

// Member details
$full_name = "Test Member";
$email = "member@test.com";
$phone = "0123456789";
$password = "member123";
$role = "member";

// Hash password (same as register.php)
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Member-specific defaults (from your register.php)
$status = "active";
$email_notifications = 1;
$sms_notifications = 0;
$preferences = json_encode([
    "emailNotifications" => true,
    "smsNotifications" => false
]);

$sql = "INSERT INTO users 
        (full_name, email, phone, password_hash, role, status,
         email_notifications, sms_notifications,
         preferences, created_at, updated_at)
        VALUES
        (:full_name, :email, :phone, :password_hash, :role, :status,
         :email_notifications, :sms_notifications,
         :preferences, NOW(), NOW())";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':full_name' => $full_name,
    ':email' => $email,
    ':phone' => $phone,
    ':password_hash' => $password_hash,
    ':role' => $role,
    ':status' => $status,
    ':email_notifications' => $email_notifications,
    ':sms_notifications' => $sms_notifications,
    ':preferences' => $preferences
]);

echo "Member account created successfully!";
