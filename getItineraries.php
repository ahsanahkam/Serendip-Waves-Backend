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

// Check if country_image column exists
$checkColumn = $conn->query("SHOW COLUMNS FROM itineraries LIKE 'country_image'");
$hasCountryImage = $checkColumn->num_rows > 0;

if ($hasCountryImage) {
    $sql = "SELECT id, ship_name, route, departure_port, start_date, end_date, notes, country_image FROM itineraries ORDER BY id DESC";
} else {
    $sql = "SELECT id, ship_name, route, departure_port, start_date, end_date, notes FROM itineraries ORDER BY id DESC";
}

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(["message" => "Query failed: " . $conn->error]);
    exit();
}

$itineraries = [];
while ($row = $result->fetch_assoc()) {
    // Calculate nights from start and end dates
    if (!empty($row['start_date']) && !empty($row['end_date'])) {
        $start = new DateTime($row['start_date']);
        $end = new DateTime($row['end_date']);
        $interval = $start->diff($end);
        $row['nights'] = $interval->days;
    } else {
        $row['nights'] = null;
    }
    
    // Add default values for frontend compatibility
    $row['flag'] = '';
    if (!$hasCountryImage) {
        $row['country_image'] = '';
    }
    $row['description'] = '';
    $row['price'] = '';
    
    $itineraries[] = $row;
}

echo json_encode($itineraries);

$conn->close();
?> 