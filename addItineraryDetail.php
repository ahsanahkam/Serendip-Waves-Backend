<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$itinerary_id = $_POST['itinerary_id'] ?? '';
$description = $_POST['description'] ?? '';
$schedule = $_POST['schedule'] ?? '';

if (empty($itinerary_id) || empty($description)) {
    http_response_code(400);
    echo json_encode(["message" => "itinerary_id and description are required."]);
    exit();
}

$conn = new mysqli("localhost", "root", "", "serendip");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "DB connection failed: " . $conn->connect_error]);
    exit();
}

// Handle up to 5 image uploads
$image_paths = [];
for ($i = 1; $i <= 5; $i++) {
    $img_field = 'image' . $i;
    $image_paths[$i] = null;
    if (isset($_FILES[$img_field]) && $_FILES[$img_field]['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'country_images/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_extension = strtolower(pathinfo($_FILES[$img_field]['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($file_extension, $allowed_extensions)) {
            $filename = uniqid() . '_' . $img_field . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            if (move_uploaded_file($_FILES[$img_field]['tmp_name'], $filepath)) {
                $image_paths[$i] = $filepath;
            }
        }
    }
}

$stmt = $conn->prepare("INSERT INTO itinerary_details (itinerary_id, description, image1, image2, image3, image4, image5, schedule) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param(
    "isssssss",
    $itinerary_id,
    $description,
    $image_paths[1],
    $image_paths[2],
    $image_paths[3],
    $image_paths[4],
    $image_paths[5],
    $schedule
);

if ($stmt->execute()) {
    echo json_encode(["message" => "Itinerary detail added successfully.", "detail_id" => $conn->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Add failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?> 