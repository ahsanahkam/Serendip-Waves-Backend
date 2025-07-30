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
if (!$data || !isset($data['passenger_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing passenger id']);
    exit();
}

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    $passenger_id = intval($data['passenger_id']);
    $sql = 'DELETE FROM passenger_management WHERE passenger_id = ?';
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$passenger_id])) {
        echo json_encode(['success' => true, 'message' => 'Passenger deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete passenger']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
