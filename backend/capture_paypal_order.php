<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "User not logged in."]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$orderID = $input['orderID'] ?? '';
$pointsToAdd = (int)($input['points'] ?? 0);
$amount = $input['amount'] ?? 0;
$userId = $_SESSION['user_id'];

if (!$orderID || !$pointsToAdd) {
    echo json_encode(["status" => "error", "message" => "Invalid request data."]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Log the transaction
    $stmt = $pdo->prepare("INSERT INTO point_transactions (user_id, paypal_order_id, amount_paid, points_added, status) VALUES (?, ?, ?, ?, 'completed')");
    $stmt->execute([$userId, $orderID, $amount, $pointsToAdd]);

    // 2. Update user points balance
    $stmt = $pdo->prepare("UPDATE users SET xpoints = xpoints + ? WHERE id = ?");
    $stmt->execute([$pointsToAdd, $userId]);

    $pdo->commit();

    echo json_encode(["status" => "success", "message" => "Points added successfully."]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Payment Capture Error: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
