<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

// Create table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS extension_chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room VARCHAR(50) NOT NULL,
        user_id INT NOT NULL,
        username VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (room),
        INDEX (created_at)
    )");
} catch (PDOException $e) {}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$room = $_GET['room'] ?? $_POST['room'] ?? 'global';
$userId = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? 'Guest';

if ($action === 'send') {
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $text = trim($input['text'] ?? '');
    
    if (empty($text) || strlen($text) > 500) {
        echo json_encode(['success' => false, 'message' => 'Invalid message']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO extension_chat_messages (room, user_id, username, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$room, $userId, $username, $text]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

} elseif ($action === 'poll') {
    // Long-polling: Get messages since a given ID
    $since = (int)($_GET['since'] ?? 0);
    
    $stmt = $pdo->prepare("
        SELECT id, user_id, username, message, created_at 
        FROM extension_chat_messages 
        WHERE room = ? AND id > ? 
        ORDER BY id ASC 
        LIMIT 50
    ");
    $stmt->execute([$room, $since]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = [];
    foreach ($messages as $m) {
        $results[] = [
            'id' => (int)$m['id'],
            'user' => $m['username'],
            'text' => htmlspecialchars($m['message']),
            'mine' => ($m['user_id'] == $userId),
            'time' => $m['created_at']
        ];
    }
    
    echo json_encode(['success' => true, 'messages' => $results]);

} elseif ($action === 'history') {
    // Get last N messages for initial load
    $limit = min((int)($_GET['limit'] ?? 20), 50);
    
    $stmt = $pdo->prepare("
        SELECT id, user_id, username, message, created_at 
        FROM extension_chat_messages 
        WHERE room = :room 
        ORDER BY id DESC 
        LIMIT :limit
    ");
    $stmt->bindValue(':room', $room, PDO::PARAM_STR);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    $results = [];
    foreach ($messages as $m) {
        $results[] = [
            'id' => (int)$m['id'],
            'user' => $m['username'],
            'text' => htmlspecialchars($m['message']),
            'mine' => ($m['user_id'] == $userId),
            'time' => $m['created_at']
        ];
    }
    
    echo json_encode(['success' => true, 'messages' => $results]);

} else {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
