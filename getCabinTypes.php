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

require_once './DbConnector.php';

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    // Get cabin types from cabin_type_pricing structure (Interior, Ocean View, Balcony, Suite)
    $cabinTypes = ['Interior', 'Ocean View', 'Balcony', 'Suite'];
    
    // Also get distinct cabin types from cabin_management table
    $query = "SELECT DISTINCT cabin_type FROM cabin_management WHERE cabin_type IS NOT NULL AND cabin_type != '' ORDER BY cabin_type";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $dbCabinTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Merge with database cabin types (avoiding duplicates)
    if (!empty($dbCabinTypes)) {
        $cabinTypes = array_unique(array_merge($cabinTypes, $dbCabinTypes));
        sort($cabinTypes);
    }
    
    // Also check booking_overview for additional cabin types
    $query = "SELECT DISTINCT room_type FROM booking_overview WHERE room_type IS NOT NULL AND room_type != '' ORDER BY room_type";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $bookingCabinTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($bookingCabinTypes)) {
        $cabinTypes = array_unique(array_merge($cabinTypes, $bookingCabinTypes));
        sort($cabinTypes);
    }
    
    echo json_encode([
        'success' => true,
        'cabinTypes' => array_values($cabinTypes), // Re-index array
        'message' => 'Cabin types retrieved successfully',
        'count' => count($cabinTypes)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'cabinTypes' => []
    ]);
}
?>
