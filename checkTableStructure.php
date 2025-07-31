<?php
require_once 'DbConnector.php';

try {
    $db = new DbConnector();
    $conn = $db->connect();
    
    // Check table structure
    $stmt = $conn->prepare('DESCRIBE meal_preferences');
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Table structure:\n";
    foreach ($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . "\n";
    }
    
    // Check existing data
    echo "\nExisting data:\n";
    $stmt = $conn->prepare('SELECT * FROM meal_preferences LIMIT 5');
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($data)) {
        echo "No data found in table\n";
    } else {
        foreach ($data as $row) {
            echo "ID: " . $row['id'] . ", Booking: " . $row['booking_id'] . ", Meal Type: " . $row['meal_type'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>
