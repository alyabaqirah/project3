<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = $_POST['booking_id'];
    $reason = $_POST['reason'];

    try {
        $update_book = "UPDATE bookings SET 
                        booking_status = 'pending_approval', 
                        refund_requested = 1, 
                        refund_status = 'pending', 
                        refund_reason = ?, 
                        refund_request_date = CURRENT_TIMESTAMP 
                        WHERE booking_id = ?";
        
        $stmt = $conn->prepare($update_book);
        $stmt->execute([$reason, $booking_id]);

        echo json_encode(['status' => 'success', 'message' => 'Request sent! Waiting for Admin approval.']);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>