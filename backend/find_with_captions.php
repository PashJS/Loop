<?php
require 'config.php';
$stmt = $pdo->query("SELECT id, title, captions_url FROM videos WHERE captions_url IS NOT NULL");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
?>
