<?php
// Prevent any output before JSON
ob_start();

require 'config.php';

// Clear any output buffered by config.php or others
ob_clean();

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';

if (empty($query)) {
    echo json_encode(['success' => true, 'users' => []]);
    exit;
}

// DEBUG: Force test user
if ($query === 'force_test') {
    echo json_encode(['success' => true, 'users' => [[
        'id' => 999,
        'username' => 'Debug User',
        'profile_picture' => '',
        'bio' => 'This is a test user to verify the display.',
        'subscribers_count' => 12345
    ]]]);
    exit;
}

try {
    // Check if bio column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'bio'");
    $hasBio = $stmt->rowCount() > 0;

    $sql = "
        SELECT 
            u.id, 
            u.username, 
            u.profile_picture, 
            u.is_pro,
            ps.comment_badge,
            ps.name_badge,
            " . ($hasBio ? "u.bio" : "NULL as bio") . ",
            (SELECT COUNT(*) FROM subscriptions WHERE channel_id = u.id) as subscribers_count
        FROM users u
        LEFT JOIN pro_settings ps ON u.id = ps.user_id
        WHERE u.username LIKE ?
        LIMIT 3
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['%' . $query . '%']);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process profile pictures
    foreach ($users as &$user) {
        $user['profile_picture'] = (function($url) {
            if (!$url) return null;
            if (strpos($url, 'http') === 0) return $url;
            if (strpos($url, '/') === 0) return '..' . $url;
            if (strpos($url, '..') === 0) return $url;
            return '../' . $url;
        })($user['profile_picture']);
    }

    echo json_encode(['success' => true, 'users' => $users]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Flush buffer
ob_end_flush();
?>
