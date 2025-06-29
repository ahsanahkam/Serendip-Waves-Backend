<?php
require_once 'DbConnector.php';

abstract class User
{
    protected $id;
    protected $full_name;
    protected $email;
    protected $password;
    protected $phone_number;
    protected $date_of_birth;
    protected $gender;
    protected $passport_number;
    protected $pdo;
    

    public function __construct()
    {
        $db = new DBConnector();
        $this->pdo = $db->connect();
    }

    public function isAlreadyExists()
    {
        $query = "SELECT email FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(":email", $this->email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function checkEmailExists($email)
{
    try {
        $query = "SELECT email FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Failed to verify email. " . $e->getMessage()]);
        return false; // Make sure to return false on exception
    }
}


public function registerUser($full_name, $email, $password, $phone_number, $date_of_birth, $gender, $passport_number = null)
{
    $this->full_name = $full_name;
    $this->email = $email;
    $this->password = $password;
    $this->phone_number = $phone_number;
    $this->date_of_birth = $date_of_birth;
    $this->gender = $gender;
    $this->passport_number = $passport_number;

    if ($this->isAlreadyExists()) {
        // Optionally: throw error or log that user exists
        // Just returning false here means frontend will show generic message
        return false;
    }

    try {
        $sql = "INSERT INTO users (full_name, email, password, phone_number, date_of_birth, gender, passport_number) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(1, $this->full_name);
        $stmt->bindParam(2, $this->email);
        $stmt->bindParam(3, $this->password);
        $stmt->bindParam(4, $this->phone_number);
        $stmt->bindParam(5, $this->date_of_birth);
        $stmt->bindParam(6, $this->gender);
        $stmt->bindParam(7, $this->passport_number);    
        $rs = $stmt->execute();

        if ($rs) {
            return true;
        }
        return false;
    } catch (PDOException $e) {
        // Log error to PHP error log for debugging
        error_log("PDOException in registerUser: " . $e->getMessage());
        // Return false after catching exception
        return false;
    }
}



    
}
