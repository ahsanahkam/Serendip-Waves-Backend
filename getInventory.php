<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$conn = new mysqli("localhost", "root", "", "serendip");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed."]);
    exit();
}

// Check if we should include inactive items
$includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === 'true';

if ($includeInactive) {
    // Get all items including inactive ones
    $result = $conn->query("
        SELECT *, 
               COALESCE(purchase_date, expiry_date) as purchase_date,
               COALESCE(item_status, 'active') as item_status
        FROM food_inventory 
        ORDER BY item_status DESC, food_item_name ASC
    ");
} else {
    // Get only active items (default behavior)
    $result = $conn->query("
        SELECT *, 
               COALESCE(purchase_date, expiry_date) as purchase_date,
               COALESCE(item_status, 'active') as item_status
        FROM food_inventory 
        WHERE COALESCE(item_status, 'active') = 'active' 
        ORDER BY food_item_name ASC
    ");
}

$items = [];
while ($row = $result->fetch_assoc()) {
    // Keep the original status field for stock status and add item_status for active/inactive
    $items[] = $row;
}
echo json_encode($items);
$conn->close();
?> 