<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once './DbConnector.php';

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // Get all facilities
            $query = "SELECT facility_id, facility, unit_price, status FROM facilities ORDER BY facility";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'facilities' => $facilities,
                'total' => count($facilities)
            ]);
            break;
            
        case 'POST':
            // Add new facility
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['facility']) || !isset($input['unit_price'])) {
                echo json_encode(['success' => false, 'message' => 'Facility name and unit price are required']);
                exit();
            }
            
            $query = "INSERT INTO facilities (facility, unit_price, status) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute([
                $input['facility'],
                $input['unit_price'],
                $input['status'] ?? 'active'
            ]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Facility added successfully',
                    'facility_id' => $pdo->lastInsertId()
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add facility']);
            }
            break;
            
        case 'PUT':
            // Update facility
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['facility_id'])) {
                echo json_encode(['success' => false, 'message' => 'Facility ID is required']);
                exit();
            }
            
            // Check if this is a status-only update (for reactivation)
            if (isset($input['status']) && count($input) == 2) {
                // Status-only update
                $query = "UPDATE facilities SET status = ? WHERE facility_id = ?";
                $stmt = $pdo->prepare($query);
                $result = $stmt->execute([
                    $input['status'],
                    $input['facility_id']
                ]);
            } else {
                // Full update
                if (!isset($input['facility']) || !isset($input['unit_price'])) {
                    echo json_encode(['success' => false, 'message' => 'Facility name and unit price are required for full update']);
                    exit();
                }
                
                $query = "UPDATE facilities SET facility = ?, unit_price = ?, status = ? WHERE facility_id = ?";
                $stmt = $pdo->prepare($query);
                $result = $stmt->execute([
                    $input['facility'],
                    $input['unit_price'],
                    $input['status'],
                    $input['facility_id']
                ]);
            }
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Facility updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update facility']);
            }
            break;
            
        case 'DELETE':
            // Deactivate facility (soft delete)
            $facility_id = $_GET['facility_id'] ?? null;
            
            if (!$facility_id) {
                echo json_encode(['success' => false, 'message' => 'Facility ID is required']);
                exit();
            }
            
            $query = "UPDATE facilities SET status = 'inactive' WHERE facility_id = ?";
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute([$facility_id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Facility deactivated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to deactivate facility']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
