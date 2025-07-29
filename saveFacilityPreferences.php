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
    
    // Create facility_preferences table if it doesn't exist
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS facility_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id VARCHAR(50) NOT NULL,
            selected_facilities JSON,
            quantities JSON,
            total_cost DECIMAL(10,2) DEFAULT 0.00,
            payment_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_booking (booking_id)
        )
    ";
    $pdo->exec($createTableQuery);
    
    // Check if preference already exists
    $checkQuery = "SELECT id FROM facility_preferences WHERE booking_id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$data['booking_id']]);
    $exists = $checkStmt->fetch();
    
    $facilitiesJson = json_encode($data['selected_facilities'] ?? []);
    $quantitiesJson = json_encode($data['quantities'] ?? []);
    $totalCost = $data['total_cost'] ?? 0.00;
    $paymentStatus = $data['payment_status'] ?? 'pending';
    
    if ($exists) {
        // Update existing preference
        $updateQuery = "
            UPDATE facility_preferences 
            SET selected_facilities = ?, quantities = ?, total_cost = ?, payment_status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE booking_id = ?
        ";
        $stmt = $pdo->prepare($updateQuery);
        $result = $stmt->execute([
            $facilitiesJson,
            $quantitiesJson,
            $totalCost,
            $paymentStatus,
            $data['booking_id']
        ]);
    } else {
        // Insert new preference
        $insertQuery = "
            INSERT INTO facility_preferences (booking_id, selected_facilities, quantities, total_cost, payment_status)
            VALUES (?, ?, ?, ?, ?)
        ";
        $stmt = $pdo->prepare($insertQuery);
        $result = $stmt->execute([
            $data['booking_id'],
            $facilitiesJson,
            $quantitiesJson,
            $totalCost,
            $paymentStatus
        ]);
    }
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Facility preferences saved successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save facility preferences'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
