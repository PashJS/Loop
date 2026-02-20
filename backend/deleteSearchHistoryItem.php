<?php
require_once 'config.php';

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
$historyId = isset($data['id']) ? intval($data['id']) : 0;

if ($historyId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    // $pdo is already created in config.php
    if (!isset($pdo)) {
        throw new PDOException("Database connection not established");
    }

    $userId = $_SESSION['user_id'];

    // Delete specific history item ensuring it belongs to the user
    $stmt = $pdo->prepare("DELETE FROM search_history WHERE id = ? AND user_id = ?");
    $stmt->execute([$historyId, $userId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found or access denied']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
