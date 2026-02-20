<?php
// backend/uploadProfilePicture.php - Upload profile picture
header('Content-Type: application/json');
session_start();
require 'config.php';

// Check for user_id in Session OR Post
$userId = 0;
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
} elseif (isset($_POST['user_id'])) {
    $userId = (int)$_POST['user_id'];
}

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in or provide user_id.'
    ]);
    exit;
}

if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'No file uploaded or upload error.'
    ]);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/../uploads/profile_pictures/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Validate file type - REMOVED to allow all extensions
// $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
// ... logic removed ...

// Validate file size (max 10MB)
$maxSize = 10 * 1024 * 1024; // 10MB
if ($_FILES['profile_picture']['size'] > $maxSize) {
    echo json_encode([
        'success' => false,
        'message' => 'Image file is too large. Maximum size is 5MB.'
    ]);
    exit;
}

try {
    // userId is already set
    
    // Get old profile picture path if exists
    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $oldPictureUrl = $user['profile_picture'] ?? null;
    $oldPicturePath = null;
    
    // Extract old file path if it exists
    if ($oldPictureUrl && strpos($oldPictureUrl, '/uploads/profile_pictures/') !== false) {
        $oldPicturePath = __DIR__ . '/..' . $oldPictureUrl;
    }
    
    // Generate unique filename
    $extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
    $filename = uniqid('profile_', true) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file to profile pictures folder
    if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filepath)) {
        throw new Exception('Failed to move uploaded file.');
    }
    
    // Use filesystem path for profile picture URL
    $profilePictureUrl = '/uploads/profile_pictures/' . $filename;
    
    // Update database
    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
    $stmt->execute([$profilePictureUrl, $userId]);
    
    // Delete old profile picture from filesystem if exists
    if ($oldPicturePath && file_exists($oldPicturePath)) {
        unlink($oldPicturePath);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile picture updated successfully!',
        'profile_picture' => $profilePictureUrl
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to upload profile picture: ' . $e->getMessage()
    ]);
}
?>
