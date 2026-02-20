<?php
require 'config.php';
$stmt = $pdo->prepare("SELECT id, title, video_url, captions_url FROM videos WHERE title LIKE '%Story%'");
$stmt->execute();
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
?>
