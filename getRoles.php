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

require_once __DIR__ . '/DbConnector.php';

function tableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return isset($row['c']) && (int)$row['c'] > 0;
}

function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return isset($row['c']) && (int)$row['c'] > 0;
}

try {
    $db = (new DBConnector())->connect();
    $roles = [];
    if (tableExists($db, 'roles')) {
        $hasName = columnExists($db, 'roles', 'name');
        $hasKey = columnExists($db, 'roles', 'role_key');
        $hasAssignable = columnExists($db, 'roles', 'is_assignable');
        $hasPrivileged = columnExists($db, 'roles', 'is_privileged');
        $hasAdmin = columnExists($db, 'roles', 'is_admin');

        $selectCols = [];
        if ($hasKey) { $selectCols[] = 'role_key'; }
        if ($hasName) { $selectCols[] = 'name'; }
        if ($hasAssignable) { $selectCols[] = 'is_assignable'; }
        if ($hasPrivileged) { $selectCols[] = 'is_privileged'; }
        if ($hasAdmin) { $selectCols[] = 'is_admin'; }
        if (!$selectCols) { $selectCols = ['*']; }

        $sql = 'SELECT ' . implode(',', $selectCols) . ' FROM roles';
        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $roleKey = $r['role_key'] ?? ($r['name'] ?? null);
            if (!$roleKey) { continue; }
            $roleKey = strtolower(trim($roleKey));
            $assignable = isset($r['is_assignable']) ? (bool)$r['is_assignable'] : true;
            $privileged = isset($r['is_privileged']) ? (bool)$r['is_privileged'] : (isset($r['is_admin']) ? (bool)$r['is_admin'] : in_array($roleKey, ['admin','super_admin'], true));
            $roles[] = [
                'role' => $roleKey,
                'label' => ucfirst(str_replace('_',' ', $roleKey)),
                'assignable' => $assignable,
                'privileged' => $privileged,
            ];
        }
    } else {
        // Fallback: distinct roles from users table
        $rows = $db->query("SELECT DISTINCT role FROM users")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $roleKey = strtolower(trim($r['role']));
            $roles[] = [
                'role' => $roleKey,
                'label' => ucfirst(str_replace('_',' ', $roleKey)),
                'assignable' => ($roleKey !== 'passenger'),
                'privileged' => in_array($roleKey, ['admin','super_admin'], true),
            ];
        }
    }

    echo json_encode(['success' => true, 'roles' => $roles]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load roles']);
}
