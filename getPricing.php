<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/Main Classes/Booking.php';

try {
    // Get query parameters
    $ship_name = $_GET['ship_name'] ?? '';
    $route = $_GET['route'] ?? '';
    $room_type = $_GET['room_type'] ?? '';
    $number_of_guests = intval($_GET['number_of_guests'] ?? 1);
    
    if (empty($ship_name) || empty($route)) {
        echo json_encode(['success' => false, 'message' => 'ship_name and route parameters are required']);
        exit();
    }
    
    $booking = new Booking();
    
    if (!empty($room_type)) {
        // Get pricing for specific room type
        $pricing_result = $booking->getPricingForShipRoute($ship_name, $route);
        
        if (!$pricing_result['success']) {
            echo json_encode($pricing_result);
            exit();
        }
        
        $pricing = $pricing_result['pricing'];
        
        // Map room types to prices
        $price_map = [
            'Interior' => $pricing['interior_price'],
            'Ocean View' => $pricing['ocean_view_price'],
            'Balcony' => $pricing['balcony_price'],
            'Suit' => $pricing['suit_price']
        ];
        
        if (!isset($price_map[$room_type])) {
            echo json_encode(['success' => false, 'message' => 'Invalid room type']);
            exit();
        }
        
        $base_price = floatval($price_map[$room_type]);
        $total_price = $base_price * $number_of_guests;
        
        echo json_encode([
            'success' => true,
            'ship_name' => $ship_name,
            'route' => $route,
            'room_type' => $room_type,
            'base_price_per_person' => $base_price,
            'number_of_guests' => $number_of_guests,
            'total_amount' => $total_price
        ]);
        
    } else {
        // Get all pricing for the ship/route
        $result = $booking->getPricingForShipRoute($ship_name, $route);
        
        if ($result['success']) {
            $pricing = $result['pricing'];
            
            echo json_encode([
                'success' => true,
                'ship_name' => $ship_name,
                'route' => $route,
                'pricing' => [
                    'interior' => [
                        'price_per_person' => floatval($pricing['interior_price']),
                        'total_for_guests' => floatval($pricing['interior_price']) * $number_of_guests
                    ],
                    'ocean_view' => [
                        'price_per_person' => floatval($pricing['ocean_view_price']),
                        'total_for_guests' => floatval($pricing['ocean_view_price']) * $number_of_guests
                    ],
                    'balcony' => [
                        'price_per_person' => floatval($pricing['balcony_price']),
                        'total_for_guests' => floatval($pricing['balcony_price']) * $number_of_guests
                    ],
                    'suite' => [
                        'price_per_person' => floatval($pricing['suit_price']),
                        'total_for_guests' => floatval($pricing['suit_price']) * $number_of_guests
                    ]
                ],
                'number_of_guests' => $number_of_guests
            ]);
        } else {
            echo json_encode($result);
        }
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
