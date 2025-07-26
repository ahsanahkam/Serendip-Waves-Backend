<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');
require_once __DIR__ . '/config/db.php';

$response = array('success' => false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $response['message'] = 'Invalid JSON.';
    echo json_encode($response);
    exit;
}

$id = $input['id'] ?? null;
$interior_price = $input['interior_price'] ?? 0;
$ocean_view_price = $input['ocean_view_price'] ?? 0;
$balcony_price = $input['balcony_price'] ?? 0;
$suit_price = $input['suit_price'] ?? 0;

if (!$id) {
    $response['message'] = 'Missing pricing record ID.';
    echo json_encode($response);
    exit;
}

try {
    require_once __DIR__ . '/DbConnector.php';
    $db = new DBConnector();
    $pdo = $db->connect();
    $stmt = $pdo->prepare('UPDATE cabin_type_pricing SET interior_price = ?, ocean_view_price = ?, balcony_price = ?, suit_price = ? WHERE id = ?');
    $stmt->execute([$interior_price, $ocean_view_price, $balcony_price, $suit_price, $id]);
    $response['success'] = true;
    $response['message'] = 'Pricing updated.';
} catch (Exception $e) {
    $response['message'] = 'Error updating pricing: ' . $e->getMessage();
}

echo json_encode($response);
