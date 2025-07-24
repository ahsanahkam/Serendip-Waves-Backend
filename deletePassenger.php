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
$sql = 'DELETE FROM passenger_management WHERE id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Passenger deleted']);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}
$stmt->close();
$conn->close();
