<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Include database connection
require_once 'DbConnector.php';

try {
    if (!isset($_GET['booking_id'])) {
        echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
        exit;
    }
    
    $booking_id = $_GET['booking_id'];
    
    // Validate booking ID
    if (!is_numeric($booking_id) || $booking_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
        exit;
    }
    
    $db = new DBConnector();
    $conn = $db->connect();
    
    // Query to get meal order history for the booking
    $sql = "SELECT 
                mp.id,
                mp.booking_id,
                mp.meal_option_id,
                mp.meal_type,
                mp.main_meals,
                mp.tea_times,
                mp.days,
                mp.notes,
                mp.created_at as order_date,
                CONCAT('ORD-', LPAD(mp.id, 6, '0')) as order_number
            FROM meal_preferences mp 
            WHERE mp.booking_id = ? 
            ORDER BY mp.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$booking_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $history = [];
    foreach ($orders as $order) {
        // Parse main meals and tea times from JSON or comma-separated values
        $main_meals = [];
        $tea_times = [];
        
        if (!empty($order['main_meals'])) {
            // Try to decode as JSON first, if that fails, split by comma
            $decoded_main = json_decode($order['main_meals'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_main)) {
                $main_meals = $decoded_main;
            } else {
                $main_meals = explode(',', $order['main_meals']);
            }
        }
        
        if (!empty($order['tea_times'])) {
            // Try to decode as JSON first, if that fails, split by comma
            $decoded_tea = json_decode($order['tea_times'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_tea)) {
                $tea_times = $decoded_tea;
            } else {
                $tea_times = explode(',', $order['tea_times']);
            }
        }
        
        // Convert meal time IDs to readable names
        $main_meal_names = [];
        foreach ($main_meals as $meal_id) {
            switch (trim($meal_id)) {
                case 'breakfast':
                    $main_meal_names[] = 'Breakfast';
                    break;
                case 'lunch':
                    $main_meal_names[] = 'Lunch';
                    break;
                case 'dinner':
                    $main_meal_names[] = 'Dinner';
                    break;
                default:
                    if (!empty(trim($meal_id))) {
                        $main_meal_names[] = ucfirst(trim($meal_id));
                    }
            }
        }
        
        $tea_time_names = [];
        foreach ($tea_times as $tea_id) {
            switch (trim($tea_id)) {
                case 'morning_tea':
                    $tea_time_names[] = 'Morning Teatime';
                    break;
                case 'evening_tea':
                    $tea_time_names[] = 'Evening Teatime';
                    break;
                default:
                    if (!empty(trim($tea_id))) {
                        $tea_time_names[] = ucfirst(str_replace('_', ' ', trim($tea_id)));
                    }
            }
        }
        
        $history[] = [
            'id' => $order['id'],
            'booking_id' => $order['booking_id'],
            'meal_option_id' => $order['meal_option_id'],
            'meal_option_title' => $order['meal_type'],
            'main_meals' => $main_meals,
            'tea_times' => $tea_times,
            'main_meal_names' => $main_meal_names,
            'tea_time_names' => $tea_time_names,
            'days' => (int)$order['days'],
            'notes' => $order['notes'],
            'order_date' => $order['order_date'],
            'order_number' => $order['order_number']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'history' => $history,
        'count' => count($history)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
