<?php
// backend/search_users.php - Search for users to start a conversation
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Allow both session auth and mobile auth (user_id param for mobile)
$user_id = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_GET['user_id'])) {
    // Mobile app passes user_id
    $user_id = intval($_GET['user_id']);
}

// For search, we can allow unauthenticated searches, just exclude no one
// Or require at least some form of auth - let's be permissive for now
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 1) {
    echo json_encode(['success' => true, 'users' => []]);
    exit();
}

try {
    // Search by username
    if ($user_id) {
        // Exclude current user
        $stmt = $pdo->prepare("
            SELECT 
                id, 
                username, 
                profile_picture,
                last_active_at
            FROM users 
            WHERE id != ? 
            AND username LIKE ?
            ORDER BY 
                CASE WHEN username LIKE ? THEN 0 ELSE 1 END,
                username ASC
            LIMIT 20
        ");
        
        $searchPattern = '%' . $query . '%';
        $exactPattern = $query . '%';
        $stmt->execute([$user_id, $searchPattern, $exactPattern]);
    } else {
        // No current user to exclude
        $stmt = $pdo->prepare("
            SELECT 
                id, 
                username, 
                profile_picture,
                last_active_at
            FROM users 
            WHERE username LIKE ?
            ORDER BY 
                CASE WHEN username LIKE ? THEN 0 ELSE 1 END,
                username ASC
            LIMIT 20
        ");
        
        $searchPattern = '%' . $query . '%';
        $exactPattern = $query . '%';
        $stmt->execute([$searchPattern, $exactPattern]);
    }
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format profile pictures
    foreach ($users as &$user) {
        if ($user['profile_picture'] && strpos($user['profile_picture'], 'http') !== 0) {
            $user['profile_picture'] = ltrim($user['profile_picture'], './');
        }
    }
    unset($user);
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'count' => count($users)
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
