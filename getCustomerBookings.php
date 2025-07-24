<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$conn = new mysqli("localhost", "root", "", "serendip");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? null;

if (!$email) {
    echo json_encode(["success" => false, "message" => "Missing email"]);
    exit();
}

$stmt = $conn->prepare("SELECT b.*, i.start_date AS departure_date, i.end_date AS return_date FROM booking_overview b LEFT JOIN itineraries i ON b.ship_name = i.ship_name AND (b.destination = i.route OR b.destination = i.destination) WHERE b.email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

echo json_encode(["success" => true, "bookings" => $bookings]);
$stmt->close();
$conn->close(); 