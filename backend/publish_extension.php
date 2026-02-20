<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Create table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS market_extensions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        extension_id VARCHAR(100) NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        price INT DEFAULT 0,
        thumbnail_url TEXT,
        files_json LONGTEXT,
        version VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (extension_id)
    )");

    // Ensure screenshots column exists (Standard MySQL doesn't support IF NOT EXISTS in ALTER)
    $stmt = $pdo->query("SHOW COLUMNS FROM market_extensions LIKE 'screenshots_json'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE market_extensions ADD COLUMN screenshots_json LONGTEXT AFTER thumbnail_url");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (action)
    )");
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Setup Error: ' . $e->getMessage()]);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$userId = $_SESSION['user_id'];
$price = (int)$input['price'];
$thumb = trim($input['thumb']);
$desc = trim($input['desc']);
$screenshots = isset($input['screenshots']) ? $input['screenshots'] : [];
$proj = $input['project'];

// Validation
if ($price < 0 || $price > 5000) {
    echo json_encode(['success' => false, 'message' => 'Price limit exceeded (Max 5000)']);
    exit;
}
if (empty($thumb) || empty($desc)) {
    echo json_encode(['success' => false, 'message' => 'Missing fields']);
    exit;
}
if (!isset($proj['id']) || !isset($proj['files'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid project bundle']);
    exit;
}

// Insert into market
try {
    $stmt = $pdo->prepare("INSERT INTO market_extensions (user_id, extension_id, name, description, price, thumbnail_url, screenshots_json, files_json, version) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $proj['id'],
        $proj['name'],
        $desc,
        $price,
        $thumb,
        json_encode($screenshots),
        json_encode($proj['files']),
        $proj['version'] ?? '1.0.0'
    ]);

    // Also log activity
    $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
    $logStmt->execute([$userId, 'publish_market', "Published extension '{$proj['name']}' for {$price} XPoints"]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
