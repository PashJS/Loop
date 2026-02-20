<?php
require 'config.php';

try {
    $sql = file_get_contents('schema_updates_v2.sql');
    $pdo->exec($sql);
    echo "Schema updates executed successfully.";
} catch (PDOException $e) {
    echo "Error executing schema updates: " . $e->getMessage();
}
?>
