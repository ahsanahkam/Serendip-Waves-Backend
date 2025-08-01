<?php
require_once './DbConnector.php';

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    echo "Checking latest booking data:\n";
    $stmt = $pdo->query('SELECT booking_id, full_name, total_price, ship_name, destination FROM booking_overview ORDER BY booking_id DESC LIMIT 3');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        foreach ($rows as $row) {
            echo "ID: " . $row['booking_id'] . ", Name: " . $row['full_name'] . ", Total: $" . $row['total_price'] . ", Ship: " . $row['ship_name'] . "\n";
        }
    } else {
        echo "No bookings found\n";
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>
