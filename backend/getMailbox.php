<?php
// backend/getMailbox.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require 'config.php';

try {
    $stmt = $pdo->prepare("SELECT * FROM mailbox_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$_SESSION['user_id']]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'logs' => $logs]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
