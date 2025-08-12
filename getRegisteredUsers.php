<?php
header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
session_start();
require_once __DIR__ . '/DbConnector.php';
try {
    $currentUser = $_SESSION['user'] ?? null;
    $sessionRole = strtolower($currentUser['role'] ?? '');
    if (!$currentUser || $sessionRole !== 'super_admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit();
    }
    $db = (new DBConnector())->connect();
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
    $role   = isset($_GET['role']) ? strtolower(trim($_GET['role'])) : '';
    $params = [];
    $where = [];

    // Exclude passengers explicitly
    $where[] = "role <> 'passenger'";

    if ($search !== '') {
        $where[] = "(full_name LIKE ? OR email LIKE ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
    }
    if ($role !== '') {
        $where[] = "role = ?";
        $params[] = $role;
    }

    $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sql = "SELECT id, full_name, email, role, phone_number, date_of_birth, gender, created_at FROM users $whereSql ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'users' => $users, 'count' => count($users)]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch users']);
}
