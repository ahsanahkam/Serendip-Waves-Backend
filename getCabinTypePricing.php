<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');
require_once __DIR__ . '/config/db.php';

$response = array('success' => false);

try {
    require_once __DIR__ . '/DbConnector.php';
    $db = new DBConnector();
    $pdo = $db->connect();
    // Join with itineraries and cruise tables for full info
    $stmt = $pdo->prepare('
        SELECT ctp.*, i.ship_name, i.route, s.class as ship_class, s.year_built
        FROM cabin_type_pricing ctp
        JOIN itineraries i ON ctp.ship_name = i.ship_name AND ctp.route = i.route
        JOIN ship_details s ON i.ship_name = s.ship_name
    ');
    $stmt->execute();
    $pricing = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response['success'] = true;
    $response['pricing'] = $pricing;
} catch (Exception $e) {
    $response['message'] = 'Error fetching pricing: ' . $e->getMessage();
}

echo json_encode($response);
