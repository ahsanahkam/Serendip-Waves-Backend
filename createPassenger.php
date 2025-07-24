<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$required = ['booking_id', 'passenger_name', 'phone_number', 'email', 'ship_name', 'route', 'cabin_id', 'age', 'gender', 'citizenship'];
foreach ($required as $field) {
    if (!isset($data[$field]) || $data[$field] === '') {
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit();
    }
}

$sql = 'INSERT INTO passenger_management (
    booking_id, passenger_name, phone_number, email, ship_name, route, cabin_id, age, gender, citizenship, is_primary, created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())';
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    'issssssisss',
    $data['booking_id'],
    $data['passenger_name'],
    $data['phone_number'],
    $data['email'],
    $data['ship_name'],
    $data['route'],
    $data['cabin_id'],
    $data['age'],
    $data['gender'],
    $data['citizenship']
);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Passenger created', 'id' => $conn->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}
$stmt->close();
$conn->close();
