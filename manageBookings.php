<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once './DbConnector.php';

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($method) {
        case 'POST':
            // Create new booking
            $query = "INSERT INTO booking_overview (
                full_name, email, gender, citizenship, age, 
                ship_name, destination, room_type, adults, children,
                booking_date, total_amount
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute([
                $input['primaryPassenger']['name'],
                $input['primaryPassenger']['email'],
                $input['primaryPassenger']['gender'],
                $input['primaryPassenger']['citizenship'],
                $input['primaryPassenger']['age'],
                $input['cruise'],
                $input['destination'] ?? 'Various Destinations',
                $input['cabinType'],
                $input['adults'] ?? 1,
                $input['children'] ?? 0,
                $input['date'],
                $input['price']
            ]);
            
            if ($result) {
                $bookingId = $pdo->lastInsertId();
                echo json_encode([
                    'success' => true,
                    'message' => 'Booking created successfully',
                    'booking_id' => $bookingId
                ]);
            } else {
                throw new Exception('Failed to create booking');
            }
            break;
            
        case 'PUT':
            // Update existing booking
            $bookingId = $input['id'];
            
            $query = "UPDATE booking_overview SET 
                full_name = ?, email = ?, gender = ?, citizenship = ?, age = ?,
                ship_name = ?, destination = ?, room_type = ?, adults = ?, children = ?,
                booking_date = ?, total_amount = ?
                WHERE booking_id = ?";
            
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute([
                $input['primaryPassenger']['name'],
                $input['primaryPassenger']['email'],
                $input['primaryPassenger']['gender'],
                $input['primaryPassenger']['citizenship'],
                $input['primaryPassenger']['age'],
                $input['cruise'],
                $input['destination'] ?? 'Various Destinations',
                $input['cabinType'],
                $input['adults'] ?? 1,
                $input['children'] ?? 0,
                $input['date'],
                $input['price'],
                $bookingId
            ]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Booking updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update booking');
            }
            break;
            
        case 'DELETE':
            // Delete booking
            $bookingId = $input['id'] ?? $_GET['id'];
            
            $query = "DELETE FROM booking_overview WHERE booking_id = ?";
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute([$bookingId]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Booking deleted successfully'
                ]);
            } else {
                throw new Exception('Failed to delete booking');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
