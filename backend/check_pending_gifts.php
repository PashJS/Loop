<?php
// backend/check_pending_gifts.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT g.id, u.username as sender_name 
        FROM pro_gifts g 
        JOIN users u ON g.sender_id = u.id 
        WHERE g.receiver_id = ? AND g.status = 'pending' 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $gift = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($gift) {
        echo json_encode(['success' => true, 'gift' => $gift]);
    } else {
        echo json_encode(['success' => false]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
