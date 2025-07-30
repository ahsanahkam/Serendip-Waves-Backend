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

// Function to save base64 image to file
function saveBase64Image($base64String, $facilityName) {
    if (empty($base64String) || strpos($base64String, 'data:image/') !== 0) {
        return null;
    }
    
    // Extract the image data
    $data = explode(',', $base64String);
    if (count($data) !== 2) {
        return null;
    }
    
    // Get image type
    $header = $data[0];
    $imageData = base64_decode($data[1]);
    
    // Determine file extension
    $extension = 'jpg';
    if (strpos($header, 'image/png') !== false) {
        $extension = 'png';
    } elseif (strpos($header, 'image/gif') !== false) {
        $extension = 'gif';
    } elseif (strpos($header, 'image/webp') !== false) {
        $extension = 'webp';
    }
    
    // Create unique filename
    $sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $facilityName);
    $filename = $sanitizedName . '_' . time() . '.' . $extension;
    $filepath = './facility_images/' . $filename;
    
    // Save the file
    if (file_put_contents($filepath, $imageData)) {
        return 'facility_images/' . $filename;
    }
    
    return null;
}

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // Get all facilities
            $query = "SELECT facility_id, facility, about, image, unit_price, status FROM facilities ORDER BY facility";
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
            
            // Handle image upload
            $imagePath = null;
            if (!empty($input['image'])) {
                $imagePath = saveBase64Image($input['image'], $input['facility']);
            }
            
            $query = "INSERT INTO facilities (facility, about, image, unit_price, status) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute([
                $input['facility'],
                $input['about'] ?? null,
                $imagePath,
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
                
                // Handle image upload for updates
                $imagePath = $input['image'] ?? null;
                
                // If image is a base64 string, save it as file
                if (!empty($imagePath) && strpos($imagePath, 'data:image/') === 0) {
                    $newImagePath = saveBase64Image($imagePath, $input['facility']);
                    if ($newImagePath) {
                        // Delete old image file if it exists
                        $oldQuery = "SELECT image FROM facilities WHERE facility_id = ?";
                        $oldStmt = $pdo->prepare($oldQuery);
                        $oldStmt->execute([$input['facility_id']]);
                        $oldResult = $oldStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($oldResult && $oldResult['image'] && file_exists('./' . $oldResult['image'])) {
                            unlink('./' . $oldResult['image']);
                        }
                        
                        $imagePath = $newImagePath;
                    }
                }
                
                $query = "UPDATE facilities SET facility = ?, about = ?, image = ?, unit_price = ?, status = ? WHERE facility_id = ?";
                $stmt = $pdo->prepare($query);
                $result = $stmt->execute([
                    $input['facility'],
                    $input['about'] ?? null,
                    $imagePath,
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
