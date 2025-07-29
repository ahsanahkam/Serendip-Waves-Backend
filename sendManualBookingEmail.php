<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once './DbConnector.php';
require_once './Main Classes/Mailer.php';

try {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['booking_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing booking_id']);
        exit();
    }
    
    $booking_id = $data['booking_id'];
    
    // Fetch booking details from database
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    $sql = "SELECT b.*, i.start_date as departure_date, i.end_date as return_date 
            FROM booking_overview b 
            LEFT JOIN itineraries i ON b.ship_name = i.ship_name AND b.destination = i.route 
            WHERE b.booking_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit();
    }
    
    // Prepare email data
    $emailData = [
        'email' => $booking['email'],
        'full_name' => $booking['full_name'],
        'booking_id' => $booking['booking_id'],
        'cruise_title' => $booking['ship_name'] . ' - ' . $booking['destination'],
        'cabin_type' => $booking['room_type'],
        'cabin_number' => $booking['cabin_number'],
        'adults' => $booking['adults'],
        'children' => $booking['children'],
        'departure_date' => $booking['departure_date'] ?? 'TBD',
        'return_date' => $booking['return_date'] ?? 'TBD',
        'total_price' => '$' . number_format($booking['total_price'], 2),
        'ship_name' => $booking['ship_name'],
        'destination' => $booking['destination'],
        'special_requests' => 'None'
    ];
    
    // Compose the email body
    $body = <<<EOT
Dear {$emailData['full_name']},

🌊 Thank you for choosing **Serendip Waves** for your upcoming cruise adventure!  
We're thrilled to confirm your booking and look forward to giving you an unforgettable experience at sea.

---

🧾 **Booking Details:**

- **Booking ID:** #{$emailData['booking_id']}
- **Full Name:** {$emailData['full_name']}
- **Email:** {$emailData['email']}
- **Cruise Title:** {$emailData['cruise_title']}
- **Cabin Type:** {$emailData['cabin_type']}
- **Cabin Number:** {$emailData['cabin_number']}
- **Number of Adults:** {$emailData['adults']}  
- **Number of Children:** {$emailData['children']}  
- **Departure Date:** {$emailData['departure_date']}  
- **Return Date:** {$emailData['return_date']}  
- **Total Price:** {$emailData['total_price']}

---

🛳️ **Ship Name:** {$emailData['ship_name']}  
📍 **Destination:** {$emailData['destination']}

---

📌 **Special Requests:**  
{$emailData['special_requests']}

---

We can't wait to welcome you on board! If you have any questions or need to make changes to your booking, please don't hesitate to contact us.

---

Best regards,  
**Serendip Waves Booking Team**  
📞 +94771234567
📧 info@serendipwaves.com 
🌐 www.serendipwaves.com
EOT;

    $mailer = new Mailer();
    $mailer->setInfo($emailData['email'], 'Your Serendip Waves Booking Confirmation', nl2br($body));
    $sent = $mailer->send();

    if ($sent === true) {
        echo json_encode(["success" => true, "message" => "Confirmation email sent successfully to " . $emailData['email']]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to send confirmation email"]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
