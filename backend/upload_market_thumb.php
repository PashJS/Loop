<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_FILES['thumbnail'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['thumbnail'];
$targetDir = "../uploads/market_thumbs/";

if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if (!in_array(strtolower($ext), $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type']);
    exit;
}

$filename = "thumb_" . $_SESSION['user_id'] . "_" . time() . "." . $ext;
$targetFile = $targetDir . $filename;

if (move_uploaded_file($file['tmp_name'], $targetFile)) {
    $publicUrl = "uploads/market_thumbs/" . $filename;
    echo json_encode(['success' => true, 'url' => $publicUrl]);
} else {
    echo json_encode(['success' => false, 'message' => 'Upload failed']);
}
