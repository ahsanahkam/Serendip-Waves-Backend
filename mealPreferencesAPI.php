<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'DbConnector.php';

try {
    $db = new DbConnector();
    $conn = $db->connect();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // Get all meal preferences with passenger and meal option details
            $sql = "
                SELECT 
                    mp.id as preference_id,
                    mp.booking_id,
                    mp.meal_option_id,
                    mp.meal_type,
                    mp.main_meals,
                    mp.tea_times,
                    mp.days,
                    mp.notes as dietary_restrictions,
                    '' as special_requests,
                    '' as emergency_contact_name,
                    '' as emergency_contact_phone,
                    mp.created_at,
                    mp.updated_at,
                    CONCAT('Passenger ', mp.booking_id) as passenger_name,
                    CONCAT('passenger', mp.booking_id, '@demo.com') as email,
                    'Demo Cruise' as cruise_title,
                    mo.title as meal_option_title,
                    mo.type as meal_option_type,
                    mo.description as meal_option_description,
                    mo.image as meal_option_image
                FROM meal_preferences mp
                LEFT JOIN meal_options mo ON mp.meal_option_id = mo.option_id
                ORDER BY mp.created_at DESC
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $preferences = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decode JSON fields
            foreach ($preferences as &$pref) {
                if ($pref['main_meals']) {
                    $pref['main_meals'] = json_decode($pref['main_meals'], true) ?: [];
                }
                if ($pref['tea_times']) {
                    $pref['tea_times'] = json_decode($pref['tea_times'], true) ?: [];
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $preferences,
                'count' => count($preferences)
            ]);
            break;
            
        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['preference_id'])) {
                throw new Exception('Preference ID is required');
            }
            
            $sql = "DELETE FROM meal_preferences WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$input['preference_id']]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Meal preference deleted successfully'
                ]);
            } else {
                throw new Exception('Failed to delete meal preference');
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
