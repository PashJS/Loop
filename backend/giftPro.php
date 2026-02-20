<?php
// backend/giftPro.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$sender_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$receiver_username = $data['username'] ?? '';

if (empty($receiver_username)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a username']);
    exit;
}

try {
    // 1. Check if sender can gift
    $stmt = $pdo->prepare("SELECT is_pro, is_gifted_pro, pro_gifts_count FROM users WHERE id = ?");
    $stmt->execute([$sender_id]);
    $sender = $stmt->fetch();

    if (!$sender || $sender['is_pro'] == 0) {
        echo json_encode(['success' => false, 'message' => 'Only Pro members can gift']);
        exit;
    }

    if ($sender['is_gifted_pro'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Gifted Pro members cannot gift others']);
        exit;
    }

    if ($sender['pro_gifts_count'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'You have no gifts remaining this month']);
        exit;
    }

    // 2. Find receiver
    $stmt = $pdo->prepare("SELECT id, is_pro FROM users WHERE username = ?");
    $stmt->execute([$receiver_username]);
    $receiver = $stmt->fetch();

    if (!$receiver) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    if ($receiver['id'] == $sender_id) {
        echo json_encode(['success' => false, 'message' => 'You cannot gift yourself']);
        exit;
    }

    if ($receiver['is_pro'] == 1) {
        echo json_encode(['success' => false, 'message' => 'User is already a Pro member']);
        exit;
    }

    // 3. Check for existing pending gift
    $stmt = $pdo->prepare("SELECT id FROM pro_gifts WHERE receiver_id = ? AND status = 'pending'");
    $stmt->execute([$receiver['id']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'User already has a pending gift']);
        exit;
    }

    // 4. Send gift
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO pro_gifts (sender_id, receiver_id) VALUES (?, ?)");
    $stmt->execute([$sender_id, $receiver['id']]);

    $stmt = $pdo->prepare("UPDATE users SET pro_gifts_count = pro_gifts_count - 1 WHERE id = ?");
    $stmt->execute([$sender_id]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Gift sent successfully!']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
