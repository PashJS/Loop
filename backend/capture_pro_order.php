<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$orderID = $data['orderID'] ?? '';

if (empty($orderID)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Order ID']);
    exit;
}

try {
    // In a real app, you would verify the order with PayPal API here
    // But for this sandbox demo, we assume the frontend capture was successful
    
    $user_id = $_SESSION['user_id'];
    
    $pdo->beginTransaction();
    
    // Update user to Pro status
    // IMPORTANT: Clear any old gift/expiration data so the subscription isn't auto-revoked
    $stmt = $pdo->prepare("UPDATE users SET is_pro = 1, is_gifted_pro = 0, pro_expires_at = NULL WHERE id = ?");
    $stmt->execute([$user_id]);
    
    $stmt = $pdo->prepare("UPDATE users SET xpoints = xpoints + 5000 WHERE id = ?");
    $stmt->execute([$user_id]);
    
    // Log the transaction
    $stmt = $pdo->prepare("INSERT INTO pro_transactions (user_id, paypal_order_id, amount, status) VALUES (?, ?, 9.99, 'completed')");
    $stmt->execute([$user_id, $orderID]);
    
    $pdo->commit();
    
    echo json_encode(['status' => 'success', 'message' => 'Pro membership activated!']);
    error_log("SUCCESS: User " . $user_id . " activated Pro status.");

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
