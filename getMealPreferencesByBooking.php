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
    $booking_id = $_GET['booking_id'] ?? null;
    
    if (!$booking_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Booking ID is required'
        ]);
        exit();
    }
    
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    // Get meal preferences for specific booking
    $query = "
        SELECT 
            mp.*,
            mo.title as meal_option_title,
            mo.type as meal_option_type,
            mo.description as meal_option_description
        FROM meal_preferences mp
        LEFT JOIN meal_options mo ON mp.meal_option_id = mo.option_id
        WHERE mp.booking_id = ?
        ORDER BY mp.created_at DESC
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$booking_id]);
    $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($preferences) {
        // Decode JSON fields
        $preferences['main_meals'] = json_decode($preferences['main_meals'] ?? '[]', true);
        $preferences['tea_times'] = json_decode($preferences['tea_times'] ?? '[]', true);
        
        echo json_encode([
            'success' => true,
            'preferences' => $preferences
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No meal preferences found for this booking'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
