<?php
// Diagnostic page to check PHP upload settings
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP Upload Configuration</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #1a1a1a; color: #fff; }
        .info { background: #2a2a2a; padding: 15px; margin: 10px 0; border-radius: 8px; }
        .ok { color: #4caf50; }
        .warning { color: #ff9800; }
        .error { color: #f44336; }
        h2 { color: #3ea6ff; }
    </style>
</head>
<body>
    <h1>PHP Upload Configuration Check</h1>
    
    <div class="info">
        <h2>Upload Settings</h2>
        <p><strong>upload_max_filesize:</strong> <?php echo ini_get('upload_max_filesize'); ?></p>
        <p><strong>post_max_size:</strong> <?php echo ini_get('post_max_size'); ?></p>
        <p><strong>memory_limit:</strong> <?php echo ini_get('memory_limit'); ?></p>
        <p><strong>max_execution_time:</strong> <?php echo ini_get('max_execution_time'); ?> seconds</p>
        <p><strong>max_input_time:</strong> <?php echo ini_get('max_input_time'); ?> seconds</p>
    </div>
    
    <div class="info">
        <h2>Upload Directory Permissions</h2>
        <?php
        $uploadDir = __DIR__ . '/../uploads/videos/';
        $thumbnailDir = __DIR__ . '/../uploads/thumbnails/';
        
        echo "<p><strong>Videos Directory:</strong> " . realpath($uploadDir) . "</p>";
        echo "<p>Exists: " . (is_dir($uploadDir) ? '<span class="ok">YES</span>' : '<span class="error">NO</span>') . "</p>";
        echo "<p>Writable: " . (is_writable($uploadDir) ? '<span class="ok">YES</span>' : '<span class="error">NO</span>') . "</p>";
        
        echo "<p><strong>Thumbnails Directory:</strong> " . realpath($thumbnailDir) . "</p>";
        echo "<p>Exists: " . (is_dir($thumbnailDir) ? '<span class="ok">YES</span>' : '<span class="error">NO</span>') . "</p>";
        echo "<p>Writable: " . (is_writable($thumbnailDir) ? '<span class="ok">YES</span>' : '<span class="error">NO</span>') . "</p>";
        ?>
    </div>
    
    <div class="info">
        <h2>PHP Temp Directory</h2>
        <?php
        $tempDir = sys_get_temp_dir();
        echo "<p><strong>Temp Directory:</strong> " . $tempDir . "</p>";
        echo "<p>Writable: " . (is_writable($tempDir) ? '<span class="ok">YES</span>' : '<span class="error">NO</span>') . "</p>";
        
        $uploadTmpDir = ini_get('upload_tmp_dir');
        if ($uploadTmpDir) {
            echo "<p><strong>Upload Temp Directory:</strong> " . $uploadTmpDir . "</p>";
            echo "<p>Writable: " . (is_writable($uploadTmpDir) ? '<span class="ok">YES</span>' : '<span class="error">NO</span>') . "</p>";
        }
        ?>
    </div>
    
    <div class="info">
        <h2>Recommendations</h2>
        <?php
        $uploadMax = ini_get('upload_max_filesize');
        $postMax = ini_get('post_max_size');
        
        if (strpos($uploadMax, 'M') !== false && (int)$uploadMax < 100) {
            echo '<p class="warning">⚠️ upload_max_filesize is less than 100M. For video uploads, set it to at least 512M</p>';
        } else {
            echo '<p class="ok">✓ upload_max_filesize looks good</p>';
        }
        
        if (strpos($postMax, 'M') !== false && (int)$postMax < 100) {
            echo '<p class="warning">⚠️ post_max_size is less than 100M. For video uploads, set it to at least 512M</p>';
        } else {
            echo '<p class="ok">✓ post_max_size looks good</p>';
        }
        
        if (!is_writable($uploadDir)) {
            echo '<p class="error">❌ Video upload directory is not writable! Check folder permissions.</p>';
        }
        ?>
    </div>
    
    <p><a href="upload_video.php" style="color: #3ea6ff;">← Back to Upload</a></p>
</body>
</html>
