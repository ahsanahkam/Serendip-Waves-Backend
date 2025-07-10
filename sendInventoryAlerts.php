<?php
require_once 'config.php';
require_once 'Mailer.php'; // assumes you have a Mailer class or use PHPMailer

date_default_timezone_set('Asia/Colombo'); // set as per your region

$today = date('Y-m-d');
$warningDate = date('Y-m-d', strtotime('+7 days'));

// 1. Low Stock Alerts
$lowStockSql = "SELECT * FROM food_inventory WHERE quantity_in_stock < 10 AND status != 'Low Stock'";
$lowStockResult = $conn->query($lowStockSql);

while ($row = $lowStockResult->fetch_assoc()) {
    $subject = "⚠️ Low Stock Alert: " . $row['food_item_name'];
    $message = "Dear " . $row['supplier_name'] . ",<br><br>"
             . "The following item is low on stock:<br>"
             . "<strong>Item:</strong> " . $row['food_item_name'] . "<br>"
             . "<strong>Current Quantity:</strong> " . $row['quantity_in_stock'] . " " . $row['unit'] . "<br><br>"
             . "Please restock soon to avoid shortages.<br><br>Regards,<br>Pantry Management System";

    sendEmail($row['supplier_email'], $subject, $message);

    // Optional: Update status
    $conn->query("UPDATE food_inventory SET status='Low Stock' WHERE item_id=" . $row['item_id']);
}

// 2. Expiry Alerts (within 7 days)
$expirySql = "SELECT * FROM food_inventory WHERE expiry_date <= '$warningDate' AND status != 'Expired'";
$expiryResult = $conn->query($expirySql);

while ($row = $expiryResult->fetch_assoc()) {
    $subject = "⚠️ Expiry Alert: " . $row['food_item_name'];
    $message = "Dear " . $row['supplier_name'] . ",<br><br>"
             . "The following item is nearing expiry:<br>"
             . "<strong>Item:</strong> " . $row['food_item_name'] . "<br>"
             . "<strong>Expiry Date:</strong> " . $row['expiry_date'] . "<br><br>"
             . "Please take necessary action.<br><br>Regards,<br>Pantry Management System";

    sendEmail($row['supplier_email'], $subject, $message);

    // Optional: Update status if expired
    if ($row['expiry_date'] < $today) {
        $conn->query("UPDATE food_inventory SET status='Expired' WHERE item_id=" . $row['item_id']);
    }
}

// Simple mail function (can replace with PHPMailer or Mailer class)
function sendEmail($to, $subject, $htmlContent) {
    $headers = "From: pantry@serendipwaves.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    mail($to, $subject, $htmlContent, $headers);
}
?>
