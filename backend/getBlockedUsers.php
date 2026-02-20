<?php
// backend/getBlockedUsers.php - Get list of users blocked by current user
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Ensure blocked_users table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS blocked_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blocker_id INT NOT NULL,
        blocked_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_block (blocker_id, blocked_id)
    )");

    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.profile_picture, b.created_at
        FROM blocked_users b
        JOIN users u ON b.blocked_id = u.id
        WHERE b.blocker_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format profile picture URLs
    foreach ($users as &$user) {
        if ($user['profile_picture']) {
            if (strpos($user['profile_picture'], 'http') !== 0) {
                $user['profile_picture'] = '..' . $user['profile_picture'];
            }
        }
    }

    echo json_encode(['success' => true, 'count' => count($users), 'users' => $users]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
