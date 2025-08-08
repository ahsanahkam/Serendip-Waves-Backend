<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';
require_once 'phpmailer/src/Exception.php';

$input = json_decode(file_get_contents("php://input"));
if (!$input || !isset($input->item_id)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid input."]);
    exit();
}

// Extract purchase_date with fallback
$purchase_date = $input->purchase_date ?? $input->expiry_date ?? null;

$conn = new mysqli("localhost", "root", "", "serendip");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed."]);
    exit();
}

// If action is delete, mark as inactive instead of deleting
if (isset($input->action) && $input->action === 'delete') {
    // Get supplier email and item details before marking inactive
    $stmt = $conn->prepare("SELECT supplier_email, food_item_name, item_status FROM food_inventory WHERE item_id=?");
    $stmt->bind_param("i", $input->item_id);
    $stmt->execute();
    $stmt->bind_result($supplier_email, $food_item_name, $current_status);
    $stmt->fetch();
    $stmt->close();

    // Update item_status to 'inactive' instead of deleting
    $del = $conn->prepare("UPDATE food_inventory SET item_status='inactive', status_updated_at=NOW(), status_updated_by='System' WHERE item_id=?");
    $del->bind_param("i", $input->item_id);
    $success = $del->execute();
    $del->close();

    if ($success) {
        sendMail([$supplier_email], "Pantry Alert: Item Deactivated", "The item '$food_item_name' has been deactivated in the cruise ship pantry inventory.");
        echo json_encode(["message" => "Food item deactivated and alert sent."]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Failed to deactivate food item."]);
    }
    $conn->close();
    exit();
}

// If action is activate, mark as active
if (isset($input->action) && $input->action === 'activate') {
    // Get supplier email and item details
    $stmt = $conn->prepare("SELECT supplier_email, food_item_name FROM food_inventory WHERE item_id=?");
    $stmt->bind_param("i", $input->item_id);
    $stmt->execute();
    $stmt->bind_result($supplier_email, $food_item_name);
    $stmt->fetch();
    $stmt->close();

    // Update item_status to 'active'
    $activate = $conn->prepare("UPDATE food_inventory SET item_status='active', status_updated_at=NOW(), status_updated_by='System' WHERE item_id=?");
    $activate->bind_param("i", $input->item_id);
    $success = $activate->execute();
    $activate->close();

    if ($success) {
        sendMail([$supplier_email], "Pantry Alert: Item Reactivated", "The item '$food_item_name' has been reactivated in the cruise ship pantry inventory.");
        echo json_encode(["message" => "Food item reactivated and alert sent."]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Failed to reactivate food item."]);
    }
    $conn->close();
    exit();
}

// Handle toggle_status action
if (isset($input->action) && $input->action === 'toggle_status') {
    // Get current status and item details
    $stmt = $conn->prepare("SELECT supplier_email, food_item_name, item_status FROM food_inventory WHERE item_id=?");
    $stmt->bind_param("i", $input->item_id);
    $stmt->execute();
    $stmt->bind_result($supplier_email, $food_item_name, $current_status);
    $stmt->fetch();
    $stmt->close();

    // Update to the new status
    $new_status = $input->new_status;
    $update = $conn->prepare("UPDATE food_inventory SET item_status=?, status_updated_at=NOW(), status_updated_by='System' WHERE item_id=?");
    $update->bind_param("si", $new_status, $input->item_id);
    $success = $update->execute();
    $update->close();

    if ($success) {
        $action_text = ($new_status === 'active') ? 'reactivated' : 'deactivated';
        sendMail([$supplier_email], "Pantry Alert: Item " . ucfirst($action_text), "The item '$food_item_name' has been $action_text in the cruise ship pantry inventory.");
        echo json_encode(["message" => "Food item $action_text and alert sent."]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Failed to update food item status."]);
    }
    $conn->close();
    exit();
}

$stmt = $conn->prepare("
    UPDATE food_inventory
    SET food_item_name=?, category=?, quantity_in_stock=?, unit=?, unit_price=?, expiry_date=?, purchase_date=?, supplier_name=?, supplier_contact=?, supplier_email=?, status=?
    WHERE item_id=?
");
$stmt->bind_param(
    "ssissssssssi",
    $input->food_item_name,
    $input->category,
    $input->quantity_in_stock,
    $input->unit,
    $input->unit_price,
    $input->expiry_date,
    $purchase_date,
    $input->supplier_name,
    $input->supplier_contact,
    $input->supplier_email,
    $input->status,
    $input->item_id
);

if ($stmt->execute()) {
    // Send alert if status is 'Low Stock'
    if ($input->status === 'Low Stock') {
        sendMail([
            $input->supplier_email
        ], "Pantry Alert: Low Stock", "The item '{$input->food_item_name}' is low in stock ({$input->quantity_in_stock} units left).");
    }
    echo json_encode(["message" => "Food item updated successfully."]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Failed to update food item."]);
}
$stmt->close();
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