<?php
require_once './DbConnector.php';

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    echo "Cabin Type Pricing Table Structure:\n";
    $stmt = $pdo->query('DESCRIBE cabin_type_pricing');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo $column['Field'] . ' - ' . $column['Type'] . "\n";
    }
    
    echo "\nSample pricing data:\n";
    $stmt = $pdo->query('SELECT * FROM cabin_type_pricing LIMIT 3');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        foreach ($rows as $row) {
            echo "Ship: " . $row['ship_name'] . ", Route: " . $row['route'] . "\n";
            echo "  Interior: $" . $row['interior_price'] . ", Ocean View: $" . $row['ocean_view_price'] . "\n";
            echo "  Balcony: $" . $row['balcony_price'] . ", Suite: $" . $row['suite_price'] . "\n\n";
        }
    } else {
        echo "No pricing data found\n";
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>
