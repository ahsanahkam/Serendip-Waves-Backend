<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once './config.php'; // Include your DB connection
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$email = isset($data['email']) ? filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL) : null;
$enteredOtp = isset($data['otp']) ? trim($data['otp']) : null;

if (!$email || !$enteredOtp) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Email and OTP are required."]);
    exit();
}

// Check if OTP exists and is valid
$query = "SELECT * FROM otp_table WHERE email = ? AND otp = ? AND created_at >= NOW() - INTERVAL 10 MINUTE";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $email, $enteredOtp);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    // OTP is valid, optionally delete it to prevent reuse
    $deleteQuery = "DELETE FROM otp_table WHERE email = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param("s", $email);
    $deleteStmt->execute();

    echo json_encode(["success" => true, "message" => "OTP validated. Proceed to reset password."]);
} else {
    echo json_encode(["success" => false, "message" => "Invalid or expired OTP."]);
}
