<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'DbConnector.php';

try {
    $db = new DBConnector();
    $pdo = $db->connect();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // Get all meal options
            $sql = "SELECT * FROM meal_options ORDER BY option_id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $mealOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert key_features from comma-separated to array
            foreach ($mealOptions as &$option) {
                if ($option['key_features']) {
                    $option['key_features'] = array_map('trim', explode(',', $option['key_features']));
                } else {
                    $option['key_features'] = [];
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $mealOptions
            ]);
            break;
            
        case 'POST':
            // Handle both JSON and FormData input (like cruise ships)
            $input = [];
            
            if ($_SERVER['CONTENT_TYPE'] && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
                // FormData from frontend with file upload
                $input = $_POST;
                
                // Handle image upload if present
                $imagePath = '';
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = 'meal_images/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($fileExtension, $allowedExtensions)) {
                        $filename = uniqid() . '.' . $fileExtension;
                        $filepath = $uploadDir . $filename;
                        
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
                            $imagePath = $filename; // Store just the filename, not the full path
                        }
                    }
                }
                
                // Use uploaded image or existing image
                if (!empty($imagePath)) {
                    $input['image'] = $imagePath;
                } else if (isset($input['existing_image'])) {
                    $input['image'] = $input['existing_image'];
                } else {
                    $input['image'] = '';
                }
            } else {
                // JSON input (backward compatibility)
                $input = json_decode(file_get_contents('php://input'), true);
            }
            
            if (!isset($input['action'])) {
                throw new Exception('Action not specified');
            }
            
            switch ($input['action']) {
                case 'create':
                    $sql = "INSERT INTO meal_options (title, type, description, key_features, image, status) VALUES (?, ?, ?, ?, ?, ?)";
                    $keyFeatures = is_array($input['key_features']) ? implode(',', $input['key_features']) : $input['key_features'];
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $input['title'],
                        $input['type'],
                        $input['description'],
                        $keyFeatures,
                        $input['image'] ?? '',
                        $input['status'] ?? 'active'
                    ]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Meal option created successfully',
                        'id' => $pdo->lastInsertId()
                    ]);
                    break;
                    
                case 'update':
                    if (!isset($input['option_id'])) {
                        throw new Exception('Option ID is required for update');
                    }
                    
                    $sql = "UPDATE meal_options SET title = ?, type = ?, description = ?, key_features = ?, image = ?, status = ? WHERE option_id = ?";
                    $keyFeatures = is_array($input['key_features']) ? implode(',', $input['key_features']) : $input['key_features'];
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $input['title'],
                        $input['type'],
                        $input['description'],
                        $keyFeatures,
                        $input['image'] ?? '',
                        $input['status'] ?? 'active',
                        $input['option_id']
                    ]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Meal option updated successfully'
                    ]);
                    break;
                    
                case 'delete':
                    if (!isset($input['option_id'])) {
                        throw new Exception('Option ID is required for delete');
                    }
                    
                    $sql = "DELETE FROM meal_options WHERE option_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$input['option_id']]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Meal option deleted successfully'
                    ]);
                    break;
                    
                case 'toggle_status':
                    if (!isset($input['option_id'])) {
                        throw new Exception('Option ID is required for status toggle');
                    }
                    
                    $sql = "UPDATE meal_options SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE option_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$input['option_id']]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Meal option status updated successfully'
                    ]);
                    break;
                    
                default:
                    throw new Exception('Invalid action specified');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
