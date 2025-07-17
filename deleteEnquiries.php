<?php
// CORS headers
if (isset($_SERVER['HTTP_ORIGIN'])) {
    if (preg_match('/^http:\/\/localhost(:[0-9]+)?$/', $_SERVER['HTTP_ORIGIN'])) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Credentials: true');
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
header('Content-Type: application/json');
$host = "localhost";
$user = "root";
$password = "";
$database = "serendip";
$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}
$id = $_POST['id'] ?? '';
if (empty($id)) {
    echo json_encode(["status" => "error", "message" => "ID is required."]);
    exit;
}
$stmt = $conn->prepare("DELETE FROM enquiries WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Enquiry deleted."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to delete enquiry."]);
}
$stmt->close();
$conn->close();
?> 