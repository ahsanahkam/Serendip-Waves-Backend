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

require_once __DIR__ . '/DbConnector.php'; // assumes $conn is your mysqli connection
require_once __DIR__ . '/CabinManager.php';

$data = json_decode(file_get_contents('php://input'), true);

$required = [
    'booking_id', 'passenger_name', 'cruise_name', 'cabin_type',
    'cabin_number', 'guests_count', 'booking_date', 'total_cost'
];
foreach ($required as $field) {
    if (!isset($data[$field]) || $data[$field] === "") {
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit();
    }
}

try {
    $cabinManager = new CabinManager($conn);
    $result = $cabinManager->addCabin(
        $data['booking_id'],
        $data['passenger_name'],
        $data['cruise_name'],
        $data['cabin_type'],
        $data['cabin_number'],
        $data['guests_count'],
        $data['booking_date'],
        $data['total_cost']
    );
    echo json_encode($result);
} catch (Exception $e) {
    
    echo json_encode(['success' => false, 'message' => 'Error adding cabin', 'error' => $e->getMessage()]);
}
