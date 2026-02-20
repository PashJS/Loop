<?php
require_once __DIR__ . '/config.php';

try {
    // Add xpoints column if it doesn't exist
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS xpoints INT DEFAULT 0");
    
    // Create point_transactions table
    $sql = file_get_contents(__DIR__ . '/schema_xpoints.sql');
    $pdo->exec($sql);
    
    echo json_encode(["status" => "success", "message" => "XPoints system database initialized."]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
