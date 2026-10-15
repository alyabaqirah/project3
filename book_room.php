<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sila login terlebih dahulu.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_type = $_POST['room_type']; 
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $guests = $_POST['guests'];
    $user_id = $_SESSION['user_id'];
    $full_name = $_SESSION['full_name'];

    try {
        $conn->beginTransaction();

        // cari bilik yang tarikh tak overlap
        $sql_check = "
            SELECT room_id, room_number, price_per_night 
            FROM rooms 
            WHERE room_type = ? 
            AND status = 'available' 
            AND room_id NOT IN (
                SELECT room_id 
                FROM bookings 
                WHERE booking_status NOT IN ('cancelled', 'refunded') 
                AND NOT (check_out_date <= ? OR check_in_date >= ?)
            ) 
            LIMIT 1";

        $stmt = $conn->prepare($sql_check);
        $stmt->execute([$room_type, $check_in, $check_out]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            echo json_encode(['status' => 'error', 'message' => 'Maaf, tiada bilik kosong untuk tarikh tersebut!']);
            exit;
        }

        $room_id = $room['room_id'];
        $room_number = $room['room_number'];

        $d1 = new DateTime($check_in);
        $d2 = new DateTime($check_out);
        $nights = $d2->diff($d1)->format("%a");
        
        if ($nights <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Tarikh check-out mestilah selepas tarikh check-in.']);
            exit;
        }

        $total = $nights * $room['price_per_night'];

        // masuk data booking
        $sql_book = "INSERT INTO bookings (user_id, room_id, guest_name, guest_email, check_in_date, check_out_date, number_of_guests, number_of_nights, total_amount, booking_status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $stmt_book = $conn->prepare($sql_book);
        $stmt_book->execute([
            $user_id, 
            $room_id, 
            $full_name, 
            $_SESSION['email'] ?? 'member@smartstay.com',
            $check_in, 
            $check_out, 
            $guests, 
            $nights, 
            $total
        ]);

        $conn->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => "Tempahan Berjaya! No Bilik: $room_number. Sila buat pembayaran segera."
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Ralat: ' . $e->getMessage()]);
    }
}
?>