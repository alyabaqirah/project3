<?php
/**
 * SmartStay AI Concierge - Complete Local Implementation
 * Requirements: XAMPP (Apache/MySQL) + Ollama (Llama 3.1)
 */

// 1. ERROR REPORTING (Helpful for debugging localhost)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. DATABASE CONNECTION
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "smartstay_hotel"; // Your confirmed database name

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// 3. FETCH ROOM DATA (Context for the AI)
$roomContext = "";
$sql = "SELECT room_name, room_type, price_per_night, has_wifi, has_ac, status FROM rooms";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $wifi = $row['has_wifi'] ? "Yes" : "No";
        $ac = $row['has_ac'] ? "Yes" : "No";
        $roomContext .= "Room: {$row['room_name']} ({$row['room_type']}). Price: \${$row['price_per_night']}/night. WiFi: $wifi. AC: $ac. Current Status: {$row['status']}. | ";
    }
}

// 4. HANDLE THE AI CHAT REQUEST (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $userMsg = $_POST['message'];

    // The Payload for Ollama
    $myHotelRules = "Rules: 1. Be polite. 
                            2. If asked for total price, multiply price by nights. 
                            3. If a room is 'booked', say it's unavailable.
                            4. Only answer questions based on the provided room data. If a user asks about something you don't know (like local weather or flight prices), politely tell them that you cannot answer that question.
                            5. The price_per_night is in RM. So use RM when telling the price (for example Deluxe Suite RM599.00/per night).
                            ";

    $data = [
        "model" => "llama3.1",
        "messages" => [
            [
                "role" => "system", 
                "content" => "You are the SmartStay Virtual Assistant. Rules: $myHotelRules. Data: $roomContext"
            ],
            ["role" => "user", "content" => $userMsg]
        ],
        "stream" => false
    ];

    // cURL to Ollama API
    $ch = curl_init("http://localhost:11434/api/chat");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    
    $response = curl_exec($ch);
    $result = json_decode($response, true);
    
    // Return only the text response
    echo $result['message']['content'] ?? 'I am having trouble connecting to my brain. Please check if Ollama is running.';
    exit; // Crucial: Stop PHP from loading the HTML below during an AJAX call
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartStay AI Support</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #eef2f3; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        #chat-container { width: 400px; height: 600px; background: white; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); display: flex; flex-direction: column; overflow: hidden; }
        #header { background: #2c3e50; color: white; padding: 20px; text-align: center; font-size: 1.2em; font-weight: bold; }
        #chat-box { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 15px; background: #f9f9f9; }
        .msg { padding: 12px 16px; border-radius: 18px; max-width: 80%; font-size: 0.95em; line-height: 1.4; }
        .user { background: #3498db; color: white; align-self: flex-end; border-bottom-right-radius: 2px; }
        .ai { background: #ecf0f1; color: #333; align-self: flex-start; border-bottom-left-radius: 2px; border: 1px solid #ddd; }
        #input-area { display: flex; padding: 15px; background: white; border-top: 1px solid #ddd; }
        #msg-input { flex: 1; border: 1px solid #ccc; border-radius: 25px; padding: 10px 15px; outline: none; transition: border 0.3s; }
        #msg-input:focus { border-color: #3498db; }
        #send-btn { background: #27ae60; color: white; border: none; padding: 10px 20px; border-radius: 25px; margin-left: 10px; cursor: pointer; font-weight: bold; }
        #send-btn:hover { background: #219150; }
        .typing { font-style: italic; color: #7f8c8d; font-size: 0.85em; }
    </style>
</head>
<body>

<div id="chat-container">
    <div id="header">SmartStay Concierge</div>
    <div id="chat-box">
        <div class="msg ai">Hello! I'm your SmartStay AI. I can see our live room availability. How can I help you today?</div>
    </div>
    <div id="input-area">
        <input type="text" id="msg-input" placeholder="Ask me anything..." onkeypress="if(event.key === 'Enter') ask()">
        <button id="send-btn" onclick="ask()">Send</button>
    </div>
</div>

<script>
async function ask() {
    const input = document.getElementById('msg-input');
    const box = document.getElementById('chat-box');
    const message = input.value.trim();
    
    if (!message) return;

    // 1. Add User Message to UI
    box.innerHTML += `<div class="msg user">${message}</div>`;
    input.value = '';
    
    // 2. Add temporary Loading indicator
    const tempId = "loading-" + Date.now();
    box.innerHTML += `<div class="msg ai typing" id="${tempId}">SmartStay is checking...</div>`;
    box.scrollTop = box.scrollHeight;

    // 3. Send to PHP Backend
    const formData = new FormData();
    formData.append('message', message);

    try {
        const response = await fetch('ai_chat_v2.php', {
            method: 'POST',
            body: formData
        });
        
        const reply = await response.text();
        
        // 4. Replace loading with AI response
        document.getElementById(tempId).remove();
        box.innerHTML += `<div class="msg ai">${reply}</div>`;
    } catch (error) {
        document.getElementById(tempId).innerText = "Connection error. Is Ollama running?";
    }
    
    // Scroll to bottom
    box.scrollTop = box.scrollHeight;
}
</script>

</body>
</html>