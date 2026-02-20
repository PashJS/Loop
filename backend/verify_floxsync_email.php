<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';

// 1. Check Authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$code = trim($data['code'] ?? '');
$tempId = $data['temp_account_id'] ?? 0;

if (empty($code) || empty($tempId)) {
    echo json_encode(['success' => false, 'error' => 'Missing verification data.']);
    exit;
}

try {
    // 2. Lookup Unverified Account
    $stmt = $pdo->prepare("SELECT * FROM floxsync_accounts WHERE id = ? AND user_id = ? AND is_verified = 0");
    $stmt->execute([$tempId, $userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        echo json_encode(['success' => false, 'error' => 'Invalid or expired verification session.']);
        exit;
    }

    // 3. Check Expiry
    if (strtotime($account['verification_expires']) < time()) {
        echo json_encode(['success' => false, 'error' => 'Verification code expired.']);
        exit;
    }

    // 4. Verify Code
    if ($account['verification_token'] !== $code) {
        echo json_encode(['success' => false, 'error' => 'Invalid verification code.']);
        exit;
    }

    // 5. Activate Account
    $updateStmt = $pdo->prepare("UPDATE floxsync_accounts SET is_verified = 1, verification_token = NULL, verification_expires = NULL WHERE id = ?");
    $updateStmt->execute([$tempId]);

    echo json_encode(['success' => true, 'message' => 'Email verified successfully. Account created.']);

} catch (PDOException $e) {
    error_log("Verification Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error.']);
}
?>
