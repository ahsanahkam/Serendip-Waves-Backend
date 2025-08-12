<?php
header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

require_once __DIR__ . '/DbConnector.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    // Ensure logged-in admin/super_admin
    $currentUser = $_SESSION['user'] ?? null;
    $sessionRole = strtolower($currentUser['role'] ?? '');
    if (!$currentUser || $sessionRole !== 'super_admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid user id']);
        exit;
    }

    // Prevent self-delete
    if ((int)$currentUser['id'] === $userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
        exit;
    }

    $db = (new DBConnector())->connect();

    // Ensure user exists and is not a passenger
    $check = $db->prepare("SELECT role FROM users WHERE id = ?");
    $check->execute([$userId]);
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    if ($row['role'] === 'passenger') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot delete passenger records']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);

    echo json_encode(['success' => true, 'message' => 'User deleted']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
}
