<?php
// Fix existing facility booking totals in database
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require_once './DbConnector.php';

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    // First, get all facility data for price lookup
    $facilityQuery = "SELECT facility_id, facility, unit_price FROM facilities WHERE status = 'active'";
    $facilityStmt = $pdo->prepare($facilityQuery);
    $facilityStmt->execute();
    $facilityRows = $facilityStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Map facility codes to prices
    $facilityMap = [];
    foreach ($facilityRows as $facility) {
        // Create facility code from facility name for backward compatibility
        $facilityCode = strtolower(str_replace([' ', '&', "'"], ['_', 'and', ''], $facility['facility']));
        $facilityCode = preg_replace('/[^a-z0-9_]/', '', $facilityCode);
        
        $facilityMap[$facilityCode] = [
            'name' => $facility['facility'],
            'price' => floatval($facility['unit_price'])
        ];
    }
    
    // Get all facility preferences that need fixing
    $query = "SELECT id, booking_id, selected_facilities, quantities, total_cost FROM facility_preferences";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $preferences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fixedCount = 0;
    $totalRecords = count($preferences);
    
    foreach ($preferences as $preference) {
        $selectedFacilities = json_decode($preference['selected_facilities'] ?? '{}', true);
        $quantities = json_decode($preference['quantities'] ?? '{}', true);
        $currentTotal = floatval($preference['total_cost']);
        
        // Recalculate correct total
        $correctTotal = 0;
        foreach ($selectedFacilities as $facilityCode => $isSelected) {
            if ($isSelected && isset($facilityMap[$facilityCode])) {
                $quantity = $quantities[$facilityCode] ?? 1;
                $facilityPrice = $facilityMap[$facilityCode]['price'];
                $correctTotal += $facilityPrice * $quantity;
            }
        }
        
        // Only update if the total is different
        if (abs($currentTotal - $correctTotal) > 0.01) {
            $updateQuery = "UPDATE facility_preferences SET total_cost = ? WHERE id = ?";
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->execute([$correctTotal, $preference['id']]);
            $fixedCount++;
            
            // Log the fix (don't output directly as it breaks JSON)
            error_log("Fixed booking {$preference['booking_id']}: $currentTotal -> $correctTotal");
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Database fix completed successfully!",
        'details' => [
            'total_records' => $totalRecords,
            'fixed_records' => $fixedCount,
            'facility_map_count' => count($facilityMap)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database fix error: ' . $e->getMessage()
    ]);
}
?>
