<?php
// backend/db_diag.php
require_once 'config.php';

header('Content-Type: text/plain');
echo "--- DB DIAGNOSTICS ---\n";

try {
    echo "Testing Connection...\n";
    $pdo->query("SELECT 1");
    echo "[OK] Connected to Database.\n\n";

    echo "--- PROCESSLIST ---\n";
    $stmt = $pdo->query("SHOW FULL PROCESSLIST");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }

    echo "\n--- TABLE STATUS ---\n";
    $stmt = $pdo->query("SHOW TABLE STATUS");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Table: {$row['Name']}, Rows: {$row['Rows']}, Size: " . round($row['Data_length']/1024/1024, 2) . " MB\n";
    }

} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
}
?>
