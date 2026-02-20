<?php
// backend/toggle_2fa.php
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if ($input === null && !empty($rawInput)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$enabled = isset($input['enabled']) ? (int)$input['enabled'] : 0;

try {
    // Check if column exists, if not, add it
    $stmtCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'two_factor_enabled'");
    if (!$stmtCheck->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0");
        $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_code VARCHAR(10) NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_expires DATETIME NULL");
    }

    $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
    $stmt->execute([$enabled, $user_id]);
    
    echo json_encode(['success' => true, 'message' => '2FA ' . ($enabled ? 'enabled' : 'disabled')]);
} catch (PDOException $e) {
    error_log("toggle_2fa.php PDO Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("toggle_2fa.php General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
