<?php
// backend/saveFileToDB.php - Helper function to save files to database
// This is included by other upload scripts

function saveFileToDatabase($pdo, $filePath, $fileType, $mimeType, $originalName, $userId = null, $relatedId = null) {
    if (!file_exists($filePath)) {
        error_log("File does not exist: " . $filePath);
        return null;
    }
    
    $fileData = file_get_contents($filePath);
    if ($fileData === false) {
        error_log("Failed to read file: " . $filePath);
        return null;
    }
    
    $fileSize = filesize($filePath);
    $fileName = basename($filePath);
    
    // Check if file_storage table exists
    try {
        $pdo->query("SELECT 1 FROM file_storage LIMIT 1");
    } catch (PDOException $e) {
        error_log("file_storage table does not exist. Please run phpmyadmin.sql to create it.");
        throw new Exception("Database table 'file_storage' does not exist. Please run the SQL setup script.");
    }
    
    try {
        // Check current max_allowed_packet setting
        $stmt = $pdo->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentMaxPacket = isset($result['Value']) ? (int)$result['Value'] : 4194304; // Default 4MB
        
        // Try to increase max_allowed_packet for this session
        $desiredMaxPacket = 524288000; // 500MB
        try {
            $pdo->exec("SET SESSION max_allowed_packet = " . $desiredMaxPacket);
            // Verify it was set
            $stmt = $pdo->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $currentMaxPacket = isset($result['Value']) ? (int)$result['Value'] : $currentMaxPacket;
        } catch(PDOException $e) {
            error_log("Could not set max_allowed_packet: " . $e->getMessage());
        }
        
        // Check if file is too large
        if ($fileSize > $currentMaxPacket) {
            $fileSizeMB = round($fileSize / 1024 / 1024, 2);
            $maxPacketMB = round($currentMaxPacket / 1024 / 1024, 2);
            throw new Exception("File size ({$fileSizeMB} MB) exceeds MySQL max_allowed_packet limit ({$maxPacketMB} MB). Please increase max_allowed_packet in MySQL configuration or use a smaller file.");
        }
        
        // For very large files, we need to use a different approach
        // Use LOAD_FILE or stream the data in chunks
        if ($fileSize > 100 * 1024 * 1024) { // Files larger than 100MB
            // For large files, we'll use a streaming approach
            $stmt = $pdo->prepare("
                INSERT INTO file_storage (file_type, mime_type, file_name, original_name, file_data, file_size, user_id, related_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Open file as stream
            $fileHandle = fopen($filePath, 'rb');
            if (!$fileHandle) {
                throw new Exception("Could not open file for reading");
            }
            
            $stmt->bindValue(1, $fileType, PDO::PARAM_STR);
            $stmt->bindValue(2, $mimeType, PDO::PARAM_STR);
            $stmt->bindValue(3, $fileName, PDO::PARAM_STR);
            $stmt->bindValue(4, $originalName, PDO::PARAM_STR);
            $stmt->bindValue(5, $fileHandle, PDO::PARAM_LOB);
            $stmt->bindValue(6, $fileSize, PDO::PARAM_INT);
            $stmt->bindValue(7, $userId, PDO::PARAM_INT);
            $stmt->bindValue(8, $relatedId, PDO::PARAM_INT);
            
            $stmt->execute();
            fclose($fileHandle);
        } else {
            // For smaller files, load into memory
            $stmt = $pdo->prepare("
                INSERT INTO file_storage (file_type, mime_type, file_name, original_name, file_data, file_size, user_id, related_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bindValue(1, $fileType, PDO::PARAM_STR);
            $stmt->bindValue(2, $mimeType, PDO::PARAM_STR);
            $stmt->bindValue(3, $fileName, PDO::PARAM_STR);
            $stmt->bindValue(4, $originalName, PDO::PARAM_STR);
            $stmt->bindValue(5, $fileData, PDO::PARAM_LOB);
            $stmt->bindValue(6, $fileSize, PDO::PARAM_INT);
            $stmt->bindValue(7, $userId, PDO::PARAM_INT);
            $stmt->bindValue(8, $relatedId, PDO::PARAM_INT);
            
            $stmt->execute();
        }
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error saving file to database: " . $e->getMessage());
        error_log("File size: " . $fileSize . " bytes (" . round($fileSize / 1024 / 1024, 2) . " MB)");
        error_log("File type: " . $fileType);
        error_log("Error code: " . $e->getCode());
        
        // Provide more helpful error message
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);
        if (strpos($e->getMessage(), 'max_allowed_packet') !== false || $e->getCode() == 'HY000') {
            throw new Exception("File size ({$fileSizeMB} MB) is too large. Please run the SQL in backend/fix_mysql_settings.sql to increase max_allowed_packet, or use a smaller file.");
        }
        
        throw new Exception("Database error: " . $e->getMessage());
    }
}

function saveImageToDatabase($pdo, $imageResource, $fileType, $mimeType, $fileName, $userId = null, $relatedId = null) {
    // Capture image to string
    ob_start();
    
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            imagejpeg($imageResource, null, 90);
            break;
        case 'image/png':
            imagepng($imageResource, null, 9);
            break;
        case 'image/gif':
            imagegif($imageResource);
            break;
        default:
            ob_end_clean();
            return null;
    }
    
    $fileData = ob_get_clean();
    $fileSize = strlen($fileData);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO file_storage (file_type, mime_type, file_name, original_name, file_data, file_size, user_id, related_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $fileType,
            $mimeType,
            $fileName,
            $fileName,
            $fileData,
            $fileSize,
            $userId,
            $relatedId
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error saving image to database: " . $e->getMessage());
        return null;
    }
}

function deleteFileFromDatabase($pdo, $fileId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM file_storage WHERE id = ?");
        $stmt->execute([$fileId]);
        return true;
    } catch (PDOException $e) {
        error_log("Error deleting file from database: " . $e->getMessage());
        return false;
    }
}

function getFileUrl($fileId) {
    // Return URL that works from frontend pages (which use ../backend/)
    return "../backend/getFile.php?id=" . $fileId;
}
?>

