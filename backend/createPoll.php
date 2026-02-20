<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['question']) || empty(trim($data['question']))) {
    echo json_encode(['success' => false, 'message' => 'Question cannot be empty']);
    exit;
}

if (!isset($data['options']) || !is_array($data['options']) || count($data['options']) < 2) {
    echo json_encode(['success' => false, 'message' => 'At least 2 options required']);
    exit;
}

$question = trim($data['question']);
$options = $data['options'];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO polls (user_id, question) VALUES (?, ?)");
    $stmt->execute([$user_id, $question]);
    $poll_id = $pdo->lastInsertId();

    $stmtOpt = $pdo->prepare("INSERT INTO poll_options (poll_id, option_text) VALUES (?, ?)");
    foreach ($options as $opt) {
        $stmtOpt->execute([$poll_id, trim($opt)]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Poll created successfully']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
