<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Decode JSON body
$input = json_decode(file_get_contents("php://input"), true);

if (!$input || empty($input['ship_name'])) {
    http_response_code(400);
    echo json_encode(["message" => "Ship name is required"]);
    exit();
}

$ship_name = $input['ship_name'];

$conn = new mysqli("localhost", "root", "", "serendip");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

$stmt = $conn->prepare("DELETE FROM ship_details WHERE ship_name = ?");
$stmt->bind_param("s", $ship_name);

if ($stmt->execute()) {
    echo json_encode(["message" => "Ship deleted successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Delete failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?> 