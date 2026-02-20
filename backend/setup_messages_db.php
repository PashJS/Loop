<?php
require_once __DIR__ . '/config.php';

try {
    $sql = file_get_contents(__DIR__ . '/schema_messages.sql');
    $pdo->exec($sql);
    echo "Messages table created successfully!";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
