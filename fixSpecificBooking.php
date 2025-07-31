<?php
// Fix specific booking total by recalculating from facility details
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once './DbConnector.php';

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    // Get the booking ID from request (you can modify this)
    $bookingId = $_GET['booking_id'] ?? null;
    
    if (!$bookingId) {
        echo json_encode(['success' => false, 'message' => 'Booking ID required']);
        exit;
    }
    
    // Get all facility data for price lookup
    $facilityQuery = "SELECT facility_id, facility, unit_price FROM facilities WHERE status = 'active'";
    $facilityStmt = $pdo->prepare($facilityQuery);
    $facilityStmt->execute();
    $facilityRows = $facilityStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Map facility codes to prices
    $facilityMap = [];
    foreach ($facilityRows as $facility) {
        $facilityCode = strtolower(str_replace([' ', '&', "'"], ['_', 'and', ''], $facility['facility']));
        $facilityCode = preg_replace('/[^a-z0-9_]/', '', $facilityCode);
        
        $facilityMap[$facilityCode] = [
            'name' => $facility['facility'],
            'price' => floatval($facility['unit_price'])
        ];
    }
    
    // Get the specific booking
    $query = "SELECT id, selected_facilities, quantities, total_cost FROM facility_preferences WHERE booking_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$bookingId]);
    $preference = $stmt->fetch();
    
    if (!$preference) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }
    
    $selectedFacilities = json_decode($preference['selected_facilities'] ?? '{}', true);
    $quantities = json_decode($preference['quantities'] ?? '{}', true);
    $oldTotal = floatval($preference['total_cost']);
    
    // Recalculate correct total
    $newTotal = 0;
    $breakdown = [];
    
    foreach ($selectedFacilities as $facilityCode => $isSelected) {
        if ($isSelected && isset($facilityMap[$facilityCode])) {
            $quantity = $quantities[$facilityCode] ?? 1;
            $facilityPrice = $facilityMap[$facilityCode]['price'];
            $lineTotal = $facilityPrice * $quantity;
            $newTotal += $lineTotal;
            
            $breakdown[] = [
                'facility' => $facilityMap[$facilityCode]['name'],
                'quantity' => $quantity,
                'unit_price' => $facilityPrice,
                'line_total' => $lineTotal
            ];
        }
    }
    
    // Update the database with correct total
    $updateQuery = "UPDATE facility_preferences SET total_cost = ? WHERE id = ?";
    $updateStmt = $pdo->prepare($updateQuery);
    $success = $updateStmt->execute([$newTotal, $preference['id']]);
    
    echo json_encode([
        'success' => $success,
        'booking_id' => $bookingId,
        'old_total' => $oldTotal,
        'new_total' => $newTotal,
        'difference' => $oldTotal - $newTotal,
        'breakdown' => $breakdown
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Fix error: ' . $e->getMessage()
    ]);
}
?>
