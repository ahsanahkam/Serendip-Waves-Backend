<?php
// replyEnquiry.php - Send reply to enquiry via PHPMailer

use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/Main Classes/Mailer.php';

$data = json_decode(file_get_contents('php://input'), true);
file_put_contents(__DIR__ . '/debug_reply.txt', print_r($data, true));

// Required fields
$required = ['to', 'subject', 'message'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit();
    }
}

$to = $data['to'];
$subject = $data['subject'];
$message = $data['message'];
$name = isset($data['name']) ? $data['name'] : '';

// Compose the email body (HTML)
$body = <<<EOT
Dear {$name},<br><br>

Thank you for contacting <b>Serendip Waves</b>!<br><br>

We have received your enquiry and here is our reply:<br><br>
<div style="border-left:4px solid #007bff;padding-left:12px;margin:10px 0 20px 0;font-style:italic;">{$message}</div>

If you have further questions, feel free to reply to this email.<br><br>
Best regards,<br>
<b>Serendip Waves Admin Team</b><br>
📞 +94771234567<br>
📧 info@serendipwaves.com<br>
🌐 www.serendipwaves.com
EOT;

$mailer = new Mailer();
$mailer->setInfo($to, $subject, $body);
$sent = $mailer->send();

if ($sent === true) {
    echo json_encode(['success' => true, 'message' => 'Reply sent successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send reply.']);
}
