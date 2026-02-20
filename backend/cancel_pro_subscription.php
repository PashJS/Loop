<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    // Simple cancellation: just set is_pro to 0. 
    // In a real app, you'd handle the PayPal subscription cancellation via API.
    $stmt = $pdo->prepare("UPDATE users SET is_pro = 0 WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
