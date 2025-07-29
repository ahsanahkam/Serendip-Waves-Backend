<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/DbConnector.php';

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    $sql = "SELECT * FROM passenger_management ORDER BY booking_id DESC";
    $stmt = $pdo->query($sql);
    
    $passengers = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $passengers[] = $row;
    }
    
    echo json_encode(["success" => true, "passengers" => $passengers]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
