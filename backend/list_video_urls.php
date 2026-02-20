<?php
require 'config.php';
$stmt = $pdo->query("SELECT id, title, video_url, captions_url FROM videos ORDER BY id DESC LIMIT 20");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | Title: {$row['title']} | Video: {$row['video_url']} | CC: {$row['captions_url']}\n";
}
?>
