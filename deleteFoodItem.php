<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");
require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';
require_once 'phpmailer/src/Exception.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = json_decode(file_get_contents("php://input"));
if (!$input || !isset($input->item_id)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid input."]);
    exit();
}

$conn = new mysqli("localhost", "root", "", "serendip");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed."]);
    exit();
}

// Get supplier email before deleting
$stmt = $conn->prepare("SELECT supplier_email, food_item_name FROM food_inventory WHERE item_id=?");
$stmt->bind_param("i", $input->item_id);
$stmt->execute();
$stmt->bind_result($supplier_email, $food_item_name);
$stmt->fetch();
$stmt->close();

$del = $conn->prepare("DELETE FROM food_inventory WHERE item_id=?");
$del->bind_param("i", $input->item_id);
$success = $del->execute();
$del->close();

if ($success) {
    sendMail([$supplier_email], "Pantry Alert: Item Deleted", "The item '$food_item_name' has been deleted from the cruise ship pantry inventory.");
    echo json_encode(["message" => "Food item deleted and alert sent."]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Failed to delete food item."]);
}
$conn->close();

function sendMail($to, $subject, $body) {
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    $mail->isSMTP();
    $mail->Host = 'smtp.example.com'; // Set your SMTP server
    $mail->SMTPAuth = true;
    $mail->Username = 'your@email.com';
    $mail->Password = 'yourpassword';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->setFrom('noreply@cruise.com', 'Cruise Pantry');
    foreach ($to as $email) $mail->addAddress($email);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->send();
}
?> 