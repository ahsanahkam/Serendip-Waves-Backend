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

$stmt = $conn->prepare("
    SELECT 
        b.*, 
        i.start_date AS departure_date, 
        i.end_date AS return_date,
        fp.payment_status as facility_payment_status,
        fp.total_cost as facility_total_cost,
        fp.created_at as facility_booking_date
    FROM booking_overview b 
    LEFT JOIN itineraries i ON b.ship_name = i.ship_name AND b.destination = i.route 
    LEFT JOIN facility_preferences fp ON b.booking_id = fp.booking_id
    WHERE b.email = ?
    ORDER BY b.booking_date DESC
");
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