<?php

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed']);
    exit();
}

require_once './Main Classes/Customer.php';

// Get input
$data = json_decode(file_get_contents("php://input"));

// Validate input
if (!isset($data->email) || !isset($data->password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit();
}

// Sanitize input
$email = filter_var($data->email, FILTER_SANITIZE_EMAIL);
$password = trim($data->password);

// Instantiate and call login
$userLogin = new Customer();
$signinResult = $userLogin->login($email, $password);

// Respond
if ($signinResult['success']) {
    http_response_code(200);
} else {
    http_response_code(401);
}
echo json_encode($signinResult);

