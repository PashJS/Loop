<?php
// backend/getFile.php - Serve files from database
session_start();
require 'config.php';

$fileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($fileId <= 0) {
    http_response_code(400);
    die('Invalid file ID');
}

try {
    $stmt = $pdo->prepare("
        SELECT file_name, mime_type, file_data, file_size, file_type
        FROM file_storage
        WHERE id = ?
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        http_response_code(404);
        die('File not found');
    }
    
    // Set headers
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . $file['file_size']);
    header('Content-Disposition: inline; filename="' . $file['file_name'] . '"');
    header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
    
    // Output file data
    echo $file['file_data'];
    
} catch (PDOException $e) {
    http_response_code(500);
    die('Error retrieving file');
}
?>

