<?php
// backend/requestDataDownload.php
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS data_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        status ENUM('pending', 'processing', 'completed') DEFAULT 'pending',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL
    )");

    // Check for existing pending request
    $check = $pdo->prepare("SELECT id FROM data_requests WHERE user_id = ? AND status = 'pending'");
    $check->execute([$user_id]);
    
    if ($check->rowCount() > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'You already have a pending data request.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO data_requests (user_id) VALUES (?)");
    $stmt->execute([$user_id]);
    
    // Send email notification (mock)
    // mail($user_email, "Data Request Received", "Your data download is being prepared.");

    echo json_encode([
        'success' => true, 
        'message' => 'Data request submitted. We will email you when it is ready.'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
