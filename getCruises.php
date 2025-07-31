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
    
    // Get distinct ship names from booking_overview table
    $query = "SELECT DISTINCT ship_name FROM booking_overview WHERE ship_name IS NOT NULL AND ship_name != '' ORDER BY ship_name";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $shipNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // If no ship names found in booking_overview, provide default options
    if (empty($shipNames)) {
        $shipNames = [
            'Caribbean Adventure',
            'Mediterranean Escape', 
            'Alaskan Expedition',
            'Asian Discovery',
            'Norwegian Star',
            'Royal Princess',
            'Celebrity Eclipse',
            'MSC Seaside'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'cruises' => $shipNames,
        'message' => 'Cruise titles retrieved successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'cruises' => []
    ]);
}
?>
