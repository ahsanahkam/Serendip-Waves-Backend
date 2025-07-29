<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
require_once __DIR__ . '/DbConnector.php';

$data = json_decode(file_get_contents('php://input'), true);

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();

    $required = ['booking_id', 'ship_name', 'route', 'cabin_id', 'passengerList'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
            exit();
        }
    }

    $booking_id = $data['booking_id'];
    $ship_name = $data['ship_name'];
    $route = $data['route'];
    $cabin_id = $data['cabin_id'];
    $passengerList = $data['passengerList'];

    $sql = "INSERT INTO passenger_management (
        booking_id, passenger_name, email,
        ship_name, route, cabin_id, age, gender, citizenship,
        is_primary, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $pdo->prepare($sql);
    foreach ($passengerList as $idx => $p) {
        $is_primary = ($idx === 0) ? 1 : 0;
        $stmt->execute([
            $booking_id,
            $p['passenger_name'],
            $p['email'],
            $ship_name,
            $route,
            $cabin_id,
            $p['age'],
            $p['gender'],
            $p['citizenship'],
            $is_primary
        ]);
    }
    echo json_encode(['success' => true, 'message' => 'Passengers added successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>




