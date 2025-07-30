<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once './DbConnector.php';

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    // Get booking_id from query parameter
    $bookingId = $_GET['booking_id'] ?? null;
    
    if (!$bookingId) {
        echo json_encode([
            'success' => false,
            'message' => 'Booking ID is required'
        ]);
        exit();
    }
    
    // Get facility preferences for specific customer
    $query = "
        SELECT 
            fp.booking_id,
            fp.selected_facilities,
            fp.quantities,
            fp.total_cost,
            fp.payment_status,
            fp.created_at,
            fp.updated_at,
            b.full_name as passenger_name,
            b.email as passenger_email,
            b.ship_name,
            b.destination,
            b.room_type,
            b.adults,
            b.children,
            i.start_date as departure_date,
            i.end_date as return_date
        FROM facility_preferences fp
        LEFT JOIN booking_overview b ON fp.booking_id = b.booking_id
        LEFT JOIN itineraries i ON b.ship_name = i.ship_name AND b.destination = i.route
        WHERE fp.booking_id = ?
        ORDER BY fp.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$bookingId]);
    $preference = $stmt->fetch();
    
    if (!$preference) {
        echo json_encode([
            'success' => false,
            'message' => 'No facility preferences found for this booking'
        ]);
        exit();
    }
    
    // Process the JSON fields and add calculated fields
    $preference['selected_facilities'] = json_decode($preference['selected_facilities'] ?? '{}', true);
    $preference['quantities'] = json_decode($preference['quantities'] ?? '{}', true);
    
    // Calculate trip duration if dates are available
    if ($preference['departure_date'] && $preference['return_date']) {
        $start = new DateTime($preference['departure_date']);
        $end = new DateTime($preference['return_date']);
        $preference['trip_duration'] = $end->diff($start)->days;
    } else {
        $preference['trip_duration'] = null;
    }
    
    // Check if journey is completed
    $today = date('Y-m-d');
    $preference['journey_completed'] = $preference['return_date'] && $preference['return_date'] < $today;
    
    // Add facility details for customer dashboard display
    $facilityDetails = [];
    $selectedFacilities = $preference['selected_facilities'];
    $quantities = $preference['quantities'];
    
    // Fetch facility data from database
    $facilityQuery = "SELECT facility_id, facility, unit_price FROM facilities WHERE status = 'active'";
    $facilityStmt = $pdo->prepare($facilityQuery);
    $facilityStmt->execute();
    $facilityRows = $facilityStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Map facility codes to readable names and prices
    $facilityMap = [];
    foreach ($facilityRows as $facility) {
        // Create facility code from facility name for backward compatibility
        $facilityCode = strtolower(str_replace([' ', '&', "'"], ['_', 'and', ''], $facility['facility']));
        $facilityCode = preg_replace('/[^a-z0-9_]/', '', $facilityCode);
        
        $facilityMap[$facilityCode] = [
            'name' => $facility['facility'],
            'price' => floatval($facility['unit_price']),
            'unit' => 'access' // Default unit
        ];
    }
    
    foreach ($selectedFacilities as $facilityCode => $isSelected) {
        if ($isSelected && isset($facilityMap[$facilityCode])) {
            $quantity = $quantities[$facilityCode] ?? 1;
            $facility = $facilityMap[$facilityCode];
            $totalPrice = $facility['price'] * $quantity;
            
            $facilityDetails[] = [
                'code' => $facilityCode,
                'name' => $facility['name'],
                'quantity' => $quantity,
                'unit_price' => $facility['price'],
                'total_price' => $totalPrice,
                'unit' => $facility['unit'],
                'unit_text' => $facility['price'] > 0 ? 'per ' . $facility['unit'] : 'Free'
            ];
        }
    }
    
    $preference['facility_details'] = $facilityDetails;
    $preference['total_facilities'] = count($facilityDetails);
    
    // Check if customer can still modify preferences
    $canModify = !$preference['journey_completed'] && $preference['payment_status'] !== 'paid';
    $preference['can_modify'] = $canModify;
    
    echo json_encode([
        'success' => true,
        'preference' => $preference
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
