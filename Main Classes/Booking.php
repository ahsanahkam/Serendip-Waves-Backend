<?php
require_once __DIR__ . '/../DbConnector.php';

class Booking {
    private $pdo;
    
    public function __construct() {
        $dbConnector = new DbConnector();
        $this->pdo = $dbConnector->connect();
    }
    
    public function addBooking($full_name, $gender, $email, $citizenship, $age, $room_type, $cabin_number, $adults, $children, $number_of_guests, $ship_name, $destination, $card_type = 'Visa', $card_number = '0000000000000000', $card_expiry = '') {
        try {
            // Validate required fields
            if (empty($full_name) || empty($email) || empty($room_type) || empty($ship_name) || empty($destination)) {
                return ['success' => false, 'message' => 'Missing required fields: full_name, email, room_type, ship_name, destination'];
            }
            
            // Validate card expiry if provided
            if (!empty($card_expiry)) {
                $expiryValidation = $this->validateCardExpiry($card_expiry);
                if (!$expiryValidation['valid']) {
                    return ['success' => false, 'message' => $expiryValidation['message']];
                }
            }
            
            // Get dynamic pricing from cabin_type_pricing table
            $pricing = $this->getCabinTypePrice($ship_name, $destination, $room_type);
            if (!$pricing['success']) {
                return ['success' => false, 'message' => $pricing['message']];
            }
            
            $base_price = $pricing['price'];
            
            // Calculate total price based on adult/child breakdown or number of guests
            $trip_duration = $this->getTripDuration($ship_name, $destination);
            $total_price = $this->calculateTotalPrice($base_price, $adults, $children, $number_of_guests);
            
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
            // Map room types to database column names (handle variations)
            $price_column_map = [
                'Interior' => 'interior_price',
                'Ocean View' => 'ocean_view_price',
                'Balcony' => 'balcony_price',
                'Suite' => 'suite_price'
            ];
            
            // Normalize the room type (trim and handle case variations)
            $room_type = trim($room_type);
            
            if (!isset($price_column_map[$room_type])) {
                // Log the invalid room type for debugging
                error_log("Invalid room type received: '$room_type'. Valid types: " . implode(', ', array_keys($price_column_map)));
                return ['success' => false, 'message' => "Invalid room type: '$room_type'. Valid types: Interior, Ocean View, Balcony, Suite"];
            }
            
            $price_column = $price_column_map[$room_type];
            
            $stmt = $this->pdo->prepare("SELECT {$price_column} as price FROM cabin_type_pricing WHERE ship_name = ? AND route = ?");
            $stmt->execute([$ship_name, $route]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['price'] !== null) {
                return ['success' => true, 'price' => (float)$result['price']];
            } else {
                return ['success' => false, 'message' => 'Pricing not found for this ship and route combination'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    private function getTripDuration($ship_name, $destination) {
        try {
            $stmt = $this->pdo->prepare("SELECT duration_days FROM itineraries WHERE ship_name = ? AND destination = ?");
            $stmt->execute([$ship_name, $destination]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? (int)$result['duration_days'] : 7; // Default to 7 days
        } catch (Exception $e) {
            return 7; // Default fallback
        }
    }
    
    private function calculateTotalPrice($base_price, $adults, $children, $number_of_guests) {
        // Price is per person for the entire trip, not per day
        $total_guests = $adults + $children;
        
        if ($total_guests == $number_of_guests && $total_guests > 0) {
            // Use the specific adult/children breakdown (children pay half price)
            return ($adults * $base_price) + ($children * $base_price * 0.5);
        } else {
            // Fallback: treat all guests as adults if breakdown doesn't match
            return $number_of_guests * $base_price;
        }
    }
    
    private function generateCabinNumber($room_type) {
        $prefixes = [
            'Interior' => 'I',
            'Ocean View' => 'O', 
            'Balcony' => 'B',
            'Suite' => 'S'
        ];
        
        $prefix = $prefixes[$room_type] ?? 'G';
        $random_number = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        return $prefix . $random_number;
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
    
    /**
     * Validate card expiry date - rejects past dates
     * @param string $card_expiry Card expiry in MM/YY format
     * @return array ['valid' => bool, 'message' => string]
     */
    private function validateCardExpiry($card_expiry) {
        // Check format MM/YY
        $expiryMatch = preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $card_expiry, $matches);
        
        if (!$expiryMatch) {
            return [
                'valid' => false,
                'message' => 'Card expiry must be in MM/YY format (e.g., 12/25)'
            ];
        }
        
        $expMonth = (int)$matches[1];
        $expYear = 2000 + (int)$matches[2]; // Convert 2-digit year to 4-digit
        
        // Handle year rollover intelligently
        $currentYear = (int)date('Y');
        
        // If the year is significantly in the past, it probably means next century
        // But be conservative - only roll over for very old years (before 2000)
        if ($expYear < 2000) {
            $expYear += 100;
        }
        // If year is way in the future (more than 20 years), assume it's a mistake
        else if ($expYear > $currentYear + 20) {
            $expYear -= 100;
        }
        
        // Create expiry date (last day of the expiry month)
        $expiryDate = new DateTime();
        $expiryDate->setDate($expYear, $expMonth, 1);
        $expiryDate->modify('last day of this month');
        $expiryDate->setTime(23, 59, 59); // End of the day
        
        $currentDate = new DateTime();
        
        if ($expiryDate < $currentDate) {
            return [
                'valid' => false,
                'message' => 'Card expiry date must be in the future. Your card expired on ' . $expiryDate->format('m/Y')
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Card expiry date is valid'
        ];
    }
}
?>
