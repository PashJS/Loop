<?php
session_start();
require_once 'config.php';
require_once 'mailer.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}

// Fetch user details
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

// Prepare email content
$subject = "FloxSync: Private Information Change Request";
$emailBody = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; padding: 30px; }
        h2 { color: #333; margin-bottom: 20px; }
        .info { background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .info p { margin: 5px 0; color: #555; }
        .message { background: #e8f4fd; padding: 15px; border-left: 4px solid #3ea6ff; border-radius: 0 8px 8px 0; }
        .message p { color: #333; white-space: pre-wrap; }
        .footer { margin-top: 20px; font-size: 12px; color: #888; }
    </style>
</head>
<body>
    <div class='container'>
        <h2>Private Information Change Request</h2>
        <div class='info'>
            <p><strong>User ID:</strong> {$_SESSION['user_id']}</p>
            <p><strong>Username:</strong> {$user['username']}</p>
            <p><strong>Email:</strong> {$user['email']}</p>
            <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
        </div>
        <h3>Requested Changes:</h3>
        <div class='message'>
            <p>" . nl2br(htmlspecialchars($message)) . "</p>
        </div>
        <div class='footer'>
            <p>This request was submitted via FloxSync Private Information page.</p>
        </div>
    </div>
</body>
</html>
";

// Send email to support
$sent = sendFloxEmail('floxxteam@gmail.com', $subject, $emailBody, true);

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to send email']);
}
