<?php

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once './Main Classes/Customer.php';

// Get input
$data = json_decode(file_get_contents("php://input"));

// Check if this is a booking request
if (isset($data->action)) {
    $userLogin = new Customer();
    
    if ($data->action === 'create_booking') {
        // Create booking
        if (!isset($data->user_id) || !isset($data->full_name) || !isset($data->email) || !isset($data->cruise_title) || !isset($data->cabin_type)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required booking fields']);
            exit();
        }
        
        $result = $userLogin->createBooking(
            $data->user_id,
            $data->full_name,
            $data->email,
            $data->cruise_title,
            $data->cabin_type,
            $data->adults ?? 1,
            $data->children ?? 0,
            $data->booking_date,
            $data->departure_date,
            $data->return_date,
            $data->total_price,
            $data->special_requests ?? null
        );
        
        if ($result['success']) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Booking created successfully!',
                'booking_id' => $result['booking_id'],
                'cabin_number' => $result['cabin_number']
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $result['error']]);
        }
        
    } elseif ($data->action === 'get_bookings') {
        // Get user bookings
        if (!isset($data->user_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'User ID required']);
            exit();
        }
        
        $bookings = $userLogin->getUserBookings($data->user_id);
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'bookings' => $bookings,
            'count' => count($bookings)
        ]);
        
    } elseif ($data->action === 'get_user') {
        // Get user info by ID
        if (!isset($data->user_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'User ID required']);
            exit();
        }
        $user = $userLogin->getUserById($data->user_id);
        if ($user) {
            http_response_code(200);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} else {
    // Regular login request
    // Only allow POST method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed']);
        exit();
    }

    // Validate input
    if (!isset($data->email) || !isset($data->password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        exit();
    }

    // Sanitize input
    $email = filter_var($data->email, FILTER_SANITIZE_EMAIL);
    $password = trim($data->password);

    // Instantiate and call login
    $userLogin = new Customer();
    $signinResult = $userLogin->login($email, $password);

    // Respond
    if ($signinResult['success']) {
        http_response_code(200);
    } else {
        http_response_code(401);
    }
    echo json_encode($signinResult);
}

?>

