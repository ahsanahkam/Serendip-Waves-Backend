<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once __DIR__ . '/DbConnector.php';

$db = new DBConnector();
$pdo = $db->connect();

try {
    $sql = "SELECT c.*, i.start_date, i.end_date FROM cabin_management c
            LEFT JOIN itineraries i ON c.cruise_name = i.ship_name
            ORDER BY c.booking_date DESC, c.cabin_id DESC";
    $stmt = $pdo->query($sql);
    $cabins = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Fetch all passenger names for this booking_id
        $booking_id = $row['booking_id'];
        $passenger_names = [];
        $passenger_stmt = $pdo->query("SELECT passenger_name FROM passenger_management WHERE booking_id = " . intval($booking_id));
        if ($passenger_stmt) {
            while ($prow = $passenger_stmt->fetch(PDO::FETCH_ASSOC)) {
                $passenger_names[] = $prow['passenger_name'];
            }
        }
        $row['all_passenger_names'] = implode(', ', $passenger_names);
        $cabins[] = $row;
    }
    echo json_encode(['success' => true, 'cabins' => $cabins]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching cabins', 'error' => $e->getMessage()]);
}
