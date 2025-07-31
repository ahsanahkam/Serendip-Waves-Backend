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
    
    // Get all meal preferences with passenger information
    $query = "
        SELECT 
            mp.booking_id,
            mp.meal_type,
            mp.main_meals,
            mp.tea_times,
            mp.days,
            mp.notes,
            mp.created_at,
            CONCAT('Passenger ', mp.booking_id) as passenger_name,
            'Demo Cruise' as ship_name,
            'Various Destinations' as destination
        FROM meal_preferences mp
        ORDER BY mp.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $preferences = $stmt->fetchAll();
    
    // Process the JSON fields
    foreach ($preferences as &$pref) {
        $pref['main_meals'] = json_decode($pref['main_meals'] ?? '[]', true);
        $pref['tea_times'] = json_decode($pref['tea_times'] ?? '[]', true);
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
