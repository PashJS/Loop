<?php
// backend/getUser.php - Get current user information
header('Content-Type: application/json');
session_start();

try {
    require 'config.php';
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Configuration error: ' . $e->getMessage()
    ]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated.'
    ]);
    exit;
}

try {
    // Check if bio column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'bio'");
    $hasBio = (bool)$stmt->fetch();
    
    // Auto-migrate 2FA columns and Google ID if missing
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'two_factor_enabled'");
    if (!$stmt->fetch()) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0");
            $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_code VARCHAR(10) NULL");
            $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_expires DATETIME NULL");
        } catch (Exception $e) {}
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'google_id'");
    if (!$stmt->fetch()) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(100) NULL UNIQUE");
        } catch (Exception $e) {}
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_notifications'");
    if (!$stmt->fetch()) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN email_notifications TINYINT(1) DEFAULT 1");
        } catch (Exception $e) {}
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'subscription_notifications'");
    if (!$stmt->fetch()) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN subscription_notifications TINYINT(1) DEFAULT 1");
        } catch (Exception $e) {}
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'notification_frequency'");
    if (!$stmt->fetch()) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN notification_frequency VARCHAR(20) DEFAULT 'immediate'");
        } catch (Exception $e) {}
    }

    
    if ($hasBio) {
        $stmt = $pdo->prepare("
            SELECT id, username, email, created_at, profile_picture, banner_url, bio, two_factor_enabled, google_id, email_notifications, subscription_notifications, notification_frequency, profile_visibility
            FROM users
            WHERE id = ?
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT id, username, email, created_at, profile_picture, banner_url, NULL as bio, two_factor_enabled, google_id, email_notifications, subscription_notifications, notification_frequency, profile_visibility
            FROM users
            WHERE id = ?
        ");
    }
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Get video count
        $stmt = $pdo->prepare("SELECT COUNT(*) as video_count FROM videos WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $videoCount = $stmt->fetch(PDO::FETCH_ASSOC)['video_count'];
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'created_at' => $user['created_at'],
                'profile_picture' => $user['profile_picture'] ? (strpos($user['profile_picture'], 'http') === 0 ? $user['profile_picture'] : '..' . $user['profile_picture']) : null,
                'banner_url' => $user['banner_url'] ? (strpos($user['banner_url'], 'http') === 0 ? $user['banner_url'] : '..' . $user['banner_url']) : null,
                'bio' => $user['bio'] ?? '',
                'video_count' => (int)$videoCount,
                'two_factor_enabled' => (bool)$user['two_factor_enabled'],
                'google_id' => $user['google_id'],
                'email_notifications' => (bool)$user['email_notifications'],
                'subscription_notifications' => (bool)$user['subscription_notifications'],
                'notification_frequency' => $user['notification_frequency'],
                'profile_visibility' => $user['profile_visibility']
            ]
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User not found.'
        ]);
    }
} catch (PDOException $e) {
    error_log("getUser.php PDO Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("getUser.php General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
