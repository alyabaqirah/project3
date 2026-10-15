<?php
require_once 'db.php';
header('Content-Type: application/json');

$check_in = $_GET['checkin'] ?? null;
$check_out = $_GET['checkout'] ?? null;

try {
    if (!$check_in || !$check_out || $check_in == 'default') {
        $sql = "SELECT room_name, room_type, price_per_night, image_url, max_guests, COUNT(*) as units_left 
                FROM rooms 
                WHERE status = 'available' 
                GROUP BY room_name, room_type, price_per_night, image_url, max_guests";
        $stmt = $conn->query($sql);
    } else {
        $sql = "SELECT room_name, room_type, price_per_night, image_url, max_guests, COUNT(*) as units_left 
                FROM rooms 
                WHERE status = 'available' 
                AND room_id NOT IN (
                    SELECT room_id FROM bookings 
                    WHERE booking_status NOT IN ('cancelled', 'refunded')
                    AND check_in_date < ? 
                    AND check_out_date > ?
                )
                GROUP BY room_name, room_type, price_per_night, image_url, max_guests";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$check_out, $check_in]);
    }
    
    $availableRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($availableRooms);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>