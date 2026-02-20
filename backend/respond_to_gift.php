<?php
// backend/respond_to_gift.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$gift_id = $data['gift_id'] ?? null;
$action = $data['action'] ?? ''; // 'accept' or 'decline'

if (!$gift_id || !in_array($action, ['accept', 'decline'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // 1. Verify gift belongs to user
    $stmt = $pdo->prepare("SELECT sender_id FROM pro_gifts WHERE id = ? AND receiver_id = ? AND status = 'pending'");
    $stmt->execute([$gift_id, $user_id]);
    $gift = $stmt->fetch();

    if (!$gift) {
        echo json_encode(['success' => false, 'message' => 'Gift not found or already processed']);
        exit;
    }

    $pdo->beginTransaction();

    if ($action === 'accept') {
        // Accept: Update user to Pro for 1 week
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET is_pro = 1, is_gifted_pro = 1, pro_expires_at = ? 
            WHERE id = ?
        ");
        $stmt->execute([$expires_at, $user_id]);

        $stmt = $pdo->prepare("UPDATE pro_gifts SET status = 'accepted', responded_at = NOW() WHERE id = ?");
        $stmt->execute([$gift_id]);
        
        // Ensure pro_settings exist for the new pro user
        $stmt = $pdo->prepare("INSERT IGNORE INTO pro_settings (user_id) VALUES (?)");
        $stmt->execute([$user_id]);

    } else {
        // Decline: Give gift back to sender
        $stmt = $pdo->prepare("UPDATE users SET pro_gifts_count = pro_gifts_count + 1 WHERE id = ?");
        $stmt->execute([$gift['sender_id']]);

        $stmt = $pdo->prepare("UPDATE pro_gifts SET status = 'declined', responded_at = NOW() WHERE id = ?");
        $stmt->execute([$gift_id]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Gift ' . ($action === 'accept' ? 'accepted' : 'declined')]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
