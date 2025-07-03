<?php
include_once 'Main Classes/User.php';

class Customer extends User {

    public function __construct() {
        parent::__construct();
    }

    public function login($email, $password) {
        $query = "SELECT * FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($query); // use $this->pdo
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && (password_verify($password, $user['password']) || $user['password'] === $password)) {
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'fullName' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ];
        }

        return ['success' => false, 'message' => 'Invalid credentials'];
    }
}
