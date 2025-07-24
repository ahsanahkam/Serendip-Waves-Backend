<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once './Main Classes/Booking.php'; // Make sure this file and class exist

$data = json_decode(file_get_contents("php://input"));

$booking = new Booking(); // Your Booking class should have the addBooking method

$result = $booking->addBooking(
    $data->full_name,
    $data->gender,
    $data->email,
    $data->citizenship,
    $data->age,
    $data->room_type,
    $data->cabin_number,
    $data->adults,
    $data->children,
    $data->number_of_guests,
    $data->total_price,
    $data->ship_name,
    $data->destination
);

echo json_encode($result);
?>
