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

$destination = isset($_GET['destination']) ? $conn->real_escape_string($_GET['destination']) : '';

$sql = "SELECT d.detail_id, d.description, d.image1, d.image2, d.image3, d.image4, d.image5, d.schedule, d.created_at, i.route AS destination, i.start_date, i.end_date FROM itinerary_details d JOIN itineraries i ON d.itinerary_id = i.id";
if ($destination) {
    $sql .= " WHERE LOWER(i.route) = LOWER('" . $destination . "')";
}
$sql .= " ORDER BY d.detail_id DESC";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(["message" => "Query failed: " . $conn->error]);
    exit();
}

$details = [];
while ($row = $result->fetch_assoc()) {
    $details[] = $row;
}

echo json_encode($details);

$conn->close();
?> 