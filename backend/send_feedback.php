<?php
session_start();
require_once 'config.php';
require_once 'mailer.php';

header('Content-Type: application/json');

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$type = trim($input['type'] ?? 'Feedback'); // 'Feedback' or 'Help Question'

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}

$userInfo = "Guest User";
$userId = "N/A";
$userEmail = "N/A";

// Fetch user details if logged in
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userInfo = $user['username'];
        $userId = $_SESSION['user_id'];
        $userEmail = $user['email'];
    }
} else if (isset($input['user_id'])) {
    // Fallback if session is missing but user_id is sent
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$input['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userInfo = $user['username'];
        $userId = $input['user_id'];
        $userEmail = $user['email'];
    }
}

// Prepare email content
$subject = "App $type from $userInfo";
$emailBody = "
<html>
<head>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; padding: 20px; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: #007bff; color: white; padding: 20px; text-align: center; }
        .header h2 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .meta { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #e9ecef; }
        .meta-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .meta-label { color: #6c757d; font-weight: 600; }
        .meta-value { color: #212529; }
        .message-box { background: #fff; border: 1px solid #dee2e6; border-left: 4px solid #007bff; border-radius: 4px; padding: 20px; line-height: 1.6; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #adb5bd; background: #f8f9fa; border-top: 1px solid #e9ecef; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>New $type Received</h2>
        </div>
        <div class='content'>
            <div class='meta'>
                <div class='meta-row'>
                    <span class='meta-label'>User:</span>
                    <span class='meta-value'>$userInfo</span>
                </div>
                <div class='meta-row'>
                    <span class='meta-label'>User ID:</span>
                    <span class='meta-value'>$userId</span>
                </div>
                <div class='meta-row'>
                    <span class='meta-label'>Email:</span>
                    <span class='meta-value'>$userEmail</span>
                </div>
                <div class='meta-row'>
                    <span class='meta-label'>Date:</span>
                    <span class='meta-value'>" . date('Y-m-d H:i:s') . "</span>
                </div>
            </div>
            
            <div class='message-box'>
                " . nl2br(htmlspecialchars($message)) . "
            </div>
        </div>
        <div class='footer'>
            Submitted via FloxWatch Mobile App
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
