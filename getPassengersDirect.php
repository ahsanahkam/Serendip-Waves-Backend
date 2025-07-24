<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/DbConnector.php';
$db = new DBConnector();
$conn = $db->connect();

try {
    $stmt = $conn->query("SELECT * FROM passenger_management ORDER BY booking_id DESC");
    $passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "passengers" => $passengers]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
