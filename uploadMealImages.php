<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'DbConnector.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

    $db = new DBConnector();
    $pdo = $db->connect();

    // Check if files were uploaded
    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        throw new Exception('No images uploaded');
    }

    $uploadDir = 'meal_images/';
    $uploadedImages = [];
    $errors = [];

    // Ensure upload directory exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Process each uploaded file
    $fileCount = count($_FILES['images']['name']);
    for ($i = 0; $i < $fileCount; $i++) {
        $fileName = $_FILES['images']['name'][$i];
        $fileTmpName = $_FILES['images']['tmp_name'][$i];
        $fileSize = $_FILES['images']['size'][$i];
        $fileError = $_FILES['images']['error'][$i];
        $fileType = $_FILES['images']['type'][$i];

        // Skip empty files
        if (empty($fileName)) {
            continue;
        }

        // Check for upload errors
        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading $fileName: Upload error code $fileError";
            continue;
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $actualMimeType = finfo_file($finfo, $fileTmpName);
        finfo_close($finfo);

        if (!in_array($actualMimeType, $allowedTypes)) {
            $errors[] = "Invalid file type for $fileName. Only JPEG, PNG, GIF, and WebP images are allowed.";
            continue;
        }

        // Validate file size (max 5MB)
        if ($fileSize > 5 * 1024 * 1024) {
            $errors[] = "File $fileName is too large. Maximum size is 5MB.";
            continue;
        }

        // Generate unique filename
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $uniqueFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', pathinfo($fileName, PATHINFO_FILENAME)) . '.' . $fileExtension;
        $uploadPath = $uploadDir . $uniqueFileName;

        // Move uploaded file
        if (move_uploaded_file($fileTmpName, $uploadPath)) {
            $uploadedImages[] = [
                'original_name' => $fileName,
                'stored_name' => $uniqueFileName,
                'path' => $uploadPath,
                'size' => $fileSize,
                'type' => $actualMimeType
            ];
        } else {
            $errors[] = "Failed to move uploaded file $fileName";
        }
    }

    // Prepare response
    $response = [
        'success' => true,
        'uploaded_images' => $uploadedImages,
        'upload_count' => count($uploadedImages)
    ];

    if (!empty($errors)) {
        $response['errors'] = $errors;
    }

    // If no images were successfully uploaded
    if (empty($uploadedImages)) {
        $response['success'] = false;
        $response['message'] = 'No images were successfully uploaded';
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
