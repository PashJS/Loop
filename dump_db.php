<?php
require 'backend/config.php';
$stmt = $pdo->query("SELECT id, video_url FROM videos ORDER BY id DESC LIMIT 10");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('db_dump.txt', print_r($results, true));
echo "Dumped " . count($results) . " videos";
