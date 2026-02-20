<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';
require_once 'mailer.php';

// 1. Check Authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

// 2. Get POST Data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid Request']);
    exit;
}

// 3. Validation
$firstName = trim($data['first_name'] ?? '');
$lastName = trim($data['last_name'] ?? '');
$dob = trim($data['dob'] ?? ''); // YYYY-MM-DD
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (empty($firstName) || empty($lastName) || empty($dob) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'All fields are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address.']);
    exit;
}

// 4. Secure Password Hashing
$algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
$passwordHash = password_hash($password, $algo);

if ($passwordHash === false) {
    echo json_encode(['success' => false, 'error' => 'Encryption error.']);
    exit;
}

// 5. Generate Verification Code
$verificationCode = sprintf("%06d", mt_rand(100000, 999999));
$expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

// 6. Insert into Database (Unverified)
try {
    $stmt = $pdo->prepare("INSERT INTO floxsync_accounts (user_id, first_name, last_name, dob, email, password_hash, is_verified, verification_token, verification_expires) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)");
    $stmt->execute([$userId, $firstName, $lastName, $dob, $email, $passwordHash, $verificationCode, $expires]);
    
    $newAccountId = $pdo->lastInsertId();

    // 7. Send Verification Email
    $subject = "Verify your FloxSync Account";
    $message = "
    <html>
    <head>
        <style>
            .code { font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #3ea6ff; }
            .container { font-family: sans-serif; padding: 20px; color: #333; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>Verify your new FloxSync Identity</h2>
            <p>You are creating a new FloxSync account for <strong>$email</strong>.</p>
            <p>Please enter the following code to verify this email address:</p>
            <p class='code'>$verificationCode</p>
            <p>This code expires in 15 minutes.</p>
            <p>If you did not request this, please ignore this email.</p>
        </div>
    </body>
    </html>
    ";

    if (sendFloxEmail($email, $subject, $message, true)) {
        echo json_encode([
            'success' => true, 
            'verification_required' => true,
            'message' => 'Verification code sent.',
            'temp_account_id' => $newAccountId,
            'email' => $email
        ]);
    } else {
        // Rollback? Or just tell user to retry?
        // Let's delete the unverified entry to keep clean if email fails
        $pdo->prepare("DELETE FROM floxsync_accounts WHERE id = ?")->execute([$newAccountId]);
        echo json_encode(['success' => false, 'error' => 'Failed to send verification email. Please try again.']);
    }

} catch (PDOException $e) {
    // Check for duplicate entry (Email + UserID unique constraint - wait, we removed that index?)
    // But we might still want to catch general errors
    error_log("FloxSync DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred.']);
}
?>
