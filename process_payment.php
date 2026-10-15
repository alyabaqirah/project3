<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = $_POST['booking_id'];
    $method = $_POST['method'];
    $user_id = $_SESSION['user_id'];

    if ($method == 'cash') {
        $payment_status = 'cash_pending';
        $booking_status = 'confirmed';
    } else {
        $payment_status = 'paid';
        $booking_status = 'confirmed';
    }

    $prefix = ($method === 'online_banking') ? 'ONL' : 'CSH';
    $transaction_id = $prefix . "TXN" . time();

    try {
        $conn->beginTransaction();

        $stmt_amt = $conn->prepare("SELECT total_amount FROM bookings WHERE booking_id = ?");
        $stmt_amt->execute([$booking_id]);
        $booking_data = $stmt_amt->fetch();

        if (!$booking_data) {
            throw new Exception("Booking not found.");
        }

        $amount = $booking_data['total_amount'];

        $update_sql = "UPDATE bookings SET 
                        payment_status = ?, 
                        booking_status = ?, 
                        payment_method = ?, 
                        transaction_id = ?, 
                        payment_date = CURRENT_TIMESTAMP 
                       WHERE booking_id = ?";
        $stmt1 = $conn->prepare($update_sql);
        $stmt1->execute([$payment_status, $booking_status, $method, $transaction_id, $booking_id]);

        $insert_pay = "INSERT INTO payments (booking_id, user_id, payment_method, payment_status, transaction_id, amount_paid, payment_date) 
                       VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
        $stmt2 = $conn->prepare($insert_pay);
        $stmt2->execute([$booking_id, $user_id, $method, $payment_status, $transaction_id, $amount]);

        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Payment recorded! You can now view your receipt.'
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>