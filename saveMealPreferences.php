<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once './DbConnector.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['booking_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request data'
    ]);
    exit();
}

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    // Create meal_preferences table if it doesn't exist
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS meal_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id VARCHAR(50) NOT NULL,
            meal_type VARCHAR(50) NOT NULL,
            main_meals JSON,
            tea_times JSON,
            days INT DEFAULT 1,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_booking (booking_id)
        )
    ";
    $pdo->exec($createTableQuery);
    
    // Check if preference already exists
    $checkQuery = "SELECT id FROM meal_preferences WHERE booking_id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$data['booking_id']]);
    $exists = $checkStmt->fetch();
    
    $mainMealsJson = json_encode($data['main_meals'] ?? []);
    $teaTimesJson = json_encode($data['tea_times'] ?? []);
    
    if ($exists) {
        // Update existing preference
        $updateQuery = "
            UPDATE meal_preferences 
            SET meal_type = ?, main_meals = ?, tea_times = ?, days = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
            WHERE booking_id = ?
        ";
        $stmt = $pdo->prepare($updateQuery);
        $result = $stmt->execute([
            $data['meal_type'],
            $mainMealsJson,
            $teaTimesJson,
            $data['days'] ?? 1,
            $data['notes'] ?? '',
            $data['booking_id']
        ]);
    } else {
        // Insert new preference
        $insertQuery = "
            INSERT INTO meal_preferences (booking_id, meal_type, main_meals, tea_times, days, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        $stmt = $pdo->prepare($insertQuery);
        $result = $stmt->execute([
            $data['booking_id'],
            $data['meal_type'],
            $mainMealsJson,
            $teaTimesJson,
            $data['days'] ?? 1,
            $data['notes'] ?? ''
        ]);
    }
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Meal preferences saved successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save meal preferences'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
