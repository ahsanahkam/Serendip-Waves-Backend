<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once './Main Classes/Booking.php';
require_once './Main Classes/Mailer.php';

function sendConfirmationEmail($data) {
    try {
        // Log email attempt
        error_log("Attempting to send confirmation email for booking ID: " . $data['booking_id']);
        
        // Required fields check
        $required = [
            'email', 'full_name', 'booking_id', 'cruise_title', 'cabin_type', 'cabin_number',
            'adults', 'children', 'departure_date', 'return_date', 'total_price',
            'ship_name', 'destination', 'special_requests'
        ];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                error_log("Email sending failed: Missing field $field for booking ID " . $data['booking_id']);
                return ["success" => false, "message" => "Missing field: $field"];
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
We're thrilled to confirm your booking and look forward to giving you an unforgettable experience at sea.

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

We can't wait to welcome you on board! If you have any questions or need to make changes to your booking, please don't hesitate to contact us.

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
            error_log("Confirmation email sent successfully for booking ID: " . $data['booking_id']);
            return ["success" => true, "message" => "Confirmation email sent."];
        } else {
            error_log("Failed to send confirmation email for booking ID: " . $data['booking_id']);
            return ["success" => false, "message" => "Failed to send confirmation email."];
        }
    } catch (Exception $e) {
        error_log("Email error for booking ID " . $data['booking_id'] . ": " . $e->getMessage());
        return ["success" => false, "message" => "Email error: " . $e->getMessage()];
    }
}

try {
    // Get and decode input data
    $input = file_get_contents("php://input");
    $data = json_decode($input);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit();
    }
    
    // Validate required fields
    $requiredFields = ['full_name', 'email', 'room_type', 'ship_name', 'destination'];
    foreach ($requiredFields as $field) {
        if (!isset($data->$field) || empty($data->$field)) {
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            exit();
        }
    }
    
    // Create booking instance
    $booking = new Booking();
    
    // Call addBooking method - total_price will be calculated dynamically from cabin_type_pricing
    $result = $booking->addBooking(
        $data->full_name ?? '',
        $data->gender ?? 'Male',
        $data->email ?? '',
        $data->citizenship ?? '',
        $data->age ?? 0,
        $data->room_type ?? '',
        $data->cabin_number ?? '',
        $data->adults ?? 0,
        $data->children ?? 0,
        $data->number_of_guests ?? 1,
        $data->ship_name ?? '',
        $data->destination ?? '',
        $data->card_type ?? 'Visa',
        $data->card_number ?? '0000000000000000'
    );
    
    // If booking was successful, send confirmation email (only if requested)
    if ($result['success']) {
        $sendEmail = $data->send_email ?? false; // Default to false, must be explicitly enabled
        
        if ($sendEmail) {
            // Prepare data for confirmation email
            $emailData = [
                'email' => $data->email,
                'full_name' => $data->full_name,
                'booking_id' => $result['booking_id'],
                'cruise_title' => $data->ship_name . ' - ' . $data->destination,
                'cabin_type' => $data->room_type,
                'cabin_number' => $result['cabin_number'],
                'adults' => $data->adults ?? 0,
                'children' => $data->children ?? 0,
                'departure_date' => 'TBD', // You may want to fetch this from itineraries
                'return_date' => 'TBD',    // You may want to fetch this from itineraries
                'total_price' => '$' . number_format($result['pricing_details']['total_amount'], 2),
                'ship_name' => $data->ship_name,
                'destination' => $data->destination,
                'special_requests' => 'None'
            ];
            
            // Send confirmation email
            $emailResult = sendConfirmationEmail($emailData);
            
            // Add email status to the result
            $result['email_sent'] = $emailResult['success'];
            if (!$emailResult['success']) {
                $result['email_error'] = $emailResult['message'];
            }
        } else {
            $result['email_sent'] = false;
            $result['email_skipped'] = true;
        }
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
