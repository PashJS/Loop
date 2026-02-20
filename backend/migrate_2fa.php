<?php
// backend/migrate_2fa.php
header('Content-Type: application/json');
require 'config.php';

try {
    // 1. Add two_factor_enabled
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0");
    } catch (Exception $e) { /* Column might exist */ }

    // 2. Add two_factor_code
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_code VARCHAR(10) NULL");
    } catch (Exception $e) { /* Column might exist */ }

    // 3. Add two_factor_expires
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_expires DATETIME NULL");
    } catch (Exception $e) { /* Column might exist */ }

    echo json_encode(['success' => true, 'message' => 'Database migration completed.']);
} catch (Exception $e) {
    error_log("migrate_2fa.php Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
