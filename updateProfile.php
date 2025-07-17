<?php
require_once __DIR__ . '/config/cors.php';

require_once 'DbConnector.php';
$db = (new DBConnector())->connect();

function updateProfile($userId, $fullName, $email, $dateOfBirth, $gender, $phoneNumber, $passportNumber) {
    global $db;
    $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, date_of_birth = ?, gender = ?, phone_number = ?, passport_number = ? WHERE id = ?");
    return $stmt->execute([$fullName, $email, $dateOfBirth, $gender, $phoneNumber, $passportNumber, $userId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully', 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
}
?>