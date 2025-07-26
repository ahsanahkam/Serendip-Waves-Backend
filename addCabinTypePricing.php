<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');
require_once __DIR__ . '/config/db.php';

$response = array('success' => false);

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $response['message'] = 'Invalid JSON.';
    echo json_encode($response);
    exit;
}

$ship_name = $input['ship_name'] ?? '';
$route = $input['route'] ?? '';
$interior_price = $input['interior_price'] ?? 0;
$ocean_view_price = $input['ocean_view_price'] ?? 0;
$balcony_price = $input['balcony_price'] ?? 0;
$suit_price = $input['suit_price'] ?? 0;

if (!$ship_name || !$route) {
    $response['message'] = 'Ship name and route are required.';
    echo json_encode($response);
    exit;
}

try {
    require_once __DIR__ . '/DbConnector.php';
    $db = new DBConnector();
    $pdo = $db->connect();
    // Check if this ship/route combo exists in itineraries
    $stmt = $pdo->prepare('SELECT 1 FROM itineraries WHERE ship_name = ? AND route = ?');
    $stmt->execute([$ship_name, $route]);
    if (!$stmt->fetch()) {
        $response['message'] = 'Invalid ship name or route.';
        echo json_encode($response);
        exit;
    }
    // Insert new pricing
    $stmt = $pdo->prepare('INSERT INTO cabin_type_pricing (ship_name, route, interior_price, ocean_view_price, balcony_price, suit_price) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$ship_name, $route, $interior_price, $ocean_view_price, $balcony_price, $suit_price]);
    $response['success'] = true;
    $response['message'] = 'Pricing added.';
} catch (Exception $e) {
    $response['message'] = 'Error adding pricing: ' . $e->getMessage();
}

echo json_encode($response);
