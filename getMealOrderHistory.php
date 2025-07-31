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
    
    // Get all meal preferences/orders for this booking (history)
    $query = "
        SELECT 
            mp.*,
            mo.title as meal_option_title,
            mo.type as meal_option_type,
            mo.description as meal_option_description,
            DATE_FORMAT(mp.created_at, '%Y-%m-%d %H:%i:%s') as order_date
        FROM meal_preferences mp
        LEFT JOIN meal_options mo ON mp.meal_option_id = mo.option_id
        WHERE mp.booking_id = ?
        ORDER BY mp.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$booking_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($history) {
        // Process each history record
        foreach ($history as &$order) {
            // Decode JSON fields
            $order['main_meals'] = json_decode($order['main_meals'] ?? '[]', true);
            $order['tea_times'] = json_decode($order['tea_times'] ?? '[]', true);
            
            // Add human-readable meal times
            $main_meal_names = [];
            $tea_time_names = [];
            
            // Map meal IDs to names
            $meal_time_map = [
                'breakfast' => 'Breakfast',
                'lunch' => 'Lunch', 
                'dinner' => 'Dinner'
            ];
            
            $tea_time_map = [
                'morning_tea' => 'Morning Teatime',
                'evening_tea' => 'Evening Teatime'
            ];
            
            foreach ($order['main_meals'] as $meal_id) {
                if (isset($meal_time_map[$meal_id])) {
                    $main_meal_names[] = $meal_time_map[$meal_id];
                }
            }
            
            foreach ($order['tea_times'] as $tea_id) {
                if (isset($tea_time_map[$tea_id])) {
                    $tea_time_names[] = $tea_time_map[$tea_id];
                }
            }
            
            $order['main_meal_names'] = $main_meal_names;
            $order['tea_time_names'] = $tea_time_names;
            $order['order_number'] = '#' . str_pad($order['id'], 4, '0', STR_PAD_LEFT);
        }
        
        echo json_encode([
            'success' => true,
            'history' => $history,
            'total_orders' => count($history)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No meal orders found for this booking',
            'history' => [],
            'total_orders' => 0
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
