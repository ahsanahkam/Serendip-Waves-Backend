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
require_once __DIR__ . '/DbConnector.php';
try {
    session_start();
    $currentUser = $_SESSION['user'] ?? null;
    $sessionRole = strtolower($currentUser['role'] ?? '');
    if (!$currentUser || $sessionRole !== 'super_admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit();
    }
    $db = (new DBConnector())->connect();
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = intval($data['user_id'] ?? 0);
    $role   = strtolower(trim($data['role'] ?? ''));

    if (!$userId || $role === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid user id or role']);
        exit();
    }

    // Validate role dynamically
    $allowed = [];
    // Prefer roles table if exists
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'roles'");
    $stmt->execute();
    $hasRolesTable = ((int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0) > 0);
    if ($hasRolesTable) {
        $rows = $db->query("SELECT LOWER(COALESCE(role_key, name)) AS role FROM roles WHERE (ISNULL(is_assignable) OR is_assignable = 1)")->fetchAll(PDO::FETCH_ASSOC);
        $allowed = array_values(array_unique(array_map(fn($r) => $r['role'], $rows)));
    } else {
        $rows = $db->query("SELECT DISTINCT role FROM users")->fetchAll(PDO::FETCH_ASSOC);
        $allowed = array_values(array_unique(array_map(fn($r) => strtolower(trim($r['role'])), $rows)));
    }

    // Never allow passenger to be assigned here
    $allowed = array_values(array_filter($allowed, fn($r) => $r !== 'passenger'));

    if (!in_array($role, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Role not allowed']);
        exit();
    }

    $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
    $ok = $stmt->execute([$role, $userId]);
    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Role updated']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Failed to update role']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
