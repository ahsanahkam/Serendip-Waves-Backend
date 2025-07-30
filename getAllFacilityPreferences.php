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
    
    // Get all facility preferences with passenger information
    $query = "
        SELECT 
            fp.booking_id,
            COALESCE(fp.passenger_name, b.full_name) as passenger_name,
            fp.selected_facilities,
            fp.quantities,
            fp.total_cost,
            fp.payment_status,
            COALESCE(fp.status, fp.payment_status) as status,
            fp.created_at,
            fp.updated_at,
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
        ORDER BY fp.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $preferences = $stmt->fetchAll();
    
    // Process the JSON fields and add calculated fields
    foreach ($preferences as &$pref) {
        $pref['selected_facilities'] = json_decode($pref['selected_facilities'] ?? '{}', true);
        $pref['quantities'] = json_decode($pref['quantities'] ?? '{}', true);
        
        // Calculate trip duration if dates are available
        if ($pref['departure_date'] && $pref['return_date']) {
            $start = new DateTime($pref['departure_date']);
            $end = new DateTime($pref['return_date']);
            $pref['trip_duration'] = $end->diff($start)->days;
        } else {
            $pref['trip_duration'] = null;
        }
        
        // Check if journey is completed
        $today = date('Y-m-d');
        $pref['journey_completed'] = $pref['return_date'] && $pref['return_date'] < $today;
        
        // Add facility details for dashboard display
        $facilityDetails = [];
        $selectedFacilities = $pref['selected_facilities'];
        $quantities = $pref['quantities'];
        
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
                    'unit' => $facility['unit']
                ];
            }
        }
        
        $pref['facility_details'] = $facilityDetails;
        $pref['total_facilities'] = count($facilityDetails);
    }
    
    echo json_encode([
        'success' => true,
        'preferences' => $preferences
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
