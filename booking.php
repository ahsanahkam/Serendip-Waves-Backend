<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Allow from frontend origin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$database = "serendip";  // change this

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);

// Required fields
$requiredFields = [
    'full_name', 'gender', 'email', 'citizenship', 'age',
    'room_type', 'number_of_guests',
    'card_type', 'card_number'
];

// Check required fields
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit();
    }
}

// Optional fields
$adults = isset($data['adults']) ? (int)$data['adults'] : 0;
$children = isset($data['children']) ? (int)$data['children'] : 0;
$ship_name = $data['ship_name'] ?? null;
$destination = $data['destination'] ?? null;

// --- Cabin allocation logic ---
if (!$ship_name || !$data['room_type']) {
    echo json_encode(['success' => false, 'message' => 'Missing ship_name or room_type']);
    exit();
}

// 1. Get ship capacity
$stmt = $conn->prepare("SELECT passenger_count FROM ship_details WHERE ship_name = ?");
$stmt->bind_param("s", $ship_name);
$stmt->execute();
$stmt->bind_result($passenger_count);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Ship not found']);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

// 2. Calculate max cabins per type
$room_percentages = [
    'Interior' => 0.5,
    'Ocean View' => 0.25,
    'Balcony' => 0.15,
    'Suit' => 0.10
];
$room_type = $data['room_type'];
if (!isset($room_percentages[$room_type])) {
    echo json_encode(['success' => false, 'message' => 'Invalid room type']);
    $conn->close();
    exit();
}
$max_cabins = floor($passenger_count * $room_percentages[$room_type]);

// 3. Count current bookings for this ship and room type
$stmt = $conn->prepare("SELECT COUNT(*) FROM booking_overview WHERE ship_name = ? AND room_type = ?");
$stmt->bind_param("ss", $ship_name, $room_type);
$stmt->execute();
$stmt->bind_result($current_bookings);
$stmt->fetch();
$stmt->close();

if ($current_bookings >= $max_cabins) {
    echo json_encode(['success' => false, 'message' => 'This type of cabin is full.']);
    $conn->close();
    exit();
}

// 4. Assign a cabin number (simple: next available)
$cabin_number = $room_type[0] . str_pad($current_bookings + 1, 3, "0", STR_PAD_LEFT);

$total_price = isset($data['total_price']) ? (float)$data['total_price'] : null;

// Enable mysqli error reporting for development
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ... existing code ...

// 5. Insert query
try {
    $stmt = $conn->prepare("INSERT INTO booking_overview (
        full_name, gender, email, citizenship, age, room_type, cabin_number,
        adults, children, number_of_guests, card_type, card_number,
        total_price, ship_name, destination
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
} catch (mysqli_sql_exception $e) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed', 'error' => $e->getMessage()]);
    $conn->close();
    exit();
}

try {
    $stmt->bind_param(
        "ssssissiiissdss",
        $data['full_name'],
        $data['gender'],
        $data['email'],
        $data['citizenship'],
        $data['age'],
        $room_type,
        $cabin_number,
        $adults,
        $children,
        $data['number_of_guests'],
        $data['card_type'],
        $data['card_number'],
        $total_price,
        $ship_name,
        $destination
    );
} catch (mysqli_sql_exception $e) {
    echo json_encode(['success' => false, 'message' => 'Bind failed', 'error' => $e->getMessage()]);
    $stmt->close();
    $conn->close();
    exit();
}

require_once __DIR__ . '/CabinManager.php';

try {
    if ($stmt->execute()) {
        $booking_id = $stmt->insert_id;
        $booking_date = date('Y-m-d');
        // Insert into cabin_management
        $cabinManager = new CabinManager($conn);
        $cabinResult = $cabinManager->addCabin(
            $booking_id,
            $data['full_name'], // passenger_name
            $ship_name,         // cruise_name
            $room_type,         // cabin_type
            $cabin_number,      // cabin_number
            $data['number_of_guests'], // guests_count
            $booking_date,      // booking_date
            $total_price        // total_cost
        );
        if (!$cabinResult['success']) {
            file_put_contents(__DIR__ . '/cabin_debug.log', "Cabin insert failed: " . $cabinResult['error'] . PHP_EOL, FILE_APPEND);
        }
        echo json_encode(['success' => true, 'message' => 'Booking added successfully!', 'booking_id' => $booking_id, 'cabin_number' => $cabin_number, 'cabin_inserted' => $cabinResult['success']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Booking failed', 'error' => $stmt->error]);
    }
} catch (mysqli_sql_exception $e) {
    echo json_encode(['success' => false, 'message' => 'Execute failed', 'error' => $e->getMessage()]);
    $stmt->close();
    $conn->close();
    exit();
}

$stmt->close();
$conn->close();
?>
