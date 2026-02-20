<?php
// Helper function to recursively get nested replies
function getNestedReplies($parentId, $allReplies, $pdo, $userId, $videoCreatorId, $depth = 0) {
    $nested = [];

    foreach ($allReplies as $reply) {
        if ((int)$reply['parent_id'] === $parentId) {
            $replyId = (int)$reply['id'];

            // Get like/dislike counts
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comment_likes WHERE comment_id = ?");
            $stmt->execute([$replyId]);
            $likeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comment_dislikes WHERE comment_id = ?");
            $stmt->execute([$replyId]);
            $dislikeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Check if user liked/disliked
            $isLiked = false;
            $isDisliked = false;
            if ($userId) {
                $stmt = $pdo->prepare("SELECT id FROM comment_likes WHERE user_id = ? AND comment_id = ?");
                $stmt->execute([$userId, $replyId]);
                $isLiked = $stmt->fetch() !== false;

                $stmt = $pdo->prepare("SELECT id FROM comment_dislikes WHERE user_id = ? AND comment_id = ?");
                $stmt->execute([$userId, $replyId]);
                $isDisliked = $stmt->fetch() !== false;
            }

            // Get parent comment author for "Replying to" chain
            $parentAuthor = null;
            if ($parentId) {
                // Find parent in all replies or comments
                $stmt = $pdo->prepare("SELECT u.username FROM comments c INNER JOIN users u ON c.user_id = u.id WHERE c.id = ?");
                $stmt->execute([$parentId]);
                $parent = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($parent) {
                    $parentAuthor = $parent['username'];
                }
            }

            // Get reply chain (all parent usernames)
            $replyChain = getReplyChain($replyId, $pdo);

            $formattedReply = [
                'id' => $replyId,
                'comment' => $reply['comment'],
                'parent_id' => (int)$reply['parent_id'],
                'created_at' => $reply['created_at'],
                'updated_at' => $reply['updated_at'],
                'likes' => (int)$likeCount,
                'dislikes' => (int)$dislikeCount,
                'is_liked' => $isLiked,
                'is_disliked' => $isDisliked,
                'is_creator' => ($reply['user_id'] == $videoCreatorId),
                'can_edit' => ($reply['user_id'] == $userId),
                'can_delete' => ($reply['user_id'] == $userId || $userId == $videoCreatorId),
                'depth' => $depth,
                'reply_chain' => $replyChain, // Array of usernames in reply chain
                'parent_author' => $parentAuthor,
                'author' => [
                    'id' => (int)$reply['user_id'],
                    'username' => $reply['username'],
                    'profile_picture' => $reply['profile_picture'] ? (strpos($reply['profile_picture'], 'http') === 0 ? $reply['profile_picture'] : '..' . $reply['profile_picture']) : null
                ],
                'replies' => getNestedReplies($replyId, $allReplies, $pdo, $userId, $videoCreatorId, $depth + 1)
            ];

            $nested[] = $formattedReply;
        }
    }

    return $nested;
}

function getReplyChain($commentId, $pdo) {
    $chain = [];
    $currentId = $commentId;
    $maxDepth = 10; // Prevent infinite loops

    for ($i = 0; $i < $maxDepth; $i++) {
        // Get current comment's parent id
        $stmt = $pdo->prepare("SELECT parent_id FROM comments WHERE id = ?");
        $stmt->execute([$currentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || empty($result['parent_id'])) {
            break;
        }

        $parentId = (int)$result['parent_id'];

        // Fetch the parent's author username
        $stmt = $pdo->prepare("SELECT u.username FROM comments c INNER JOIN users u ON c.user_id = u.id WHERE c.id = ?");
        $stmt->execute([$parentId]);
        $parentUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parentUser) break;

        array_unshift($chain, $parentUser['username']);
        $currentId = $parentId;
    }

    return $chain;
}
