<?php
// Fix CORS: Always send headers before any output
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/Main Classes/Mailer.php';

$data = json_decode(file_get_contents("php://input"), true);

// Add debug logging to capture POST data


// Required fields
$required = [
    'email', 'full_name', 'booking_id', 'cruise_title', 'cabin_type', 'cabin_number',
    'adults', 'children', 'departure_date', 'return_date', 'total_price',
    'ship_name', 'destination', 'special_requests'
];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        echo json_encode(["success" => false, "message" => "Missing field: $field"]);
        exit();
    }
}

$email = $data['email'];
$full_name = $data['full_name'];
$booking_id = $data['booking_id'];
$cruise_title = $data['cruise_title'];
$cabin_type = $data['cabin_type'];
$cabin_number = $data['cabin_number'];
$adults = $data['adults'];
$children = $data['children'];
$departure_date = $data['departure_date'];
$return_date = $data['return_date'];
$total_price = $data['total_price'];
$ship_name = $data['ship_name'];
$destination = $data['destination'];
$special_requests = $data['special_requests'] ?: 'None';

// Compose the email body
$body = <<<EOT
Dear {$full_name},

🌊 Thank you for choosing **Serendip Waves** for your upcoming cruise adventure!  
We’re thrilled to confirm your booking and look forward to giving you an unforgettable experience at sea.

---

🧾 **Booking Details:**

- **Booking ID:** #{$booking_id}
- **Full Name:** {$full_name}
- **Email:** {$email}
- **Cruise Title:** {$cruise_title}
- **Cabin Type:** {$cabin_type}
- **Cabin Number:** {$cabin_number}
- **Number of Adults:** {$adults}  
- **Number of Children:** {$children}  
- **Departure Date:** {$departure_date}  
- **Return Date:** {$return_date}  
- **Total Price:** {$total_price}

---

🛳️ **Ship Name:** {$ship_name}  
📍 **Destination:** {$destination}

---

📌 **Special Requests:**  
{$special_requests}

---

We can’t wait to welcome you on board! If you have any questions or need to make changes to your booking, please don’t hesitate to contact us.

---

Best regards,  
**Serendip Waves Booking Team**  
📞 +94771234567
📧 info@serendipwaves.com 
🌐 www.serendipwaves.com
EOT;

$mailer = new Mailer();
$mailer->setInfo($email, 'Your Serendip Waves Booking Confirmation', nl2br($body));
$sent = $mailer->send();

if ($sent === true) {
    echo json_encode(["success" => true, "message" => "Confirmation email sent."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to send confirmation email."]);
} 