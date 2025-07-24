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
if (!$data || !isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing passenger id']);
    exit();
}
$id = intval($data['id']);
$fields = [
    'passenger_name', 'phone_number', 'email',
    'ship_name', 'route', 'cabin_id', 'age', 'gender', 'citizenship'
];
$updates = [];
$params = [];
$types = '';
foreach ($fields as $f) {
    if (isset($data[$f])) {
        $updates[] = "$f = ?";
        $params[] = $data[$f];
        $types .= 's';
    }
}
if (!$updates) {
    echo json_encode(['success' => false, 'message' => 'No fields to update']);
    exit();
}
$params[] = $id;
$types .= 'i';
$sql = 'UPDATE passenger_management SET ' . implode(', ', $updates) . ' WHERE id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Passenger updated']);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}
$stmt->close();
$conn->close();
