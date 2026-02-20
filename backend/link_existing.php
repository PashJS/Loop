<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email and password required']);
    exit;
}

try {
    // 1. Find the target user in USERS table
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        // Security: Don't reveal user existence
        echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
        exit;
    }

    // 2. Verify Password
    // Note: Use password_verify if hashed, or plain check if legacy (assuming hashed)
    // If passwords aren't hashed in your dev env yet, adjust here.
    // Assuming implementation uses password_verify or simple check for now.
    // The previous code in link_floxsync used password_hash, so we assume verify.
    // However, older 'users' might just be md5 or plain? Let's try verify first.
    
    $passwordValid = false;
    if (password_verify($password, $targetUser['password'])) {
        $passwordValid = true;
    } elseif ($targetUser['password'] === $password) { 
        // Fallback for plain text during dev
        $passwordValid = true; 
    }

    if (!$passwordValid) {
        echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
        exit;
    }

    // 3. Link Logic
    // Prevent self-linking
    if ($targetUser['id'] == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'error' => 'You cannot link your own current session account']);
        exit;
    }

    // Check if already linked
    $checkStmt = $pdo->prepare("SELECT id FROM floxsync_accounts WHERE user_id = ? AND email = ?");
    $checkStmt->execute([$_SESSION['user_id'], $targetUser['email']]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Account already linked']);
        exit;
    }

    // 4. Create Link Entry
    // We need first_name etc for the display. If 'users' doesn't have it, use username/placeholder.
    // We will use the username as first_name if real names are missing.
    $firstName = $targetUser['username']; 
    $lastName = '(Linked)'; 
    
    // Check if there is a floxsync profile for THAT user to get real name?
    $fsProfileStmt = $pdo->prepare("SELECT first_name, last_name, dob FROM floxsync_accounts WHERE user_id = ? LIMIT 1");
    $fsProfileStmt->execute([$targetUser['id']]);
    $fsProfile = $fsProfileStmt->fetch(PDO::FETCH_ASSOC);

    if ($fsProfile) {
        $firstName = $fsProfile['first_name'];
        $lastName = $fsProfile['last_name'];
        $dob = $fsProfile['dob'];
    } else {
         $dob = '2000-01-01'; // Default
    }

    // Insert into floxsync_accounts associated with CURRENT SESSION USER
    $insertStmt = $pdo->prepare("INSERT INTO floxsync_accounts (user_id, first_name, last_name, dob, email, password_hash) VALUES (?, ?, ?, ?, ?, ?)");
    $insertStmt->execute([
        $_SESSION['user_id'],
        $firstName,
        $lastName,
        $dob,
        $targetUser['email'],
        $targetUser['password'] // Storing the hash
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Link Existing Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
