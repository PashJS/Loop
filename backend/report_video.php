<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';
require_once 'mailer.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get the raw POST data
$input = json_decode(file_get_contents('php://input'), true);

$videoId = isset($input['videoId']) ? (int)$input['videoId'] : 0;
$reason = isset($input['reason']) ? trim($input['reason']) : '';
$description = isset($input['description']) ? trim($input['description']) : '';

if ($videoId === 0 || empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Get video title and uploader for better report info
$stmt = $pdo->prepare("SELECT title FROM videos WHERE id = ?");
$stmt->execute([$videoId]);
$video = $stmt->fetch();
$videoTitle = $video ? $video['title'] : "Unknown (ID: $videoId)";

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Anonymous';
$userEmail = isset($_SESSION['email']) ? $_SESSION['email'] : 'Not provided';

$subject = "FloxWatch Report: " . $reason;
$message = "
<h2>Video Report Received</h2>
<p><strong>Video:</strong> $videoTitle (ID: $videoId)</p>
<p><strong>Reason:</strong> $reason</p>
<p><strong>Additional Details:</strong> " . ($description ? htmlspecialchars($description) : "None provided") . "</p>
<hr>
<p><strong>Reported by:</strong> $username</p>
<p><strong>Reporter Email:</strong> $userEmail</p>
<p><strong>URL:</strong> http://localhost/floxwatch/frontend/videoid.php?id=$videoId</p>
";

$sent = sendFloxEmail('floxxwatch@gmail.com', $subject, $message, true);

if ($sent) {
    echo json_encode(['success' => true, 'message' => 'Report submitted successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send report email. Please try again later.']);
}
