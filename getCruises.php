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
    
    // Get ship names from ship_details table first (primary source)
    $query = "SELECT DISTINCT ship_name FROM ship_details WHERE ship_name IS NOT NULL AND ship_name != '' ORDER BY ship_name";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $shipNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // If no ships in ship_details, get from booking_overview as fallback
    if (empty($shipNames)) {
        $query = "SELECT DISTINCT ship_name FROM booking_overview WHERE ship_name IS NOT NULL AND ship_name != '' ORDER BY ship_name";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $shipNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // If still no ship names found, get from cabin_management as final fallback
    if (empty($shipNames)) {
        $query = "SELECT DISTINCT cruise_name FROM cabin_management WHERE cruise_name IS NOT NULL AND cruise_name != '' ORDER BY cruise_name";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $shipNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    echo json_encode([
        'success' => true,
        'cruises' => $shipNames,
        'message' => 'Cruise titles retrieved successfully',
        'count' => count($shipNames)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'cruises' => []
    ]);
}
?>
