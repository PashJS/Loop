<?php
ob_start();
require_once 'config.php';
ob_clean();

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

    // Get the last 10 unique search queries
    // Since we delete duplicates on insert, we can just select the top 10 by created_at desc
    $stmt = $pdo->prepare("SELECT query FROM search_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$userId]);
    $history = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['success' => true, 'history' => $history]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
