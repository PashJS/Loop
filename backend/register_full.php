<?php
// Manual Session ID Handling for Web (Cross-Origin)
$receivedSessionId = $_REQUEST['session_id'] ?? ($_POST['session_id'] ?? null);
if ($receivedSessionId && !empty($receivedSessionId)) {
    session_id($receivedSessionId);
}
require 'config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$first_name = trim($input['first_name'] ?? '');
$last_name = trim($input['last_name'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? ''; // Don't trim password potentially
$code = trim($input['code'] ?? '');

// Basic Validation
if (empty($email) || empty($password) || empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Missing fields']);
    exit;
}

// 1. Verify Code (Security Check)
$stmt = $pdo->prepare("SELECT * FROM email_verifications WHERE email = ? AND code = ? AND expires_at > NOW()");
$stmt->execute([$email, $code]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired code']);
    exit;
}

// 2. Hash Password
$hash = password_hash($password, PASSWORD_DEFAULT);

// 3. Create User
// Username? Generate from name or email.
$username = strtolower(explode(' ', $first_name)[0] . rand(100,999));

try {
    $sql = "INSERT INTO users (username, email, password, first_name, last_name) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $email, $hash, $first_name, $last_name]);
    
    // 4. Delete used code
    $pdo->prepare("DELETE FROM email_verifications WHERE email = ?")->execute([$email]);
    
    // 5. Fetch newly created user
    $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, profile_picture FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        echo json_encode(['success' => true, 'user' => $user, 'session_id' => session_id()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User created but could not be fetched']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
}
?>
