<?php
require_once __DIR__ . '/../DbConnector.php';

class Booking {
    private $pdo;
    
    public function __construct() {
        $dbConnector = new DbConnector();
        $this->pdo = $dbConnector->connect();
    }
    
    public function addBooking($full_name, $gender, $email, $citizenship, $age, $room_type, $cabin_number, $adults, $children, $number_of_guests, $ship_name, $destination, $card_type = 'Visa', $card_number = '0000000000000000') {
        try {
            // Validate required fields
            if (empty($full_name) || empty($email) || empty($room_type) || empty($ship_name) || empty($destination)) {
                return ['success' => false, 'message' => 'Missing required fields: full_name, email, room_type, ship_name, destination'];
            }
            
            // Get dynamic pricing from cabin_type_pricing table
            $pricing = $this->getCabinTypePrice($ship_name, $destination, $room_type);
            if (!$pricing['success']) {
                return ['success' => false, 'message' => $pricing['message']];
            }
            
            $base_price = $pricing['price'];
            
            // Calculate total price based on number of guests and duration
            $trip_duration = $this->getTripDuration($ship_name, $destination);
            $total_price = $this->calculateTotalPrice($base_price, $number_of_guests, $trip_duration);
            
            // Generate cabin number if not provided
            if (empty($cabin_number)) {
                $cabin_number = $this->generateCabinNumber($room_type);
            }
            
            // Prepare SQL statement (let MySQL auto-generate booking_id)
            $sql = "INSERT INTO booking_overview (
                full_name, gender, email, citizenship, age, 
                room_type, cabin_number, adults, children, number_of_guests, 
                card_type, card_number, total_price, ship_name, destination
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $full_name,
                $gender ?: 'Male',
                $email,
                $citizenship ?: '',
                $age ?: 0,
                $room_type,
                $cabin_number ?: '',
                $adults ?: 0,
                $children ?: 0,
                $number_of_guests ?: 1,
                $card_type,
                $card_number,
                $total_price,
                $ship_name,
                $destination
            ]);
            
            // Get the auto-generated booking_id
            $booking_id = $this->pdo->lastInsertId();
            
            if ($result) {
                // Add to cabin management system
                $cabinResult = $this->addToCabinManagement(
                    $booking_id,
                    $full_name,
                    $ship_name,
                    $room_type,
                    $cabin_number,
                    $number_of_guests,
                    date('Y-m-d'),
                    $total_price
                );
                
                $response = [
                    'success' => true, 
                    'message' => 'Booking added successfully',
                    'booking_id' => $booking_id,
                    'cabin_number' => $cabin_number,
                    'pricing_details' => [
                        'base_price_per_person' => $base_price,
                        'number_of_guests' => $number_of_guests,
                        'trip_duration_days' => $trip_duration,
                        'total_amount' => $total_price
                    ]
                ];
                
                // Add cabin management status to response
                if ($cabinResult['success']) {
                    $response['cabin_management'] = [
                        'success' => true,
                        'cabin_id' => $cabinResult['cabin_id']
                    ];
                } else {
                    $response['cabin_management'] = [
                        'success' => false,
                        'error' => $cabinResult['error']
                    ];
                }
                
                return $response;
            } else {
                return ['success' => false, 'message' => 'Failed to add booking'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    private function getCabinTypePrice($ship_name, $route, $room_type) {
        try {
            // Map room types to database column names
            $price_column_map = [
                'Interior' => 'interior_price',
                'Ocean View' => 'ocean_view_price', 
                'Balcony' => 'balcony_price',
                'Suit' => 'suit_price'
            ];
            
            if (!isset($price_column_map[$room_type])) {
                return ['success' => false, 'message' => 'Invalid room type'];
            }
            
            $price_column = $price_column_map[$room_type];
            
            $sql = "SELECT {$price_column} as price FROM cabin_type_pricing 
                    WHERE ship_name = ? AND route = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ship_name, $route]);
            $result = $stmt->fetch();
            
            if ($result) {
                return [
                    'success' => true, 
                    'price' => floatval($result['price'])
                ];
            } else {
                return ['success' => false, 'message' => 'Pricing not found for this ship and route combination'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error fetching pricing: ' . $e->getMessage()];
        }
    }
    
    private function getTripDuration($ship_name, $route) {
        try {
            $sql = "SELECT DATEDIFF(end_date, start_date) as duration 
                    FROM itineraries 
                    WHERE ship_name = ? AND route = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ship_name, $route]);
            $result = $stmt->fetch();
            
            return $result ? intval($result['duration']) : 7; // Default 7 days if not found
            
        } catch (Exception $e) {
            return 7; // Default fallback
        }
    }
    
    private function calculateTotalPrice($base_price, $number_of_guests, $trip_duration) {
        // Base price is per person for the entire trip duration
        // For children, apply 50% discount (if differentiation needed)
        $total = $base_price * $number_of_guests;
        
        // You can add additional logic here for:
        // - Child discounts
        // - Group discounts
        // - Seasonal pricing
        
        return round($total, 2);
    }
    
    public function getBookingById($booking_id) {
        try {
            $sql = "SELECT * FROM booking_overview WHERE booking_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$booking_id]);
            
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($booking) {
                return ['success' => true, 'booking' => $booking];
            } else {
                return ['success' => false, 'message' => 'Booking not found'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function getPricingForShipRoute($ship_name, $route) {
        try {
            $sql = "SELECT * FROM cabin_type_pricing WHERE ship_name = ? AND route = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ship_name, $route]);
            
            $pricing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($pricing) {
                return ['success' => true, 'pricing' => $pricing];
            } else {
                return ['success' => false, 'message' => 'Pricing not found'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    private function generateCabinNumber($room_type) {
        // Generate cabin number based on room type
        $prefix = '';
        switch ($room_type) {
            case 'Interior':
                $prefix = 'I';
                break;
            case 'Ocean View':
                $prefix = 'O';
                break;
            case 'Balcony':
                $prefix = 'B';
                break;
            case 'Suit':
                $prefix = 'S';
                break;
            default:
                $prefix = 'C';
                break;
        }
        
        // Generate a random 3-digit number
        $number = str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
        return $prefix . $number;
    }
    
    private function addToCabinManagement($booking_id, $passenger_name, $cruise_name, $cabin_type, $cabin_number, $guests_count, $booking_date, $total_cost) {
        try {
            $sql = "INSERT INTO cabin_management (
                booking_id, passenger_name, cruise_name, cabin_type,
                cabin_number, guests_count, booking_date, total_cost
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $booking_id,
                $passenger_name,
                $cruise_name,
                $cabin_type,
                $cabin_number,
                $guests_count,
                $booking_date,
                $total_cost
            ]);
            
            if ($result) {
                return ['success' => true, 'cabin_id' => $this->pdo->lastInsertId()];
            } else {
                return ['success' => false, 'error' => 'Failed to insert into cabin_management'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Cabin management error: ' . $e->getMessage()];
        }
    }
}
?>
