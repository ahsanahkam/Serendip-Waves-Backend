<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$detail_id = $_POST['detail_id'] ?? '';
$itinerary_id = $_POST['itinerary_id'] ?? '';
$description = $_POST['description'] ?? '';
$schedule = $_POST['schedule'] ?? '';

if (empty($detail_id)) {
    http_response_code(400);
    echo json_encode(["message" => "detail_id is required."]);
    exit();
}

$conn = new mysqli("localhost", "root", "", "serendip");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "DB connection failed: " . $conn->connect_error]);
    exit();
}

// Fetch current images
$current = $conn->query("SELECT image1, image2, image3, image4, image5 FROM itinerary_details WHERE detail_id=" . intval($detail_id));
if (!$current || $current->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["message" => "Itinerary detail not found."]);
    exit();
}
$currentImages = $current->fetch_assoc();

// Handle up to 5 image uploads (replace if new file provided)
$image_paths = [];
for ($i = 1; $i <= 5; $i++) {
    $img_field = 'image' . $i;
    $image_paths[$i] = $currentImages[$img_field];
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

$stmt = $conn->prepare("UPDATE itinerary_details SET itinerary_id=?, description=?, image1=?, image2=?, image3=?, image4=?, image5=?, schedule=? WHERE detail_id=?");
$stmt->bind_param(
    "isssssssi",
    $itinerary_id,
    $description,
    $image_paths[1],
    $image_paths[2],
    $image_paths[3],
    $image_paths[4],
    $image_paths[5],
    $schedule,
    $detail_id
);

if ($stmt->execute()) {
    echo json_encode(["message" => "Itinerary detail updated successfully."]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Update failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?> 