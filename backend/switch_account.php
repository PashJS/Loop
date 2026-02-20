<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';
require_once 'log_activity.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$currentUserOriginalId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$accountId = $data['account_id'] ?? null;

if (!$accountId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing account ID']);
    exit;
}

try {
    // 1. Verify ownership of the linked account logic
    $stmt = $pdo->prepare("SELECT * FROM floxsync_accounts WHERE id = ? AND user_id = ? AND is_verified = 1");
    $stmt->execute([$accountId, $currentUserOriginalId]);
    $linkedAccount = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$linkedAccount) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Account not found or access denied']);
        exit;
    }

    // 2. Resolve Target User
    $targetEmail = $linkedAccount['email'];
    $stmtUser = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmtUser->execute([$targetEmail]);
    $targetUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $targetUserId = null;

    if ($targetUser) {
        $targetUserId = $targetUser['id'];
    } else {
        $username = strtolower($linkedAccount['first_name'] . $linkedAccount['last_name']) . rand(100, 999);
        $insertStmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $insertStmt->execute([
            $username,
            $targetEmail,
            $linkedAccount['password_hash']
        ]);
        $targetUser = [
            'id' => $pdo->lastInsertId(),
            'username' => $username,
            'email' => $targetEmail,
            'profile_picture' => null // New user
        ];
        $targetUserId = $targetUser['id'];
    }

    // 3. Auto-Create Reverse Link (Make sure new user calls back to old user)
    // Get old user details
    $stmtOld = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmtOld->execute([$currentUserOriginalId]);
    $oldUser = $stmtOld->fetch(PDO::FETCH_ASSOC);

    if ($oldUser) {
        // Check if link exists for target user -> old user
        $checkStmt = $pdo->prepare("SELECT id FROM floxsync_accounts WHERE user_id = ? AND email = ?");
        $checkStmt->execute([$targetUserId, $oldUser['email']]);
        if (!$checkStmt->fetch()) {
            // Create the link
            // We need first/last name, dob, etc. Ideally we fetch from old user profile if available, or placeholder/parse
            // For now, let's just use username or dummy data as this is an implicit link.
            // Actually, we can try to find if there is a 'floxsync_accounts' entry for the OLD user that points to ANYONE to guess names? 
            // Better: use username as firstname, empty last name if needed.
            // Or just leave it basic.
            $stmtReverse = $pdo->prepare("INSERT INTO floxsync_accounts (user_id, first_name, last_name, dob, email, password_hash) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtReverse->execute([
                $targetUserId,
                $oldUser['username'], // Using username as first name
                '(Linked)',          // Placeholder last name
                '2000-01-01',        // Dummy DOB
                $oldUser['email'],
                $oldUser['password'] // Reuse hash
            ]);
        }
    }

    // 4. Perform Switch
    $_SESSION['user_id'] = $targetUserId;

    logLoginActivity($pdo, $targetUserId);

    // 5. Fetch Data for Frontend Update
    // New Current User
    $newCurrentUser = [
        'username' => $targetUser['username'],
        'email' => $targetUser['email'],
        'profile_picture' => $targetUser['profile_picture'] ?? null
    ];

    // New Linked Accounts
    $stmtLinks = $pdo->prepare("SELECT * FROM floxsync_accounts WHERE user_id = ? AND is_verified = 1 ORDER BY created_at DESC");
    $stmtLinks->execute([$targetUserId]);
    $newLinkedAccounts = $stmtLinks->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'currentUser' => $newCurrentUser,
        'linkedAccounts' => $newLinkedAccounts
    ]);
    session_write_close();

} catch (Exception $e) {
    error_log("Switch error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
