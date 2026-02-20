<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$q = $_GET['q'] ?? '';

try {
    $stmt = $pdo->prepare("
        SELECT id, username, profile_picture 
        FROM users 
        WHERE username LIKE ? 
        LIMIT 10
    ");
    $stmt->execute(['%' . $q . '%']);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'users' => $users]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
