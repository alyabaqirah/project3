<?php
// ai_handler.php
$db_name = "smartstay_hotel"; 
$conn = new mysqli("localhost", "root", "", $db_name);

if ($conn->connect_error) { die("DB Error"); }

// Fetch room data
$roomData = "";
$res = $conn->query("SELECT room_name, price_per_night, status FROM rooms");
while($row = $res->fetch_assoc()) {
    $roomData .= "{$row['room_name']} is \${$row['price_per_night']}. Status: {$row['status']}. ";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userMsg = $_POST['message'] ?? '';

    $myHotelRules = "Rules: 1. Be polite. 
                            2. If asked for total price, multiply price by nights. 
                            3. If a room is 'booked', say it's unavailable.
                            4. Only answer questions based on the provided room data. If a user asks about something you don't know (like local weather or flight prices), politely tell them that you cannot answer that question.
                            5. The price_per_night is in RM. So use RM when telling the price (for example Deluxe Suite RM599.00/per night).
                            ";
    
    $data = [
        "model" => "llama3.1",
        "messages" => [
            ["role" => "system", "content" => "You are the SmartStay Virtual Assistant. Rules: $myHotelRules. Data: $roomData"],
            ["role" => "user", "content" => $userMsg]
        ],
        "stream" => false
    ];

    $ch = curl_init("http://localhost:11434/api/chat");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    $response = curl_exec($ch);
    $result = json_decode($response, true);
    echo $result['message']['content'] ?? 'AI Error';
    exit;
}
?>