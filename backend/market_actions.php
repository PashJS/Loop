<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';
require_once 'mailer.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$userId = $_SESSION['user_id'];

// Merge JSON input with POST/GET for flexibility (FormData support)
$extId = $input['extension_id'] ?? $_POST['extension_id'] ?? $_GET['extension_id'] ?? null;
$action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? null;

if (!$extId || !$action) {
    echo json_encode(['success' => false, 'message' => 'Invalid request', 'received' => ['action' => $action, 'id' => $extId]]);
    exit;
}

try {
    if ($action === 'like') {
        // Toggle Like
        $stmt = $pdo->prepare("SELECT id FROM extension_likes WHERE extension_id = ? AND user_id = ?");
        $stmt->execute([$extId, $userId]);
        if ($stmt->fetch()) {
            $pdo->prepare("DELETE FROM extension_likes WHERE extension_id = ? AND user_id = ?")->execute([$extId, $userId]);
            $isLiked = false;
        } else {
            $pdo->prepare("INSERT INTO extension_likes (extension_id, user_id) VALUES (?, ?)")->execute([$extId, $userId]);
            $isLiked = true;
        }
        $count = $pdo->prepare("SELECT COUNT(*) FROM extension_likes WHERE extension_id = ?");
        $count->execute([$extId]);
        echo json_encode(['success' => true, 'liked' => $isLiked, 'count' => $count->fetchColumn()]);

    } elseif ($action === 'comment') {
        // Use $_POST for text and parent_id since it might come via FormData for files
        $text = trim($_POST['comment'] ?? $input['comment'] ?? '');
        $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : (isset($input['parent_id']) ? (int)$input['parent_id'] : null);
        if (empty($text)) throw new Exception("Comment cannot be empty");

        $imageUrl = null;
        if(isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $dir = '../uploads/market_comments/';
            if(!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('cmt_') . '.' . $ext;
            if(move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fileName)) {
                $imageUrl = 'uploads/market_comments/' . $fileName;
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO extension_comments (extension_id, user_id, parent_id, comment, image_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$extId, $userId, $parentId, $text, $imageUrl]);
        $newId = $pdo->lastInsertId();

        $u = $pdo->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
        $u->execute([$userId]);
        $userData = $u->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'comment_id' => $newId,
            'user' => $userData,
            'image_url' => $imageUrl
        ]);

    } elseif ($action === 'install') {
        // Record install
        try {
            $pdo->prepare("INSERT INTO extension_installs (extension_id, user_id) VALUES (?, ?)")->execute([$extId, $userId]);
        } catch(Exception $e){} // Ignore duplicate keys
        
        $count = $pdo->prepare("SELECT COUNT(*) FROM extension_installs WHERE extension_id = ?");
        $count->execute([$extId]);
        echo json_encode(['success' => true, 'count' => $count->fetchColumn()]);

    } elseif ($action === 'uninstall') {
        // Remove user's installation
        $pdo->prepare("DELETE FROM extension_installs WHERE extension_id = ? AND user_id = ?")->execute([$extId, $userId]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'delete_extension') {
        // Verify ownership
        $check = $pdo->prepare("SELECT user_id FROM market_extensions WHERE id = ?");
        $check->execute([$extId]);
        $ownerId = $check->fetchColumn();
        
        if ($ownerId != $userId) {
            throw new Exception("You can only delete your own extensions");
        }
        
        // Delete all related data (cascade)
        $pdo->prepare("DELETE FROM extension_installs WHERE extension_id = ?")->execute([$extId]);
        $pdo->prepare("DELETE FROM extension_stars WHERE extension_id = ?")->execute([$extId]);
        $pdo->prepare("DELETE FROM extension_comment_votes WHERE comment_id IN (SELECT id FROM extension_comments WHERE extension_id = ?)")->execute([$extId]);
        $pdo->prepare("DELETE FROM extension_comments WHERE extension_id = ?")->execute([$extId]);
        $pdo->prepare("DELETE FROM extension_reports WHERE extension_id = ?")->execute([$extId]);
        $pdo->prepare("DELETE FROM market_extensions WHERE id = ?")->execute([$extId]);
        
        echo json_encode(['success' => true]);

    } elseif ($action === 'vote_comment') {
        $commentId = (int)$input['comment_id'];
        $type = $input['vote_type']; // 'like' or 'dislike'

        // Check for existing vote
        $check = $pdo->prepare("SELECT vote_type FROM extension_comment_votes WHERE comment_id = ? AND user_id = ?");
        $check->execute([$commentId, $userId]);
        $existing = $check->fetchColumn();

        if ($existing === $type) {
            // Un-vote
            $pdo->prepare("DELETE FROM extension_comment_votes WHERE comment_id = ? AND user_id = ?")->execute([$commentId, $userId]);
            $finalAction = 'removed';
        } else {
            // Swap or New vote
            $pdo->prepare("DELETE FROM extension_comment_votes WHERE comment_id = ? AND user_id = ?")->execute([$commentId, $userId]);
            $stmt = $pdo->prepare("INSERT INTO extension_comment_votes (comment_id, user_id, vote_type) VALUES (?, ?, ?)");
            $stmt->execute([$commentId, $userId, $type]);
            $finalAction = 'set';
        }

        // Get new counts
        $res = $pdo->prepare("SELECT 
            (SELECT COUNT(*) FROM extension_comment_votes WHERE comment_id = ? AND vote_type = 'like') as likes,
            (SELECT COUNT(*) FROM extension_comment_votes WHERE comment_id = ? AND vote_type = 'dislike') as dislikes
        ");
        $res->execute([$commentId, $commentId]);
        echo json_encode(['success' => true, 'action' => $finalAction, 'counts' => $res->fetch(PDO::FETCH_ASSOC)]);

    } elseif ($action === 'delete_comment') {
        $commentId = (int)$input['comment_id'];

        // Security check: must be the owner
        $check = $pdo->prepare("SELECT user_id FROM extension_comments WHERE id = ?");
        $check->execute([$commentId]);
        $ownerId = $check->fetchColumn();

        if ($ownerId != $userId) throw new Exception("Unauthorized to delete this comment");

        $pdo->prepare("DELETE FROM extension_comments WHERE id = ?")->execute([$commentId]);
        // Also clean up votes
        $pdo->prepare("DELETE FROM extension_comment_votes WHERE comment_id = ?")->execute([$commentId]);
        
        echo json_encode(['success' => true]);

    } elseif ($action === 'rate') {
        $rating = (int)($input['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) throw new Exception("Invalid rating");

        // Insert or Update rating
        $stmt = $pdo->prepare("INSERT INTO extension_stars (extension_id, user_id, rating) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating)");
        $stmt->execute([$extId, $userId, $rating]);

        // Get new Average
        $res = $pdo->prepare("SELECT AVG(rating) as avg, COUNT(*) as count FROM extension_stars WHERE extension_id = ?");
        $res->execute([$extId]);
        $stats = $res->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'avg' => round($stats['avg'], 1), 'total' => $stats['count']]);

    } elseif ($action === 'report') {
        $reason = trim($input['reason'] ?? 'No reason provided');
        
        // Fetch details for the email
        $extStmt = $pdo->prepare("SELECT name FROM market_extensions WHERE id = ?");
        $extStmt->execute([$extId]);
        $extName = $extStmt->fetchColumn() ?: "Unknown Extension";

        $userStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $reporter = $userStmt->fetchColumn() ?: "Anonymous";

        // Record in Database
        $stmt = $pdo->prepare("INSERT INTO extension_reports (extension_id, user_id, reason) VALUES (?, ?, ?)");
        $stmt->execute([$extId, $userId, $reason]);

        // Send Email Notification via PHPMailer
        $to = "floxxteam@gmail.com";
        $subject = "🚨 Marketplace Report: " . $extName;
        $message = "A new report has been filed on FloxWatch Marketplace.\n\n" .
                   "Extension: " . $extName . " (ID: $extId)\n" .
                   "Reported By: @" . $reporter . " (UID: $userId)\n" .
                   "Reason: " . $reason . "\n\n" .
                   "Please review this immediately in the Admin Panel.\n" .
                   "-- FloxWatch Security Engine";

        $mailSent = sendFloxEmail($to, $subject, $message);

        // Log Activity
        $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
        $logStmt->execute([$userId, 'report_extension', "Extension '{$extName}' reported for: {$reason}. Email sent via PHPMailer: " . ($mailSent ? 'Yes' : 'No')]);

        if ($mailSent) {
            echo json_encode(['success' => true, 'message' => 'Incident reported. Our team has been notified via email.']);
        } else {
            // Even if mail() fails (common on local dev), we successfully recorded it in DB/Logs
            echo json_encode(['success' => true, 'message' => 'Report recorded in security logs. (Note: Email delivery skipped on local environment)']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
