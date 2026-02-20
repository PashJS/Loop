<?php
require 'config.php';
$res = $pdo->query("SELECT id, title, captions_url FROM videos WHERE captions_url IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
foreach($res as $r) {
    echo "ID: {$r['id']} | Title: {$r['title']} | CC: {$r['captions_url']}\n";
}
?>
