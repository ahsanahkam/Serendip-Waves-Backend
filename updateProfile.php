<?php
// Inline CORS + JSON headers (avoid missing include)
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

require_once 'DbConnector.php';
$db = (new DBConnector())->connect();

function updateProfile($userId, $fullName, $email, $dateOfBirth, $gender, $phoneNumber, $passportNumber) {
    global $db;
    $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, date_of_birth = ?, gender = ?, phone_number = ?, passport_number = ? WHERE id = ?");
    return $stmt->execute([$fullName, $email, $dateOfBirth, $gender, $phoneNumber, $passportNumber, $userId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $userId         = intval($_POST['id'] ?? 0);
        $fullName       = trim($_POST['full_name'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $dateOfBirth    = trim($_POST['date_of_birth'] ?? '');
        $gender         = trim($_POST['gender'] ?? '');
        $phoneNumber    = trim($_POST['phone_number'] ?? '');
        $passportNumber = trim($_POST['passport_number'] ?? '');

        $result = updateProfile($userId, $fullName, $email, $dateOfBirth, $gender, $phoneNumber, $passportNumber);

        if ($result) {
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && isset($user['password'])) {
                unset($user['password']);
            }
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully', 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error updating profile']);
    }
}
?>