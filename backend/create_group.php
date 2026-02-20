<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$name = $data['name'] ?? 'New Group';
$members = $data['members'] ?? []; // Array of user IDs
$creator_id = $_SESSION['user_id'];

if (empty($members)) {
    echo json_encode(['success' => false, 'message' => 'Add at least one member']);
    exit();
}

try {
    // 1. Create Groups Table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        created_by INT NOT NULL,
        picture VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Create Group Members Table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_group_members (
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        role VARCHAR(20) DEFAULT 'member',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (group_id, user_id)
    )");

    // 3. Update Messages Table to support groups if not already
    // We strictly check if column exists first to avoid errors
    $stmt_check = $pdo->query("SHOW COLUMNS FROM messages LIKE 'group_id'");
    if (!$stmt_check->fetch()) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN group_id INT DEFAULT NULL"); // receiver_id will be ignored or 0 if group_id is set
        $pdo->exec("CREATE INDEX idx_group_id ON messages(group_id)");
    }

    // 4. Insert Group
    $stmt = $pdo->prepare("INSERT INTO chat_groups (name, created_by) VALUES (?, ?)");
    $stmt->execute([$name, $creator_id]);
    $group_id = $pdo->lastInsertId();

    // 5. Insert Members (Creator + Selected)
    $members[] = $creator_id; // Add creator
    $members = array_unique($members); // Deduplicate

    $sql = "INSERT INTO chat_group_members (group_id, user_id, role) VALUES (?, ?, ?)";
    $stmtMember = $pdo->prepare($sql);

    foreach ($members as $uid) {
        $role = ($uid == $creator_id) ? 'admin' : 'member';
        $stmtMember->execute([$group_id, $uid, $role]);
    }

    // 6. Send Initial System Message (Optional, or just let it be empty)
    // Let's add a "Group Created" system message so it shows up
    $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, group_id, message, is_read) VALUES (?, 0, ?, ?, 1)")
        ->execute([$creator_id, $group_id, "created the group \"$name\""]);

    echo json_encode(['success' => true, 'group_id' => $group_id, 'name' => $name, 'members' => $members]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
