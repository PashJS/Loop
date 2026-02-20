<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Fetch all extensions this user has "installed"
    $stmt = $pdo->prepare("
        SELECT e.extension_id, e.name, e.files_json 
        FROM extension_installs i
        JOIN market_extensions e ON i.extension_id = e.id
        WHERE i.user_id = ?
    ");
    $stmt->execute([$userId]);
    $extensions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($extensions as $ext) {
        $files = json_decode($ext['files_json'], true);
        $results[] = [
            'id' => $ext['extension_id'],
            'name' => $ext['name'],
            'files' => $files
        ];
    }

    echo json_encode(['success' => true, 'extensions' => $results]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
