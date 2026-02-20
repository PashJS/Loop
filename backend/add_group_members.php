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
$members = $data['members'] ?? []; // Array of user IDs to add
$my_id = $_SESSION['user_id'];

if ($group_id <= 0 || empty($members)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

try {
    // 1. Verify I am an admin of the group (or just a member if we allow anyone to add, but usually admin is better)
    // For now, let's allow any member to add participants as it's a social app
    $stmt = $pdo->prepare("SELECT role FROM chat_group_members WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$group_id, $my_id]);
    $me = $stmt->fetch();

    if (!$me) {
        echo json_encode(['success' => false, 'message' => 'You are not a member of this group']);
        exit();
    }

    // 2. Fetch Group Name for the system message
    $stmtG = $pdo->prepare("SELECT name FROM chat_groups WHERE id = ?");
    $stmtG->execute([$group_id]);
    $group = $stmtG->fetch();
    $group_name = $group ? $group['name'] : 'the group';

    // 3. Add Members
    $sql = "INSERT IGNORE INTO chat_group_members (group_id, user_id, role) VALUES (?, ?, 'member')";
    $stmtAdd = $pdo->prepare($sql);
    
    $added_count = 0;
    foreach ($members as $uid) {
        $stmtAdd->execute([$group_id, (int)$uid]);
        if ($stmtAdd->rowCount() > 0) {
            $added_count++;
            
            // Fetch username for system message
            $stmtU = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmtU->execute([$uid]);
            $u = $stmtU->fetch();
            $uname = $u ? $u['username'] : 'a user';

            // Send System Message
            $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, group_id, message, is_read) VALUES (?, 0, ?, ?, 1)")
                ->execute([$my_id, $group_id, "added $uname to the group"]);
        }
    }

    echo json_encode(['success' => true, 'added_count' => $added_count]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
