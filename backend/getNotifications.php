<?php
// backend/getNotifications.php - Get user notifications (Optimized)
header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to see notifications.']);
    exit;
}

require_once 'config.php';

try {
    $userId = $_SESSION['user_id'];
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

    // Fetch Notifications (Grouped)
    $stmt = $pdo->prepare("
        SELECT 
            MAX(n.id) as id,
            n.type,
            n.target_type,
            n.target_id,
            MAX(n.created_at) as created_at,
            MIN(n.is_read) as is_read,
            COUNT(*) as group_count,
            GROUP_CONCAT(u.username ORDER BY n.created_at DESC SEPARATOR '||') as actor_usernames,
            GROUP_CONCAT(COALESCE(u.profile_picture, '') ORDER BY n.created_at DESC SEPARATOR '||') as actor_pictures,
            MAX(n.message) as message,
            MAX(CASE 
                WHEN n.target_type = 'video' THEN v1.thumbnail_url
                WHEN n.target_type = 'comment' THEN v2.thumbnail_url
                ELSE NULL
            END) as video_thumbnail
        FROM notifications n
        LEFT JOIN users u ON n.actor_id = u.id
        LEFT JOIN videos v1 ON (n.target_type = 'video' AND n.target_id = v1.id)
        LEFT JOIN comments c ON (n.target_type = 'comment' AND n.target_id = c.id)
        LEFT JOIN videos v2 ON (c.video_id = v2.id)
        WHERE n.user_id = ? 
        AND n.is_hidden = 0
        AND (n.actor_id IS NULL OR n.actor_id NOT IN (SELECT value FROM notification_preferences WHERE user_id = ? AND type = 'hidden_user'))
        AND CAST(n.type AS CHAR) NOT IN (SELECT value FROM notification_preferences WHERE user_id = ? AND type = 'hidden_type')
        GROUP BY n.type, n.target_type, n.target_id, n.user_id
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $userId, PDO::PARAM_INT);
    $stmt->bindValue(3, $userId, PDO::PARAM_INT);
    $stmt->bindValue(4, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Unread Count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0 AND is_hidden = 0");
    $stmt->execute([$userId]);
    $unreadCount = $stmt->fetchColumn();

    // Format Output
    $formatted = [];
    foreach ($notifications as $n) {
        $thumb = $n['video_thumbnail'];
        if ($thumb && strpos($thumb, 'http') !== 0) {
            $thumb = '..' . $thumb;
        }

        $usernames = explode('||', $n['actor_usernames']);
        $pictures = explode('||', $n['actor_pictures']);
        
        $latestActor = [
            'username' => $usernames[0] ?? 'Unknown',
            'profile_picture' => !empty($pictures[0]) ? ((strpos($pictures[0], 'http') === 0) ? $pictures[0] : '..' . $pictures[0]) : null
        ];

        $count = (int)$n['group_count'];
        $message = $n['message'];
        
        if ($count > 1) {
            $uniqueUsers = array_unique($usernames);
            $uniqueCount = count($uniqueUsers);
            $users = array_slice($uniqueUsers, 0, 2);
            $othersCount = $uniqueCount - 2;
            
            $actionText = 'interacted with';
            $type = (string)$n['type'];
            if (strpos($type, 'like') !== false) $actionText = 'liked';
            elseif (strpos($type, 'comment') !== false) $actionText = 'commented on';
            elseif (strpos($type, 'love') !== false) $actionText = 'loved';
            elseif (strpos($type, 'save') !== false) $actionText = 'saved';
            elseif (strpos($type, 'sub') !== false) $actionText = 'subscribed to';

            $targetText = 'your video';
            if ($n['target_type'] === 'comment') $targetText = 'your comment';
            elseif ($n['target_type'] === 'comment_reply') $targetText = 'your reply';
            elseif ($type === 'subscription') $targetText = 'your channel';

            $userString = implode(', ', $users);
            if ($othersCount > 0) {
                $message = "$userString and $othersCount others $actionText $targetText";
            } else {
                $message = "$userString $actionText $targetText";
            }
        }

        $formatted[] = [
            'id' => (int)$n['id'],
            'type' => (string)$n['type'],
            'message' => $message,
            'is_read' => (bool)$n['is_read'],
            'created_at' => $n['created_at'],
            'target_id' => $n['target_id'] ? (int)$n['target_id'] : null,
            'target_type' => $n['target_type'],
            'group_count' => $count,
            'video_thumbnail' => $thumb,
            'actor' => [
                'username' => $latestActor['username'],
                'profile_picture' => $latestActor['profile_picture']
            ]
        ];
    }

    echo json_encode([
        'success' => true,
        'notifications' => $formatted,
        'unread_count' => (int)$unreadCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
