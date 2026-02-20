<?php
ob_start();
require_once 'config.php';
ob_clean();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$query = isset($data['query']) ? trim($data['query']) : '';

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Query is required']);
    exit;
}

try {
    // $pdo is already created in config.php
    if (!isset($pdo)) {
        throw new PDOException("Database connection not established");
    }

    $userId = $_SESSION['user_id'];

    // Check if the query already exists for this user to avoid duplicates (optional, but good for history)
    // Or we can just insert and let the retrieval logic handle uniqueness/ordering.
    // Let's delete the old one if it exists so the new one is at the top (most recent).
    $stmt = $pdo->prepare("DELETE FROM search_history WHERE user_id = ? AND query = ?");
    $stmt->execute([$userId, $query]);

    // Insert the new search
    $stmt = $pdo->prepare("INSERT INTO search_history (user_id, query) VALUES (?, ?)");
    $stmt->execute([$userId, $query]);

    // Limit history to 10 items per user (delete oldest)
    // This is a bit complex in MySQL in one go, so we can do it separately or just let it grow and limit on select.
    // Let's limit on select for performance, but maybe cleanup occasionally.
    // For now, just insert.

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
