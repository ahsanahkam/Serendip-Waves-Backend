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
$ship_name = $input->ship_name ?? '';
$route = $input->route ?? '';
$departure_port = $input->departure_port ?? '';
$start_date = $input->start_date ?? '';
$end_date = $input->end_date ?? '';
$notes = $input->notes ?? null;

if (empty($ship_name) || empty($route) || empty($departure_port) || empty($start_date) || empty($end_date)) {
    http_response_code(400);
    echo json_encode(["message" => "Please fill all required fields."]);
    exit();
}

$conn = new mysqli("localhost", "root", "", "serendip");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "DB connection failed: " . $conn->connect_error]);
    exit();
}

$stmt = $conn->prepare("UPDATE itineraries SET ship_name=?, route=?, departure_port=?, start_date=?, end_date=?, notes=? WHERE id=?");
$stmt->bind_param("ssssssi", $ship_name, $route, $departure_port, $start_date, $end_date, $notes, $id);

if ($stmt->execute()) {
    echo json_encode(["message" => "Itinerary updated successfully."]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Update failed: " . $stmt->error]);
}

$stmt->close();
$conn->close(); 