<?php
require_once 'config.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // $pdo is already created in config.php
    if (!isset($pdo)) {
        throw new PDOException("Database connection not established");
    }

    $userId = $_SESSION['user_id'];

    // Get all search history for the user, ordered by most recent
    $stmt = $pdo->prepare("SELECT id, query, created_at FROM search_history WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'history' => $history]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
