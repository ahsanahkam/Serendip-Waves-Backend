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
    
    // Get distinct room types from booking_overview table
    $query = "SELECT DISTINCT room_type FROM booking_overview WHERE room_type IS NOT NULL AND room_type != '' ORDER BY room_type";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $roomTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // If no room types found in booking_overview, provide default options
    if (empty($roomTypes)) {
        $roomTypes = [
            'Interior',
            'Ocean View',
            'Balcony',
            'Suite',
            'Junior Suite',
            'Presidential Suite',
            'Family Room',
            'Connecting Rooms'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'cabinTypes' => $roomTypes,
        'message' => 'Cabin types retrieved successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'cabinTypes' => []
    ]);
}
?>
