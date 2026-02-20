<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$group_id = isset($data['group_id']) ? (int)$data['group_id'] : 0;
$my_id = $_SESSION['user_id'];

if ($group_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid group ID']);
    exit();
}

try {
    // 1. Verify membership
    $stmt = $pdo->prepare("SELECT role FROM chat_group_members WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$group_id, $my_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You are not a member of this group']);
        exit();
    }

    // 2. Remove user from group
    $stmtLeave = $pdo->prepare("DELETE FROM chat_group_members WHERE group_id = ? AND user_id = ?");
    $stmtLeave->execute([$group_id, $my_id]);

    // 3. Post System Message
    $stmtUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmtUser->execute([$my_id]);
    $user = $stmtUser->fetch();
    $username = $user ? $user['username'] : 'A user';

    $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, group_id, message, is_read) VALUES (?, 0, ?, ?, 1)")
        ->execute([$my_id, $group_id, "$username left the group"]);

    // 4. (Optional) If user was an admin, assign new admin if any members left
    // Skip for now for simplicity unless requested.

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
