<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$conn = new mysqli("localhost", "root", "", "serendip");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "DB connection failed: " . $conn->connect_error]);
    exit();
}

$sql = "SELECT * FROM itineraries ORDER BY id DESC";
$result = $conn->query($sql);

$itineraries = [];
while ($row = $result->fetch_assoc()) {
    $itineraries[] = $row;
}

echo json_encode($itineraries);

$conn->close();
?> 