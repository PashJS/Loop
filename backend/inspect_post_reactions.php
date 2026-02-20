<?php
require_once 'config.php';
try {
    $stmt = $pdo->query("DESCRIBE post_reactions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($columns, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
