<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['poll_id'])) {
    echo json_encode(['success' => false, 'message' => 'Poll ID required']);
    exit;
}

$poll_id = $data['poll_id'];

try {
    $stmt = $pdo->prepare("DELETE FROM polls WHERE id = ? AND user_id = ?");
    $stmt->execute([$poll_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Poll deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Poll not found or unauthorized']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
