<?php
session_start();
require 'config.php';
if (!isset($_SESSION['user_id'])) die("Unauthorized");

if (isset($_FILES['captions']) && isset($_POST['video_id'])) {
    $videoId = (int)$_POST['video_id'];
    $file = $_FILES['captions'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'vtt') {
            $name = uniqid('caption_', true) . '.vtt';
            $path = __DIR__ . '/../uploads/videos/' . $name;
            if (move_uploaded_file($file['tmp_name'], $path)) {
                $url = '/uploads/videos/' . $name;
                $stmt = $pdo->prepare("UPDATE videos SET captions_url = ? WHERE id = ?");
                $stmt->execute([$url, $videoId]);
                echo "Successfully uploaded and linked captions to video $videoId";
            } else echo "Failed to move file";
        } else echo "Invalid extension";
    } else echo "Upload error: " . $file['error'];
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Video Captions</title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="layout.css">
    <style>
        body { padding: 40px; background: #0f0f0f; color: white; font-family: sans-serif; }
        .fix-container { max-width: 500px; margin: 0 auto; background: #1f1f1f; padding: 30px; border-radius: 12px; border: 1px solid #333; }
        h2 { margin-top: 0; color: #fff; }
        input { width: 100%; padding: 10px; margin-bottom: 15px; background: #000; border: 1px solid #333; color: #fff; border-radius: 6px; }
        button { width: 100%; padding: 12px; background: #3ea6ff; color: #000; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        button:hover { background: #65b8ff; }
        .success { color: #2ba640; margin-top: 10px; }
        .error { color: #ff4e45; margin-top: 10px; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div style="height: 80px;"></div>
    <div class="fix-container">
        <h2>Upload Captions for Existing Video</h2>
        <form action="" method="POST" enctype="multipart/form-data">
            <label>Video ID</label>
            <input type="number" name="video_id" required placeholder="e.g. 25">
            <label>VTT File</label>
            <input type="file" name="captions" accept=".vtt" required style="padding: 10px 0;">
            <button type="submit">Upload Captions</button>
        </form>
    </div>
</body>
</html>
