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
require_once __DIR__ . '/config/db.php';

$data = json_decode(file_get_contents('php://input'), true);


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
) VALUES (:booking_id, :passenger_name, :email, :ship_name, :route, :cabin_id, :age, :gender, :citizenship, :is_primary, NOW())";

try {
    $stmt = $conn->prepare($sql);
    foreach ($passengerList as $idx => $p) {
        $is_primary = ($idx === 0) ? 1 : 0;
        $stmt->execute([
            ':booking_id' => $booking_id,
            ':passenger_name' => $p['passenger_name'],
            ':email' => $p['email'],
            ':ship_name' => $ship_name,
            ':route' => $route,
            ':cabin_id' => $cabin_id,
            ':age' => $p['age'],
            ':gender' => $p['gender'],
            ':citizenship' => $p['citizenship'],
            ':is_primary' => $is_primary
        ]);
    }
    echo json_encode(['success' => true, 'message' => 'Passengers added successfully']);
} catch (PDOException $e) {
    
echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}




