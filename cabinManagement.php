<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once './DbConnector.php';

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents("php://input"), true);
    
    switch ($method) {
        case 'GET':
            // Get all cabin assignments or specific cabin by booking_id
            if (isset($_GET['booking_id'])) {
                $booking_id = $_GET['booking_id'];
                $stmt = $pdo->prepare("SELECT * FROM cabin_management WHERE booking_id = ?");
                $stmt->execute([$booking_id]);
                $cabin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($cabin) {
                    echo json_encode(['success' => true, 'cabin' => $cabin]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Cabin not found']);
                }
            } else {
                // Get all cabins
                $stmt = $pdo->prepare("SELECT cm.*, bo.email, bo.full_name as booking_name 
                                     FROM cabin_management cm 
                                     LEFT JOIN booking_overview bo ON cm.booking_id = bo.booking_id 
                                     ORDER BY cm.booking_date DESC");
                $stmt->execute();
                $cabins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'cabins' => $cabins]);
            }
            break;
            
        case 'POST':
            // Create new cabin assignment (usually done automatically during booking)
            $required = ['booking_id', 'passenger_name', 'cruise_name', 'cabin_type', 'cabin_number', 'guests_count', 'total_cost'];
            foreach ($required as $field) {
                if (!isset($input[$field])) {
                    echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
                    exit();
                }
            }
            
            $stmt = $pdo->prepare("INSERT INTO cabin_management (
                booking_id, passenger_name, cruise_name, cabin_type,
                cabin_number, guests_count, booking_date, total_cost
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $result = $stmt->execute([
                $input['booking_id'],
                $input['passenger_name'],
                $input['cruise_name'],
                $input['cabin_type'],
                $input['cabin_number'],
                $input['guests_count'],
                $input['booking_date'] ?? date('Y-m-d'),
                $input['total_cost']
            ]);
            
            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Cabin assignment created',
                    'cabin_id' => $pdo->lastInsertId()
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create cabin assignment']);
            }
            break;
            
        case 'PUT':
            // Update cabin assignment
            if (!isset($input['cabin_id'])) {
                echo json_encode(['success' => false, 'message' => 'Missing cabin_id']);
                exit();
            }
            
            $updates = [];
            $values = [];
            $allowedFields = ['passenger_name', 'cruise_name', 'cabin_type', 'cabin_number', 'guests_count', 'total_cost', 'status'];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updates[] = "$field = ?";
                    $values[] = $input[$field];
                }
            }
            
            if (empty($updates)) {
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                exit();
            }
            
            $values[] = $input['cabin_id'];
            $sql = "UPDATE cabin_management SET " . implode(', ', $updates) . " WHERE cabin_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($values);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Cabin assignment updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update cabin assignment']);
            }
            break;
            
        case 'DELETE':
            // Delete cabin assignment and associated booking
            // Can delete by either cabin_id or booking_id
            if (!isset($input['cabin_id']) && !isset($input['booking_id'])) {
                echo json_encode(['success' => false, 'message' => 'Missing cabin_id or booking_id']);
                exit();
            }
            
            // Start transaction to ensure both deletions succeed or fail together
            $pdo->beginTransaction();
            
            try {
                $booking_id = null;
                $cabin_id = null;
                
                if (isset($input['cabin_id'])) {
                    // Get booking_id from cabin_id
                    $stmt = $pdo->prepare("SELECT booking_id FROM cabin_management WHERE cabin_id = ?");
                    $stmt->execute([$input['cabin_id']]);
                    $cabin = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$cabin) {
                        echo json_encode(['success' => false, 'message' => 'Cabin not found']);
                        $pdo->rollback();
                        exit();
                    }
                    
                    $booking_id = $cabin['booking_id'];
                    $cabin_id = $input['cabin_id'];
                } else {
                    // Use provided booking_id
                    $booking_id = $input['booking_id'];
                    
                    // Get cabin_id from booking_id
                    $stmt = $pdo->prepare("SELECT cabin_id FROM cabin_management WHERE booking_id = ?");
                    $stmt->execute([$booking_id]);
                    $cabin = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($cabin) {
                        $cabin_id = $cabin['cabin_id'];
                    }
                }
                
                // Delete from cabin_management first (if cabin exists)
                $cabinDeleted = true;
                if ($cabin_id) {
                    $stmt = $pdo->prepare("DELETE FROM cabin_management WHERE cabin_id = ?");
                    $cabinDeleted = $stmt->execute([$cabin_id]);
                }
                
                // Delete from booking_overview (parent table)
                $stmt = $pdo->prepare("DELETE FROM booking_overview WHERE booking_id = ?");
                $bookingDeleted = $stmt->execute([$booking_id]);
                
                if ($cabinDeleted && $bookingDeleted) {
                    $pdo->commit();
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Cabin assignment and booking deleted successfully',
                        'deleted_booking_id' => $booking_id,
                        'deleted_cabin_id' => $cabin_id
                    ]);
                } else {
                    $pdo->rollback();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete cabin assignment and booking']);
                }
                
            } catch (Exception $e) {
                $pdo->rollback();
                echo json_encode(['success' => false, 'message' => 'Delete error: ' . $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
