<?php
// backend/updateVideo.php - Update video details
header('Content-Type: application/json');
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to update videos.'
    ]);
    exit;
}

// Get request payload
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

$id = (int)($input['id'] ?? 0);
$title = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');
$status = trim($input['status'] ?? 'published');

if (!$id || empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Video ID and title are required.']);
    exit;
}

try {
    // Verify ownership
    $stmt = $pdo->prepare("SELECT user_id FROM videos WHERE id = ?");
    $stmt->execute([$id]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video || $video['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this video.']);
        exit;
    }

    // Update
    $stmt = $pdo->prepare("UPDATE videos SET title = ?, description = ?, status = ? WHERE id = ?");
    $result = $stmt->execute([$title, $description, $status, $id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Video updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update video.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
