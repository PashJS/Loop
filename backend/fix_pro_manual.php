<?php
// backend/fix_pro_manual.php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    die("Not logged in");
}

$user_id = $_SESSION['user_id'];

try {
    // Force set Pro if there's a recent transaction
    $stmt = $pdo->prepare("SELECT id FROM pro_transactions WHERE user_id = ? AND status = 'completed' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $transaction = $stmt->fetch();

    if ($transaction) {
        $stmt = $pdo->prepare("UPDATE users SET is_pro = 1, is_gifted_pro = 0, pro_expires_at = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        echo "Account fixed. You are now Pro.";
    } else {
        echo "No recent Pro transaction found for your account.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
