<?php
// Allow cross-origin requests (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Handle FormData instead of JSON
$ship_name = $_POST['ship_name'] ?? '';
$passenger_count = $_POST['passenger_count'] ?? 0;
$pool_count = $_POST['pool_count'] ?? 0;
$deck_count = $_POST['deck_count'] ?? 0;
$restaurant_count = $_POST['restaurant_count'] ?? 0;
$about_ship = $_POST['about_ship'] ?? '';
$class = $_POST['class'] ?? '';
$year_built = $_POST['year_built'] ?? null;
$ship_image_path = '';

// Handle image upload
if (isset($_FILES['ship_image']) && $_FILES['ship_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'ship_images/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $file_extension = strtolower(pathinfo($_FILES['ship_image']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($file_extension, $allowed_extensions)) {
        $filename = uniqid() . '.' . $file_extension;
        $filepath = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['ship_image']['tmp_name'], $filepath)) {
            $ship_image_path = $filepath;
        }
    }
}

// Basic validation
if (empty($ship_name)) {
    http_response_code(400);
    echo json_encode(["message" => "Ship name is required"]);
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "serendip");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// Prepare insert query
$stmt = $conn->prepare("
    INSERT INTO ship_details (
        ship_name, passenger_count, pool_count, deck_count, restaurant_count, about_ship, ship_image, class, year_built
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "siiiisssi",
    $ship_name,
    $passenger_count,
    $pool_count,
    $deck_count,
    $restaurant_count,
    $about_ship,
    $ship_image_path,
    $class,
    $year_built
);

// Execute and handle result
if ($stmt->execute()) {
    echo json_encode(["message" => "Ship details added successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Insert failed: " . $stmt->error]);
}

// Cleanup
$stmt->close();
$conn->close();
?>
