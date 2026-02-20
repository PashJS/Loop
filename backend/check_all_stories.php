<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

try {
    // Get unique user_ids who have posted stories in the last 24h
    $stmt = $pdo->query("SELECT DISTINCT user_id FROM stories WHERE created_at >= NOW() - INTERVAL 1 DAY");
    $usersWithStories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['success' => true, 'user_ids' => $usersWithStories]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
