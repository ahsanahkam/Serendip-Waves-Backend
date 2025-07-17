<?php
// CORS headers
if (isset($_SERVER['HTTP_ORIGIN'])) {
    if (preg_match('/^http:\/\/localhost(:[0-9]+)?$/', $_SERVER['HTTP_ORIGIN'])) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Methods: GET, OPTIONS');
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
$result = $conn->query("SELECT id, name, email, message FROM enquiries ORDER BY id DESC");
$enquiries = [];
while ($row = $result->fetch_assoc()) {
    $enquiries[] = $row;
}
echo json_encode(["status" => "success", "enquiries" => $enquiries]);
$conn->close();
?> 