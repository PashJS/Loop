<?php
// Manual Session ID Handling for Web (Cross-Origin) - MUST BE BEFORE ANY SESSION START
$receivedSessionId = $_GET['session_id'] ?? ($_POST['session_id'] ?? ($_REQUEST['session_id'] ?? null));
if ($receivedSessionId && !empty($receivedSessionId)) {
    session_id($receivedSessionId);
}
// backend/upload_chunk.php - Handle chunked video uploads
header('Content-Type: application/json');

// Disable error display in JSON output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Global error handler to ensure JSON is always returned
function returnJsonError($message, $code = 500) {
    ob_clean();
    http_response_code($code);
    error_log("Upload error [{$code}]: " . $message);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Handle fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal error in upload_chunk.php: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        returnJsonError('Server error: ' . $error['message'] . ' (Check server logs for details)');
    }
});

// Start output buffering to catch any errors
ob_start();

try {
    session_start();
    require 'config.php';
} catch (PDOException $e) {
    error_log("Database connection error in upload_chunk.php: " . $e->getMessage());
    returnJsonError('Database connection failed: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Configuration error in upload_chunk.php: " . $e->getMessage());
    returnJsonError('Configuration error: ' . $e->getMessage());
}

if (!isset($_SESSION['user_id'])) {
    returnJsonError('Unauthorized', 401);
}

error_log("UPLOAD_CHUNK EARLY DEBUG: action=" . ($_POST['action'] ?? 'NONE') . " | files=" . count($_FILES));
if (isset($_POST['action']) && $_POST['action'] === 'complete') {
    error_log("UPLOAD_CHUNK COMPLETE DEBUG: POST keys=" . implode(',', array_keys($_POST)) . " | FILES keys=" . implode(',', array_keys($_FILES)));
}

// Create temp directory for chunks
$tempDir = __DIR__ . '/../uploads/temp/';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

$action = $_POST['action'] ?? '';

if ($action === 'init') {
    ob_clean();
    
    // Log init request for debugging
    error_log("Upload init request: filename=" . ($_POST['filename'] ?? 'none') . ", filesize=" . ($_POST['filesize'] ?? 0));
    
    // Initialize upload
    $fileName = $_POST['filename'] ?? '';
    $fileSize = (int)($_POST['filesize'] ?? 0);
    
    if (!$fileName) {
        returnJsonError('Filename required');
    }
    
    // Maximum file size: 256GB (274877906944 bytes)
    $maxFileSize = 256 * 1024 * 1024 * 1024; // 256GB
    if ($fileSize > $maxFileSize) {
        $fileSizeGB = round($fileSize / 1024 / 1024 / 1024, 2);
        returnJsonError("File size ({$fileSizeGB} GB) exceeds maximum allowed size of 256 GB.");
    }
    
    // Check PHP upload limits
    $uploadMaxFilesize = ini_get('upload_max_filesize');
    $postMaxSize = ini_get('post_max_size');
    
    // Convert PHP size strings to bytes
    function convertToBytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return $val;
    }
    
    $uploadMaxBytes = convertToBytes($uploadMaxFilesize);
    $postMaxBytes = convertToBytes($postMaxSize);
    $phpLimit = min($uploadMaxBytes, $postMaxBytes);
    
    if ($fileSize > $phpLimit) {
        $fileSizeGB = round($fileSize / 1024 / 1024 / 1024, 2);
        $limitGB = round($phpLimit / 1024 / 1024 / 1024, 2);
        returnJsonError("File size ({$fileSizeGB} GB) exceeds PHP limit ({$limitGB} GB). Please update php.ini: upload_max_filesize=256G and post_max_size=256G");
    }
    
    // Validate file extension
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExtensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'flv', 'wmv', 'm4v', '3gp', 'ts', 'mts', 'm2ts'];
    if (!in_array($extension, $allowedExtensions)) {
        returnJsonError('Invalid file format. Allowed: MP4, WebM, OGG, MOV, AVI, MKV, FLV, WMV, M4V, 3GP, TS');
    }
    
    // Generate unique upload ID
    $uploadId = uniqid('upload_', true);
    
    // Store upload metadata in session for resumable uploads
    $_SESSION['upload_' . $uploadId] = [
        'filename' => $fileName,
        'filesize' => $fileSize,
        'started_at' => time(),
        'chunks_uploaded' => []
    ];
    
    echo json_encode([
        'success' => true,
        'upload_id' => $uploadId,
        'chunk_size' => 5 * 1024 * 1024, // 5MB chunks (recommended)
        'max_file_size' => $maxFileSize
    ]);
    exit;
}
if ($action === 'upload_chunk') {
    ob_clean();
    // Handle chunk upload
    $uploadId = $_POST['upload_id'] ?? '';
    $chunkIndex = (int)($_POST['chunk_index'] ?? 0);
    
    if (!$uploadId || !isset($_FILES['chunk'])) {
        returnJsonError('Invalid chunk data');
    }
    
    // Verify upload session exists
    $uploadKey = 'upload_' . $uploadId;
    if (!isset($_SESSION[$uploadKey])) {
        returnJsonError('Upload session expired. Please restart the upload.');
    }
    
    $chunkPath = $tempDir . $uploadId . '_' . $chunkIndex;
    
    // Create temp directory if it doesn't exist
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    if (move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkPath)) {
        // Track uploaded chunks
        if (!isset($_SESSION[$uploadKey]['chunks_uploaded'])) {
            $_SESSION[$uploadKey]['chunks_uploaded'] = [];
        }
        $_SESSION[$uploadKey]['chunks_uploaded'][] = $chunkIndex;
        
        echo json_encode([
            'success' => true,
            'chunk_index' => $chunkIndex,
            'chunks_uploaded' => count($_SESSION[$uploadKey]['chunks_uploaded'])
        ]);
    } else {
        $error = $_FILES['chunk']['error'];
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'Chunk exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'Chunk exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'Chunk was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No chunk file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write chunk to disk',
            UPLOAD_ERR_EXTENSION => 'PHP extension stopped the upload'
        ];
        $errorMsg = $errorMessages[$error] ?? 'Unknown upload error: ' . $error;
        returnJsonError('Failed to save chunk: ' . $errorMsg);
    }
    exit;
}

if ($action === 'check_progress') {
    ob_clean();
    $uploadId = $_POST['upload_id'] ?? '';
    
    if (!$uploadId) {
        returnJsonError('Upload ID required');
    }
    
    $uploadKey = 'upload_' . $uploadId;
    if (!isset($_SESSION[$uploadKey])) {
        echo json_encode([
            'success' => false,
            'message' => 'Upload session not found'
        ]);
        exit;
    }
    
    $uploadInfo = $_SESSION[$uploadKey];
    $chunksUploaded = $uploadInfo['chunks_uploaded'] ?? [];
    $totalChunks = (int)($_POST['total_chunks'] ?? 0);
    
    echo json_encode([
        'success' => true,
        'chunks_uploaded' => count($chunksUploaded),
        'total_chunks' => $totalChunks,
        'progress' => $totalChunks > 0 ? round((count($chunksUploaded) / $totalChunks) * 100, 2) : 0,
        'missing_chunks' => $totalChunks > 0 ? array_diff(range(0, $totalChunks - 1), $chunksUploaded) : []
    ]);
    exit;
}

if ($action === 'complete') {
    // Assemble chunks
    $uploadId = $_POST['upload_id'] ?? '';
    $fileName = $_POST['filename'] ?? '';
    $totalChunks = $_POST['total_chunks'] ?? 0;
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $hashtags = isset($_POST['hashtags']) ? json_decode($_POST['hashtags'], true) : [];
    $chapters = isset($_POST['chapters']) ? json_decode($_POST['chapters'], true) : [];
    
    if (!$uploadId || !$fileName) {
        echo json_encode(['success' => false, 'message' => 'Invalid completion data']);
        exit;
    }
    
    // Target file path
    $uploadDir = __DIR__ . '/../uploads/videos/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    $finalFilename = uniqid('video_', true) . '.' . $extension;
    $finalPath = $uploadDir . $finalFilename;
    
    // Get file size from session
    $uploadKey = 'upload_' . $uploadId;
    $fileSize = $_SESSION[$uploadKey]['filesize'] ?? 0;
    
    // Combine chunks efficiently using streaming
    $outFile = fopen($finalPath, 'wb');
    if (!$outFile) {
        returnJsonError('Failed to create output file');
    }
    
    $missingChunks = [];
    for ($i = 0; $i < $totalChunks; $i++) {
        $chunkPath = $tempDir . $uploadId . '_' . $i;
        if (!file_exists($chunkPath)) {
            $missingChunks[] = $i;
            continue;
        }
        
        // Stream chunk directly to output file (memory efficient)
        $chunkFile = fopen($chunkPath, 'rb');
        if ($chunkFile) {
            stream_copy_to_stream($chunkFile, $outFile);
            fclose($chunkFile);
        }
        unlink($chunkPath); // Delete chunk after copying
    }
    
    fclose($outFile);
    
    // Check if all chunks were found
    if (!empty($missingChunks)) {
        if (file_exists($finalPath)) {
            unlink($finalPath);
        }
        returnJsonError('Missing chunks: ' . implode(', ', $missingChunks) . '. Please re-upload missing chunks.');
    }
    
    // Verify file was created and has correct size
    if (!file_exists($finalPath)) {
        returnJsonError('Failed to create final video file');
    }
    
    $actualSize = filesize($finalPath);
    if ($fileSize > 0 && abs($actualSize - $fileSize) > 1024) { // Allow 1KB difference
        unlink($finalPath);
        returnJsonError("File size mismatch. Expected: {$fileSize} bytes, Got: {$actualSize} bytes");
    }
    
    // Determine MIME type from extension
    $mimeTypes = [
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'ogg' => 'video/ogg',
        'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo',
        'mkv' => 'video/x-matroska',
        'flv' => 'video/x-flv',
        'wmv' => 'video/x-ms-wmv',
        'm4v' => 'video/x-m4v',
        '3gp' => 'video/3gpp',
        'ts' => 'video/mp2t',
        'mts' => 'video/mp2t',
        'm2ts' => 'video/mp2t'
    ];
    // Use filesystem path for video URL
    $videoUrl = '/uploads/videos/' . $finalFilename;
    
    // Handle thumbnail (if provided separately via standard upload)
    $thumbnailUrl = null;
    error_log("DEBUG: Complete action started. User: " . ($_SESSION['user_id'] ?? 'NONE'));
    error_log("DEBUG: POST data: " . print_r($_POST, true));
    error_log("DEBUG: FILES data: " . print_r($_FILES, true));
    error_log("DEBUG: Raw input length: " . strlen(file_get_contents('php://input')));
    
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $thumbnailDir = __DIR__ . '/../uploads/thumbnails/';
        if (!is_dir($thumbnailDir)) mkdir($thumbnailDir, 0777, true);
        
        $thumbExt = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
        $thumbName = uniqid('thumb_', true) . '.' . $thumbExt;
        $thumbPath = $thumbnailDir . $thumbName;
        
        if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbPath)) {
            $thumbnailUrl = '/uploads/thumbnails/' . $thumbName;
            error_log("DEBUG: Thumbnail saved to $thumbnailUrl");
        } else {
            error_log("DEBUG: Failed to move uploaded thumbnail");
        }
    } elseif (isset($_FILES['thumbnail'])) {
        error_log("DEBUG: Thumbnail upload error: " . $_FILES['thumbnail']['error']);
    }
    
    // Handle captions (if provided)
    $captionsUrl = null;
    if (isset($_FILES['captions'])) {
        if ($_FILES['captions']['error'] === UPLOAD_ERR_OK) {
            $captionsDir = __DIR__ . '/../uploads/videos/';
            if (!is_dir($captionsDir)) mkdir($captionsDir, 0777, true);
            
            $captionExt = strtolower(pathinfo($_FILES['captions']['name'], PATHINFO_EXTENSION));
            if ($captionExt === 'vtt') {
                $captionName = uniqid('caption_', true) . '.vtt';
                $captionPath = $captionsDir . $captionName;
                
                if (move_uploaded_file($_FILES['captions']['tmp_name'], $captionPath)) {
                    $captionsUrl = '/uploads/videos/' . $captionName;
                    error_log("DEBUG: Captions saved to $captionsUrl");
                } else {
                    error_log("DEBUG: Failed to move uploaded captions to $captionPath");
                }
            } else {
                error_log("DEBUG: Invalid caption extension: $captionExt (Full name: " . $_FILES['captions']['name'] . ")");
            }
        } else {
            error_log("DEBUG: Captions upload error code: " . $_FILES['captions']['error']);
        }
    } else {
        error_log("DEBUG: No captions file received in FILES.");
    }
    
    // If no thumbnail, create placeholder
    if (!$thumbnailUrl) {
        $thumbnailDir = __DIR__ . '/../uploads/thumbnails/';
        if (!is_dir($thumbnailDir)) mkdir($thumbnailDir, 0777, true);
        
        $thumbName = uniqid('thumb_', true) . '.jpg';
        $thumbPath = $thumbnailDir . $thumbName;
        
        $img = imagecreatetruecolor(320, 180);
        $bgColor = imagecolorallocate($img, 26, 26, 26);
        $textColor = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $bgColor);
        imagestring($img, 5, 100, 80, 'VIDEO', $textColor);
        imagejpeg($img, $thumbPath, 80);
        imagedestroy($img);
        
        $thumbnailUrl = '/uploads/thumbnails/' . $thumbName;
    }
    
    $isClip = isset($_POST['is_clip']) && $_POST['is_clip'] === 'true' ? 1 : 0;
    
    // Debug logging
    error_log("DEBUG: is_clip POST value = " . (isset($_POST['is_clip']) ? $_POST['is_clip'] : 'NOT SET'));
    error_log("DEBUG: is_clip final value = " . $isClip);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO videos (title, description, video_url, thumbnail_url, captions_url, user_id, status, views, is_clip)
            VALUES (?, ?, ?, ?, ?, ?, 'published', 0, ?)
        ");
        
        $stmt->execute([
            $title,
            $description,
            $videoUrl,
            $thumbnailUrl,
            $captionsUrl,
            $_SESSION['user_id'],
            $isClip
        ]);
        
        $videoId = $pdo->lastInsertId();
        
        // Clean up upload session
        unset($_SESSION[$uploadKey]);
        
        // Save hashtags
        if (!empty($hashtags) && is_array($hashtags)) {
            // Create hashtags table if not exists (just in case)
            $pdo->exec("CREATE TABLE IF NOT EXISTS hashtags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tag_name VARCHAR(50) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            $pdo->exec("CREATE TABLE IF NOT EXISTS video_hashtags (
                video_id INT NOT NULL,
                hashtag_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (video_id, hashtag_id),
                FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
                FOREIGN KEY (hashtag_id) REFERENCES hashtags(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $stmtTag = $pdo->prepare("INSERT IGNORE INTO hashtags (tag_name) VALUES (?)");
            $stmtGetTag = $pdo->prepare("SELECT id FROM hashtags WHERE tag_name = ?");
            $stmtLink = $pdo->prepare("INSERT IGNORE INTO video_hashtags (video_id, hashtag_id) VALUES (?, ?)");
            
            foreach ($hashtags as $tag) {
                $tag = trim($tag);
                if (empty($tag)) continue;
                
                // Insert tag
                $stmtTag->execute([$tag]);
                
                // Get tag ID
                $stmtGetTag->execute([$tag]);
                $tagId = $stmtGetTag->fetchColumn();
                
                if ($tagId) {
                    // Link to video
                    $stmtLink->execute([$videoId, $tagId]);
                }
            }
        }

        // Save Chapters
        if (!empty($chapters) && is_array($chapters)) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS video_chapters (
                id INT AUTO_INCREMENT PRIMARY KEY,
                video_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                start_time DECIMAL(10,2) NOT NULL,
                end_time DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $stmtChapter = $pdo->prepare("
                INSERT INTO video_chapters (video_id, title, start_time, end_time) 
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($chapters as $chapter) {
                $cTitle = trim($chapter['title']);
                $cStart = (float)$chapter['start'];
                $cEnd = (float)$chapter['end'];
                
                if (empty($cTitle)) continue;
                $stmtChapter->execute([$videoId, $cTitle, $cStart, $cEnd]);
            }
        }
        
        // Auto-generate captions if none provided
        if (!$captionsUrl) {
            $scriptPath = __DIR__ . '/generate_captions.py';
            // Windows background execution
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "start /B python \"$scriptPath\" --video_id $videoId > NUL 2>&1";
                pclose(popen($cmd, "r"));
                error_log("DEBUG: Triggered background caption generation for Video $videoId");
            } else {
                // Linux/Unix
                $cmd = "python3 \"$scriptPath\" --video_id $videoId > /dev/null 2>&1 &";
                exec($cmd);
                error_log("DEBUG: Triggered background caption generation for Video $videoId");
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Video uploaded successfully!',
            'video' => [
                'id' => $videoId
            ]
        ]);
        
    } catch (Exception $e) {
        // Clean up filesystem files if video insert failed
        if (file_exists($finalPath)) {
            unlink($finalPath);
        }
        if (isset($thumbnailUrl) && $thumbnailUrl) {
            $thumbPath = __DIR__ . '/..' . $thumbnailUrl;
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
        }
        error_log("Upload error: " . $e->getMessage());
        returnJsonError($e->getMessage());
    } catch (PDOException $e) {
        // Clean up filesystem files if video insert failed
        if (file_exists($finalPath)) {
            unlink($finalPath);
        }
        if (isset($thumbnailUrl) && $thumbnailUrl) {
            $thumbPath = __DIR__ . '/..' . $thumbnailUrl;
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
        }
        error_log("Database error: " . $e->getMessage());
        returnJsonError('Database error: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log("Fatal error: " . $e->getMessage());
        returnJsonError('Server error: ' . $e->getMessage());
    }
    exit;
}
?>
