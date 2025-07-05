<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = json_decode(file_get_contents("php://input"));

if (!$input || !isset($input->id)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid JSON or missing id"]);
    exit();
}

$id = $input->id;

$conn = new mysqli("localhost", "root", "", "serendip");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "DB connection failed: " . $conn->connect_error]);
    exit();
}

$stmt = $conn->prepare("DELETE FROM itineraries WHERE id=?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(["message" => "Itinerary deleted successfully."]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Delete failed: " . $stmt->error]);
}

$stmt->close();
$conn->close(); 