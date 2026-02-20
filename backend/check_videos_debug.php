<?php
require 'config.php';
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM videos WHERE status = 'published' AND (is_clip = 0 OR is_clip IS NULL)");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Published Videos Count: " . $row['count'] . "\n";
    
    $stmt = $pdo->query("SELECT id, title, status, is_clip FROM videos LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "First 5 Videos in DB:\n";
    print_r($rows);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
