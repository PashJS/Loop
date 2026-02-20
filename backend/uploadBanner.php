<?php
// backend/uploadBanner.php - Upload profile banner/background
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in.'
    ]);
    exit;
}

if (!isset($_FILES['banner']) || $_FILES['banner']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'No file uploaded or upload error.'
    ]);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/../uploads/banners/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$fileType = $_FILES['banner']['type'];
if (!in_array($fileType, $allowedTypes)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid image format. Allowed: JPEG, PNG, GIF, WEBP'
    ]);
    exit;
}

// Validate file size (max 10MB for banners)
$maxSize = 10 * 1024 * 1024; // 10MB
if ($_FILES['banner']['size'] > $maxSize) {
    echo json_encode([
        'success' => false,
        'message' => 'Banner file is too large. Maximum size is 10MB.'
    ]);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    // Get old banner path if exists
    $stmt = $pdo->prepare("SELECT banner_url FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $oldBannerUrl = $user['banner_url'] ?? null;
    $oldBannerPath = null;
    
    // Extract old file path if it exists
    if ($oldBannerUrl && strpos($oldBannerUrl, '/uploads/banners/') !== false) {
        $oldBannerPath = __DIR__ . '/..' . $oldBannerUrl;
    }
    
    // Generate unique filename
    $extension = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
    $filename = uniqid('banner_', true) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file to banners folder
    if (!move_uploaded_file($_FILES['banner']['tmp_name'], $filepath)) {
        throw new Exception('Failed to move uploaded file.');
    }
    
    // Use filesystem path for banner URL
    $bannerUrl = '/uploads/banners/' . $filename;
    
    // Update database
    $stmt = $pdo->prepare("UPDATE users SET banner_url = ? WHERE id = ?");
    $stmt->execute([$bannerUrl, $userId]);
    
    // Delete old banner from filesystem if exists
    if ($oldBannerPath && file_exists($oldBannerPath)) {
        unlink($oldBannerPath);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile banner updated successfully!',
        'banner_url' => $bannerUrl
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to upload banner: ' . $e->getMessage()
    ]);
}
?>
