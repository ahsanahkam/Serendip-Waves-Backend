<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents("php://input"));

if (!$input) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid input."]);
    exit();
}

// Extract data
$food_item_name   = $input->food_item_name ?? '';
$category         = $input->category ?? '';
$quantity_in_stock = $input->quantity_in_stock ?? 0;
$unit             = $input->unit ?? 'kg'; // Add unit field with default
$unit_price       = $input->unit_price ?? 0.00;
$expiry_date      = $input->expiry_date ?? '';
$purchase_date    = $input->purchase_date ?? null;
$supplier_name    = $input->supplier_name ?? '';
$supplier_contact = $input->supplier_contact ?? '';
$supplier_email   = $input->supplier_email ?? '';
$status           = $input->status ?? 'In Stock';

// Validate
if (
    empty($food_item_name) || empty($category) || empty($expiry_date) ||
    $quantity_in_stock <= 0 || $unit_price <= 0
) {
    http_response_code(400);
    echo json_encode(["message" => "Required fields are missing or invalid."]);
    exit();
}

// Set default purchase date if not provided
if (empty($purchase_date)) {
    $purchase_date = $expiry_date; // Use expiry date as default
}

// DB connection
$conn = new mysqli("localhost", "root", "", "serendip");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed."]);
    exit();
}

// Insert query - include unit and item_status with default 'active'
$stmt = $conn->prepare("
    INSERT INTO food_inventory 
    (food_item_name, category, quantity_in_stock, unit, unit_price, expiry_date, purchase_date, supplier_name, supplier_contact, supplier_email, status, item_status, status_updated_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 'System')
");

$stmt->bind_param(
    "ssissssssss",
    $food_item_name,
    $category,
    $quantity_in_stock,
    $unit,
    $unit_price,
    $expiry_date,
    $purchase_date,
    $supplier_name,
    $supplier_contact,
    $supplier_email,
    $status
);

if ($stmt->execute()) {
    echo json_encode(["message" => "Food item added successfully."]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Failed to add food item."]);
}

$stmt->close();
$conn->close();
?>
