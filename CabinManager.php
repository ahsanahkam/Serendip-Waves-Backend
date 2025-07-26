<?php
class CabinManager {
    private $conn;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    public function addCabin(
        $booking_id,
        $passenger_name,
        $cruise_name,
        $cabin_type,
        $cabin_number,
        $guests_count,
        $booking_date,
        $total_cost
    ) {
        $stmt = $this->conn->prepare("INSERT INTO cabin_management (
            booking_id, passenger_name, cruise_name, cabin_type,
            cabin_number, guests_count, booking_date, total_cost
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "issssisd",
            $booking_id,
            $passenger_name,
            $cruise_name,
            $cabin_type,
            $cabin_number,
            $guests_count,
            $booking_date,
            $total_cost
        );

        if ($stmt->execute()) {
            return ['success' => true, 'cabin_id' => $this->conn->insert_id];
        } else {
            return ['success' => false, 'error' => $stmt->error];
        }
    }
}
?>
