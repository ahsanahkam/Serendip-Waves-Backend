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
    
    // Query to get existing meal preferences for the booking
    $sql = "SELECT 
                mp.*,
                COUNT(*) as preference_count
            FROM meal_preferences mp 
            WHERE mp.booking_id = ? 
            GROUP BY mp.booking_id
            ORDER BY mp.created_at DESC 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$booking_id]);
    $preference = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($preference) {
        // Parse main meals and tea times from JSON or comma-separated values
        $main_meals = [];
        $tea_times = [];
        
        if (!empty($preference['main_meals'])) {
            // Try to decode as JSON first, if that fails, split by comma
            $decoded_main = json_decode($preference['main_meals'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_main)) {
                $main_meals = $decoded_main;
            } else {
                $main_meals = explode(',', $preference['main_meals']);
            }
        }
        
        if (!empty($preference['tea_times'])) {
            // Try to decode as JSON first, if that fails, split by comma
            $decoded_tea = json_decode($preference['tea_times'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_tea)) {
                $tea_times = $decoded_tea;
            } else {
                $tea_times = explode(',', $preference['tea_times']);
            }
        }
        
        $preferences_data = [
            'id' => $preference['id'],
            'booking_id' => $preference['booking_id'],
            'meal_option_id' => $preference['meal_option_id'],
            'meal_type' => $preference['meal_type'],
            'main_meals' => $main_meals,
            'tea_times' => $tea_times,
            'days' => (int)$preference['days'],
            'notes' => $preference['notes'],
            'created_at' => $preference['created_at'],
            'preference_count' => (int)$preference['preference_count']
        ];
        
        echo json_encode([
            'success' => true,
            'preferences' => $preferences_data,
            'has_preferences' => true
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'preferences' => null,
            'has_preferences' => false,
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
