<?php
require 'config.php';
header('Content-Type: text/plain');

function showSchema($pdo, $table) {
    echo "\n--- $table ---\n";
    $stmt = $pdo->query("SHOW CREATE TABLE $table");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row['Create Table'] . "\n";
    
    $stmt = $pdo->query("SHOW FULL COLUMNS FROM $table");
    while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$col['Field']} | {$col['Type']} | {$col['Collation']}\n";
    }
}

try {
    showSchema($pdo, 'notifications');
    showSchema($pdo, 'notification_preferences');
    
    echo "\n--- GLOBAL COLLATIONS ---\n";
    $stmt = $pdo->query("SHOW VARIABLES LIKE 'collation%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['Variable_name']} = {$row['Value']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
