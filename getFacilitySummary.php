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
    
    // Get facility summary for admin dashboard
    $query = "
        SELECT 
            fp.booking_id,
            fp.selected_facilities,
            fp.quantities,
            fp.total_cost,
            fp.payment_status,
            fp.created_at,
            b.full_name as passenger_name,
            b.ship_name,
            b.destination,
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
    
    // Facility mapping
    $facilityMap = [
        'spa' => ['name' => 'Spa and Wellness Center', 'price' => 50, 'unit' => 'day'],
        'water_sports' => ['name' => 'Water Sports Pass', 'price' => 30, 'unit' => 'day'],
        'casino' => ['name' => 'Casino Entry Pass', 'price' => 25, 'unit' => 'day'],
        'babysitting' => ['name' => 'Babysitting Services', 'price' => 20, 'unit' => 'hour'],
        'private_party' => ['name' => 'Private Party/Event Hall', 'price' => 200, 'unit' => 'event'],
        'translator' => ['name' => 'Translator Support', 'price' => 50, 'unit' => 'booking'],
        'fitness' => ['name' => 'Fitness Center', 'price' => 0, 'unit' => 'access'],
        'cinema' => ['name' => 'Cinema & Open-Air Movies', 'price' => 15, 'unit' => 'day'],
        'kids_club' => ['name' => "Kids' Club & Play Area", 'price' => 0, 'unit' => 'access']
    ];
    
    // Initialize summary data
    $facilitySummary = [];
    $totalRevenue = 0;
    $totalBookings = count($preferences);
    $paymentStatusSummary = ['pending' => 0, 'paid' => 0, 'cancelled' => 0];
    
    // Process each preference record
    foreach ($preferences as $pref) {
        $selectedFacilities = json_decode($pref['selected_facilities'] ?? '{}', true);
        $quantities = json_decode($pref['quantities'] ?? '{}', true);
        
        // Update payment status summary
        $paymentStatusSummary[$pref['payment_status']]++;
        
        // Add to total revenue if paid
        if ($pref['payment_status'] === 'paid') {
            $totalRevenue += floatval($pref['total_cost']);
        }
        
        // Process each selected facility
        foreach ($selectedFacilities as $facilityCode => $isSelected) {
            if ($isSelected && isset($facilityMap[$facilityCode])) {
                $quantity = $quantities[$facilityCode] ?? 1;
                $facility = $facilityMap[$facilityCode];
                
                if (!isset($facilitySummary[$facilityCode])) {
                    $facilitySummary[$facilityCode] = [
                        'code' => $facilityCode,
                        'name' => $facility['name'],
                        'unit_price' => $facility['price'],
                        'unit' => $facility['unit'],
                        'total_bookings' => 0,
                        'total_quantity' => 0,
                        'total_revenue' => 0,
                        'pending_bookings' => 0,
                        'paid_bookings' => 0,
                        'cancelled_bookings' => 0
                    ];
                }
                
                $facilitySummary[$facilityCode]['total_bookings']++;
                $facilitySummary[$facilityCode]['total_quantity'] += $quantity;
                
                // Calculate revenue for this facility booking
                $facilityRevenue = $facility['price'] * $quantity;
                if ($pref['payment_status'] === 'paid') {
                    $facilitySummary[$facilityCode]['total_revenue'] += $facilityRevenue;
                }
                
                // Update payment status counts
                $facilitySummary[$facilityCode][$pref['payment_status'] . '_bookings']++;
            }
        }
    }
    
    // Convert to indexed array and sort by popularity
    $facilitySummaryArray = array_values($facilitySummary);
    usort($facilitySummaryArray, function($a, $b) {
        return $b['total_bookings'] - $a['total_bookings'];
    });
    
    // Calculate occupancy rates (assuming ship capacity)
    $occupancyData = [];
    $shipBookings = [];
    foreach ($preferences as $pref) {
        $shipName = $pref['ship_name'];
        if (!isset($shipBookings[$shipName])) {
            $shipBookings[$shipName] = [
                'ship_name' => $shipName,
                'total_passengers' => 0,
                'facility_bookings' => 0
            ];
        }
        $shipBookings[$shipName]['total_passengers'] += ($pref['adults'] + $pref['children']);
        $shipBookings[$shipName]['facility_bookings']++;
    }
    
    echo json_encode([
        'success' => true,
        'summary' => [
            'total_bookings' => $totalBookings,
            'total_revenue' => $totalRevenue,
            'payment_status' => $paymentStatusSummary,
            'facility_summary' => $facilitySummaryArray,
            'ship_occupancy' => array_values($shipBookings)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
