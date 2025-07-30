<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/DbConnector.php';

$data = json_decode(file_get_contents('php://input'), true);
$required = ['booking_id', 'passenger_name', 'email', 'ship_name', 'route', 'cabin_id', 'age', 'gender', 'citizenship'];

foreach ($required as $field) {
    if (!isset($data[$field]) || $data[$field] === '') {
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit();
    }
}

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    $sql = 'INSERT INTO passenger_management (
        booking_id, passenger_name, email, ship_name, route, cabin_id, age, gender, citizenship, is_primary, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())';
    
    $stmt = $pdo->prepare($sql);
    $params = [
        $data['booking_id'],
        $data['passenger_name'],
        $data['email'],
        $data['ship_name'],
        $data['route'],
        $data['cabin_id'],
        $data['age'],
        $data['gender'],
        $data['citizenship']
    ];
    
    if ($stmt->execute($params)) {
        echo json_encode(['success' => true, 'message' => 'Passenger created', 'id' => $pdo->lastInsertId()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create passenger']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
