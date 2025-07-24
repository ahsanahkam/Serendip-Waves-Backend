<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config/db.php';

$sql = "SELECT * FROM passenger_management ORDER BY booking_id DESC";
$result = $conn->query($sql);

if ($result === false) {
    echo json_encode(["success" => false, "message" => "Query failed", "error" => $conn->error]);
    exit();
}

$passengers = [];
while ($row = $result->fetch_assoc()) {
    $passengers[] = $row;
}

echo json_encode(["success" => true, "passengers" => $passengers]);
$conn->close();
