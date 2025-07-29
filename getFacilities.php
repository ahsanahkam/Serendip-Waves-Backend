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
    
    // Get all active facilities
    $query = "
        SELECT 
            facility_id,
            facility,
            unit_price,
            status
        FROM facilities 
        WHERE status = 'active'
        ORDER BY facility
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data for frontend consumption
    $formattedFacilities = [];
    $facilitiesMap = [];
    
    foreach ($facilities as $facility) {
        $unitText = $facility['unit_price'] == 0 ? 'Free' : '$' . number_format($facility['unit_price'], 2);
        
        // Create facility code from facility name for backward compatibility
        $facilityCode = strtolower(str_replace([' ', '&', "'"], ['_', 'and', ''], $facility['facility']));
        $facilityCode = preg_replace('/[^a-z0-9_]/', '', $facilityCode);
        
        // Create the facilities map for backward compatibility
        $facilitiesMap[$facilityCode] = [
            'name' => $facility['facility'],
            'price' => (float)$facility['unit_price'],
            'unit' => 'access', // Default unit since we removed this field
            'unitText' => $unitText
        ];
        
        // Also create a detailed array
        $formattedFacilities[] = [
            'id' => $facility['facility_id'],
            'code' => $facilityCode,
            'name' => $facility['facility'],
            'price' => (float)$facility['unit_price'],
            'unit' => 'access',
            'unitText' => $unitText,
            'status' => $facility['status']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'facilities' => $formattedFacilities,
        'facilitiesMap' => $facilitiesMap,
        'totalCount' => count($facilities)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
